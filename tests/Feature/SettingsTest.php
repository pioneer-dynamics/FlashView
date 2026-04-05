<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_settings_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/user/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('storeMaskedRecipientEmail')
        );
    }

    public function test_unauthenticated_user_cannot_view_settings_page(): void
    {
        $response = $this->get('/user/settings');

        $response->assertRedirect('/login');
    }

    public function test_settings_page_shows_correct_current_value(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $response = $this->actingAs($user)->get('/user/settings');

        $response->assertInertia(fn ($page) => $page
            ->where('storeMaskedRecipientEmail', true)
        );
    }

    public function test_settings_page_shows_false_when_disabled(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => false,
        ]);

        $response = $this->actingAs($user)->get('/user/settings');

        $response->assertInertia(fn ($page) => $page
            ->where('storeMaskedRecipientEmail', false)
        );
    }

    public function test_user_can_enable_store_masked_recipient_email(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put('/user/settings', [
                'store_masked_recipient_email' => true,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->store_masked_recipient_email);
    }

    public function test_user_can_disable_store_masked_recipient_email(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put('/user/settings', [
                'store_masked_recipient_email' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->store_masked_recipient_email);
    }

    public function test_update_requires_password_confirmation(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => false,
        ]);

        $response = $this->actingAs($user)
            ->put('/user/settings', [
                'store_masked_recipient_email' => true,
            ]);

        $response->assertRedirect();
        $this->assertFalse($user->fresh()->store_masked_recipient_email);
    }

    public function test_guest_cannot_update_settings(): void
    {
        $response = $this->put('/user/settings', [
            'store_masked_recipient_email' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_validation_requires_boolean(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put('/user/settings', [
                'store_masked_recipient_email' => 'not-a-boolean',
            ]);

        $response->assertSessionHasErrors('store_masked_recipient_email');
    }
}
