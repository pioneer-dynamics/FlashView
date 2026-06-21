<?php

namespace Tests\Feature\Call;

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CallParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_belongs_to_call_session(): void
    {
        $session = CallSession::factory()->create();
        $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

        $this->assertTrue($participant->session->is($session));
    }

    public function test_call_session_has_many_participants(): void
    {
        $session = CallSession::factory()->create();
        CallParticipant::factory()->count(3)->create(['call_session_id' => $session->id]);

        $this->assertCount(3, $session->participants);
    }

    public function test_ip_address_is_encrypted_at_rest(): void
    {
        $session = CallSession::factory()->create();
        $participant = CallParticipant::factory()->create([
            'call_session_id' => $session->id,
            'ip_address' => '192.168.1.100',
        ]);

        // The raw database value should not equal the plaintext IP
        $raw = DB::table('call_participants')
            ->where('id', $participant->id)
            ->value('ip_address');

        $this->assertNotEquals('192.168.1.100', $raw);

        // But the model attribute should transparently decrypt it
        $this->assertEquals('192.168.1.100', $participant->fresh()->ip_address);
    }
}
