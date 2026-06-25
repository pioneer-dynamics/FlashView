<?php

use App\Models\Plan;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('unauthenticated user is redirected from admin plans', function () {
    $response = $this->get(route('admin.plans.index'));

    $response->assertRedirect('/login');
});

test('non admin receives 403 on admin plans', function () {
    $user = nonAdminUser();

    $response = $this->actingAs($user)->get(route('admin.plans.index'));

    $response->assertStatus(403);
});

test('admin can view plans index', function () {
    $admin = adminUser();
    Plan::factory()->create(['name' => 'Starter']);

    $response = $this->actingAs($admin)->get(route('admin.plans.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Admin/Plans/Index')
        ->has('plans', 1)
    );
});

test('admin can create plan with mapped stripe ids', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), planPayload([
        'name' => 'Growth',
        'price_per_month' => 25.00,
    ]));

    $response->assertRedirect(route('admin.plans.index'));
    $this->assertDatabaseHas('plans', ['name' => 'Growth', 'price_per_month' => 25.00]);
});

test('admin can edit plan features', function () {
    $admin = adminUser();
    $plan = Plan::factory()->create();

    $updatedFeatures = planPayload()['features'];
    $updatedFeatures['messages'] = ['order' => 2, 'type' => 'limit', 'config' => ['message_length' => 99999]];

    $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), planPayload([
        'name' => $plan->name,
        'price_per_month' => $plan->price_per_month,
        'price_per_year' => $plan->price_per_year,
        'features' => $updatedFeatures,
    ]));

    $response->assertRedirect(route('admin.plans.index'));
    $plan->refresh();
    expect($plan->features['messages']['config']['message_length'])->toEqual(99999);
    expect($plan->features['messages']['order'])->toEqual(2);
    expect($plan->features['messages']['type'])->toEqual('limit');
});

test('admin cannot save plan with empty features', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), planPayload([
        'features' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['features']);
});

test('admin cannot save plan with missing type feature', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), planPayload([
        'features' => [
            'api' => ['order' => 1, 'type' => 'missing', 'config' => []],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['features.api.type']);
});

test('admin can delete plan with no subscribers', function () {
    $admin = adminUser();
    $plan = Plan::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

    $response->assertRedirect(route('admin.plans.index'));
    $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
});

test('admin cannot delete plan with active subscribers', function () {
    $admin = adminUser();
    $plan = Plan::factory()->create([
        'stripe_monthly_price_id' => 'price_monthly_abc',
        'stripe_yearly_price_id' => 'price_yearly_abc',
    ]);

    $subscriber = User::factory()->withPersonalTeam()->create();
    $subscription = $subscriber->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
    $subscription->items()->create([
        'stripe_id' => 'si_test_'.uniqid(),
        'stripe_product' => 'prod_abc',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

    $response->assertStatus(422);
    $this->assertDatabaseHas('plans', ['id' => $plan->id]);
});

test('is admin is true in inertia props for admin user', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.user.is_admin', true)
    );
});

test('is admin is false in inertia props for non admin', function () {
    $user = nonAdminUser();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.user.is_admin', false)
    );
});

test('admin store validates required fields', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.plans.store'), []);

    $response->assertSessionHasErrors(['name', 'price_per_month', 'price_per_year', 'create_stripe_product', 'features']);
});

test('non admin cannot create plan', function () {
    $user = nonAdminUser();

    $response = $this->actingAs($user)->postJson(route('admin.plans.store'), planPayload());

    $response->assertStatus(403);
});

test('non admin cannot update plan', function () {
    $user = nonAdminUser();
    $plan = Plan::factory()->create();

    $response = $this->actingAs($user)->putJson(route('admin.plans.update', $plan), planPayload());

    $response->assertStatus(403);
});

test('admin can save plan with start and end date', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), planPayload([
        'name' => 'Limited Plan',
        'start_date' => '2026-06-01',
        'end_date' => '2026-12-31',
    ]));

    $response->assertRedirect(route('admin.plans.index'));

    $plan = Plan::where('name', 'Limited Plan')->firstOrFail();
    expect($plan->start_date->toDateString())->toEqual('2026-06-01');
    expect($plan->end_date->toDateString())->toEqual('2026-12-31');
});

test('admin can clear dates on plan', function () {
    $admin = adminUser();
    $plan = Plan::factory()->activeWindow()->create();

    $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), planPayload([
        'name' => $plan->name,
        'price_per_month' => $plan->price_per_month,
        'price_per_year' => $plan->price_per_year,
        'start_date' => null,
        'end_date' => null,
    ]));

    $response->assertRedirect(route('admin.plans.index'));
    $plan->refresh();
    expect($plan->start_date)->toBeNull();
    expect($plan->end_date)->toBeNull();
});

test('end date before start date returns validation error', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), planPayload([
        'start_date' => '2026-12-31',
        'end_date' => '2026-06-01',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['end_date']);
});
