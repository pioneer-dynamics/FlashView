<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PurgeMetadataForExpiredMessages;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeMetadataForExpiredMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_secrets_past_prune_threshold(): void
    {
        $secret = Secret::factory()->readyToPrune()->create();

        (new PurgeMetadataForExpiredMessages)->handle();

        $this->assertNull(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id));
    }

    public function test_does_not_delete_recently_expired_secrets(): void
    {
        $secret = Secret::factory()->create([
            'expires_at' => now()->subDays(5),
            'message' => null,
        ]);

        (new PurgeMetadataForExpiredMessages)->handle();

        $this->assertNotNull(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id));
    }

    public function test_does_not_delete_expired_secrets_with_message_still_present(): void
    {
        $secret = Secret::factory()->create([
            'expires_at' => now()->subDays(config('secrets.prune_after') + 1),
        ]);

        (new PurgeMetadataForExpiredMessages)->handle();

        $this->assertNotNull(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id));
    }

    public function test_handles_no_purgeable_secrets_gracefully(): void
    {
        (new PurgeMetadataForExpiredMessages)->handle();

        $this->assertTrue(true);
    }
}
