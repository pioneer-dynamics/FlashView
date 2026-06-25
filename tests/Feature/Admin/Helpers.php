<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Creates a user and sets their email as the admin email in config.
 */
function adminUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    Config::set('admin.emails', [$user->email]);

    return $user;
}

/**
 * Creates a regular (non-admin) user.
 */
function nonAdminUser(): User
{
    return User::factory()->withPersonalTeam()->create();
}

/**
 * Returns a default plan creation payload with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function planPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Plan',
        'price_per_month' => 10.00,
        'price_per_year' => 100.00,
        'create_stripe_product' => false,
        'stripe_product_id' => '',
        'stripe_monthly_price_id' => '',
        'stripe_yearly_price_id' => '',
        'features' => [
            'messages' => ['order' => 1, 'type' => 'limit',   'config' => ['message_length' => 5000]],
            'expiry' => ['order' => 3, 'type' => 'limit',   'config' => ['expiry_minutes' => 20160]],
            'throttling' => ['order' => 4, 'type' => 'feature', 'config' => []],
            'support' => ['order' => 5, 'type' => 'feature', 'config' => []],
            'api' => ['order' => 6, 'type' => 'feature', 'config' => []],
        ],
    ], $overrides);
}
