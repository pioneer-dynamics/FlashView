<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

class SubscriptionObserverTest extends TestCase
{
    use RefreshDatabase;

    private function subscribeUser(User $user, Plan $plan): Subscription
    {
        return $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->bothify('??????????'),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
    }

    public function test_tokens_deleted_when_subscription_loses_api_access(): void
    {
        $apiPlan = Plan::factory()->withApiAccess()->create();
        $freePlan = Plan::factory()->free()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $subscription = $this->subscribeUser($user, $apiPlan);
        $user->createToken('test-token', ['secrets:list']);
        $this->assertCount(1, $user->tokens);

        $subscription->update(['stripe_price' => $freePlan->stripe_monthly_price_id]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_webhook_cleared_when_plan_loses_api_access(): void
    {
        $apiPlan = Plan::factory()->withApiAccess()->create();
        $freePlan = Plan::factory()->free()->create();
        $user = User::factory()->withPersonalTeam()->withWebhook()->create();

        $subscription = $this->subscribeUser($user, $apiPlan);

        $subscription->update(['stripe_price' => $freePlan->stripe_monthly_price_id]);

        $user->refresh();
        $this->assertNull($user->webhook_url);
        $this->assertNull($user->webhook_secret);
    }

    public function test_email_notification_reset_when_plan_loses_email_support(): void
    {
        $emailPlan = Plan::factory()->withEmailNotifications()->create();
        $freePlan = Plan::factory()->free()->create();
        $user = User::factory()->withPersonalTeam()->withSecretRetrievedNotifications()->create();

        $subscription = $this->subscribeUser($user, $emailPlan);
        $this->assertTrue($user->notify_secret_retrieved);

        $subscription->update(['stripe_price' => $freePlan->stripe_monthly_price_id]);

        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }

    public function test_tokens_not_deleted_when_plan_retains_api_access(): void
    {
        $plan1 = Plan::factory()->withApiAccess()->create();
        $plan2 = Plan::factory()->withApiAccess()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $subscription = $this->subscribeUser($user, $plan1);
        $user->createToken('test-token', ['secrets:list']);

        $subscription->update(['stripe_price' => $plan2->stripe_monthly_price_id]);

        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_subscription_deletion_clears_everything(): void
    {
        $apiPlan = Plan::factory()->withApiAccess()->create();
        $user = User::factory()->withPersonalTeam()
            ->withWebhook()
            ->withSecretRetrievedNotifications()
            ->create();

        $subscription = $this->subscribeUser($user, $apiPlan);
        $user->createToken('test-token', ['secrets:list']);

        $subscription->delete();

        $user->refresh();
        $this->assertCount(0, $user->tokens);
        $this->assertNull($user->webhook_url);
        $this->assertNull($user->webhook_secret);
        $this->assertFalse($user->notify_secret_retrieved);
    }

    public function test_only_cli_tokens_deleted_when_losing_api_access_but_retaining_mobile_access(): void
    {
        $bothPlan = Plan::factory()->withApiAccess()->create([
            'features' => array_merge(
                Plan::factory()->withApiAccess()->make()->features,
                ['mobile_app' => ['order' => 8, 'label' => 'Mobile App Access', 'config' => [], 'type' => 'feature']]
            ),
        ]);
        $mobilePlan = Plan::factory()->withMobileAccess()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $subscription = $this->subscribeUser($user, $bothPlan);

        $cliToken = $user->createToken('My CLI', ['secrets:list']);
        $cliToken->accessToken->update(['type' => 'cli']);

        $mobileToken = $user->createToken('My iPhone', ['secrets:list']);
        $mobileToken->accessToken->update(['type' => 'mobile']);

        $this->assertCount(2, $user->fresh()->tokens);

        $subscription->update(['stripe_price' => $mobilePlan->stripe_monthly_price_id]);

        $remaining = $user->fresh()->tokens;
        $this->assertCount(1, $remaining);
        $this->assertEquals('mobile', $remaining->first()->type);
    }

    public function test_only_mobile_tokens_deleted_when_losing_mobile_access_but_retaining_api_access(): void
    {
        $bothPlan = Plan::factory()->withApiAccess()->create([
            'features' => array_merge(
                Plan::factory()->withApiAccess()->make()->features,
                ['mobile_app' => ['order' => 8, 'label' => 'Mobile App Access', 'config' => [], 'type' => 'feature']]
            ),
        ]);
        $apiPlan = Plan::factory()->withApiAccess()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $subscription = $this->subscribeUser($user, $bothPlan);

        $cliToken = $user->createToken('My CLI', ['secrets:list']);
        $cliToken->accessToken->update(['type' => 'cli']);

        $mobileToken = $user->createToken('My iPhone', ['secrets:list']);
        $mobileToken->accessToken->update(['type' => 'mobile']);

        $this->assertCount(2, $user->fresh()->tokens);

        $subscription->update(['stripe_price' => $apiPlan->stripe_monthly_price_id]);

        $remaining = $user->fresh()->tokens;
        $this->assertCount(1, $remaining);
        $this->assertEquals('cli', $remaining->first()->type);
    }
}
