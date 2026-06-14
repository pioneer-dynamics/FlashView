<?php

namespace Database\Factories;

use App\Models\CallSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallSession>
 */
class CallSessionFactory extends Factory
{
    public function definition(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'key_salt' => base64_encode(random_bytes(32)),
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addMinutes(55),
            'max_participants' => 2,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['ends_at' => now()->subMinute()]);
    }

    public function notYetStarted(): static
    {
        return $this->state(['starts_at' => now()->addMinutes(10)]);
    }
}
