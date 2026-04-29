<?php

namespace App\Jobs;

use App\Models\PipeSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClearExpiredPipeSessions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        PipeSession::where('expires_at', '<', now())->delete();
    }
}
