<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

test('unauthenticated user is redirected from admin users', function () {
    $this->get(route('admin.users.index'))->assertRedirect('/login');
});

test('non admin receives 403 on admin users', function () {
    $this->actingAs(nonAdminUser())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admin can access admin users index', function () {
    $this->actingAs(adminUser())
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Users/Index')->has('users')
        );
});

test('admin sees all users listed', function () {
    $admin = adminUser();
    User::factory()->withPersonalTeam()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Users/Index')
            ->has('users', 4)
            ->has('users.0', fn (AssertableInertia $user) => $user->hasAll([
                'id', 'name', 'email', 'plan_name', 'subscription_status',
                'joined_at', 'is_suspended', 'suspended_at',
            ])
            )
        );
});

test('admin can suspend a user', function () {
    Notification::fake();

    $admin = adminUser();
    $target = nonAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $target))
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->suspended_at)->not->toBeNull();
});

test('notification is not sent when admin suspends themselves', function () {
    Notification::fake();

    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $admin))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('notification is not sent when non admin attempts suspend', function () {
    Notification::fake();

    $nonAdmin = nonAdminUser();
    $target = nonAdminUser();

    $this->actingAs($nonAdmin)
        ->post(route('admin.users.suspend', $target))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('admin can unsuspend a user', function () {
    $admin = adminUser();
    $target = nonAdminUser();
    $target->update(['suspended_at' => now()]);

    $this->actingAs($admin)
        ->delete(route('admin.users.unsuspend', $target))
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->suspended_at)->toBeNull();
});

test('admin cannot suspend themselves', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $admin))
        ->assertForbidden();

    expect($admin->fresh()->suspended_at)->toBeNull();
});

test('suspended user is redirected to login on next request', function () {
    $user = nonAdminUser();
    $user->update(['suspended_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});
