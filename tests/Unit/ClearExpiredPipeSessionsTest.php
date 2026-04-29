<?php

namespace Tests\Unit;

use App\Jobs\ClearExpiredPipeDevices;
use App\Jobs\ClearExpiredPipeSessions;
use App\Models\PipeDevice;
use App\Models\PipeSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearExpiredPipeSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_deletes_expired_sessions(): void
    {
        PipeSession::factory()->create(['expires_at' => now()->subMinute()]);
        PipeSession::factory()->create(['expires_at' => now()->addMinutes(10)]);

        (new ClearExpiredPipeSessions)->handle();

        $this->assertDatabaseCount('pipe_sessions', 1);
    }

    public function test_job_deletes_expired_devices(): void
    {
        PipeDevice::factory()->create(['expires_at' => now()->subMinute()]);
        PipeDevice::factory()->create(['expires_at' => now()->addMinutes(10)]);

        (new ClearExpiredPipeDevices)->handle();

        $this->assertDatabaseCount('pipe_devices', 1);
    }
}
