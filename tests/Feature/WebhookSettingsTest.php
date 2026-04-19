<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookNotification;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $primePlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primePlan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => [
                'email_notification' => [
                    'order' => 4.5,
                    'label' => 'Email Notifications',
                    'config' => ['email' => true],
                    'type' => 'feature',
                ],
                'webhook_notification' => [
                    'order' => 4.6,
                    'label' => 'Webhook Notifications',
                    'config' => ['webhook' => true],
                    'type' => 'feature',
                ],
                'api' => [
                    'order' => 6,
                    'label' => 'API Access',
                    'config' => [],
                    'type' => 'feature',
                ],
            ],
        ]);

        $this->user = User::factory()->withPersonalTeam()->create();
    }

    private function subscribeUserToPlan(User $user, Plan $plan): void
    {
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->word(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
    }

    public function test_guest_cannot_update_webhook_settings(): void
    {
        $response = $this->put('/user/webhook-settings', [
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_user_without_api_plan_cannot_update_webhook_settings(): void
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'stripe_monthly_price_id' => 'price_monthly_free',
            'stripe_yearly_price_id' => 'price_yearly_free',
            'stripe_product_id' => 'prod_free',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
            ],
        ]);

        $this->subscribeUserToPlan($this->user, $freePlan);
        $this->actingAs($this->user);

        $response = $this->put('/user/webhook-settings', [
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_save_webhook_url(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->put('/user/webhook-settings', [
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $response->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertEquals('https://example.com/webhook', $this->user->webhook_url);
        $this->assertNotNull($this->user->webhook_secret);
    }

    public function test_webhook_secret_auto_generated_on_first_url_save(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $this->assertNull($this->user->webhook_secret);

        $this->put('/user/webhook-settings', [
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->webhook_secret);
        $this->assertEquals(64, strlen($this->user->webhook_secret));
    }

    public function test_clearing_webhook_url_clears_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $this->put('/user/webhook-settings', [
            'webhook_url' => '',
        ]);

        $this->user->refresh();
        $this->assertNull($this->user->webhook_url);
        $this->assertNull($this->user->webhook_secret);
    }

    public function test_validation_rejects_non_https_url(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->put('/user/webhook-settings', [
            'webhook_url' => 'http://example.com/webhook',
        ]);

        $response->assertSessionHasErrors('webhook_url');
    }

    public function test_validation_rejects_invalid_url(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->put('/user/webhook-settings', [
            'webhook_url' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('webhook_url');
    }

    public function test_user_can_regenerate_webhook_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $oldSecret = bin2hex(random_bytes(32));
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => $oldSecret,
        ]);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/regenerate-secret');

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNotEquals($oldSecret, $this->user->webhook_secret);
        $this->assertEquals(64, strlen($this->user->webhook_secret));
    }

    public function test_regenerate_secret_returns_secret_via_flash(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/regenerate-secret');

        $response->assertSessionHas('flash.webhookSecret');
    }

    public function test_reveal_secret_returns_secret_via_flash(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $secret = bin2hex(random_bytes(32));
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => $secret,
        ]);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/reveal-secret');

        $response->assertSessionHas('flash.webhookSecret', $secret);
    }

    public function test_reveal_secret_requires_password_confirmation(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->post('/user/webhook-settings/reveal-secret');

        $response->assertRedirect();
        $response->assertSessionMissing('flash.webhookSecret');
    }

    public function test_regenerate_secret_requires_password_confirmation(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $oldSecret = bin2hex(random_bytes(32));
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => $oldSecret,
        ]);
        $this->actingAs($this->user);

        $response = $this->post('/user/webhook-settings/regenerate-secret');

        $response->assertRedirect();
        $this->assertEquals($oldSecret, $this->user->fresh()->webhook_secret);
    }

    public function test_cannot_regenerate_secret_without_webhook_configured(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/regenerate-secret');

        $response->assertStatus(422);
    }

    public function test_webhook_cleared_when_subscription_cancelled(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        $subscription = $this->user->subscriptions()->first();
        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        $this->user->refresh();
        $this->assertNull($this->user->webhook_url);
        $this->assertNull($this->user->webhook_secret);
    }

    public function test_webhook_cleared_when_plan_downgraded_to_non_api(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        $basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic_downgrade',
            'stripe_yearly_price_id' => 'price_yearly_basic_downgrade',
            'stripe_product_id' => 'prod_basic_downgrade',
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => [
                'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
            ],
        ]);

        $subscription = $this->user->subscriptions()->first();
        $subscription->update([
            'stripe_price' => $basicPlan->stripe_monthly_price_id,
        ]);

        $this->user->refresh();
        $this->assertNull($this->user->webhook_url);
        $this->assertNull($this->user->webhook_secret);
    }

    public function test_user_can_delete_webhook(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/webhook-settings');

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNull($this->user->webhook_url);
        $this->assertNull($this->user->webhook_secret);
    }

    public function test_delete_webhook_requires_api_plan(): void
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'stripe_monthly_price_id' => 'price_monthly_free_del',
            'stripe_yearly_price_id' => 'price_yearly_free_del',
            'stripe_product_id' => 'prod_free_del',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
            ],
        ]);

        $this->subscribeUserToPlan($this->user, $freePlan);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/webhook-settings');

        $response->assertStatus(403);
    }

    public function test_delete_webhook_requires_password_confirmation(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->delete('/user/webhook-settings');

        $response->assertRedirect();
        $this->assertNotNull($this->user->fresh()->webhook_url);
    }

    public function test_delete_webhook_when_none_configured(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/webhook-settings');

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNull($this->user->webhook_url);
        $this->assertNull($this->user->webhook_secret);
    }

    public function test_user_can_send_test_webhook(): void
    {
        Queue::fake();

        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/test');

        $response->assertRedirect();

        Queue::assertPushed(SendWebhookNotification::class, function ($job) {
            return $job->event === 'ping'
                && str_starts_with($job->hashId, 'test-')
                && $job->webhookUrl === 'https://example.com/webhook'
                && $job->userId === $this->user->id;
        });
    }

    public function test_test_webhook_requires_password_confirmation(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->post('/user/webhook-settings/test');

        $response->assertRedirect();
    }

    public function test_test_webhook_requires_webhook_configured(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/test');

        $response->assertStatus(422);
    }

    public function test_test_webhook_requires_api_plan(): void
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'stripe_monthly_price_id' => 'price_monthly_free_test',
            'stripe_yearly_price_id' => 'price_yearly_free_test',
            'stripe_product_id' => 'prod_free_test',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
            ],
        ]);

        $this->subscribeUserToPlan($this->user, $freePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $this->actingAs($this->user);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/webhook-settings/test');

        $response->assertStatus(403);
    }
}
