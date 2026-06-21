<?php

namespace Tests\Feature\Call;

use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChallengeCallSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_challenge_returns_challenge_and_salt_for_valid_hash_id(): void
    {
        $session = CallSession::factory()->create();

        $response = $this->getJson("/call-sessions/{$session->hash_id}/challenge");

        $response->assertOk()
            ->assertJsonStructure(['challenge', 'salt'])
            ->assertJsonPath('salt', $session->key_salt);

        $this->assertEquals(64, strlen($response->json('challenge')));
    }

    public function test_challenge_returns_404_for_unknown_hash_id(): void
    {
        $response = $this->getJson('/call-sessions/unknownhash/challenge');

        $response->assertNotFound();
    }

    public function test_challenge_is_stored_in_cache_with_60_second_ttl(): void
    {
        $session = CallSession::factory()->create();

        $response = $this->getJson("/call-sessions/{$session->hash_id}/challenge");

        $response->assertOk();

        $cached = Cache::get("call-challenge:{$session->id}");
        $this->assertNotNull($cached);
        $this->assertEquals($response->json('challenge'), $cached);
    }
}
