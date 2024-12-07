<?php

use App\Http\Controllers\SecretController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => false,
        // 'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::middleware('signed')->group(function () {
    Route::get('secret/{secret}/decrypt', [SecretController::class,  'decrypt'])->name('secret.decrypt');
    Route::get('secret/{secret}', [SecretController::class,  'show'])->name('secret.show');
});

Route::middleware(['throttle:secrets'])->group(function () {
    Route::post('secret', [SecretController::class,  'store'])->name('secret.store');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
