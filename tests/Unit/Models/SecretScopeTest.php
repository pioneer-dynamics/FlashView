<?php

use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('active scope returns non expired secrets with message', function () {
    $active = Secret::factory()->create();
    Secret::factory()->expired()->create();
    Secret::factory()->retrieved()->create();

    $results = Secret::withoutGlobalScope(ActiveScope::class)->active()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->is($active))->toBeTrue();
});

test('active scope excludes expired secrets', function () {
    Secret::factory()->expired()->create();

    $results = Secret::withoutGlobalScope(ActiveScope::class)->active()->get();

    expect($results)->toHaveCount(0);
});

test('active scope excludes secrets with null message', function () {
    Secret::factory()->retrieved()->create();

    $results = Secret::withoutGlobalScope(ActiveScope::class)->active()->get();

    expect($results)->toHaveCount(0);
});

test('expired scope returns past expiry secrets', function () {
    Secret::factory()->expired()->create();
    Secret::factory()->create();

    $results = Secret::withoutGlobalScope(ActiveScope::class)->expired()->get();

    expect($results)->toHaveCount(1);
});

test('expired scope excludes future expiry secrets', function () {
    Secret::factory()->create();

    $results = Secret::withoutGlobalScope(ActiveScope::class)->expired()->get();

    expect($results)->toHaveCount(0);
});

test('ready to prune returns old expired secrets without message', function () {
    Secret::factory()->readyToPrune()->create();
    Secret::factory()->expired()->create();

    $results = Secret::withoutGlobalScope(ActiveScope::class)->readyToPrune()->get();

    expect($results)->toHaveCount(1);
});

test('ready to prune excludes recent expired secrets', function () {
    Secret::factory()->create([
        'expires_at' => now()->subDays(5),
        'message' => null,
    ]);

    $results = Secret::withoutGlobalScope(ActiveScope::class)->readyToPrune()->get();

    expect($results)->toHaveCount(0);
});

test('global active scope applied by default', function () {
    Secret::factory()->create();
    Secret::factory()->expired()->create();

    expect(Secret::all())->toHaveCount(1);
});

test('without global scope bypasses active scope', function () {
    Secret::factory()->create();
    Secret::factory()->expired()->create();

    expect(Secret::withoutGlobalScope(ActiveScope::class)->get())->toHaveCount(2);
});
