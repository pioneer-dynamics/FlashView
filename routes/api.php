<?php

use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\PipeController;
use App\Http\Controllers\Api\PipePairingController;
use App\Http\Controllers\Api\PipeSignalController;
use App\Http\Controllers\Api\SecretController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\EnsurePlanHasApiAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::middleware(['auth:sanctum', EnsurePlanHasApiAccess::class])->group(function () {
        Route::get('config', ConfigController::class)
            ->name('config');

        Route::apiResource('secrets', SecretController::class)
            ->only(['index', 'store', 'show', 'destroy']);

        Route::get('secrets/{secret}/retrieve', [SecretController::class, 'retrieve'])
            ->name('secrets.retrieve');

        Route::get('secrets/{secret}/file', [SecretController::class, 'downloadFile'])
            ->name('secrets.file');

        Route::post('secrets/{secret}/file/downloaded', [SecretController::class, 'confirmFileDownloaded'])
            ->name('secrets.file.downloaded');

        Route::post('secrets/file/prepare', [FileUploadController::class, 'prepare'])
            ->name('secrets.file.prepare');

        Route::post('secrets/file/upload/{token}', [FileUploadController::class, 'upload'])
            ->withoutMiddleware(EnsurePlanHasApiAccess::class)
            ->middleware('signed')
            ->name('secrets.file.upload');

        Route::middleware('ability:webhook:manage')->group(function () {
            Route::get('webhook', [WebhookController::class, 'show'])->name('webhook.show');
            Route::put('webhook', [WebhookController::class, 'update'])->name('webhook.update');
            Route::post('webhook/regenerate-secret', [WebhookController::class, 'regenerateSecret'])->name('webhook.regenerate-secret');
            Route::delete('webhook', [WebhookController::class, 'destroy'])->name('webhook.destroy');
        });
    });

    // Pairing routes — require authentication (identity is account-linked)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('pipe/devices', [PipePairingController::class, 'registerDevice'])->name('pipe.devices.store');
        Route::get('pipe/devices/waiting', [PipePairingController::class, 'waitingDevices'])->name('pipe.devices.waiting');
        Route::delete('pipe/devices/{deviceId}', [PipePairingController::class, 'destroyDevice'])->name('pipe.devices.destroy');
        Route::post('pipe/pairings', [PipePairingController::class, 'sendSeed'])->name('pipe.pairings.store');
        // NOTE: /pairings/pending MUST be before /{pairing} to avoid route conflict
        Route::get('pipe/pairings/pending', [PipePairingController::class, 'pendingSeed'])->name('pipe.pairings.pending');
        Route::get('pipe/pairings/{pairing}', [PipePairingController::class, 'show'])->name('pipe.pairings.show');
        Route::post('pipe/pairings/{pairing}/accept', [PipePairingController::class, 'accept'])->name('pipe.pairings.accept');
    });

    // Transfer routes — session_id as capability token (no auth required)
    Route::post('pipe', [PipeController::class, 'store'])->name('pipe.store')->middleware('throttle:pipe-sessions');
    Route::get('pipe/{sessionId}', [PipeController::class, 'show'])->name('pipe.show');
    Route::post('pipe/{sessionId}/chunk', [PipeController::class, 'uploadChunk'])->name('pipe.chunk.upload');
    Route::get('pipe/{sessionId}/chunk/{index}', [PipeController::class, 'downloadChunk'])->name('pipe.chunk.download');
    Route::post('pipe/{sessionId}/complete', [PipeController::class, 'complete'])->name('pipe.complete');
    Route::delete('pipe/{sessionId}', [PipeController::class, 'destroy'])->name('pipe.destroy');
    Route::post('pipe/{sessionId}/signal', [PipeSignalController::class, 'store'])->name('pipe.signal.store');
    Route::get('pipe/{sessionId}/signal', [PipeSignalController::class, 'index'])->name('pipe.signal.index');
});
