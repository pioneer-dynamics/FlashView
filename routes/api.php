<?php

use App\Http\Controllers\Api\SecretController;
use App\Http\Middleware\EnsurePlanHasApiAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum', EnsurePlanHasApiAccess::class])->group(function () {
    Route::post('secrets', [SecretController::class, 'store'])
        ->middleware('throttle:api-secrets');

    Route::get('secrets', [SecretController::class, 'index']);

    Route::delete('secrets/{secret}', [SecretController::class, 'destroy']);
});
