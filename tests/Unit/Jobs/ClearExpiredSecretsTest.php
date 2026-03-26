<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ClearExpiredSecrets;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearExpiredSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clears_message_from_expired_secrets(): void
    {
        $secret = Secret::factory()->expired()->create();

        (new ClearExpiredSecrets)->handle();

        $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
        $this->assertNull($secret->message);
    }

    public function test_does_not_clear_active_secrets(): void
    {
        $secret = Secret::factory()->create([
            'expires_at' => now()->addHours(4),
        ]);

        (new ClearExpiredSecrets)->handle();

        $secret->refresh();
        $this->assertNotNull($secret->message);
    }

    public function test_handles_no_expired_secrets_gracefully(): void
    {
        (new ClearExpiredSecrets)->handle();

        $this->assertTrue(true);
    }
}
