<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookNotification;
use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WebhookNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $reflection->setValue($this->app, false);
    }

    public function test_webhook_dispatched_when_plan_supports_webhook_and_user_has_webhook(): void
    {
        Bus::fake();

        $plan = $this->createPlanWithWebhook(true);
        $user = User::factory()->withWebhook()->create();
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Bus::assertDispatched(SendWebhookNotification::class, function ($job) use ($secret, $user) {
            return $job->hashId === $secret->hash_id
                && $job->event === 'retrieved'
                && $job->userId === $user->id;
        });
    }

    public function test_webhook_not_dispatched_when_plan_does_not_support_webhook(): void
    {
        Bus::fake();

        $plan = $this->createPlanWithWebhook(false);
        $user = User::factory()->withWebhook()->create();
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Bus::assertNotDispatched(SendWebhookNotification::class);
    }

    public function test_webhook_not_dispatched_when_user_has_no_webhook_configured(): void
    {
        Bus::fake();

        $plan = $this->createPlanWithWebhook(true);
        $user = User::factory()->create();
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Bus::assertNotDispatched(SendWebhookNotification::class);
    }

    public function test_webhook_not_dispatched_for_guest_secrets(): void
    {
        Bus::fake();

        $secret = $this->createActiveSecret();
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Bus::assertNotDispatched(SendWebhookNotification::class);
    }

    public function test_webhook_not_dispatched_for_user_without_plan(): void
    {
        Bus::fake();

        $user = User::factory()->withWebhook()->create();

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Bus::assertNotDispatched(SendWebhookNotification::class);
    }

    public function test_webhook_payload_contains_correct_fields(): void
    {
        Bus::fake();

        $plan = $this->createPlanWithWebhook(true);
        $user = User::factory()->withWebhook('https://example.com/hook', 'test-secret-123')->create();
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Bus::assertDispatched(SendWebhookNotification::class, function ($job) use ($secret, $user) {
            return $job->webhookUrl === 'https://example.com/hook'
                && $job->webhookSecret === 'test-secret-123'
                && $job->hashId === $secret->hash_id
                && $job->event === 'retrieved'
                && ! empty($job->createdAt)
                && ! empty($job->retrievedAt)
                && $job->userId === $user->id;
        });
    }

    private function createPlanWithWebhook(bool $webhookEnabled): Plan
    {
        return Plan::factory()->create([
            'name' => $webhookEnabled ? 'Prime' : 'Basic',
            'features' => [
                'email_notification' => [
                    'order' => 4.5,
                    'label' => 'Email Notifications',
                    'config' => [
                        'email' => true,
                    ],
                    'type' => 'feature',
                ],
                'webhook_notification' => [
                    'order' => 4.6,
                    'label' => 'Webhook Notifications',
                    'config' => [
                        'webhook' => $webhookEnabled,
                    ],
                    'type' => $webhookEnabled ? 'feature' : 'missing',
                ],
                'api' => [
                    'order' => 6,
                    'label' => 'API Access',
                    'config' => [],
                    'type' => 'feature',
                ],
            ],
            'stripe_product_id' => 'prod_test',
            'stripe_monthly_price_id' => 'price_monthly_test_'.($webhookEnabled ? 'prime' : 'basic'),
            'stripe_yearly_price_id' => 'price_yearly_test',
            'price_per_month' => 50,
            'price_per_year' => 500,
        ]);
    }

    private function createActiveSecret(?User $user = null): Secret
    {
        return Secret::withoutGlobalScopes()->create([
            'message' => 'test-secret-message',
            'user_id' => $user?->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function createSubscriptionForUser(User $user, Plan $plan): void
    {
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.$user->id,
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
        ]);
    }
}
