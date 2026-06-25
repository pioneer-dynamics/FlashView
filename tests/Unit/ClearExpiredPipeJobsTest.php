<?php

use App\Jobs\ClearExpiredPipeDevices;
use App\Jobs\ClearExpiredPipeSessions;
use App\Models\PipeDevice;
use App\Models\PipeSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('job deletes expired sessions', function () {
    PipeSession::factory()->create(['expires_at' => now()->subMinute()]);
    PipeSession::factory()->create(['expires_at' => now()->addMinutes(10)]);

    (new ClearExpiredPipeSessions)->handle();

    $this->assertDatabaseCount('pipe_sessions', 1);
});

test('job deletes expired devices', function () {
    PipeDevice::factory()->create(['expires_at' => now()->subMinute()]);
    PipeDevice::factory()->create(['expires_at' => now()->addMinutes(10)]);

    (new ClearExpiredPipeDevices)->handle();

    $this->assertDatabaseCount('pipe_devices', 1);
});
