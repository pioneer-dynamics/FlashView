<?php

namespace App\Services;

use App\Mail\NewSecretNotification;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SecretService
{
    /**
     * Create a new secret and generate a signed URL.
     *
     * @return array{secret: Secret, url: string}
     */
    public function createSecret(string $message, int $expiresInMinutes, ?int $userId = null): array
    {
        $expiresAt = now()->addMinutes($expiresInMinutes);

        $secret = Secret::create([
            'message' => $message,
            'expires_at' => $expiresAt,
            'user_id' => $userId,
        ]);

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
