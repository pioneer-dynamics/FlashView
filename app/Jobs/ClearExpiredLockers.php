<?php

namespace App\Jobs;

use App\Models\Locker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClearExpiredLockers implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Step 1: Delete S3 files for expired file lockers.
        Locker::expired()
            ->whereNotNull('storage_path')
            ->whereNotNull('payload')
            ->each(function (Locker $locker) {
                try {
                    Storage::delete($locker->storage_path);
                } catch (\Throwable $e) {
                    Log::warning('ClearExpiredLockers: failed to delete S3 object', [
                        'account_id' => $locker->account_id,
                        'storage_path' => $locker->storage_path,
                        'error' => $e->getMessage(),
                    ]);
                }

                DB::table($locker->getTable())
                    ->where('id', $locker->id)
                    ->update(['storage_path' => null]);
            });

        // Step 2: Wipe payload for all expired lockers.
        Locker::expired()
            ->whereNotNull('payload')
            ->update(['payload' => null]);
    }
}
