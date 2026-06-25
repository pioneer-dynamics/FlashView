<?php

use App\Models\LockerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('is used returns false when unused', function () {
    $credit = LockerCredit::factory()->create();

    expect($credit->isUsed())->toBeFalse();
});

test('is used returns true when used at set', function () {
    $credit = LockerCredit::factory()->used()->create();

    expect($credit->isUsed())->toBeTrue();
});

test('unused scope returns only unused', function () {
    LockerCredit::factory()->create(['token' => 'unused1']);
    LockerCredit::factory()->used()->create(['token' => 'used1']);

    $unused = LockerCredit::unused()->get();

    expect($unused)->toHaveCount(1);
    expect($unused->first()->token)->toEqual('unused1');
});
