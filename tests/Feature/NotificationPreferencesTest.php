<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_notification_preferences(): void
    {
        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_user_can_enable_secret_retrieved_notification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->notify_secret_retrieved);
    }

    public function test_user_can_disable_secret_retrieved_notification(): void
    {
        $user = User::factory()->withSecretRetrievedNotifications()->create();

        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }

    public function test_validation_requires_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors('notify_secret_retrieved');
    }

    public function test_notification_preference_cleared_when_plan_downgraded(): void
    {
        $basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic',
            'stripe_yearly_price_id' => 'price_yearly_basic',
            'stripe_product_id' => 'prod_basic',
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => [
                'notification' => [
                    'order' => 4.5,
                    'label' => 'Notifications',
                    'config' => ['email' => true, 'webhook' => false],
                    'type' => 'feature',
                ],
            ],
        ]);

        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.$user->id,
            'stripe_status' => 'active',
            'stripe_price' => $basicPlan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $this->assertTrue($user->fresh()->notify_secret_retrieved);

        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'stripe_monthly_price_id' => 'price_monthly_free',
            'stripe_yearly_price_id' => 'price_yearly_free',
            'stripe_product_id' => 'prod_free',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'notification' => [
                    'order' => 4.5,
                    'label' => 'Notifications',
                    'config' => ['email' => false, 'webhook' => false],
                    'type' => 'missing',
                ],
            ],
        ]);

        $subscription = $user->subscriptions()->first();
        $subscription->update([
            'stripe_price' => $freePlan->stripe_monthly_price_id,
        ]);

        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }

    public function test_notification_preference_cleared_when_subscription_cancelled(): void
    {
        $basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic_cancel',
            'stripe_yearly_price_id' => 'price_yearly_basic_cancel',
            'stripe_product_id' => 'prod_basic_cancel',
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => [
                'notification' => [
                    'order' => 4.5,
                    'label' => 'Notifications',
                    'config' => ['email' => true, 'webhook' => false],
                    'type' => 'feature',
                ],
            ],
        ]);

        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_cancel_'.$user->id,
            'stripe_status' => 'active',
            'stripe_price' => $basicPlan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $this->assertTrue($user->fresh()->notify_secret_retrieved);

        $subscription = $user->subscriptions()->first();
        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }
}
