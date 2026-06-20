<?php

namespace Database\Factories;

use App\Models\SecureLineCredit;
use App\Models\SecureLineProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecureLineCredit>
 */
class SecureLineCreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => bin2hex(random_bytes(32)),
            'stripe_session_id' => 'cs_test_'.$this->faker->uuid(),
            'secure_line_product_id' => SecureLineProduct::factory(),
            'call_session_id' => null,
            'used_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
        ]);
    }
}
