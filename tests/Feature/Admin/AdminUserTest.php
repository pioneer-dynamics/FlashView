<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Config::set('admin.emails', [$user->email]);

        return $user;
    }

    private function nonAdminUser(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    public function test_unauthenticated_user_is_redirected_from_admin_users(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect('/login');
    }

    public function test_non_admin_receives_403_on_admin_users(): void
    {
        $this->actingAs($this->nonAdminUser())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_users_index(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Users/Index')->has('users')
            );
    }

    public function test_admin_sees_all_users_listed(): void
    {
        $admin = $this->adminUser();
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
    }

    public function test_admin_can_suspend_a_user(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        $target = $this->nonAdminUser();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNotNull($target->fresh()->suspended_at);
    }

    public function test_notification_is_not_sent_when_admin_suspends_themselves(): void
    {
        Notification::fake();

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $admin))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_notification_is_not_sent_when_non_admin_attempts_suspend(): void
    {
        Notification::fake();

        $nonAdmin = $this->nonAdminUser();
        $target = $this->nonAdminUser();

        $this->actingAs($nonAdmin)
            ->post(route('admin.users.suspend', $target))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_admin_can_unsuspend_a_user(): void
    {
        $admin = $this->adminUser();
        $target = $this->nonAdminUser();
        $target->update(['suspended_at' => now()]);

        $this->actingAs($admin)
            ->delete(route('admin.users.unsuspend', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($target->fresh()->suspended_at);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $admin))
            ->assertForbidden();

        $this->assertNull($admin->fresh()->suspended_at);
    }

    public function test_suspended_user_is_redirected_to_login_on_next_request(): void
    {
        $user = $this->nonAdminUser();
        $user->update(['suspended_at' => now()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
