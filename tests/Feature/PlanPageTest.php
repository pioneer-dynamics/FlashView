<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

test('guest can view plans page', function () {
    Plan::factory()->free()->create();

    $this->get(route('plans.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
});

test('authenticated user without subscription can view plans page', function () {
    $user = User::factory()->withPersonalTeam()->create();
    Plan::factory()->free()->create();

    $this->actingAs($user)
        ->get(route('plans.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
});

test('authenticated user with subscription can view plans page', function () {
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
});

test('cancellation route requires authentication', function () {
    $this->post(route('plans.unsubscribe'))
        ->assertRedirectToRoute('login');
});

test('subscribe to unavailable plan does not reach stripe', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $plan = Plan::factory()->expiredWindow()->create();

    $response = $this->actingAs($user)
        ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

    // Guard fires before Stripe is touched — no stripe-related exception, just a redirect
    $response->assertRedirectToRoute('plans.index');
});

test('subscribe to not yet started plan redirects with banner error', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $plan = Plan::factory()->futureWindow()->create();

    $response = $this->actingAs($user)
        ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

    $response->assertRedirectToRoute('plans.index');
    expect(session('flash.banner'))->toEqual('This plan is not yet available.');
    expect(session('flash.bannerStyle'))->toEqual('danger');
});

test('subscribe to expired plan redirects with banner error', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $plan = Plan::factory()->expiredWindow()->create();

    $response = $this->actingAs($user)
        ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

    $response->assertRedirectToRoute('plans.index');
    expect(session('flash.banner'))->toEqual('This plan is no longer available for subscription.');
    expect(session('flash.bannerStyle'))->toEqual('danger');
});

test('plan swap to unavailable plan is blocked', function () {
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
    expect(session('flash.banner'))->toEqual('This plan is no longer available for subscription.');
});

test('free plan with date restrictions does not affect unsubscribed users', function () {
    $freePlan = Plan::factory()->free()->expiredWindow()->create();
    $user = User::factory()->withPersonalTeam()->create();

    $resolvedPlan = $user->resolvePlan();

    expect($resolvedPlan->id)->toEqual($freePlan->id);
});

test('subscribed user can cancel and is redirected to plans', function () {
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

    $mockStripeClient = new stdClass;
    $mockStripeClient->subscriptions = $mockSubscriptionsService;

    // bind() is used (not instance()) because Cashier passes constructor params to app(),
    // which causes instance() bindings to be bypassed in the container.
    $this->app->bind(StripeClient::class, fn () => $mockStripeClient);

    $this->actingAs($user)
        ->post(route('plans.unsubscribe'))
        ->assertRedirectToRoute('plans.index');
});
