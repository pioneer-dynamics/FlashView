<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can view settings page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)->get('/user/settings');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Index')
        ->has('storeMaskedRecipientEmail')
    );
});

test('unauthenticated user cannot view settings page', function () {
    $response = $this->get('/user/settings');

    $response->assertRedirect('/login');
});

test('settings page shows correct current value', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $response = $this->actingAs($user)->get('/user/settings');

    $response->assertInertia(fn ($page) => $page
        ->where('storeMaskedRecipientEmail', true)
    );
});

test('settings page shows false when disabled', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => false,
    ]);

    $response = $this->actingAs($user)->get('/user/settings');

    $response->assertInertia(fn ($page) => $page
        ->where('storeMaskedRecipientEmail', false)
    );
});

test('user can enable store masked recipient email', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => false,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put('/user/settings', [
            'store_masked_recipient_email' => true,
        ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->store_masked_recipient_email)->toBeTrue();
});

test('user can disable store masked recipient email', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put('/user/settings', [
            'store_masked_recipient_email' => false,
        ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->store_masked_recipient_email)->toBeFalse();
});

test('update requires password confirmation', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => false,
    ]);

    $response = $this->actingAs($user)
        ->put('/user/settings', [
            'store_masked_recipient_email' => true,
        ]);

    $response->assertRedirect();
    expect($user->fresh()->store_masked_recipient_email)->toBeFalse();
});

test('guest cannot update settings', function () {
    $response = $this->put('/user/settings', [
        'store_masked_recipient_email' => true,
    ]);

    $response->assertRedirect('/login');
});

test('validation requires boolean', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put('/user/settings', [
            'store_masked_recipient_email' => 'not-a-boolean',
        ]);

    $response->assertSessionHasErrors('store_masked_recipient_email');
});
