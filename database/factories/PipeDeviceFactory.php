<?php

namespace Database\Factories;

use App\Models\PipeDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipeDevice>
 */
class PipeDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => 'DEV'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            'public_key' => base64_encode(json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => base64_encode(random_bytes(32)), 'y' => base64_encode(random_bytes(32))])),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
