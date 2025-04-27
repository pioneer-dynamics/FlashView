<?php

namespace App\Jobs;

use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
