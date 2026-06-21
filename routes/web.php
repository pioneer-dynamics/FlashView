<?php

use App\Http\Controllers\Admin\AdminLockerPlanController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSecureLineProductController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CallPageController;
use App\Http\Controllers\CallSessionController;
use App\Http\Controllers\CliAuthController;
use App\Http\Controllers\CliDeviceController;
use App\Http\Controllers\CliInstallationController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\LockerController;
use App\Http\Controllers\MarkdownDocumentController;
use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\PaymentConfirmingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SecretController;
use App\Http\Controllers\SecureLineCheckoutController;
use App\Http\Controllers\SenderIdentityController;
use App\Http\Controllers\WebhookSettingsController;
use App\Http\Middleware\EnsurePlanHasApiAccess;
use App\Http\Middleware\EnsurePlanHasSenderIdentity;
use App\Support\BlogRepository;
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
        'latestPost' => app(BlogRepository::class)->latest(),
    ]);
})->name('welcome');

Route::post('/secret/file/prepare', [FileUploadController::class, 'prepare'])
    ->name('secret.file.prepare')
    ->middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified']);

Route::post('/secret/file/upload/{token}', [FileUploadController::class, 'upload'])
    ->name('secret.file.upload')
    ->middleware(['signed', 'auth:sanctum', config('jetstream.auth_session')]);

Route::resource('secret', SecretController::class)->only(['store', 'show']);
Route::get('secret/{secret}/decrypt', [SecretController::class, 'decrypt'])->name('secret.decrypt');
Route::get('secret/{secret}/file', [SecretController::class, 'downloadFile'])->name('secret.file');
Route::post('secret/{secret}/file/downloaded', [SecretController::class, 'confirmFileDownloaded'])->name('secret.file.downloaded');

Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

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
    Route::get('/payment/confirming', [PaymentConfirmingController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('payment.confirming');

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

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'admin',
])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('plans', AdminPlanController::class)->except(['show']);
    Route::resource('locker-plans', AdminLockerPlanController::class)->except(['show']);
    Route::resource('secure-line-products', AdminSecureLineProductController::class)->except(['show']);
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::delete('users/{user}/suspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
});

// Call page routes — Inertia views; no auth required
Route::prefix('calls')->name('calls.')->group(function () {
    Route::get('/', [CallPageController::class, 'index'])->name('index');

    // Anonymous purchase flow — static routes must precede /{callSession} wildcard
    Route::get('/buy', [SecureLineCheckoutController::class, 'buy'])->name('buy');
    Route::post('/checkout', [SecureLineCheckoutController::class, 'checkout'])->name('checkout');
    Route::get('/await-credit', [SecureLineCheckoutController::class, 'awaitCredit'])->name('await-credit');
    Route::get('/credit-status', [SecureLineCheckoutController::class, 'creditStatus'])
        ->middleware('throttle:30,1')->name('credit-status');
    Route::get('/create', [SecureLineCheckoutController::class, 'create'])->name('create');
    Route::post('/', [SecureLineCheckoutController::class, 'store'])
        ->middleware('throttle:6,1')->name('store');

    Route::get('/{callSession}', [CallPageController::class, 'show'])->name('join');
    Route::get('/{callSession}/room', [CallPageController::class, 'room'])->name('room');
});

// Call session routes — no auth required; Ed25519 challenge-response verifies access
Route::prefix('call-sessions')->name('call-sessions.')->group(function () {
    Route::get('/{callSession}/challenge', [CallSessionController::class, 'challenge'])
        ->name('challenge')
        ->middleware('throttle:call-sessions-challenge');

    Route::post('/{callSession}/join', [CallSessionController::class, 'join'])
        ->name('join')
        ->middleware('throttle:call-sessions-join');
});

// eLocker routes — no auth required; all anonymous
Route::prefix('lockers')->name('lockers.')->group(function () {
    // Static routes must precede /{accountId} wildcard
    Route::get('/', [LockerController::class, 'index'])->name('index');
    Route::get('/buy', [LockerController::class, 'buy'])->name('buy');
    Route::post('/file/prepare', [LockerController::class, 'prepareFile'])->name('file.prepare');
    Route::post('/checkout', [LockerController::class, 'checkout'])->name('checkout');
    Route::get('/await-credit', [LockerController::class, 'awaitCredit'])->name('await-credit');
    Route::get('/credit-status', [LockerController::class, 'creditStatus'])
        ->middleware('throttle:30,1')->name('credit-status');
    Route::get('/create', [LockerController::class, 'create'])->name('create');
    Route::get('/open', [LockerController::class, 'open'])->name('open');
    Route::get('/renew', [LockerController::class, 'renewPage'])->name('renew');
    Route::post('/', [LockerController::class, 'store'])
        ->middleware('throttle:6,1')->name('store');

    // Wildcard routes — static sub-paths must precede /{accountId} wildcard
    Route::get('/{accountId}/auth-info', [LockerController::class, 'authInfo'])
        ->middleware('throttle:30,1')->name('auth-info');
    Route::patch('/{accountId}/settings', [LockerController::class, 'updateSettings'])
        ->middleware('throttle:10,1')->name('settings');
    Route::get('/{accountId}', [LockerController::class, 'show'])->name('show');
    Route::get('/{accountId}/challenge', [LockerController::class, 'challenge'])
        ->middleware('throttle:30,1')->name('challenge');
    Route::post('/{accountId}/unlock', [LockerController::class, 'unlock'])
        ->name('unlock');
    Route::get('/{accountId}/payload', [LockerController::class, 'payload'])
        ->middleware('throttle:locker-payload')->name('payload');
    Route::put('/{accountId}', [LockerController::class, 'update'])
        ->middleware('throttle:6,1')->name('update');
    Route::delete('/{accountId}', [LockerController::class, 'destroy'])
        ->middleware('throttle:6,1')->name('destroy');
    Route::get('/{accountId}/download-url', [LockerController::class, 'downloadUrl'])
        ->middleware('throttle:30,1')->name('download-url');
    Route::get('/{accountId}/renew', [LockerController::class, 'renewChallenge'])
        ->middleware('throttle:6,1')->name('renew.challenge');
    Route::post('/{accountId}/renew', [LockerController::class, 'renewPurchase'])
        ->middleware('throttle:6,1')->name('renew.purchase');
    Route::post('/{accountId}/upgrade-auth', [LockerController::class, 'upgradeAuth'])
        ->middleware('throttle:6,1')->name('upgrade-auth');
});
