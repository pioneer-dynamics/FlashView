<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_notification_settings_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)
            ->get('/user/notification-settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('NotificationSettings/Index'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/user/notification-settings');

        $response->assertRedirect('/login');
    }
}
