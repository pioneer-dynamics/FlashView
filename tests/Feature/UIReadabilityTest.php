<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UIReadabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_returns_200(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_welcome_page_renders_secret_form(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
        );
    }

    public function test_plans_page_returns_200(): void
    {
        $response = $this->get('/plans');

        $response->assertStatus(200);
    }

    public function test_plans_page_renders_correct_component(): void
    {
        $response = $this->get('/plans');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Plan/Index')
        );
    }

    public function test_faq_page_returns_200(): void
    {
        $response = $this->get('/faq');

        $response->assertStatus(200);
    }

    public function test_about_page_returns_200(): void
    {
        $response = $this->get('/about');

        $response->assertStatus(200);
    }

    public function test_dashboard_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_secrets_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('secrets.index'));

        $response->assertStatus(200);
    }

    public function test_secrets_index_renders_correct_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('secrets.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Secret/Index')
        );
    }
}
