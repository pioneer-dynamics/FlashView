<?php

namespace Database\Factories;

use App\Models\PipeSession;
use App\Models\PipeSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipeSignal>
 */
class PipeSignalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pipe_session_id' => PipeSession::factory(),
            'role' => $this->faker->randomElement(['sender', 'receiver']),
            'type' => $this->faker->randomElement(['offer', 'answer', 'ice-candidate']),
            'payload' => ['sdp' => $this->faker->sha256()],
        ];
    }
}
