<?php

use App\Exceptions\WebhookDeliveryFailedException;
use App\Jobs\SendWebhookNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->webhookUrl = 'https://example.com/webhook';
    $this->webhookSecret = 'test-webhook-secret-key-for-hmac';
});

test('sends correct json payload', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $job = new SendWebhookNotification(
        webhookUrl: $this->webhookUrl,
        webhookSecret: $this->webhookSecret,
        hashId: 'abc123',
        createdAt: '2026-03-25T10:00:00+00:00',
        retrievedAt: '2026-03-25T12:00:00+00:00',
        userId: 1,
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
});

test('sends valid hmac signature', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $job = new SendWebhookNotification(
        webhookUrl: $this->webhookUrl,
        webhookSecret: $this->webhookSecret,
        hashId: 'abc123',
        createdAt: '2026-03-25T10:00:00+00:00',
        retrievedAt: '2026-03-25T12:00:00+00:00',
        userId: 1,
    );

    $job->handle();

    Http::assertSent(function ($request) {
        $payload = $request->body();
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $this->webhookSecret);

        return $request->hasHeader('X-Signature-256', $expectedSignature);
    });
});

test('sends correct user agent', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $job = new SendWebhookNotification(
        webhookUrl: $this->webhookUrl,
        webhookSecret: $this->webhookSecret,
        hashId: 'abc123',
        createdAt: '2026-03-25T10:00:00+00:00',
        retrievedAt: '2026-03-25T12:00:00+00:00',
        userId: 1,
    );

    $job->handle();

    Http::assertSent(function ($request) {
        return $request->hasHeader('User-Agent', 'FlashView-Webhook/1.0');
    });
});

test('throws on failed response', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $job = new SendWebhookNotification(
        webhookUrl: $this->webhookUrl,
        webhookSecret: $this->webhookSecret,
        hashId: 'abc123',
        createdAt: '2026-03-25T10:00:00+00:00',
        retrievedAt: '2026-03-25T12:00:00+00:00',
        userId: 1,
    );

    $this->expectException(WebhookDeliveryFailedException::class);
    $this->expectExceptionMessage('Webhook delivery failed with status 500');

    $job->handle();
});

test('backoff returns exponential delays', function () {
    $job = new SendWebhookNotification(
        webhookUrl: $this->webhookUrl,
        webhookSecret: $this->webhookSecret,
        hashId: 'abc123',
        createdAt: '2026-03-25T10:00:00+00:00',
        retrievedAt: '2026-03-25T12:00:00+00:00',
        userId: 1,
    );

    $backoff = $job->backoff();

    expect($backoff)->toHaveCount(10);
    expect($backoff[0])->toEqual(30);
    expect($backoff[9])->toEqual(28800);
});

test('retry until returns future datetime', function () {
    $job = new SendWebhookNotification(
        webhookUrl: $this->webhookUrl,
        webhookSecret: $this->webhookSecret,
        hashId: 'abc123',
        createdAt: '2026-03-25T10:00:00+00:00',
        retrievedAt: '2026-03-25T12:00:00+00:00',
        userId: 1,
    );

    $retryUntil = $job->retryUntil();

    expect($retryUntil)->toBeGreaterThan(now());
    expect($retryUntil)->toBeLessThanOrEqual(now()->addHours(24)->addMinute());
});
