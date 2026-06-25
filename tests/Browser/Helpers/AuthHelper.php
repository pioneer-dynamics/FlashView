<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a regular test user with a known password.
 */
function createTestUser(string $password = 'password'): User
{
    return User::factory()->withPersonalTeam()->create([
        'password' => Hash::make($password),
    ]);
}

/**
 * Creates a user whose email is set as the sole admin email in config.
 * Mirrors the pattern in tests/Feature/Admin/Helpers.php::adminUser().
 */
function createAdminUser(string $password = 'password'): User
{
    $user = User::factory()->withPersonalTeam()->create([
        'password' => Hash::make($password),
    ]);
    Config::set('admin.emails', [$user->email]);

    return $user;
}

/**
 * Logs a user in via the browser login form and returns the page after redirect.
 * Use this in browser tests to authenticate before testing protected pages.
 *
 * @return mixed The $page object positioned at /dashboard after login
 */
function browserLogin(User $user, string $password = 'password'): mixed
{
    return visit('/login')
        ->fill('#email', $user->email)
        ->fill('#password', $password)
        ->click('button[type="submit"]');
}
