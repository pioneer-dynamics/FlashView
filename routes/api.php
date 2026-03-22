<?php

use App\Http\Controllers\Api\SecretController;
use App\Http\Middleware\EnsurePlanHasApiAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->as('api.v1.')->middleware(['auth:sanctum', EnsurePlanHasApiAccess::class])->group(function () {
    Route::post('secrets', [SecretController::class, 'store'])
        ->middleware('throttle:api-secrets')
        ->name('secrets.store');

    Route::get('secrets', [SecretController::class, 'index'])
        ->name('secrets.index');

    Route::delete('secrets/{secret}', [SecretController::class, 'destroy'])
        ->name('secrets.destroy');
});
