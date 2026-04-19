<?php

use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\FileUploadController;
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
});
