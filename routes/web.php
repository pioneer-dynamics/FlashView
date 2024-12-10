<?php

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SecretController;
use App\Http\Controllers\MarkdownDocumentController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
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

Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('faq', FaqController::class)->name('faq.index');

Route::get('/terms-of-service', [MarkdownDocumentController::class, 'terms'])->name('terms.show');
Route::get('/privacy-policy', [MarkdownDocumentController::class, 'privacy'])->name('policy.show');
Route::get('/license', [MarkdownDocumentController::class, 'license'])->name('license.show');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('plans/{plan}/{period}', [PlanController::class, 'subscribe'])->name('plans.subscribe');
    Route::post('plans/cancel', [PlanController::class, 'unsubscribe'])->name('plans.unsubscribe');
    Route::post('plans/resume', [PlanController::class, 'resume'])->name('plans.resume');

    Route::get('secrets', [SecretController::class,  'index'])->name('secrets.index');
    Route::delete('secrets/{secret}', [SecretController::class,  'destroy'])->name('secrets.destroy');

    Route::get('/billing', function (Request $request) {
        return $request->user()->redirectToBillingPortal(route('dashboard'));
    })->middleware(['auth'])->name('billing');
});
