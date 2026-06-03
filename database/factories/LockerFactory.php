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

    public function ecdsa(): static
    {
        return $this->state(function (array $attributes) {
            $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            $details = openssl_pkey_get_details($key);
            $ecPoint = $details['ec'];
            $toBase64url = fn ($b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
            $jwk = ['kty' => 'EC', 'crv' => 'P-256', 'x' => $toBase64url($ecPoint['x']), 'y' => $toBase64url($ecPoint['y'])];

            return [
                'public_key' => base64_encode(json_encode($jwk)),
                'auth_challenge' => null,
                'auth_verifier' => null,
                'update_token_hash' => null,
            ];
        });
    }
}
