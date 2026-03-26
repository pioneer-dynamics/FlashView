<?php

namespace Tests\Unit\Models;

use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_returns_non_expired_secrets_with_message(): void
    {
        $active = Secret::factory()->create();
        Secret::factory()->expired()->create();
        Secret::factory()->retrieved()->create();

        $results = Secret::withoutGlobalScope(ActiveScope::class)->active()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($active));
    }

    public function test_active_scope_excludes_expired_secrets(): void
    {
        Secret::factory()->expired()->create();

        $results = Secret::withoutGlobalScope(ActiveScope::class)->active()->get();

        $this->assertCount(0, $results);
    }

    public function test_active_scope_excludes_secrets_with_null_message(): void
    {
        Secret::factory()->retrieved()->create();

        $results = Secret::withoutGlobalScope(ActiveScope::class)->active()->get();

        $this->assertCount(0, $results);
    }

    public function test_expired_scope_returns_past_expiry_secrets(): void
    {
        Secret::factory()->expired()->create();
        Secret::factory()->create();

        $results = Secret::withoutGlobalScope(ActiveScope::class)->expired()->get();

        $this->assertCount(1, $results);
    }

    public function test_expired_scope_excludes_future_expiry_secrets(): void
    {
        Secret::factory()->create();

        $results = Secret::withoutGlobalScope(ActiveScope::class)->expired()->get();

        $this->assertCount(0, $results);
    }

    public function test_ready_to_prune_returns_old_expired_secrets_without_message(): void
    {
        Secret::factory()->readyToPrune()->create();
        Secret::factory()->expired()->create();

        $results = Secret::withoutGlobalScope(ActiveScope::class)->readyToPrune()->get();

        $this->assertCount(1, $results);
    }

    public function test_ready_to_prune_excludes_recent_expired_secrets(): void
    {
        Secret::factory()->create([
            'expires_at' => now()->subDays(5),
            'message' => null,
        ]);

        $results = Secret::withoutGlobalScope(ActiveScope::class)->readyToPrune()->get();

        $this->assertCount(0, $results);
    }

    public function test_global_active_scope_applied_by_default(): void
    {
        Secret::factory()->create();
        Secret::factory()->expired()->create();

        $this->assertCount(1, Secret::all());
    }

    public function test_without_global_scope_bypasses_active_scope(): void
    {
        Secret::factory()->create();
        Secret::factory()->expired()->create();

        $this->assertCount(2, Secret::withoutGlobalScope(ActiveScope::class)->get());
    }
}
