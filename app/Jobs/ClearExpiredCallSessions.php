<?php

namespace App\Jobs;

use App\Models\CallSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClearExpiredCallSessions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        CallSession::where('ends_at', '<', now()->subDays(config('secrets.prune_after')))
            ->each(fn (CallSession $session) => $session->delete());
    }
}
