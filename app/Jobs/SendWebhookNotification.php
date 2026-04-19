<?php

namespace App\Jobs;

use App\Exceptions\WebhookDeliveryFailedException;
use App\Mail\WebhookDeliveryFailedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendWebhookNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public function __construct(
        public string $webhookUrl,
        public string $webhookSecret,
        public string $hashId,
        public string $createdAt,
        public string $retrievedAt,
        public int $userId,
        public string $event = 'retrieved',
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 900, 1800, 3600, 7200, 14400, 28800];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function handle(): void
    {
        $payload = json_encode([
            'event' => $this->event,
            'hash_id' => $this->hashId,
            'created_at' => $this->createdAt,
            'retrieved_at' => $this->retrievedAt,
        ], JSON_THROW_ON_ERROR);

        $signature = 'sha256='.hash_hmac('sha256', $payload, $this->webhookSecret);

        $response = Http::timeout(10)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['https'],
                ],
            ])
            ->withHeaders([
                'X-Signature-256' => $signature,
                'User-Agent' => 'FlashView-Webhook/1.0',
            ])
            ->withBody($payload, 'application/json')
            ->post($this->webhookUrl);

        if ($response->failed()) {
            throw new WebhookDeliveryFailedException(
                "Webhook delivery failed with status {$response->status()}"
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Webhook delivery permanently failed', [
            'webhook_url' => Str::mask($this->webhookUrl, '*', 15),
            'hash_id' => $this->hashId,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);

        $user = User::find($this->userId);

        if ($user) {
            Mail::to($user)->sendNow(new WebhookDeliveryFailedMail($user, $this->webhookUrl));
        }
    }
}
