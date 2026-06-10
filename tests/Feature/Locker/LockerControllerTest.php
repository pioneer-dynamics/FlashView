<?php

namespace Tests\Feature\Locker;

use App\Models\Locker;
use App\Models\LockerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LockerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_buy_page(): void
    {
        $response = $this->get(route('lockers.buy'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Locker/Buy'));
    }

    public function test_create_page_requires_valid_credit_token(): void
    {
        $response = $this->get(route('lockers.create').'?token=invalid');

        $response->assertStatus(404);
    }

    public function test_create_page_rejects_used_credit_token(): void
    {
        $credit = LockerCredit::factory()->used()->create(['token' => 'usedtoken']);

        $response = $this->get(route('lockers.create').'?token=usedtoken');

        $response->assertStatus(404);
    }

    public function test_create_page_loads_with_valid_unused_credit_token(): void
    {
        $credit = LockerCredit::factory()->create(['token' => 'validtoken', 'tier' => 'text', 'years' => 1]);

        $response = $this->get(route('lockers.create').'?token=validtoken');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Locker/Create'));
    }

    public function test_store_creates_locker_with_valid_credit_token(): void
    {
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
        $this->assertNotNull($credit->used_at);
    }

    public function test_store_rejects_duplicate_account_id(): void
    {
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
    }

    public function test_store_validates_account_id_is_10_digits(): void
    {
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
    }

    public function test_open_page_renders_open_component(): void
    {
        $response = $this->get(route('lockers.open'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Locker/Open'));
    }

    public function test_open_page_passes_renewed_false_by_default(): void
    {
        $response = $this->get(route('lockers.open'));

        $response->assertInertia(fn ($page) => $page->where('renewed', false));
    }

    public function test_open_page_passes_renewed_true_when_query_param_set(): void
    {
        $response = $this->get(route('lockers.open').'?renewed=1');

        $response->assertInertia(fn ($page) => $page->where('renewed', true));
    }

    public function test_show_route_redirects_to_open_for_any_account_id(): void
    {
        $response = $this->get(route('lockers.show', '9999999999'));

        $response->assertRedirect(route('lockers.open'));
    }

    public function test_unlock_returns_401_for_unknown_account(): void
    {
        $response = $this->postJson(route('lockers.unlock', '9999999999'), [
            'verifier' => str_repeat('a', 64),
        ]);

        $response->assertStatus(401)->assertJson(['error' => 'Credentials do not match.']);
    }

    public function test_unlock_returns_401_for_wrong_verifier(): void
    {
        Locker::factory()->create([
            'account_id' => '1234567890',
            'auth_verifier' => str_repeat('a', 64),
        ]);

        $response = $this->postJson(route('lockers.unlock', '1234567890'), [
            'verifier' => str_repeat('b', 64),
        ]);

        $response->assertStatus(401)->assertJson(['error' => 'Credentials do not match.']);
    }

    public function test_unlock_returns_410_for_expired_locker_with_correct_verifier(): void
    {
        Locker::factory()->expired()->create([
            'account_id' => '1234567890',
            'auth_verifier' => str_repeat('a', 64),
        ]);

        $response = $this->postJson(route('lockers.unlock', '1234567890'), [
            'verifier' => str_repeat('a', 64),
        ]);

        $response->assertStatus(410);
    }

    public function test_unlock_returns_payload_for_correct_verifier(): void
    {
        Locker::factory()->create([
            'account_id' => '1234567890',
            'payload' => 'hex_blob',
            'auth_verifier' => str_repeat('a', 64),
        ]);

        $response = $this->postJson(route('lockers.unlock', '1234567890'), [
            'verifier' => str_repeat('a', 64),
        ]);

        $response->assertStatus(200)->assertJsonStructure(['payload', 'expires_at', 'is_file_locker']);
    }

    public function test_show_route_redirects_for_active_locker(): void
    {
        Locker::factory()->create(['account_id' => '1234567890']);

        $response = $this->get(route('lockers.show', '1234567890'));

        $response->assertRedirect(route('lockers.open'));
    }

    public function test_payload_returns_blob_for_active_locker(): void
    {
        Locker::factory()->create(['account_id' => '1234567890', 'payload' => 'hex_blob_here']);

        $response = $this->getJson(route('lockers.payload', '1234567890'));

        $response->assertStatus(200)
            ->assertJsonStructure(['payload', 'auth_challenge']);
    }

    public function test_update_requires_valid_update_token(): void
    {
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
    }

    public function test_update_rejects_invalid_update_token(): void
    {
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
    }

    public function test_update_replaces_payload(): void
    {
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
    }

    public function test_destroy_requires_valid_update_token(): void
    {
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
    }

    public function test_destroy_deletes_locker_record(): void
    {
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
    }

    public function test_renew_challenge_returns_challenge_for_known_account(): void
    {
        Locker::factory()->create(['account_id' => '1234567890', 'auth_challenge' => 'mychallenge']);

        $response = $this->getJson(route('lockers.renew.challenge', '1234567890'));

        $response->assertStatus(200)->assertJson(['challenge' => 'mychallenge']);
    }

    public function test_renew_purchase_verifies_verifier_before_stripe(): void
    {
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
    }

    public function test_renew_purchase_rejects_wrong_verifier(): void
    {
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
    }

    public function test_credit_status_returns_pending_for_unknown_session(): void
    {
        $response = $this->getJson(route('lockers.credit-status').'?session=unknown_session_id');

        $response->assertStatus(200)->assertJson(['pending' => true]);
    }

    public function test_credit_status_returns_token_when_credit_exists(): void
    {
        LockerCredit::factory()->create([
            'stripe_session_id' => 'cs_test_123',
            'token' => 'mytoken',
        ]);

        $response = $this->getJson(route('lockers.credit-status').'?session=cs_test_123');

        $response->assertStatus(200)->assertJson(['token' => 'mytoken']);
    }

    public function test_prepare_file_returns_s3_direct_upload_url(): void
    {
        Storage::fake();
        $credit = LockerCredit::factory()->create(['token' => 'filetoken', 'tier' => 'file', 'years' => 1]);

        $response = $this->postJson(route('lockers.file.prepare'), [
            'credit_token' => 'filetoken',
        ]);

        $response->assertStatus(200)
            ->assertJson(['upload_type' => 's3_direct'])
            ->assertJsonStructure(['upload_url', 'upload_headers', 'storage_path']);
    }

    public function test_unlock_returns_presigned_download_url_for_file_locker(): void
    {
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

        $response->assertStatus(200)
            ->assertJsonStructure(['download_url']);
    }

    public function test_payload_returns_presigned_download_url_for_file_locker(): void
    {
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
            ->assertJsonStructure(['payload', 'auth_challenge', 'download_url']);
    }

    public function test_server_upload_route_does_not_exist(): void
    {
        $response = $this->post('/lockers/file/upload/some-token');

        $response->assertStatus(404);
    }

    public function test_server_download_route_does_not_exist(): void
    {
        $response = $this->get('/lockers/1234567890/file');

        $response->assertStatus(404);
    }

    public function test_store_saves_wrapped_file_key_when_provided(): void
    {
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
        $this->assertEquals('base64wrappedkeydata', $locker->wrapped_file_key);
    }

    public function test_store_does_not_require_wrapped_file_key_for_text_lockers(): void
    {
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
    }

    public function test_unlock_returns_wrapped_file_key_for_new_file_lockers(): void
    {
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

        $this->assertEquals('myWrappedKey123', $response->json('wrapped_file_key'));
    }

    public function test_unlock_does_not_return_wrapped_file_key_for_legacy_file_lockers(): void
    {
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
        $this->assertNull($response->json('wrapped_file_key'));
    }

    public function test_update_accepts_new_wrapped_file_key_for_passphrase_change(): void
    {
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
        $this->assertEquals('newWrappedKey', $locker->wrapped_file_key);
    }

    public function test_update_does_not_require_new_wrapped_file_key(): void
    {
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
    }

    public function test_update_without_storage_path_does_not_null_out_existing_storage_path(): void
    {
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
        $this->assertEquals('lockers/important-file.bin', $locker->storage_path, 'storage_path must not be nulled out when omitted from PUT body');
    }

    public function test_file_update_sends_updated_wrapped_file_key(): void
    {
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
        $this->assertEquals('freshWrappedKey', $locker->wrapped_file_key);
        $this->assertEquals($newStoragePath, $locker->storage_path);
    }

    // ─── ECDSA Tests ─────────────────────────────────────────────────────────

    /**
     * Generate a real P-256 keypair for tests. Returns [publicKeyJwkBase64, privateKeyPem].
     *
     * @return array{0: string, 1: string}
     */
    private function generateEcdsaKeypair(): array
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
    private function ecdsaSign(string $challengeHex, string $privateKeyPem): string
    {
        openssl_sign(hex2bin($challengeHex), $derSig, $privateKeyPem, OPENSSL_ALGO_SHA256);

        // Parse DER → r, s
        $offset = 2; // skip SEQUENCE tag+length
        $offset++; // skip INTEGER tag for r
        $rLen = ord($derSig[$offset++]);
        $r = substr($derSig, $offset, $rLen);
        $offset += $rLen;
        $offset++; // skip INTEGER tag for s
        $sLen = ord($derSig[$offset++]);
        $s = substr($derSig, $offset, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return base64_encode($r.$s);
    }

    public function test_challenge_returns_challenge_id_for_ecdsa_locker(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000001', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $response = $this->getJson(route('lockers.challenge', '1000000001'));

        $response->assertStatus(200)->assertJsonStructure(['challenge', 'challenge_id']);
    }

    public function test_challenge_does_not_return_challenge_id_for_legacy_locker(): void
    {
        Locker::factory()->create(['account_id' => '1000000002']);

        $response = $this->getJson(route('lockers.challenge', '1000000002'));

        $response->assertStatus(200)->assertJsonMissing(['challenge_id']);
    }

    public function test_store_creates_ecdsa_locker_with_public_key(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
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
    }

    public function test_unlock_succeeds_with_valid_ecdsa_signature(): void
    {
        [$publicKey, $privateKeyPem] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000004', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        // Fetch challenge
        $challengeData = $this->getJson(route('lockers.challenge', '1000000004'))->json();
        $signature = $this->ecdsaSign($challengeData['challenge'], $privateKeyPem);

        $response = $this->postJson(route('lockers.unlock', '1000000004'), [
            'challenge_id' => $challengeData['challenge_id'],
            'signature' => $signature,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['payload']);
    }

    public function test_unlock_fails_with_invalid_ecdsa_signature(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000005', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $challengeData = $this->getJson(route('lockers.challenge', '1000000005'))->json();

        $response = $this->postJson(route('lockers.unlock', '1000000005'), [
            'challenge_id' => $challengeData['challenge_id'],
            'signature' => base64_encode(str_repeat("\x00", 64)),
        ]);

        $response->assertStatus(401);
    }

    public function test_challenge_replay_is_rejected(): void
    {
        [$publicKey, $privateKeyPem] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000006', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $challengeData = $this->getJson(route('lockers.challenge', '1000000006'))->json();
        $signature = $this->ecdsaSign($challengeData['challenge'], $privateKeyPem);

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
    }

    public function test_ecdsa_update_succeeds_with_valid_signing_headers(): void
    {
        [$publicKey, $privateKeyPem] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000007', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $challengeData = $this->getJson(route('lockers.challenge', '1000000007'))->json();
        $signature = $this->ecdsaSign($challengeData['challenge'], $privateKeyPem);

        $response = $this->putJson(
            route('lockers.update', '1000000007'),
            ['payload' => str_repeat('b', 100)],
            ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_ecdsa_update_rejects_replayed_challenge(): void
    {
        [$publicKey, $privateKeyPem] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000008', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $challengeData = $this->getJson(route('lockers.challenge', '1000000008'))->json();
        $signature = $this->ecdsaSign($challengeData['challenge'], $privateKeyPem);

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
    }

    public function test_ecdsa_delete_succeeds(): void
    {
        [$publicKey, $privateKeyPem] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000009', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $challengeData = $this->getJson(route('lockers.challenge', '1000000009'))->json();
        $signature = $this->ecdsaSign($challengeData['challenge'], $privateKeyPem);

        $response = $this->deleteJson(
            route('lockers.destroy', '1000000009'),
            [],
            ['X-Signing-Challenge-Id' => $challengeData['challenge_id'], 'X-Signature' => $signature]
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('lockers', ['account_id' => '1000000009']);
    }

    public function test_ecdsa_renew_challenge_returns_challenge_id(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000010', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        $response = $this->getJson(route('lockers.renew.challenge', '1000000010'));

        $response->assertStatus(200)->assertJsonStructure(['challenge', 'challenge_id']);
    }

    public function test_legacy_locker_backward_compat_unlock(): void
    {
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
    }

    public function test_upgrade_auth_stores_public_key_and_nulls_legacy_columns(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
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
        $this->assertNotNull($locker->public_key);
        $this->assertNull($locker->auth_challenge);
        $this->assertNull($locker->auth_verifier);
        $this->assertNull($locker->update_token_hash);
    }

    public function test_upgrade_auth_returns_403_for_wrong_verifier(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
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
        $this->assertNull($locker->public_key, 'Locker must remain unchanged after failed upgrade');
    }

    public function test_upgrade_auth_is_idempotent_for_already_upgraded_locker(): void
    {
        [$publicKey] = $this->generateEcdsaKeypair();
        Locker::factory()->create(['account_id' => '1000000014', 'public_key' => $publicKey, 'auth_challenge' => null, 'auth_verifier' => null, 'update_token_hash' => null]);

        // Should return 200 without re-verifying
        $response = $this->postJson(route('lockers.upgrade-auth', '1000000014'), [
            'verifier' => str_repeat('x', 64), // wrong verifier — won't be checked for ECDSA lockers
            'public_key' => $publicKey,
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_can_create_locker_with_key_file_auth_mode(): void
    {
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
    }

    public function test_can_create_locker_with_combined_auth_mode(): void
    {
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
    }

    public function test_store_validates_key_file_count_required_for_key_file_mode(): void
    {
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
    }

    public function test_store_validates_key_file_count_required_for_combined_mode(): void
    {
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
    }

    public function test_store_rejects_key_file_count_when_passphrase_mode(): void
    {
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
    }

    public function test_auth_info_endpoint_returns_mode_and_count(): void
    {
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
    }

    public function test_auth_info_returns_passphrase_defaults_for_nonexistent_account(): void
    {
        $response = $this->getJson(route('lockers.auth-info', '9999999999'));

        $response->assertStatus(200)->assertJson([
            'auth_mode' => 'passphrase',
            'key_file_count' => null,
        ]);
    }

    public function test_existing_passphrase_locker_auth_info_returns_passphrase_mode(): void
    {
        Locker::factory()->create([
            'account_id' => '2000000007',
            // auth_mode defaults to 'passphrase' via migration
        ]);

        $response = $this->getJson(route('lockers.auth-info', '2000000007'));

        $response->assertStatus(200)->assertJson([
            'auth_mode' => 'passphrase',
            'key_file_count' => null,
        ]);
    }

    public function test_existing_locker_unlock_still_works_regression(): void
    {
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
    }

    public function test_auth_info_returns_show_clues_true_for_normal_locker(): void
    {
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
    }

    public function test_auth_info_returns_opaque_response_when_show_clues_false(): void
    {
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
    }

    public function test_renew_page_route_renders_inertia_component(): void
    {
        $response = $this->get(route('lockers.renew'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Locker/Renew'));
    }

    public function test_auth_info_includes_tier_and_expires_at_for_real_locker(): void
    {
        Locker::factory()->create([
            'account_id' => '4000000001',
            'show_clues' => true,
            'auth_mode' => 'passphrase',
        ]);

        $response = $this->getJson(route('lockers.auth-info', '4000000001'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['tier', 'expires_at']);
        $response->assertJson(['tier' => 'text', 'show_clues' => true]);
        $this->assertNotNull($response->json('expires_at'));
    }

    public function test_auth_info_includes_file_tier_for_file_locker(): void
    {
        Locker::factory()->fileLocker()->create([
            'account_id' => '4000000002',
            'show_clues' => true,
        ]);

        $response = $this->getJson(route('lockers.auth-info', '4000000002'));

        $response->assertStatus(200)->assertJson(['tier' => 'file']);
    }

    public function test_auth_info_returns_null_tier_and_expires_at_for_unknown_locker(): void
    {
        $response = $this->getJson(route('lockers.auth-info', '9999999998'));

        $response->assertStatus(200)->assertJson(['tier' => null, 'expires_at' => null]);
    }

    public function test_renew_challenge_returns_404_for_html_requests(): void
    {
        Locker::factory()->create(['account_id' => '4000000003']);

        $response = $this->get(route('lockers.renew.challenge', '4000000003'));

        $response->assertStatus(404);
    }

    public function test_store_saves_show_clues_false(): void
    {
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
    }

    public function test_store_defaults_show_clues_to_true(): void
    {
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
    }

    public function test_update_settings_requires_authentication(): void
    {
        Locker::factory()->create(['account_id' => '4000000001']);

        $response = $this->patchJson(route('lockers.settings', '4000000001'), [
            'show_clues' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_settings_updates_show_clues_with_valid_token(): void
    {
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
        $this->assertFalse((bool) $locker->show_clues);
    }

    public function test_update_settings_rejects_invalid_token(): void
    {
        Locker::factory()->create([
            'account_id' => '4000000003',
            'update_token_hash' => hash('sha256', 'correcttoken'),
        ]);

        $response = $this->patchJson(route('lockers.settings', '4000000003'), [
            'show_clues' => false,
        ], ['X-Update-Token' => 'wrongtoken']);

        $response->assertStatus(403);
    }
}
