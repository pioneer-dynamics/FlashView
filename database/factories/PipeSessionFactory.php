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
            'is_complete' => false,
            'total_chunks' => null,
            'transfer_mode' => 'relay',
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
