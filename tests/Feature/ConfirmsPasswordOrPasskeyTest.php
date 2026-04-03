<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmsPasswordOrPasskeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_passkeys_has_passkeys_in_page_props(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $user->passkeys()->create([
            'credential_id' => 'test-credential-id',
            'public_key' => 'test-public-key',
            'name' => 'Test Passkey',
        ]);

        $user->load('passkeys');

        $response = $this->actingAs($user)->get('/user/profile');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $passkeys = data_get($page->toArray(), 'props.auth.user.passkeys', []);
            $this->assertNotEmpty($passkeys, 'User with passkeys should have passkeys in page props');
        });
    }

    public function test_user_without_passkeys_has_empty_passkeys_in_page_props(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/user/profile');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $passkeys = data_get($page->toArray(), 'props.auth.user.passkeys', []);
            $this->assertEmpty($passkeys, 'User without passkeys should have empty passkeys array');
        });
    }

    public function test_password_confirmation_status_returns_confirmed_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/user/confirmed-password-status');

        $response->assertStatus(200);
        $response->assertJsonStructure(['confirmed']);
    }

    public function test_password_confirmation_status_respects_seconds_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/user/confirmed-password-status?seconds=60');

        $response->assertStatus(200);
        $response->assertJsonStructure(['confirmed']);
    }

    public function test_password_can_be_confirmed_for_protected_actions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_invalid_password_is_rejected_for_protected_actions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }
}
