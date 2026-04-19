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
     * Execute the job.
     */
    public function handle(): void
    {
        // Delete files for expired secrets.
        Secret::withoutGlobalScope(ActiveScope::class)
            ->expired()
            ->whereNotNull('filepath')
            ->each(function (Secret $secret) {
                $secret->deleteFile();
                DB::table($secret->getTable())->where('id', $secret->id)->update([
                    'filepath' => null,
                    'filename' => null,
                    'file_size' => null,
                    'file_mime_type' => null,
                ]);
            });

        // Delete files for retrieved secrets whose presigned URL has now expired
        // and the client never pinged the confirm endpoint.
        $ttlHours = config('secrets.file_upload.presigned_url_ttl_hours', 12);

        Secret::withoutGlobalScope(ActiveScope::class)
            ->whereNotNull('retrieved_at')
            ->whereNotNull('filepath')
            ->where('retrieved_at', '<', now()->subHours($ttlHours))
            ->each(function (Secret $secret) {
                $secret->deleteFile();
                DB::table($secret->getTable())->where('id', $secret->id)->update([
                    'filepath' => null,
                    'filename' => null,
                    'file_size' => null,
                    'file_mime_type' => null,
                ]);
            });

        Secret::withoutGlobalScope(ActiveScope::class)->expired()->update(['message' => null]);
    }
}
