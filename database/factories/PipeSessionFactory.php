<?php

namespace Database\Factories;

use App\Models\PipeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipeSession>
 */
class PipeSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => str_pad(bin2hex(random_bytes(16)), 32, '0'),
            'user_id' => null,
            'sender_device_id' => null,
            'receiver_device_id' => null,
            'encrypted_transfer_key' => null,
            'is_complete' => false,
            'storage_path' => null,
            'transfer_mode' => 'relay',
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
