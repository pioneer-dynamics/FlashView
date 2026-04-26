<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_currently_available_returns_true_when_no_dates_set(): void
    {
        $plan = Plan::factory()->create(['start_date' => null, 'end_date' => null]);

        $this->assertTrue($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_true_when_within_window(): void
    {
        $plan = Plan::factory()->activeWindow()->create();

        $this->assertTrue($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_false_when_before_start_date(): void
    {
        $plan = Plan::factory()->futureWindow()->create();

        $this->assertFalse($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_false_when_after_end_date(): void
    {
        $plan = Plan::factory()->expiredWindow()->create();

        $this->assertFalse($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_true_when_start_equals_today(): void
    {
        $plan = Plan::factory()->create([
            'start_date' => now()->startOfDay()->toDateString(),
            'end_date' => null,
        ]);

        $this->assertTrue($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_true_when_end_equals_today(): void
    {
        $plan = Plan::factory()->create([
            'start_date' => null,
            'end_date' => now()->startOfDay()->toDateString(),
        ]);

        $this->assertTrue($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_true_with_only_start_date_set_in_past(): void
    {
        $plan = Plan::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
        ]);

        $this->assertTrue($plan->isCurrentlyAvailable());
    }

    public function test_is_currently_available_returns_true_with_only_end_date_set_in_future(): void
    {
        $plan = Plan::factory()->create([
            'start_date' => null,
            'end_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertTrue($plan->isCurrentlyAvailable());
    }
}
