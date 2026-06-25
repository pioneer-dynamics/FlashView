<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('is currently available returns true when no dates set', function () {
    $plan = Plan::factory()->create(['start_date' => null, 'end_date' => null]);

    expect($plan->isCurrentlyAvailable())->toBeTrue();
});

test('is currently available returns true when within window', function () {
    $plan = Plan::factory()->activeWindow()->create();

    expect($plan->isCurrentlyAvailable())->toBeTrue();
});

test('is currently available returns false when before start date', function () {
    $plan = Plan::factory()->futureWindow()->create();

    expect($plan->isCurrentlyAvailable())->toBeFalse();
});

test('is currently available returns false when after end date', function () {
    $plan = Plan::factory()->expiredWindow()->create();

    expect($plan->isCurrentlyAvailable())->toBeFalse();
});

test('is currently available returns true when start equals today', function () {
    $plan = Plan::factory()->create([
        'start_date' => now()->startOfDay()->toDateString(),
        'end_date' => null,
    ]);

    expect($plan->isCurrentlyAvailable())->toBeTrue();
});

test('is currently available returns true when end equals today', function () {
    $plan = Plan::factory()->create([
        'start_date' => null,
        'end_date' => now()->startOfDay()->toDateString(),
    ]);

    expect($plan->isCurrentlyAvailable())->toBeTrue();
});

test('is currently available returns true with only start date set in past', function () {
    $plan = Plan::factory()->create([
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);

    expect($plan->isCurrentlyAvailable())->toBeTrue();
});

test('is currently available returns true with only end date set in future', function () {
    $plan = Plan::factory()->create([
        'start_date' => null,
        'end_date' => now()->addDay()->toDateString(),
    ]);

    expect($plan->isCurrentlyAvailable())->toBeTrue();
});
