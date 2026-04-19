<?php

namespace Tests\Feature\Regressions;

use App\Exceptions\WebhookDeliveryFailedException;
use App\Jobs\SendWebhookNotification;
use App\Mail\WebhookDeliveryFailedMail;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PIO-74: RuntimeException on webhook delivery failure was reported to Nightwatch on every retry.
 * Fix: custom exception implementing ShouldntReport + alert email on permanent failure.
 */
class PIO74Test extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_delivery_exception_is_not_reportable(): void
    {
        $exception = new WebhookDeliveryFailedException('Webhook delivery failed with status 401');

        $this->assertInstanceOf(ShouldntReport::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function test_user_receives_email_on_permanent_webhook_failure(): void
    {
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
    }

    public function test_failed_does_not_throw_when_user_not_found(): void
    {
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
    }
}
