<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

uses(RefreshDatabase::class);

test('api token permissions can be updated', function () {
    if (! Features::hasApiFeatures()) {
        $this->markTestSkipped('API support is not enabled.');
    }

    $this->actingAs($user = User::factory()->withPersonalTeam()->create());

    $plan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
    ]);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_perms',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    $token = $user->tokens()->create([
        'name' => 'Test Token',
        'token' => Str::random(40),
        'abilities' => ['secrets:create', 'secrets:list'],
    ]);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->put('/user/api-tokens/'.$token->id, [
            'name' => $token->name,
            'permissions' => [
                'secrets:delete',
                'missing-permission',
            ],
        ]);

    expect($user->fresh()->tokens->first()->can('secrets:delete'))->toBeTrue();
    expect($user->fresh()->tokens->first()->can('secrets:list'))->toBeFalse();
    expect($user->fresh()->tokens->first()->can('missing-permission'))->toBeFalse();
});

test('updating api token permissions requires password confirmation', function () {
    if (! Features::hasApiFeatures()) {
        $this->markTestSkipped('API support is not enabled.');
    }

    $this->actingAs($user = User::factory()->withPersonalTeam()->create());

    $plan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
    ]);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_perms_confirm',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    $token = $user->tokens()->create([
        'name' => 'Test Token',
        'token' => Str::random(40),
        'abilities' => ['secrets:create', 'secrets:list'],
    ]);

    $response = $this->put('/user/api-tokens/'.$token->id, [
        'name' => $token->name,
        'permissions' => ['secrets:delete'],
    ]);

    $response->assertRedirect();
    expect($user->fresh()->tokens->first()->can('secrets:delete'))->toBeFalse();
    expect($user->fresh()->tokens->first()->can('secrets:create'))->toBeTrue();
});
