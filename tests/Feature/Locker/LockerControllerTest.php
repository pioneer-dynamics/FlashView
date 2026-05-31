<?php

namespace Tests\Feature\Locker;

use App\Models\Locker;
use App\Models\LockerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'tier'  => 'text',
            'years' => 1,
        ]);

        $response = $this->postJson(route('lockers.store'), [
            'account_id'    => '1234567890',
            'credit_token'  => 'tok123',
            'payload'       => str_repeat('a', 100),
            'auth_verifier' => str_repeat('a', 64),
            'tier'          => 'text',
            'storage_path'  => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['update_token', 'expires_at', 'account_id']);

        $this->assertDatabaseHas('lockers', ['account_id' => '1234567890']);
        $credit->refresh();
        $this->assertNotNull($credit->used_at);
    }

    public function test_store_rejects_duplicate_account_id(): void
    {
        Locker::factory()->create(['account_id' => '1234567890']);
        $credit = LockerCredit::factory()->create(['token' => 'tok456', 'tier' => 'text', 'years' => 1]);

        $response = $this->postJson(route('lockers.store'), [
            'account_id'    => '1234567890',
            'credit_token'  => 'tok456',
            'payload'       => str_repeat('a', 100),
            'auth_verifier' => str_repeat('a', 64),
            'tier'          => 'text',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_store_validates_account_id_is_10_digits(): void
    {
        $credit = LockerCredit::factory()->create(['token' => 'tok789', 'tier' => 'text', 'years' => 1]);

        $response = $this->postJson(route('lockers.store'), [
            'account_id'    => '12345',
            'credit_token'  => 'tok789',
            'payload'       => str_repeat('a', 100),
            'auth_verifier' => str_repeat('a', 64),
            'tier'          => 'text',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_show_returns_404_for_unknown_account(): void
    {
        $response = $this->get(route('lockers.show', '9999999999'));

        $response->assertStatus(404);
    }

    public function test_show_returns_410_for_expired_locker(): void
    {
        Locker::factory()->expired()->create(['account_id' => '1234567890']);

        $response = $this->get(route('lockers.show', '1234567890'));

        $response->assertStatus(410);
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
            'account_id'        => '1234567890',
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
            'account_id'        => '1234567890',
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
            'account_id'        => '1234567890',
            'payload'           => 'original',
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
            'account_id'        => '1234567890',
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
            'account_id'        => '1234567890',
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
            'account_id'    => '1234567890',
            'auth_verifier' => str_repeat('a', 64),
        ]);

        $response = $this->postJson(route('lockers.renew.purchase', '1234567890'), [
            'verifier' => str_repeat('b', 64), // wrong verifier
            'years'    => 1,
            'tier'     => 'text',
        ]);

        $response->assertStatus(403);
    }

    public function test_renew_purchase_rejects_wrong_verifier(): void
    {
        Locker::factory()->create([
            'account_id'    => '1234567890',
            'auth_verifier' => str_repeat('a', 64),
        ]);

        $response = $this->postJson(route('lockers.renew.purchase', '1234567890'), [
            'verifier' => str_repeat('c', 64),
            'years'    => 1,
            'tier'     => 'text',
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
            'token'             => 'mytoken',
        ]);

        $response = $this->getJson(route('lockers.credit-status').'?session=cs_test_123');

        $response->assertStatus(200)->assertJson(['token' => 'mytoken']);
    }
}
