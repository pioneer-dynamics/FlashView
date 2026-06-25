<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can access notification settings page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)
        ->get('/user/notification-settings');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('NotificationSettings/Index'));
});

test('guest is redirected to login', function () {
    $response = $this->get('/user/notification-settings');

    $response->assertRedirect('/login');
});
