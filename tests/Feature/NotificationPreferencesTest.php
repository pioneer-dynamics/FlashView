<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_notification_preferences(): void
    {
        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_user_can_enable_secret_retrieved_notification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->notify_secret_retrieved);
    }

    public function test_user_can_disable_secret_retrieved_notification(): void
    {
        $user = User::factory()->withSecretRetrievedNotifications()->create();

        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }

    public function test_validation_requires_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors('notify_secret_retrieved');
    }
}
