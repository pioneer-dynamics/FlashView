<?php

namespace App\Services;

use App\Mail\NewSecretNotification;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecretService
{
    /**
     * Create a new secret and generate a signed URL.
     *
     * @return array{secret: Secret, url: string}
     */
    public function createSecret(
        ?string $message,
        int $expiresInMinutes,
        ?int $userId = null,
        ?string $maskedRecipientEmail = null,
        ?string $senderCompanyName = null,
        ?string $senderDomain = null,
        ?string $senderEmail = null,
        ?UploadedFile $encryptedFile = null,
        ?string $preUploadedFilepath = null,
        ?string $encryptedOriginalFilename = null,
        ?int $fileSize = null,
        ?string $fileMimeType = null,
    ): array {
        $expiresAt = now()->addMinutes($expiresInMinutes);

        $filepath = $preUploadedFilepath;

        if ($encryptedFile !== null && $preUploadedFilepath === null) {
            $filepath = 'secrets/'.Str::uuid().'.bin';
            Storage::put($filepath, $encryptedFile->get());
        }

        try {
            $secret = Secret::create([
                'message' => $message,
                'filepath' => $filepath,
                'filename' => $encryptedOriginalFilename,
                'file_size' => $fileSize,
                'file_mime_type' => $fileMimeType,
                'expires_at' => $expiresAt,
                'user_id' => $userId,
                'masked_recipient_email' => $maskedRecipientEmail,
                'sender_company_name' => $senderCompanyName,
                'sender_domain' => $senderDomain,
                'sender_email' => $senderEmail,
            ]);
        } catch (\Throwable $e) {
            if ($filepath) {
                Storage::delete($filepath);
            }
            throw $e;
        }

        $url = URL::temporarySignedRoute('secret.show', $expiresAt, ['secret' => $secret->hash_id]);

        return ['secret' => $secret, 'url' => $url];
    }

    /**
     * Send a notification email to the recipient.
     */
    public function notifyRecipient(User $sender, string $email, string $url, string $hashId): void
    {
        Mail::to($email)->send(new NewSecretNotification($sender, $url, $hashId));
    }

    /**
     * List a user's secrets (including expired/retrieved) without triggering events.
     */
    public function listSecrets(User $user): LengthAwarePaginator
    {
        return Secret::withoutEvents(fn () => $user->secrets()
            ->withoutGlobalScopes()
            ->orderBy('created_at', 'desc')
            ->paginate());
    }

    /**
     * Atomically mark a file secret as retrieved and redirect to a presigned URL (S3).
     * Falls back to streaming for local disk environments.
     */
    public function downloadFileSecret(string $hashId): RedirectResponse|StreamedResponse
    {
        $filepath = null;

        DB::transaction(function () use ($hashId, &$filepath) {
            $record = Secret::withoutEvents(
                fn () => Secret::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find(Secret::decodeHashId($hashId))
            );

            if (! $record || $record->retrieved_at !== null || ! $record->filepath) {
                abort(410, 'File has already been retrieved or has expired.');
            }

            $filepath = $record->filepath;

            DB::table($record->getTable())->where('id', $record->id)->update([
                'retrieved_at' => now(),
                'ip_address_retrieved' => encrypt(request()->ip(), false),
            ]);
        });

        $ttlHours = config('secrets.file_upload.presigned_url_ttl_hours', 12);

        try {
            $url = Storage::temporaryUrl($filepath, now()->addHours($ttlHours), [
                'ResponseContentType' => 'application/octet-stream',
                'ResponseContentDisposition' => 'attachment; filename="encrypted.bin"',
            ]);

            return redirect($url);
        } catch (\RuntimeException) {
            // Local disk does not support presigned URLs — stream and delete immediately.
            $content = Storage::get($filepath);
            Storage::delete($filepath);

            DB::table((new Secret)->getTable())
                ->where('filepath', $filepath)
                ->update([
                    'filepath' => null,
                    'filename' => null,
                    'file_size' => null,
                    'file_mime_type' => null,
                ]);

            return response()->stream(function () use ($content) {
                echo $content;
            }, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="encrypted.bin"',
                'Content-Length' => strlen($content),
            ]);
        }
    }

    /**
     * Delete the file for a retrieved secret (called after client confirms download).
     */
    public function deleteDownloadedFile(string $hashId): void
    {
        $record = Secret::withoutEvents(
            fn () => Secret::withoutGlobalScopes()->find(Secret::decodeHashId($hashId))
        );

        if (! $record || ! $record->filepath) {
            return;
        }

        $record->deleteFile();

        DB::table($record->getTable())->where('id', $record->id)->update([
            'filepath' => null,
            'filename' => null,
            'file_size' => null,
            'file_mime_type' => null,
        ]);
    }

    /**
     * Burn a secret by marking it as retrieved.
     */
    public function burnSecret(Secret $secret): void
    {
        $secret->markSilentlyAsRetrieved();
    }
}
