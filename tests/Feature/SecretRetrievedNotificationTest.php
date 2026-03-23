<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use App\Notifications\SecretRetrievedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecretRetrievedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Allow the Secret retrieved event to fire during tests
        $reflection = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $reflection->setValue($this->app, false);
    }

    public function test_notification_sent_when_plan_allows_and_user_opted_in(): void
    {
        Notification::fake();

        $plan = $this->createPlanWithNotifications(true);
        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Notification::assertSentTo($user, SecretRetrievedNotification::class);
    }

    public function test_notification_not_sent_when_user_opted_out(): void
    {
        Notification::fake();

        $plan = $this->createPlanWithNotifications(true);
        $user = User::factory()->create(['notify_secret_retrieved' => false]);
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Notification::assertNotSentTo($user, SecretRetrievedNotification::class);
    }

    public function test_notification_not_sent_when_plan_disallows(): void
    {
        Notification::fake();

        $plan = $this->createPlanWithNotifications(false);
        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $this->createSubscriptionForUser($user, $plan);

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Notification::assertNotSentTo($user, SecretRetrievedNotification::class);
    }

    public function test_notification_not_sent_for_guest_secrets(): void
    {
        Notification::fake();

        $secret = $this->createActiveSecret();
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Notification::assertNothingSent();
    }

    public function test_notification_not_sent_when_user_has_no_plan(): void
    {
        Notification::fake();

        $user = User::factory()->withSecretRetrievedNotifications()->create();

        $secret = $this->createActiveSecret($user);
        $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

        $this->get($url);

        Notification::assertNotSentTo($user, SecretRetrievedNotification::class);
    }

    private function createPlanWithNotifications(bool $enabled): Plan
    {
        return Plan::factory()->create([
            'name' => $enabled ? 'Pro' : 'Free',
            'features' => [
                'notification' => [
                    'order' => 4.5,
                    'label' => 'Get notified when a message is retrieved',
                    'config' => ['notifications' => $enabled],
                    'type' => $enabled ? 'feature' : 'missing',
                ],
            ],
            'stripe_product_id' => 'prod_test',
            'stripe_monthly_price_id' => 'price_monthly_test',
            'stripe_yearly_price_id' => 'price_yearly_test',
            'price_per_month' => 9.99,
            'price_per_year' => 99.99,
        ]);
    }

    /**
     * Create an active secret with an encrypted message.
     */
    private function createActiveSecret(?User $user = null): Secret
    {
        return Secret::withoutGlobalScopes()->create([
            'message' => 'test-secret-message',
            'user_id' => $user?->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    /**
     * Create a Cashier subscription linking the user to a plan.
     */
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
