<?php

namespace Database\Factories;

use App\Models\Locker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Locker>
 */
class LockerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => $this->faker->numerify('##########'),
            'payload' => bin2hex(random_bytes(64)),
            'storage_path' => null,
            'auth_challenge' => bin2hex(random_bytes(32)),
            'auth_verifier' => bin2hex(random_bytes(32)),
            'update_token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'expires_at' => now()->addYear(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function fileLocker(): static
    {
        return $this->state(fn (array $attributes) => [
            'storage_path' => 'lockers/'.$attributes['account_id'].'/payload',
        ]);
    }
}
