<?php

namespace Database\Factories;

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallParticipant>
 */
class CallParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'call_session_id' => CallSession::factory(),
            'public_key' => null,
            'joined_at' => now(),
            'left_at' => null,
            'ip_address' => '127.0.0.1',
        ];
    }
}
