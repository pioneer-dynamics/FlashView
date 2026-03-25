<?php

use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\SecretController;
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
    });
});
