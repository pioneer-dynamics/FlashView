<?php

namespace Tests\Unit;

use App\Jobs\SendWebhookNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookUrl = 'https://example.com/webhook';

    private string $webhookSecret = 'test-webhook-secret-key-for-hmac';

    public function test_sends_correct_json_payload(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $job = new SendWebhookNotification(
            webhookUrl: $this->webhookUrl,
            webhookSecret: $this->webhookSecret,
            hashId: 'abc123',
            createdAt: '2026-03-25T10:00:00+00:00',
            retrievedAt: '2026-03-25T12:00:00+00:00',
        );

        $job->handle();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === $this->webhookUrl
                && $body['event'] === 'retrieved'
                && $body['hash_id'] === 'abc123'
                && $body['created_at'] === '2026-03-25T10:00:00+00:00'
                && $body['retrieved_at'] === '2026-03-25T12:00:00+00:00';
        });
    }

    public function test_sends_valid_hmac_signature(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $job = new SendWebhookNotification(
            webhookUrl: $this->webhookUrl,
            webhookSecret: $this->webhookSecret,
            hashId: 'abc123',
            createdAt: '2026-03-25T10:00:00+00:00',
            retrievedAt: '2026-03-25T12:00:00+00:00',
        );

        $job->handle();

        Http::assertSent(function ($request) {
            $payload = $request->body();
            $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $this->webhookSecret);

            return $request->hasHeader('X-Signature-256', $expectedSignature);
        });
    }

    public function test_sends_correct_user_agent(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $job = new SendWebhookNotification(
            webhookUrl: $this->webhookUrl,
            webhookSecret: $this->webhookSecret,
            hashId: 'abc123',
            createdAt: '2026-03-25T10:00:00+00:00',
            retrievedAt: '2026-03-25T12:00:00+00:00',
        );

        $job->handle();

        Http::assertSent(function ($request) {
            return $request->hasHeader('User-Agent', 'FlashView-Webhook/1.0');
        });
    }

    public function test_throws_on_failed_response(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $job = new SendWebhookNotification(
            webhookUrl: $this->webhookUrl,
            webhookSecret: $this->webhookSecret,
            hashId: 'abc123',
            createdAt: '2026-03-25T10:00:00+00:00',
            retrievedAt: '2026-03-25T12:00:00+00:00',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Webhook delivery failed with status 500');

        $job->handle();
    }

    public function test_backoff_returns_exponential_delays(): void
    {
        $job = new SendWebhookNotification(
            webhookUrl: $this->webhookUrl,
            webhookSecret: $this->webhookSecret,
            hashId: 'abc123',
            createdAt: '2026-03-25T10:00:00+00:00',
            retrievedAt: '2026-03-25T12:00:00+00:00',
        );

        $backoff = $job->backoff();

        $this->assertCount(10, $backoff);
        $this->assertEquals(30, $backoff[0]);
        $this->assertEquals(28800, $backoff[9]);
    }

    public function test_retry_until_returns_future_datetime(): void
    {
        $job = new SendWebhookNotification(
            webhookUrl: $this->webhookUrl,
            webhookSecret: $this->webhookSecret,
            hashId: 'abc123',
            createdAt: '2026-03-25T10:00:00+00:00',
            retrievedAt: '2026-03-25T12:00:00+00:00',
        );

        $retryUntil = $job->retryUntil();

        $this->assertGreaterThan(now(), $retryUntil);
        $this->assertLessThanOrEqual(now()->addHours(24)->addMinute(), $retryUntil);
    }
}
