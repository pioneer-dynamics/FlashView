<?php

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('verify update token matches hash', function () {
    $token = 'mytoken';
    $locker = Locker::factory()->create([
        'update_token_hash' => hash('sha256', $token),
    ]);

    expect($locker->verifyUpdateToken($token))->toBeTrue();
});

test('verify update token rejects wrong token', function () {
    $locker = Locker::factory()->create([
        'update_token_hash' => hash('sha256', 'correcttoken'),
    ]);

    expect($locker->verifyUpdateToken('wrongtoken'))->toBeFalse();
});

test('verify auth verifier matches', function () {
    $verifier = str_repeat('a', 64);
    $locker = Locker::factory()->create([
        'auth_verifier' => $verifier,
    ]);

    expect($locker->verifyAuthVerifier($verifier))->toBeTrue();
});

test('is file locker returns true when storage path set', function () {
    $locker = Locker::factory()->fileLocker()->create();

    expect($locker->isFileLocker())->toBeTrue();
});

test('is file locker returns false when no storage path', function () {
    $locker = Locker::factory()->create(['storage_path' => null]);

    expect($locker->isFileLocker())->toBeFalse();
});

test('active scope excludes expired', function () {
    Locker::factory()->create(['account_id' => '1111111111']);
    Locker::factory()->expired()->create(['account_id' => '2222222222']);

    $active = Locker::active()->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->account_id)->toEqual('1111111111');
});

test('expired scope returns only expired', function () {
    Locker::factory()->create(['account_id' => '1111111111']);
    Locker::factory()->expired()->create(['account_id' => '2222222222']);

    $expired = Locker::expired()->get();

    expect($expired)->toHaveCount(1);
    expect($expired->first()->account_id)->toEqual('2222222222');
});

test('wrapped file key is encrypted at rest', function () {
    $plaintext = 'my-base64-wrapped-dek-value';
    $locker = Locker::factory()->create([
        'wrapped_file_key' => $plaintext,
    ]);

    // Raw DB value must differ from plaintext (it is wrapped by APP_KEY via encrypted cast)
    $rawValue = DB::table('lockers')
        ->where('id', $locker->id)
        ->value('wrapped_file_key');

    $this->assertNotEquals($plaintext, $rawValue, 'wrapped_file_key must be encrypted in the database');
    expect($locker->fresh()->wrapped_file_key)->toEqual($plaintext, 'Model accessor must decrypt to original value');
});
