<?php

namespace App\Jobs;

use App\Models\SenderIdentity;
use App\Notifications\DomainVerificationTimeoutNotification;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryDomainVerification implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    /**
     * @param  \DateTime  $deadline  Absolute deadline passed at dispatch time so retries
     *                               are capped relative to the original dispatch, not each
     *                               individual attempt.
     */
    public function __construct(
        public SenderIdentity $identity,
        public string $tokenAtDispatch,
        public \DateTime $deadline,
    ) {}

    /**
     * Exponential backoff: 2m, 4m, 8m, …, 512m.
     * Laravel repeats the last value (30720 s) once the array is exhausted;
     * retryUntil() acts as the effective 24-hour ceiling.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [120, 240, 480, 960, 1920, 3840, 7680, 15360, 30720];
    }

    public function retryUntil(): \DateTime
    {
        return $this->deadline;
    }

    public function handle(DomainVerificationService $verificationService): void
    {
        $identity = SenderIdentity::find($this->identity->id);

        // Stop chain if identity was deleted or domain was changed (token mismatch).
        if (! $identity || $identity->verification_token !== $this->tokenAtDispatch) {
            return;
        }

        // Stop chain if the identity was already verified by a manual click.
        if ($identity->isVerified()) {
            $identity->update(['verification_retry_dispatched_at' => null]);

            return;
        }

        if ($verificationService->verify($identity)) {
            $identity->update([
                'verified_at' => now(),
                'verification_retry_dispatched_at' => null,
            ]);
            $identity->user->notify(new DomainVerifiedNotification($identity));

            return;
        }

        // DNS TXT record not found yet — throw to trigger the next retry with backoff.
        // Note: on multi-worker setups a concurrent manual verify and this job could both
        // send DomainVerifiedNotification before the other's guard fires. The window is
        // narrow and both emails are identical — accepted trade-off.
        throw new \RuntimeException('DNS TXT record not found for domain: '.$identity->domain);
    }

    public function failed(\Throwable $exception): void
    {
        $identity = SenderIdentity::find($this->identity->id);

        if ($identity && $identity->verification_token === $this->tokenAtDispatch) {
            $identity->update(['verification_retry_dispatched_at' => null]);
            $identity->user->notify(new DomainVerificationTimeoutNotification($identity));
        }
    }
}
