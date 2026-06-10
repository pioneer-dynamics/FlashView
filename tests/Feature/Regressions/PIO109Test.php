<?php

namespace Tests\Feature\Regressions;

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIO-109: unlock response must NOT contain download_url — presigned URLs are now
 * fetched on demand via GET /lockers/{accountId}/download-url to prevent expiry.
 */
class PIO109Test extends TestCase
{
    use RefreshDatabase;

    public function test_unlock_response_does_not_include_download_url(): void
    {
        Storage::fake();
        $storagePath = 'lockers/pio109.bin';
        Storage::put($storagePath, 'encrypted-content');

        Locker::factory()->create([
            'account_id' => '1234567890',
            'auth_verifier' => str_repeat('a', 64),
            'storage_path' => $storagePath,
        ]);

        $response = $this->postJson(route('lockers.unlock', '1234567890'), [
            'verifier' => str_repeat('a', 64),
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('download_url', $response->json());
    }
}
