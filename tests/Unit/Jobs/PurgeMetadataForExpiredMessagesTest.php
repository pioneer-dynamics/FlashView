<?php

use App\Jobs\PurgeMetadataForExpiredMessages;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('deletes secrets past prune threshold', function () {
    $secret = Secret::factory()->readyToPrune()->create();

    (new PurgeMetadataForExpiredMessages)->handle();

    expect(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id))->toBeNull();
});

test('does not delete recently expired secrets', function () {
    $secret = Secret::factory()->create([
        'expires_at' => now()->subDays(5),
        'message' => null,
    ]);

    (new PurgeMetadataForExpiredMessages)->handle();

    expect(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id))->not->toBeNull();
});

test('does not delete expired secrets with message still present', function () {
    $secret = Secret::factory()->create([
        'expires_at' => now()->subDays(config('secrets.prune_after') + 1),
    ]);

    (new PurgeMetadataForExpiredMessages)->handle();

    expect(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id))->not->toBeNull();
});

test('handles no purgeable secrets gracefully', function () {
    (new PurgeMetadataForExpiredMessages)->handle();

    expect(true)->toBeTrue();
});
