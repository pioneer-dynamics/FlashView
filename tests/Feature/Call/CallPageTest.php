<?php

namespace Tests\Feature\Call;

use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_index_page_renders(): void
    {
        $response = $this->get('/calls');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Call/Index'));
    }

    public function test_call_join_page_renders_for_active_session(): void
    {
        $session = CallSession::factory()->create();

        $response = $this->get("/calls/{$session->hash_id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Call/Join')
                ->where('session.bridge_number', $session->hash_id)
                ->where('session.is_active', true)
            );
    }

    public function test_call_join_page_shows_future_session_as_not_active(): void
    {
        $session = CallSession::factory()->notYetStarted()->create();

        $response = $this->get("/calls/{$session->hash_id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Call/Join')
                ->where('session.is_active', false)
            );
    }

    public function test_call_join_page_returns_404_for_invalid_hash(): void
    {
        $response = $this->get('/calls/invalidhash');

        $response->assertNotFound();
    }

    public function test_call_room_page_renders(): void
    {
        $session = CallSession::factory()->create();

        $response = $this->get("/calls/{$session->hash_id}/room");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Call/Room')
                ->where('session.bridge_number', $session->hash_id)
            );
    }

    public function test_call_room_page_returns_404_for_invalid_hash(): void
    {
        $response = $this->get('/calls/invalidhash/room');

        $response->assertNotFound();
    }
}
