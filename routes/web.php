<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CliAuthController;
use App\Http\Controllers\CliDeviceController;
use App\Http\Controllers\CliInstallationController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\MarkdownDocumentController;
use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SecretController;
use App\Http\Controllers\SenderIdentityController;
use App\Http\Controllers\WebhookSettingsController;
use App\Http\Middleware\EnsurePlanHasApiAccess;
use App\Http\Middleware\EnsurePlanHasSenderIdentity;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\RoutePath;
use Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::resource('secret', SecretController::class)->only(['store', 'show']);
Route::get('secret/{secret}/decrypt', [SecretController::class, 'decrypt'])->name('secret.decrypt');
Route::get('secret/{secret}/file', [SecretController::class, 'downloadFile'])->name('secret.file');
Route::post('secret/{secret}/file/downloaded', [SecretController::class, 'confirmFileDownloaded'])->name('secret.file.downloaded');

Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

Route::controller(MarkdownDocumentController::class)->group(function () {
    Route::get('/terms-of-service', 'terms')->name('terms.show');
    Route::get('/privacy-policy', 'privacy')->name('policy.show');
    Route::get('/license', 'license')->name('license.show');
    Route::get('/security', 'security')->name('security.show');
    Route::get('/faq', 'faq')->name('faq.index');
    Route::get('/about', 'about')->name('about.index');
    Route::get('/use-cases', 'useCases')->name('useCases.index');
    Route::get('/cli', 'cli')->name('cli.index');
    Route::get('/webhooks', 'webhooks')->name('webhooks.index');
});

// Intentional override of Fortify's registration routes to prevent email enumeration (PIO-45).
// Two-step registration: email verification first, then complete registration via signed URL.
// Fortify registers its own routes via routes/routes.php; these take precedence because
// they are registered later. Do NOT remove without also addressing the email enumeration vulnerability.
Route::middleware(config('fortify.middleware', ['web']))->group(function () {
    // Step 1: Email collection
    Route::get(RoutePath::for('register', '/register'), [RegisterController::class, 'create'])
        ->middleware(['guest:'.config('fortify.guard')])
        ->name('register');

    Route::post(RoutePath::for('register', '/register'), [RegisterController::class, 'store'])
        ->middleware(['guest:'.config('fortify.guard'), 'throttle:signup'])
        ->name('register.store');

    Route::get('/register/success', [RegisterController::class, 'success'])
        ->middleware(['guest:'.config('fortify.guard')])
        ->name('register.success');

    // Step 2: Complete registration (signed URL from verification email)
    Route::get('/register/complete', [RegisterController::class, 'complete'])
        ->middleware(['guest:'.config('fortify.guard'), 'signed'])
        ->name('register.complete');

    Route::post('/register/complete', [RegisterController::class, 'storeComplete'])
        ->middleware(['guest:'.config('fortify.guard'), 'signed', 'throttle:signup'])
        ->name('register.complete.store');
});

// CLI Authorization Flow (auth required — Fortify handles redirect-to-login automatically)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/cli/authorize', [CliAuthController::class, 'show'])->name('cli.authorize');
    Route::post('/cli/authorize', [CliAuthController::class, 'authorize'])
        ->middleware('throttle:6,1')
        ->name('cli.authorize.store');
});

// Token exchange (validated by signed code, no session auth needed, rate-limited)
Route::post('/cli/token', [CliAuthController::class, 'exchangeToken'])
    ->middleware('throttle:6,1')
    ->name('cli.token');

// Device code flow — initiation and polling (no auth needed)
Route::post('/cli/device/initiate', [CliDeviceController::class, 'initiate'])
    ->middleware('throttle:6,1')
    ->name('cli.device.initiate');

Route::get('/cli/device/poll', [CliDeviceController::class, 'poll'])
    ->middleware('throttle:30,1')
    ->name('cli.device.poll');

// Device code flow — browser page (auth required)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/cli/device', [CliDeviceController::class, 'show'])->name('cli.device');
    Route::post('/cli/device', [CliDeviceController::class, 'activate'])
        ->middleware('throttle:6,1')
        ->name('cli.device.activate');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('plans/{plan}/{period}', [PlanController::class, 'subscribe'])
        ->middleware('environment.subscription')
        ->name('plans.subscribe');
    Route::post('plans/cancel', [PlanController::class, 'unsubscribe'])->name('plans.unsubscribe');
    Route::post('plans/resume', [PlanController::class, 'resume'])->name('plans.resume');

    Route::get('/user/notification-settings', [NotificationSettingsController::class, 'index'])
        ->name('user.notification-settings.index');

    Route::get('/user/settings', [ConfigurationController::class, 'index'])
        ->name('user.settings.index');

    Route::put('/user/settings', [ConfigurationController::class, 'update'])
        ->middleware('password.confirm')
        ->name('user.settings.update');

    Route::put('/user/notification-preferences', [NotificationPreferencesController::class, 'update'])
        ->name('user.notification-preferences.update');

    Route::controller(SecretController::class)->group(function () {
        Route::get('secrets', 'index')->name('secrets.index');
        Route::delete('secrets/{secret}', 'destroy')->name('secrets.destroy');
    });

    Route::middleware([EnsurePlanHasApiAccess::class])->group(function () {
        Route::get('/user/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('/user/api-tokens', [ApiTokenController::class, 'store'])
            ->middleware('password.confirm')
            ->name('api-tokens.store');
        Route::put('/user/api-tokens/{token}', [ApiTokenController::class, 'update'])
            ->middleware('password.confirm')
            ->name('api-tokens.update');
        Route::delete('/user/api-tokens/{token}', [ApiTokenController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('api-tokens.destroy');

        Route::delete('/user/cli-installations/{token}', [CliInstallationController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('cli-installations.destroy');

        Route::put('/user/webhook-settings', [WebhookSettingsController::class, 'update'])
            ->name('user.webhook-settings.update');
        Route::post('/user/webhook-settings/reveal-secret', [WebhookSettingsController::class, 'revealSecret'])
            ->middleware('password.confirm')
            ->name('user.webhook-settings.reveal-secret');
        Route::post('/user/webhook-settings/regenerate-secret', [WebhookSettingsController::class, 'regenerateSecret'])
            ->middleware('password.confirm')
            ->name('user.webhook-settings.regenerate-secret');
        Route::delete('/user/webhook-settings', [WebhookSettingsController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('user.webhook-settings.destroy');
        Route::post('/user/webhook-settings/test', [WebhookSettingsController::class, 'test'])
            ->middleware('password.confirm')
            ->name('user.webhook-settings.test');
    });

    Route::middleware([EnsurePlanHasSenderIdentity::class])->group(function () {
        Route::post('/user/sender-identity', [SenderIdentityController::class, 'store'])
            ->name('user.sender-identity.store');
        Route::post('/user/sender-identity/verify', [SenderIdentityController::class, 'verify'])
            ->middleware('throttle:5,1')
            ->name('user.sender-identity.verify');
        Route::delete('/user/sender-identity', [SenderIdentityController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('user.sender-identity.destroy');
    });

    Route::get('/billing', function (Request $request) {
        return $request->user()->redirectToBillingPortal(route('dashboard'));
    })->middleware(['auth'])->name('billing');
});
