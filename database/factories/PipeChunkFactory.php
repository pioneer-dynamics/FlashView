<?php

namespace Database\Factories;

use App\Models\PipeChunk;
use App\Models\PipeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipeChunk>
 */
class PipeChunkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pipe_session_id' => PipeSession::factory(),
            'chunk_index' => 0,
            'payload' => base64_encode('encrypted-chunk-data'),
        ];
    }
}
