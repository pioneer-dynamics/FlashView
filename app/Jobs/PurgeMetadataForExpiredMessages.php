<?php

namespace App\Jobs;

use App\Models\Secret;
use App\Models\Scopes\ActiveScope;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PurgeMetadataForExpiredMessages implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Secret::withoutGlobalScope(ActiveScope::class)->readyToPrune()->delete();
    }
}
