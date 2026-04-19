<?php

namespace App\Services;

use App\Exceptions\FileUploadLimitExceededException;
use App\Mail\NewSecretNotification;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
        ?string $encryptedOriginalFilename = null,
        ?int $fileSize = null,
        ?string $fileMimeType = null,
    ): array {
        $expiresAt = now()->addMinutes($expiresInMinutes);

        $filepath = null;

        if ($encryptedFile !== null) {
            if ($userId !== null) {
                $activeFileCount = Secret::withoutGlobalScopes()
                    ->where('user_id', $userId)
                    ->whereNotNull('filepath')
                    ->where('expires_at', '>=', now())
                    ->count();

                if ($activeFileCount >= config('secrets.file_upload.max_active_file_secrets', 10)) {
                    throw new FileUploadLimitExceededException('You have reached the maximum number of active file secrets. Please wait for existing ones to expire or be retrieved.');
                }
            }

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
     * Burn a secret by marking it as retrieved.
     */
    public function burnSecret(Secret $secret): void
    {
        $secret->markSilentlyAsRetrieved();
    }
}
