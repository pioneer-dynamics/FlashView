<?php

namespace Tests\Feature\Regressions;

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIO-102: unlock response for a file locker with envelope encryption must return
 * wrapped_file_key so the client can perform passphrase rotation without re-downloading
 * the full file.
 */
class PIO102Test extends TestCase
{
    use RefreshDatabase;

    public function test_unlock_response_includes_wrapped_file_key_for_file_lockers(): void
    {
        Storage::fake();
        $storagePath = 'lockers/test.bin';
        Storage::put($storagePath, 'encrypted-content');

        Locker::factory()->create([
            'account_id' => '1234567890',
            'auth_verifier' => str_repeat('a', 64),
            'storage_path' => $storagePath,
            'wrapped_file_key' => 'base64wrappedkeydata',
        ]);

        $response = $this->postJson(route('lockers.unlock', '1234567890'), [
            'verifier' => str_repeat('a', 64),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['wrapped_file_key']);
    }
}
