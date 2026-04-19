<?php

namespace App\Jobs;

use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ClearExpiredSecrets implements ShouldQueue
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
        Secret::withoutGlobalScope(ActiveScope::class)
            ->expired()
            ->whereNotNull('filepath')
            ->each(function (Secret $secret) {
                $secret->deleteFile();
                DB::table('secrets')->where('id', $secret->id)->update([
                    'filepath' => null,
                    'filename' => null,
                ]);
            });

        Secret::withoutGlobalScope(ActiveScope::class)->expired()->update(['message' => null]);
    }
}
