<?php

namespace Database\Factories;

use App\Models\PipeDevice;
use App\Models\PipePairing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipePairing>
 */
class PipePairingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sender_device_id' => PipeDevice::factory(),
            'receiver_device_id' => PipeDevice::factory(),
            'encrypted_seed' => base64_encode(random_bytes(48)),
            'is_accepted' => false,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
