<?php

use App\Models\Secret;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function validSecretData(int $plaintextLength = 50, int $expiresIn = 5): array
{
    $message = (new SecretFactory)->generateEncryptedMessage($plaintextLength);

    return [
        'message' => $message,
        'expires_in' => $expiresIn,
    ];
}

test('guest can create secret', function () {
    $response = $this->post(route('secret.store'), validSecretData());

    $response->assertRedirect();
    $response->assertSessionHas('flash.secret.url');
    $this->assertDatabaseCount('secrets', 1);
});

test('guest cannot exceed message length', function () {
    $limit = config('secrets.message_length.guest');
    $data = validSecretData($limit + 1);

    $response = $this->post(route('secret.store'), $data);

    $response->assertSessionHasErrors('message');
});

test('guest cannot exceed expiry limit', function () {
    $guestLimit = config('secrets.expiry_limits.guest');
    $beyondLimit = collect(config('secrets.expiry_options'))
        ->firstWhere(fn ($opt) => $opt['value'] > $guestLimit);

    if (! $beyondLimit) {
        $this->markTestSkipped('No expiry option exceeds guest limit.');
    }

    $data = validSecretData();
    $data['expires_in'] = $beyondLimit['value'];

    $response = $this->post(route('secret.store'), $data);

    $response->assertSessionHasErrors('expires_in');
});

test('authenticated user can create secret', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('secret.store'), validSecretData());

    $response->assertRedirect();
    $response->assertSessionHas('flash.secret.url');
    $this->assertDatabaseHas('secrets', ['user_id' => $user->id]);
});

test('guest secret has no user id', function () {
    $this->post(route('secret.store'), validSecretData());

    $this->assertDatabaseHas('secrets', ['user_id' => null]);
});

test('show requires valid signature', function () {
    $secret = Secret::factory()->create();
    $signedUrl = URL::temporarySignedRoute('secret.show', now()->addHour(), ['secret' => $secret->hash_id]);

    $response = $this->get($signedUrl);

    $response->assertOk();
});

test('show passes secret prop to inertia', function () {
    $secret = Secret::factory()->create();
    $signedUrl = URL::temporarySignedRoute('secret.show', now()->addHour(), ['secret' => $secret->hash_id]);

    $response = $this->get($signedUrl);

    $response->assertInertia(fn ($page) => $page->has('secret'));
});

test('show rejects invalid signature', function () {
    $secret = Secret::factory()->create();

    $response = $this->get(route('secret.show', ['secret' => $secret->hash_id]));

    $response->assertStatus(403);
});

test('decrypt route clears message', function () {
    $secret = Secret::factory()->create();
    $signedUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    $response = $this->get($signedUrl);

    $response->assertRedirect();
});

test('authenticated user can list secrets', function () {
    $user = User::factory()->create();
    Secret::factory()->forUser($user)->count(3)->create();

    $response = $this->actingAs($user)->get(route('secrets.index'));

    $response->assertOk();
});

test('authenticated user can burn own secret', function () {
    $user = User::factory()->create();
    $secret = Secret::factory()->forUser($user)->create();

    $response = $this->actingAs($user)
        ->delete(route('secrets.destroy', ['secret' => $secret->hash_id]));

    $response->assertOk();
});

test('authenticated user cannot burn others secret', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $secret = Secret::factory()->forUser($owner)->create();

    $response = $this->actingAs($other)
        ->delete(route('secrets.destroy', ['secret' => $secret->hash_id]));

    $response->assertForbidden();
});
