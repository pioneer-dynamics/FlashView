<?php

namespace App\Jobs;

use App\Models\CallParticipant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PurgeCallParticipantMetadata implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        CallParticipant::whereHas(
            'session',
            fn ($q) => $q->where('ends_at', '<', now()->subDays(config('secrets.prune_after')))
        )->each(fn (CallParticipant $participant) => $participant->delete());
    }
}
