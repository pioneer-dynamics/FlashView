<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GuestInertiaPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_have_auth_user_in_inertia_props(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user', null)
        );
    }

    public function test_authenticated_user_has_full_user_data_in_inertia_props(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('auth.user.id')
            ->has('auth.user.name')
            ->has('auth.user.email')
            ->where('auth.user.id', $user->id)
        );
    }
}
