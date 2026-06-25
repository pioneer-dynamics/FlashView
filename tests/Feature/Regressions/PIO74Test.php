<?php

use App\Exceptions\WebhookDeliveryFailedException;
use App\Jobs\SendWebhookNotification;
use App\Mail\WebhookDeliveryFailedMail;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('webhook delivery exception is not reportable', function () {
    $exception = new WebhookDeliveryFailedException('Webhook delivery failed with status 401');

    expect($exception)->toBeInstanceOf(ShouldntReport::class);
    expect($exception)->toBeInstanceOf(RuntimeException::class);
});

test('user receives email on permanent webhook failure', function () {
    Mail::fake();

    $user = User::factory()->create();

    $job = new SendWebhookNotification(
        webhookUrl: 'https://example.com/webhook',
        webhookSecret: 'secret',
        hashId: 'abc123',
        createdAt: now()->toIso8601String(),
        retrievedAt: now()->toIso8601String(),
        userId: $user->id,
    );

    $job->failed(new WebhookDeliveryFailedException('Webhook delivery failed with status 401'));

    Mail::assertSent(WebhookDeliveryFailedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

test('failed does not throw when user not found', function () {
    Mail::fake();

    $job = new SendWebhookNotification(
        webhookUrl: 'https://example.com/webhook',
        webhookSecret: 'secret',
        hashId: 'abc123',
        createdAt: now()->toIso8601String(),
        retrievedAt: now()->toIso8601String(),
        userId: 99999,
    );

    $this->expectNotToPerformAssertions();

    $job->failed(new WebhookDeliveryFailedException('Webhook delivery failed with status 401'));
});
