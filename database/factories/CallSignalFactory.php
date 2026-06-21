<?php

namespace Database\Factories;

use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\CallSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallSignal>
 */
class CallSignalFactory extends Factory
{
    public function definition(): array
    {
        $session = CallSession::factory()->create();
        $from = CallParticipant::factory()->for($session, 'session')->create();
        $to = CallParticipant::factory()->for($session, 'session')->create();

        return [
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => $this->faker->randomElement(['offer', 'answer', 'ice-candidate', 'key-exchange']),
            'payload' => ['sdp' => $this->faker->text(100)],
        ];
    }
}
