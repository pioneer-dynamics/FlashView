<?php

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('unlock response does not include download url', function () {
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
});
