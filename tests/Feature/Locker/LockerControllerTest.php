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

    public function test_show_always_renders_for_any_account_id(): void
    {
        // Always renders to prevent account ID enumeration — credentials checked at unlock
        $response = $this->get(route('lockers.show', '9999999999'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Locker/Show'));
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

    public function test_show_renders_for_active_locker(): void
    {
        Locker::factory()->create(['account_id' => '1234567890']);

        $response = $this->get(route('lockers.show', '1234567890'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Locker/Show'));
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
}
