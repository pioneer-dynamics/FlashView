<?php

use App\Jobs\ClearExpiredSecrets;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('clears message from expired secrets', function () {
    $secret = Secret::factory()->expired()->create();

    (new ClearExpiredSecrets)->handle();

    $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
    expect($secret->message)->toBeNull();
});

test('does not clear active secrets', function () {
    $secret = Secret::factory()->create([
        'expires_at' => now()->addHours(4),
    ]);

    (new ClearExpiredSecrets)->handle();

    $secret->refresh();
    expect($secret->message)->not->toBeNull();
});

test('handles no expired secrets gracefully', function () {
    (new ClearExpiredSecrets)->handle();

    expect(true)->toBeTrue();
});
