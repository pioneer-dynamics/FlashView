<?php

namespace App\Jobs;

use App\Models\PipeDevice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClearExpiredPipeDevices implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        PipeDevice::where('expires_at', '<', now())->delete();
    }
}
