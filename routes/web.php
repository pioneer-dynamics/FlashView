<?php

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\SecretController;

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

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('secrets', [SecretController::class,  'index'])->name('secrets.index');
    Route::delete('secrets/{secret}', [SecretController::class,  'destroy'])->name('secrets.destroy');

    Route::get('/subscribe', function (Request $request) {
        /**
         * @var App\Models\User $user
         */
        $user = $request->user();
        return $user
            ->newSubscription('default', config('subscriptions.plans.basic.price.monthly.stripe_price_id'))
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('dashboard'),
                'cancel_url' => route('dashboard'),
            ]);
    })->name('subscribe');

    Route::get('/billing', function (Request $request) {
        return $request->user()->redirectToBillingPortal(route('dashboard'));
    })->middleware(['auth'])->name('billing');
});
