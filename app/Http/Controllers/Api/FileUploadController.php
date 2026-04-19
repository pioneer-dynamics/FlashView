<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Generate a presigned upload URL for direct client-to-S3 upload.
     * Falls back to a signed server-side upload URL for local disk.
     */
    public function prepare(Request $request): JsonResponse
    {
        $filepath = 'secrets/'.Str::uuid().'.bin';
        $token = Str::uuid()->toString();

        Cache::put("pending_file_upload:{$token}", [
            'filepath' => $filepath,
            'user_id' => $request->user()->id,
        ], now()->addMinutes(30));

        try {
            ['url' => $uploadUrl, 'headers' => $uploadHeaders] = Storage::temporaryUploadUrl(
                $filepath,
                now()->addMinutes(15),
                ['ContentType' => 'application/octet-stream']
            );

            return response()->json([
                'upload_type' => 's3_direct',
                'upload_url' => $uploadUrl,
                'upload_headers' => $uploadHeaders,
                'token' => $token,
            ]);
        } catch (\RuntimeException) {
            return response()->json([
                'upload_type' => 'server',
                'upload_url' => URL::temporarySignedRoute('api.v1.secrets.file.upload', now()->addMinutes(15), ['token' => $token]),
                'upload_headers' => [],
                'token' => $token,
            ]);
        }
    }

    /**
     * Local disk fallback: receive raw encrypted binary and store it.
     */
    public function upload(Request $request, string $token): JsonResponse
    {
        $pending = Cache::get("pending_file_upload:{$token}");

        if (! $pending || $pending['user_id'] !== $request->user()->id) {
            abort(403);
        }

        Storage::put($pending['filepath'], $request->getContent());

        return response()->json(['status' => 'ok']);
    }
}
