<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_plans_page(): void
    {
        Plan::factory()->free()->create();

        $this->get(route('plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
    }

    public function test_authenticated_user_without_subscription_can_view_plans_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Plan::factory()->free()->create();

        $this->actingAs($user)
            ->get(route('plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/Index'));
    }

    public function test_cancellation_route_requires_authentication(): void
    {
        $this->post(route('plans.unsubscribe'))
            ->assertRedirectToRoute('login');
    }
}
