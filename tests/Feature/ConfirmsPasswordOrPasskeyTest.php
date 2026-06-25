<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user with passkeys has passkeys in page props', function () {
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
        expect($passkeys)->not->toBeEmpty('User with passkeys should have passkeys in page props');
    });
});

test('user without passkeys has empty passkeys in page props', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)->get('/user/profile');

    $response->assertStatus(200);
    $response->assertInertia(function ($page) {
        $passkeys = data_get($page->toArray(), 'props.auth.user.passkeys', []);
        expect($passkeys)->toBeEmpty('User without passkeys should have empty passkeys array');
    });
});

test('password confirmation status returns confirmed state', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/user/confirmed-password-status');

    $response->assertStatus(200);
    $response->assertJsonStructure(['confirmed']);
});

test('password confirmation status respects seconds parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/user/confirmed-password-status?seconds=60');

    $response->assertStatus(200);
    $response->assertJsonStructure(['confirmed']);
});

test('password can be confirmed for protected actions', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/user/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('invalid password is rejected for protected actions', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/user/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
