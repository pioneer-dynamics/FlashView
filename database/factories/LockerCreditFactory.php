<?php

namespace Database\Factories;

use App\Models\LockerCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LockerCredit>
 */
class LockerCreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => bin2hex(random_bytes(32)),
            'tier' => $this->faker->randomElement(['text', 'file']),
            'years' => $this->faker->randomElement([1, 3, 5]),
            'stripe_session_id' => null,
            'locker_id' => null,
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
