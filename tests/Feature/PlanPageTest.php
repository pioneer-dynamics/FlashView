<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class PlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_plans_page(): void
    {
        Plan::factory()->free()->create();

        $this->get(route('plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
    }

    public function test_authenticated_user_without_subscription_can_view_plans_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Plan::factory()->free()->create();

        $this->actingAs($user)
            ->get(route('plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
    }

    public function test_authenticated_user_with_subscription_can_view_plans_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->create(['price_per_month' => 10]);
        Plan::factory()->free()->create();

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_view',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
    }

    public function test_cancellation_route_requires_authentication(): void
    {
        $this->post(route('plans.unsubscribe'))
            ->assertRedirectToRoute('login');
    }

    public function test_subscribe_to_unavailable_plan_does_not_reach_stripe(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->expiredWindow()->create();

        $response = $this->actingAs($user)
            ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

        // Guard fires before Stripe is touched — no stripe-related exception, just a redirect
        $response->assertRedirectToRoute('plans.index');
    }

    public function test_subscribe_to_not_yet_started_plan_redirects_with_banner_error(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->futureWindow()->create();

        $response = $this->actingAs($user)
            ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

        $response->assertRedirectToRoute('plans.index');
        $this->assertEquals('This plan is not yet available.', session('flash.banner'));
        $this->assertEquals('danger', session('flash.bannerStyle'));
    }

    public function test_subscribe_to_expired_plan_redirects_with_banner_error(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->expiredWindow()->create();

        $response = $this->actingAs($user)
            ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

        $response->assertRedirectToRoute('plans.index');
        $this->assertEquals('This plan is no longer available for subscription.', session('flash.banner'));
        $this->assertEquals('danger', session('flash.bannerStyle'));
    }

    public function test_plan_swap_to_unavailable_plan_is_blocked(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $existingPlan = Plan::factory()->create();
        $expiredPlan = Plan::factory()->expiredWindow()->create();

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_swap',
            'stripe_status' => 'active',
            'stripe_price' => $existingPlan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)
            ->get(route('plans.subscribe', ['plan' => $expiredPlan->id, 'period' => 'monthly']));

        $response->assertRedirectToRoute('plans.index');
        $this->assertEquals('This plan is no longer available for subscription.', session('flash.banner'));
    }

    public function test_free_plan_with_date_restrictions_does_not_affect_unsubscribed_users(): void
    {
        $freePlan = Plan::factory()->free()->expiredWindow()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $resolvedPlan = $user->resolvePlan();

        $this->assertEquals($freePlan->id, $resolvedPlan->id);
    }

    public function test_subscribed_user_can_cancel_and_is_redirected_to_plans(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->create(['price_per_month' => 10]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_cancel',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $fakeStripeSubscription = (object) [
            'id' => 'sub_test_cancel',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => now()->addDays(30)->timestamp,
        ];

        $mockSubscriptionsService = Mockery::mock();
        $mockSubscriptionsService->shouldReceive('update')->andReturn($fakeStripeSubscription);

        $mockStripeClient = new \stdClass;
        $mockStripeClient->subscriptions = $mockSubscriptionsService;

        // bind() is used (not instance()) because Cashier passes constructor params to app(),
        // which causes instance() bindings to be bypassed in the container.
        $this->app->bind(StripeClient::class, fn () => $mockStripeClient);

        $this->actingAs($user)
            ->post(route('plans.unsubscribe'))
            ->assertRedirectToRoute('plans.index');
    }
}
