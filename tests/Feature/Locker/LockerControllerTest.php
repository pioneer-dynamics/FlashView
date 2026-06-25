<?php

use App\Models\Locker;
use App\Models\LockerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('user can view buy page', function () {
    $response = $this->get(route('lockers.buy'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Locker/Buy'));
});

test('create page requires valid credit token', function () {
    $response = $this->get(route('lockers.create').'?token=invalid');

    $response->assertStatus(404);
});

test('create page rejects used credit token', function () {
    $credit = LockerCredit::factory()->used()->create(['token' => 'usedtoken']);

    $response = $this->get(route('lockers.create').'?token=usedtoken');

    $response->assertStatus(404);
});

test('create page loads with valid unused credit token', function () {
    $credit = LockerCredit::factory()->create(['token' => 'validtoken', 'tier' => 'text', 'years' => 1]);

    $response = $this->get(route('lockers.create').'?token=validtoken');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Locker/Create'));
});

test('store creates locker with valid credit token', function () {
    $credit = LockerCredit::factory()->create([
        'token' => 'tok123',
        'tier' => 'text',
        'years' => 1,
    ]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '1234567890',
        'credit_token' => 'tok123',
        'payload' => str_repeat('a', 100),
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'text',
        'storage_path' => null,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['expires_at', 'account_id'])
        ->assertJsonMissing(['update_token']);

    $this->assertDatabaseHas('lockers', ['account_id' => '1234567890']);
    $credit->refresh();
    expect($credit->used_at)->not->toBeNull();
});

test('store rejects duplicate account id', function () {
    Locker::factory()->create(['account_id' => '1234567890']);
    $credit = LockerCredit::factory()->create(['token' => 'tok456', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '1234567890',
        'credit_token' => 'tok456',
        'payload' => str_repeat('a', 100),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'text',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['account_id']);
});

test('store validates account id is 10 digits', function () {
    $credit = LockerCredit::factory()->create(['token' => 'tok789', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '12345',
        'credit_token' => 'tok789',
        'payload' => str_repeat('a', 100),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'text',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['account_id']);
});

test('open page renders open component', function () {
    $response = $this->get(route('lockers.open'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Locker/Open'));
});

test('open page passes renewed false by default', function () {
    $response = $this->get(route('lockers.open'));

    $response->assertInertia(fn ($page) => $page->where('renewed', false));
});

test('open page passes renewed true when query param set', function () {
    $response = $this->get(route('lockers.open').'?renewed=1');

    $response->assertInertia(fn ($page) => $page->where('renewed', true));
});

test('show route redirects to open for any account id', function () {
    $response = $this->get(route('lockers.show', '9999999999'));

    $response->assertRedirect(route('lockers.open'));
});

test('unlock returns 401 for unknown account', function () {
    $response = $this->postJson(route('lockers.unlock', '9999999999'), [
        'verifier' => str_repeat('a', 64),
    ]);

    $response->assertStatus(401)->assertJson(['error' => 'Credentials do not match.']);
});

test('unlock returns 401 for wrong verifier', function () {
    Locker::factory()->create([
        'account_id' => '1234567890',
        'auth_verifier' => str_repeat('a', 64),
    ]);

    $response = $this->postJson(route('lockers.unlock', '1234567890'), [
        'verifier' => str_repeat('b', 64),
    ]);

    $response->assertStatus(401)->assertJson(['error' => 'Credentials do not match.']);
});

test('unlock returns 410 for expired locker with correct verifier', function () {
    Locker::factory()->expired()->create([
        'account_id' => '1234567890',
        'auth_verifier' => str_repeat('a', 64),
    ]);

    $response = $this->postJson(route('lockers.unlock', '1234567890'), [
        'verifier' => str_repeat('a', 64),
    ]);

    $response->assertStatus(410);
});

test('unlock returns payload for correct verifier', function () {
    Locker::factory()->create([
        'account_id' => '1234567890',
        'payload' => 'hex_blob',
        'auth_verifier' => str_repeat('a', 64),
    ]);

    $response = $this->postJson(route('lockers.unlock', '1234567890'), [
        'verifier' => str_repeat('a', 64),
    ]);

    $response->assertStatus(200)->assertJsonStructure(['payload', 'expires_at', 'is_file_locker']);
});

test('show route redirects for active locker', function () {
    Locker::factory()->create(['account_id' => '1234567890']);

    $response = $this->get(route('lockers.show', '1234567890'));

    $response->assertRedirect(route('lockers.open'));
});

test('payload returns blob for active locker', function () {
    Locker::factory()->create(['account_id' => '1234567890', 'payload' => 'hex_blob_here']);

    $response = $this->getJson(route('lockers.payload', '1234567890'));

    $response->assertStatus(200)
        ->assertJsonStructure(['payload', 'auth_challenge']);
});

test('update requires valid update token', function () {
    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '1234567890',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $response = $this->putJson(
        route('lockers.update', '1234567890'),
        ['payload' => str_repeat('b', 100)],
        ['X-Update-Token' => $token]
    );

    $response->assertStatus(200)->assertJson(['ok' => true]);
});

test('update rejects invalid update token', function () {
    Locker::factory()->create([
        'account_id' => '1234567890',
        'update_token_hash' => hash('sha256', 'correcttoken'),
    ]);

    $response = $this->putJson(
        route('lockers.update', '1234567890'),
        ['payload' => str_repeat('b', 100)],
        ['X-Update-Token' => 'wrongtoken']
    );

    $response->assertStatus(403);
});

test('update replaces payload', function () {
    $token = bin2hex(random_bytes(32));
    $locker = Locker::factory()->create([
        'account_id' => '1234567890',
        'payload' => 'original',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $this->putJson(
        route('lockers.update', '1234567890'),
        ['payload' => 'newpayload'],
        ['X-Update-Token' => $token]
    )->assertStatus(200);

    $this->assertDatabaseHas('lockers', ['account_id' => '1234567890', 'payload' => 'newpayload']);
});

test('destroy requires valid update token', function () {
    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '1234567890',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $response = $this->deleteJson(
        route('lockers.destroy', '1234567890'),
        [],
        ['X-Update-Token' => $token]
    );

    $response->assertStatus(200)->assertJson(['ok' => true]);
});

test('destroy deletes locker record', function () {
    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '1234567890',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $this->deleteJson(
        route('lockers.destroy', '1234567890'),
        [],
        ['X-Update-Token' => $token]
    );

    $this->assertDatabaseMissing('lockers', ['account_id' => '1234567890']);
});

test('renew challenge returns challenge for known account', function () {
    Locker::factory()->create(['account_id' => '1234567890', 'auth_challenge' => 'mychallenge']);

    $response = $this->getJson(route('lockers.renew.challenge', '1234567890'));

    $response->assertStatus(200)->assertJson(['challenge' => 'mychallenge']);
});

test('renew purchase verifies verifier before stripe', function () {
    Locker::factory()->create([
        'account_id' => '1234567890',
        'auth_verifier' => str_repeat('a', 64),
    ]);

    $response = $this->postJson(route('lockers.renew.purchase', '1234567890'), [
        'verifier' => str_repeat('b', 64), // wrong verifier
        'years' => 1,
        'tier' => 'text',
    ]);

    $response->assertStatus(403);
});

test('renew purchase rejects wrong verifier', function () {
    Locker::factory()->create([
        'account_id' => '1234567890',
        'auth_verifier' => str_repeat('a', 64),
    ]);

    $response = $this->postJson(route('lockers.renew.purchase', '1234567890'), [
        'verifier' => str_repeat('c', 64),
        'years' => 1,
        'tier' => 'text',
    ]);

    $response->assertStatus(403)->assertJson(['error' => 'Invalid passphrase.']);
});

test('credit status returns pending for unknown session', function () {
    $response = $this->getJson(route('lockers.credit-status').'?session=unknown_session_id');

    $response->assertStatus(200)->assertJson(['pending' => true]);
});

test('credit status returns token when credit exists', function () {
    LockerCredit::factory()->create([
        'stripe_session_id' => 'cs_test_123',
        'token' => 'mytoken',
    ]);

    $response = $this->getJson(route('lockers.credit-status').'?session=cs_test_123');

    $response->assertStatus(200)->assertJson(['token' => 'mytoken']);
});

test('prepare file returns s3 direct upload url', function () {
    Storage::fake();
    $credit = LockerCredit::factory()->create(['token' => 'filetoken', 'tier' => 'file', 'years' => 1]);

    $response = $this->postJson(route('lockers.file.prepare'), [
        'credit_token' => 'filetoken',
    ]);

    $response->assertStatus(200)
        ->assertJson(['upload_type' => 's3_direct'])
        ->assertJsonStructure(['upload_url', 'upload_headers', 'storage_path']);
});

test('unlock does not return presigned download url for file locker', function () {
    Storage::fake();
    $storagePath = 'lockers/test.bin';
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
    $response->assertJsonStructure(['wrapped_file_key']);
});

test('payload does not return presigned download url for file locker', function () {
    Storage::fake();
    $storagePath = 'lockers/test.bin';
    Storage::put($storagePath, 'encrypted-content');

    Locker::factory()->create([
        'account_id' => '1234567890',
        'auth_verifier' => str_repeat('a', 64),
        'storage_path' => $storagePath,
    ]);

    $response = $this->getJson(route('lockers.payload', '1234567890'));

    $response->assertStatus(200)
        ->assertJsonStructure(['payload', 'auth_challenge']);
    $this->assertArrayNotHasKey('download_url', $response->json());
});

test('server upload route does not exist', function () {
    $response = $this->post('/lockers/file/upload/some-token');

    $response->assertStatus(404);
});

test('server download route does not exist', function () {
    $response = $this->get('/lockers/1234567890/file');

    $response->assertStatus(404);
});

test('store saves wrapped file key when provided', function () {
    Storage::fake();
    $credit = LockerCredit::factory()->create(['token' => 'filetok', 'tier' => 'file', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '9876543210',
        'credit_token' => 'filetok',
        'payload' => str_repeat('a', 100),
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'file',
        'storage_path' => 'lockers/test.bin',
        'wrapped_file_key' => 'base64wrappedkeydata',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('lockers', ['account_id' => '9876543210']);

    $locker = Locker::where('account_id', '9876543210')->first();
    expect($locker->wrapped_file_key)->toEqual('base64wrappedkeydata');
});

test('store does not require wrapped file key for text lockers', function () {
    $credit = LockerCredit::factory()->create(['token' => 'texttok', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '1111111111',
        'credit_token' => 'texttok',
        'payload' => str_repeat('a', 100),
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'text',
        'storage_path' => null,
    ]);

    $response->assertStatus(200);
});

test('unlock returns wrapped file key for new file lockers', function () {
    Storage::fake();
    $storagePath = 'lockers/test.bin';
    Storage::put($storagePath, 'encrypted-content');

    Locker::factory()->create([
        'account_id' => '2222222222',
        'auth_verifier' => str_repeat('a', 64),
        'storage_path' => $storagePath,
        'wrapped_file_key' => 'myWrappedKey123',
    ]);

    $response = $this->postJson(route('lockers.unlock', '2222222222'), [
        'verifier' => str_repeat('a', 64),
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['wrapped_file_key']);

    expect($response->json('wrapped_file_key'))->toEqual('myWrappedKey123');
});

test('unlock does not return wrapped file key for legacy file lockers', function () {
    Storage::fake();
    $storagePath = 'lockers/test.bin';
    Storage::put($storagePath, 'encrypted-content');

    Locker::factory()->create([
        'account_id' => '3333333333',
        'auth_verifier' => str_repeat('a', 64),
        'storage_path' => $storagePath,
        // No wrapped_file_key
    ]);

    $response = $this->postJson(route('lockers.unlock', '3333333333'), [
        'verifier' => str_repeat('a', 64),
    ]);

    $response->assertStatus(200);
    expect($response->json('wrapped_file_key'))->toBeNull();
});

test('update accepts new wrapped file key for passphrase change', function () {
    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '4444444444',
        'payload' => 'original',
        'wrapped_file_key' => 'oldWrappedKey',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $response = $this->putJson(
        route('lockers.update', '4444444444'),
        [
            'payload' => str_repeat('b', 100),
            'new_wrapped_file_key' => 'newWrappedKey',
        ],
        ['X-Update-Token' => $token]
    );

    $response->assertStatus(200)->assertJson(['ok' => true]);

    $locker = Locker::where('account_id', '4444444444')->first();
    expect($locker->wrapped_file_key)->toEqual('newWrappedKey');
});

test('update does not require new wrapped file key', function () {
    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '5555555555',
        'payload' => 'original',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $response = $this->putJson(
        route('lockers.update', '5555555555'),
        ['payload' => str_repeat('b', 100)],
        ['X-Update-Token' => $token]
    );

    $response->assertStatus(200)->assertJson(['ok' => true]);
});

test('update without storage path does not null out existing storage path', function () {
    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '6666666666',
        'payload' => 'original',
        'storage_path' => 'lockers/important-file.bin',
        'update_token_hash' => hash('sha256', $token),
    ]);

    // Passphrase-change PUT omits storage_path intentionally
    $response = $this->putJson(
        route('lockers.update', '6666666666'),
        [
            'payload' => str_repeat('b', 100),
            'new_wrapped_file_key' => 'newKey',
        ],
        ['X-Update-Token' => $token]
    );

    $response->assertStatus(200);

    $locker = Locker::where('account_id', '6666666666')->first();
    expect($locker->storage_path)->toEqual('lockers/important-file.bin', 'storage_path must not be nulled out when omitted from PUT body');
});

test('file update sends updated wrapped file key', function () {
    Storage::fake();
    $storagePath = 'lockers/old.bin';
    Storage::put($storagePath, 'old-content');

    $token = bin2hex(random_bytes(32));
    Locker::factory()->create([
        'account_id' => '7777777777',
        'payload' => 'original',
        'storage_path' => $storagePath,
        'wrapped_file_key' => 'oldWrappedKey',
        'update_token_hash' => hash('sha256', $token),
    ]);

    $newStoragePath = 'lockers/new.bin';
    Storage::put($newStoragePath, 'new-content');

    $response = $this->putJson(
        route('lockers.update', '7777777777'),
        [
            'payload' => str_repeat('b', 100),
            'storage_path' => $newStoragePath,
            'new_wrapped_file_key' => 'freshWrappedKey',
        ],
        ['X-Update-Token' => $token]
    );

    $response->assertStatus(200);

    $locker = Locker::where('account_id', '7777777777')->first();
    expect($locker->wrapped_file_key)->toEqual('freshWrappedKey');
    expect($locker->storage_path)->toEqual($newStoragePath);
});

// ─── ECDSA Tests ─────────────────────────────────────────────────────────
/**
 * Generate a real P-256 keypair for tests. Returns [publicKeyJwkBase64, privateKeyPem].
 *
 * @return array{0: string, 1: string}
 */
function generateEcdsaKeypair(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = openssl_pkey_get_details($key);
    openssl_pkey_export($key, $privateKeyPem);

    $ecPoint = $details['ec'];
    $toBase64url = fn ($b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    $jwk = ['kty' => 'EC', 'crv' => 'P-256', 'x' => $toBase64url($ecPoint['x']), 'y' => $toBase64url($ecPoint['y'])];

    return [base64_encode(json_encode($jwk)), $privateKeyPem];
}

/**
 * Sign a challenge hex string with an OpenSSL EC private key.
 * Returns base64-encoded IEEE P1363 signature (r||s, 32+32 bytes).
 */
function ecdsaSign(string $challengeHex, string $privateKeyPem): string
{
    openssl_sign(hex2bin($challengeHex), $derSig, $privateKeyPem, OPENSSL_ALGO_SHA256);

    // Parse DER → r, s
    $offset = 2;
    // skip SEQUENCE tag+length
    $offset++;
    // skip INTEGER tag for r
    $rLen = ord($derSig[$offset++]);
    $r = substr($derSig, $offset, $rLen);
    $offset += $rLen;
    $offset++;
    // skip INTEGER tag for s
    $sLen = ord($derSig[$offset++]);
    $s = substr($derSig, $offset, $sLen);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return base64_encode($r.$s);
}

test('challenge returns challenge id for ecdsa locker', function () {
    [$publicKey] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000001', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $response = $this->getJson(route('lockers.challenge', '1000000001'));

    $response->assertStatus(200)->assertJsonStructure(['challenge', 'challenge_id']);
});

test('challenge does not return challenge id for legacy locker', function () {
    Locker::factory()->create(['account_id' => '1000000002']);

    $response = $this->getJson(route('lockers.challenge', '1000000002'));

    $response->assertStatus(200)->assertJsonMissing(['challenge_id']);
});

test('store creates ecdsa locker with public key', function () {
    [$publicKey] = generateEcdsaKeypair();
    $credit = LockerCredit::factory()->create(['token' => 'ecdsatok1', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '1000000003',
        'credit_token' => 'ecdsatok1',
        'payload' => str_repeat('a', 100),
        'public_key' => $publicKey,
        'tier' => 'text',
    ]);

    $response->assertStatus(200)->assertJsonStructure(['expires_at', 'account_id']);
    $this->assertDatabaseHas('lockers', ['account_id' => '1000000003', 'auth_challenge' => null]);
});

test('unlock succeeds with valid ecdsa signature', function () {
    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000004', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    // Fetch challenge
    $challengeData = $this->getJson(route('lockers.challenge', '1000000004'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    $response = $this->postJson(route('lockers.unlock', '1000000004'), [
        'challenge_id' => $challengeData['challenge_id'],
        'signature' => $signature,
    ]);

    $response->assertStatus(200)->assertJsonStructure(['payload']);
});

test('unlock fails with invalid ecdsa signature', function () {
    [$publicKey] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000005', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $challengeData = $this->getJson(route('lockers.challenge', '1000000005'))->json();

    $response = $this->postJson(route('lockers.unlock', '1000000005'), [
        'challenge_id' => $challengeData['challenge_id'],
        'signature' => base64_encode(str_repeat("\x00", 64)),
    ]);

    $response->assertStatus(401);
});

test('challenge replay is rejected', function () {
    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000006', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $challengeData = $this->getJson(route('lockers.challenge', '1000000006'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    // First unlock succeeds
    $this->postJson(route('lockers.unlock', '1000000006'), [
        'challenge_id' => $challengeData['challenge_id'],
        'signature' => $signature,
    ])->assertStatus(200);

    // Replaying the same challenge_id must fail
    $this->postJson(route('lockers.unlock', '1000000006'), [
        'challenge_id' => $challengeData['challenge_id'],
        'signature' => $signature,
    ])->assertStatus(401);
});

test('ecdsa update succeeds with valid signing headers', function () {
    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000007', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $challengeData = $this->getJson(route('lockers.challenge', '1000000007'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    $response = $this->putJson(
        route('lockers.update', '1000000007'),
        ['payload' => str_repeat('b', 100)],
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
    );

    $response->assertStatus(200)->assertJson(['ok' => true]);
});

test('ecdsa update rejects replayed challenge', function () {
    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000008', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $challengeData = $this->getJson(route('lockers.challenge', '1000000008'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    // First update consumes the challenge
    $this->putJson(
        route('lockers.update', '1000000008'),
        ['payload' => str_repeat('b', 100)],
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
    )->assertStatus(200);

    // Replaying must fail
    $this->putJson(
        route('lockers.update', '1000000008'),
        ['payload' => str_repeat('c', 100)],
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
    )->assertStatus(403);
});

test('ecdsa delete succeeds', function () {
    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000009', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $challengeData = $this->getJson(route('lockers.challenge', '1000000009'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    $response = $this->deleteJson(
        route('lockers.destroy', '1000000009'),
        [],
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
    );

    $response->assertStatus(200)->assertJson(['ok' => true]);
    $this->assertDatabaseMissing('lockers', ['account_id' => '1000000009']);
});

test('ecdsa renew challenge returns challenge id', function () {
    [$publicKey] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000010', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    $response = $this->getJson(route('lockers.renew.challenge', '1000000010'));

    $response->assertStatus(200)->assertJsonStructure(['challenge', 'challenge_id']);
});

test('legacy locker backward compat unlock', function () {
    $verifier = str_repeat('a', 64);
    Locker::factory()->create([
        'account_id' => '1000000011',
        'auth_verifier' => $verifier,
    ]);

    // Get legacy challenge (no challenge_id)
    $challengeData = $this->getJson(route('lockers.challenge', '1000000011'))->json();
    $this->assertArrayNotHasKey('challenge_id', $challengeData);

    $response = $this->postJson(route('lockers.unlock', '1000000011'), [
        'verifier' => $verifier,
    ]);

    $response->assertStatus(200)->assertJsonStructure(['payload']);
});

test('upgrade auth stores public key and nulls legacy columns', function () {
    [$publicKey] = generateEcdsaKeypair();
    $verifier = str_repeat('a', 64);
    Locker::factory()->create([
        'account_id' => '1000000012',
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => $verifier,
        'update_token_hash' => hash('sha256', 'token'),
    ]);

    $response = $this->postJson(route('lockers.upgrade-auth', '1000000012'), [
        'verifier' => $verifier,
        'public_key' => $publicKey,
    ]);

    $response->assertStatus(200)->assertJson(['ok' => true]);

    $locker = Locker::where('account_id', '1000000012')->first();
    expect($locker->public_key)->not->toBeNull();
    expect($locker->auth_challenge)->toBeNull();
    expect($locker->auth_verifier)->toBeNull();
    expect($locker->update_token_hash)->toBeNull();
});

test('upgrade auth returns 403 for wrong verifier', function () {
    [$publicKey] = generateEcdsaKeypair();
    $verifier = str_repeat('a', 64);
    Locker::factory()->create([
        'account_id' => '1000000013',
        'auth_verifier' => $verifier,
    ]);

    $response = $this->postJson(route('lockers.upgrade-auth', '1000000013'), [
        'verifier' => str_repeat('b', 64),
        'public_key' => $publicKey,
    ]);

    $response->assertStatus(403);
    $locker = Locker::where('account_id', '1000000013')->first();
    expect($locker->public_key)->toBeNull('Locker must remain unchanged after failed upgrade');
});

test('upgrade auth is idempotent for already upgraded locker', function () {
    [$publicKey] = generateEcdsaKeypair();
    Locker::factory()->create(['account_id' => '1000000014', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

    // Should return 200 without re-verifying
    $response = $this->postJson(route('lockers.upgrade-auth', '1000000014'), [
        'verifier' => str_repeat('x', 64), // wrong verifier — won't be checked for ECDSA lockers
        'public_key' => $publicKey,
    ]);

    $response->assertStatus(200)->assertJson(['ok' => true]);
});

test('can create locker with key file auth mode', function () {
    $credit = LockerCredit::factory()->create(['token' => 'kftok1', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '2000000001',
        'credit_token' => 'kftok1',
        'payload' => str_repeat('a', 100),
        'public_key' => base64_encode(json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => str_repeat('A', 43), 'y' => str_repeat('B', 43)])),
        'tier' => 'text',
        'storage_path' => null,
        'auth_mode' => 'key_file',
        'key_file_count' => 2,
    ]);

    $response->assertStatus(200)->assertJsonStructure(['expires_at', 'account_id']);

    $this->assertDatabaseHas('lockers', [
        'account_id' => '2000000001',
        'auth_mode' => 'key_file',
        'key_file_count' => 2,
    ]);
});

test('can create locker with combined auth mode', function () {
    $credit = LockerCredit::factory()->create(['token' => 'cmbtok1', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '2000000002',
        'credit_token' => 'cmbtok1',
        'payload' => str_repeat('a', 100),
        'public_key' => base64_encode(json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => str_repeat('A', 43), 'y' => str_repeat('B', 43)])),
        'tier' => 'text',
        'storage_path' => null,
        'auth_mode' => 'combined',
        'key_file_count' => 1,
    ]);

    $response->assertStatus(200)->assertJsonStructure(['expires_at', 'account_id']);

    $this->assertDatabaseHas('lockers', [
        'account_id' => '2000000002',
        'auth_mode' => 'combined',
        'key_file_count' => 1,
    ]);
});

test('store validates key file count required for key file mode', function () {
    $credit = LockerCredit::factory()->create(['token' => 'kftok2', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '2000000003',
        'credit_token' => 'kftok2',
        'payload' => str_repeat('a', 100),
        'public_key' => base64_encode('{}'),
        'tier' => 'text',
        'auth_mode' => 'key_file',
        // key_file_count intentionally omitted
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['key_file_count']);
});

test('store validates key file count required for combined mode', function () {
    $credit = LockerCredit::factory()->create(['token' => 'cmbtok2', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '2000000004',
        'credit_token' => 'cmbtok2',
        'payload' => str_repeat('a', 100),
        'public_key' => base64_encode('{}'),
        'tier' => 'text',
        'auth_mode' => 'combined',
        // key_file_count intentionally omitted
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['key_file_count']);
});

test('store rejects key file count when passphrase mode', function () {
    $credit = LockerCredit::factory()->create(['token' => 'pptok1', 'tier' => 'text', 'years' => 1]);

    $response = $this->postJson(route('lockers.store'), [
        'account_id' => '2000000005',
        'credit_token' => 'pptok1',
        'payload' => str_repeat('a', 100),
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'text',
        'auth_mode' => 'passphrase',
        'key_file_count' => 2,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['key_file_count']);
});

test('auth info endpoint returns mode and count', function () {
    Locker::factory()->create([
        'account_id' => '2000000006',
        'auth_mode' => 'key_file',
        'key_file_count' => 3,
    ]);

    $response = $this->getJson(route('lockers.auth-info', '2000000006'));

    $response->assertStatus(200)->assertJson([
        'auth_mode' => 'key_file',
        'key_file_count' => 3,
    ]);
});

test('auth info returns passphrase defaults for nonexistent account', function () {
    $response = $this->getJson(route('lockers.auth-info', '9999999999'));

    $response->assertStatus(200)->assertJson([
        'auth_mode' => 'passphrase',
        'key_file_count' => null,
    ]);
});

test('existing passphrase locker auth info returns passphrase mode', function () {
    Locker::factory()->create([
        'account_id' => '2000000007',
        // auth_mode defaults to 'passphrase' via migration
    ]);

    $response = $this->getJson(route('lockers.auth-info', '2000000007'));

    $response->assertStatus(200)->assertJson([
        'auth_mode' => 'passphrase',
        'key_file_count' => null,
    ]);
});

test('existing locker unlock still works regression', function () {
    $verifier = str_repeat('a', 64);
    Locker::factory()->create([
        'account_id' => '2000000008',
        'auth_verifier' => $verifier,
        // auth_mode defaults to 'passphrase' — regression check
    ]);

    $response = $this->postJson(route('lockers.unlock', '2000000008'), [
        'verifier' => $verifier,
    ]);

    $response->assertStatus(200)->assertJsonStructure(['payload', 'expires_at', 'is_file_locker']);
});

test('auth info returns show clues true for normal locker', function () {
    Locker::factory()->create([
        'account_id' => '3000000001',
        'show_clues' => true,
        'auth_mode' => 'key_file',
        'key_file_count' => 2,
    ]);

    $response = $this->getJson(route('lockers.auth-info', '3000000001'));

    $response->assertStatus(200)->assertJson([
        'auth_mode' => 'key_file',
        'key_file_count' => 2,
        'show_clues' => true,
    ]);
});

test('auth info returns opaque response when show clues false', function () {
    Locker::factory()->create([
        'account_id' => '3000000002',
        'show_clues' => false,
        'auth_mode' => 'key_file',
        'key_file_count' => 3,
    ]);

    $response = $this->getJson(route('lockers.auth-info', '3000000002'));

    // Should return fake passphrase defaults, hiding real auth mode
    $response->assertStatus(200)->assertJson([
        'auth_mode' => 'passphrase',
        'key_file_count' => null,
        'show_clues' => false,
    ]);

    $response->assertJsonMissing(['key_file']);

    // Tier and expires_at are revealed even in privacy mode — the renew page needs them.
    $response->assertJson(['tier' => 'text']);
    $response->assertJsonPath('expires_at', fn ($v) => $v !== null);
});

test('renew page route renders inertia component', function () {
    $response = $this->get(route('lockers.renew'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Locker/Renew'));
});

test('auth info includes tier and expires at for real locker', function () {
    Locker::factory()->create([
        'account_id' => '4000000001',
        'show_clues' => true,
        'auth_mode' => 'passphrase',
    ]);

    $response = $this->getJson(route('lockers.auth-info', '4000000001'));

    $response->assertStatus(200);
    $response->assertJsonStructure(['tier', 'expires_at']);
    $response->assertJson(['tier' => 'text', 'show_clues' => true]);
    expect($response->json('expires_at'))->not->toBeNull();
});

test('auth info includes file tier for file locker', function () {
    Locker::factory()->fileLocker()->create([
        'account_id' => '4000000002',
        'show_clues' => true,
    ]);

    $response = $this->getJson(route('lockers.auth-info', '4000000002'));

    $response->assertStatus(200)->assertJson(['tier' => 'file']);
});

test('auth info returns null tier and expires at for unknown locker', function () {
    $response = $this->getJson(route('lockers.auth-info', '9999999998'));

    $response->assertStatus(200)->assertJson(['tier' => null, 'expires_at' => null]);
});

test('renew challenge returns 404 for html requests', function () {
    Locker::factory()->create(['account_id' => '4000000003']);

    $response = $this->get(route('lockers.renew.challenge', '4000000003'));

    $response->assertStatus(404);
});

test('store saves show clues false', function () {
    $credit = LockerCredit::factory()->create(['token' => 'sctok01', 'tier' => 'text', 'years' => 1]);

    $this->postJson(route('lockers.store'), [
        'account_id' => '3000000003',
        'credit_token' => 'sctok01',
        'payload' => str_repeat('a', 100),
        'public_key' => base64_encode(json_encode(['kty' => 'EC'])),
        'tier' => 'text',
        'auth_mode' => 'key_file',
        'key_file_count' => 1,
        'show_clues' => false,
    ])->assertStatus(200);

    $this->assertDatabaseHas('lockers', [
        'account_id' => '3000000003',
        'show_clues' => false,
    ]);
});

test('store defaults show clues to true', function () {
    $credit = LockerCredit::factory()->create(['token' => 'sctok02', 'tier' => 'text', 'years' => 1]);

    $this->postJson(route('lockers.store'), [
        'account_id' => '3000000004',
        'credit_token' => 'sctok02',
        'payload' => str_repeat('a', 100),
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => str_repeat('a', 64),
        'update_token' => str_repeat('b', 64),
        'tier' => 'text',
    ])->assertStatus(200);

    $this->assertDatabaseHas('lockers', [
        'account_id' => '3000000004',
        'show_clues' => true,
    ]);
});

test('update settings requires authentication', function () {
    Locker::factory()->create(['account_id' => '4000000001']);

    $response = $this->patchJson(route('lockers.settings', '4000000001'), [
        'show_clues' => false,
    ]);

    $response->assertStatus(403);
});

test('update settings updates show clues with valid token', function () {
    $locker = Locker::factory()->create([
        'account_id' => '4000000002',
        'auth_verifier' => str_repeat('a', 64),
        'update_token_hash' => hash('sha256', 'validtoken'),
        'show_clues' => true,
    ]);

    $response = $this->patchJson(route('lockers.settings', '4000000002'), [
        'show_clues' => false,
    ], ['X-Update-Token' => 'validtoken']);

    $response->assertStatus(200)->assertJson(['ok' => true]);

    $locker->refresh();
    expect((bool) $locker->show_clues)->toBeFalse();
});

test('update settings rejects invalid token', function () {
    Locker::factory()->create([
        'account_id' => '4000000003',
        'update_token_hash' => hash('sha256', 'correcttoken'),
    ]);

    $response = $this->patchJson(route('lockers.settings', '4000000003'), [
        'show_clues' => false,
    ], ['X-Update-Token' => 'wrongtoken']);

    $response->assertStatus(403);
});

test('download url returns presigned url with valid ecdsa auth', function () {
    Storage::fake();
    $storagePath = 'lockers/ecdsa-dl.bin';
    Storage::put($storagePath, 'encrypted-content');

    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create([
        'account_id' => '5000000001',
        'public_key' => $publicKey,
        'auth_challenge' => null,
        'auth_verifier' => null,
        'update_token_hash' => null,
        'storage_path' => $storagePath,
    ]);

    $challengeData = $this->getJson(route('lockers.challenge', '5000000001'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    $response = $this->getJson(
        route('lockers.download-url', '5000000001'),
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
    );

    $response->assertStatus(200)->assertJsonStructure(['download_url']);
});

test('download url returns 403 with invalid ecdsa signature', function () {
    Storage::fake();

    [$publicKey] = generateEcdsaKeypair();
    Locker::factory()->create([
        'account_id' => '5000000002',
        'public_key' => $publicKey,
        'auth_challenge' => null,
        'auth_verifier' => null,
        'update_token_hash' => null,
        'storage_path' => 'lockers/invalid-sig.bin',
    ]);

    $challengeData = $this->getJson(route('lockers.challenge', '5000000002'))->json();

    $response = $this->getJson(
        route('lockers.download-url', '5000000002'),
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => base64_encode(str_repeat("\x00", 64))]
    );

    $response->assertStatus(403);
});

test('download url returns 404 for nonexistent locker', function () {
    $response = $this->getJson(route('lockers.download-url', 'doesnotexist'));

    $response->assertStatus(404);
});

test('download url returns 410 for expired locker', function () {
    Storage::fake();
    $storagePath = 'lockers/expired-dl.bin';
    Storage::put($storagePath, 'encrypted-content');

    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create([
        'account_id' => '5000000003',
        'public_key' => $publicKey,
        'auth_challenge' => null,
        'auth_verifier' => null,
        'update_token_hash' => null,
        'storage_path' => $storagePath,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->getJson(route('lockers.download-url', '5000000003'));

    $response->assertStatus(410);
});

test('download url returns 404 for text locker', function () {
    [$publicKey, $privateKeyPem] = generateEcdsaKeypair();
    Locker::factory()->create([
        'account_id' => '5000000004',
        'public_key' => $publicKey,
        'auth_challenge' => null,
        'auth_verifier' => null,
        'update_token_hash' => null,
        'storage_path' => null,
    ]);

    $challengeData = $this->getJson(route('lockers.challenge', '5000000004'))->json();
    $signature = ecdsaSign($challengeData['challenge'], $privateKeyPem);

    $response = $this->getJson(
        route('lockers.download-url', '5000000004'),
        ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
    );

    $response->assertStatus(404);
});
