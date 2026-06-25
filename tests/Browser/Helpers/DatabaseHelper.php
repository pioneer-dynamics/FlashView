<?php

use App\Models\Secret;
use App\Models\SecureLineProduct;
use Database\Seeders\PlanSeederLocal;
use Illuminate\Database\Seeder;

/**
 * Seeds the plans table using PlanSeederLocal.
 * Safe to call when the table is empty — the seeder no-ops if plans already exist.
 */
function seedPlans(): void
{
    /** @var Seeder $seeder */
    $seeder = new PlanSeederLocal;
    $seeder->run();
}

/**
 * Creates a SecureLineProduct with optional attribute overrides.
 *
 * @param  array<string, mixed>  $overrides
 */
function createSecureLineProduct(array $overrides = []): SecureLineProduct
{
    return SecureLineProduct::factory()->create(array_merge([
        'name' => 'Quick Call',
        'duration_minutes' => 30,
        'max_participants' => 5,
        'amount_cents' => 2000,
        'stripe_price_id' => null,
        'is_active' => true,
    ], $overrides));
}

/**
 * Marks all secrets as expired by setting expires_at to yesterday.
 * Replicates what ClearExpiredSecrets job does for text secrets.
 */
function expireAllSecrets(): void
{
    Secret::query()->update(['expires_at' => now()->subDay()]);
}
