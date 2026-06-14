<?php

namespace Tests\Feature\Call;

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalCallMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_session_and_participants(): void
    {
        $session = CallSession::factory()->create();
        CallParticipant::factory()->create(['call_session_id' => $session->id]);

        $this->artisan('legal:call-metadata', ['hash_id' => $session->hash_id])
            ->assertSuccessful()
            ->expectsOutputToContain($session->hash_id)
            ->expectsOutputToContain('Session')
            ->expectsOutputToContain('Participants');
    }

    public function test_command_decrypts_ip_address_in_output(): void
    {
        $session = CallSession::factory()->create();
        CallParticipant::factory()->create([
            'call_session_id' => $session->id,
            'ip_address' => '10.0.0.1',
        ]);

        $this->artisan('legal:call-metadata', ['hash_id' => $session->hash_id])
            ->assertSuccessful()
            ->expectsOutputToContain('10.0.0.1');
    }

    public function test_command_fails_for_unknown_bridge_number(): void
    {
        $this->artisan('legal:call-metadata', ['hash_id' => 'unknownhash'])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    public function test_command_outputs_empty_participants_table_when_already_purged(): void
    {
        $session = CallSession::factory()->create();

        $this->artisan('legal:call-metadata', ['hash_id' => $session->hash_id])
            ->assertSuccessful()
            ->expectsOutputToContain('Participants');
    }
}
