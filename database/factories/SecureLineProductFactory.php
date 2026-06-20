<?php

namespace Database\Factories;

use App\Models\SecureLineProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecureLineProduct>
 */
class SecureLineProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'max_participants' => fake()->numberBetween(2, 10),
            'amount_cents' => fake()->numberBetween(500, 5000),
            'stripe_price_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withStripePrice(): static
    {
        return $this->state(fn () => ['stripe_price_id' => 'price_'.fake()->bothify('??????????')]);
    }
}
