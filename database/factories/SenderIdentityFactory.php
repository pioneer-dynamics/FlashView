<?php

namespace Database\Factories;

use App\Models\SenderIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SenderIdentity>
 */
class SenderIdentityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'email',
            'company_name' => null,
            'domain' => null,
            'email' => fake()->safeEmail(),
            'verification_token' => null,
            'verified_at' => now(),
        ];
    }
}
