<?php

namespace App\Providers;

use App\Features\ApiFeature;
use App\Features\EmailNotificationFeature;
use App\Features\ExpiryFeature;
use App\Features\FileUploadFeature;
use App\Features\MessagesFeature;
use App\Features\SenderIdentityFeature;
use App\Features\SupportFeature;
use App\Features\ThrottlingFeature;
use App\Features\WebhookNotificationFeature;
use App\Listeners\HandleLockerStripeWebhook;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Observers\SubscriptionObserver;
use App\Services\FeatureRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\Sanctum;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class, fn () => new FeatureRegistry([
            new MessagesFeature,
            new ExpiryFeature,
            new ThrottlingFeature,
            new FileUploadFeature,
            new EmailNotificationFeature,
            new WebhookNotificationFeature,
            new SupportFeature,
            new ApiFeature,
            new SenderIdentityFeature,
        ]));
    }

    private function forceHttps(): void
    {
        if (! app()->environment('local')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineRateLimits();

        $this->forceHttps();

        Subscription::observe(SubscriptionObserver::class);

        Event::listen(WebhookReceived::class, HandleLockerStripeWebhook::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if (! config('posthog.disabled') && config('posthog.api_key')) {
            PostHog::init(config('posthog.api_key'), ['host' => config('posthog.host')]);
        }
    }

    private function defineRateLimits(): void
    {
        RateLimiter::for('secrets', function (Request $request) {
            if ($user = $request->user()) {
                return $this->planThrottleLimit($user);
            }

            return Limit::perMinute(config('secrets.rate_limit.guest.per_minute'))
                ->perDay(config('secrets.rate_limit.guest.per_day'))
                ->by($request->ip());
        });

        RateLimiter::for('api-secrets', function (Request $request) {
            return $this->planThrottleLimit($request->user());
        });

        RateLimiter::for('pipe-sessions', function (Request $request) {
            if ($user = $request->user()) {
                return Limit::perMinute(config('pipe.rate_limits.user.create_per_minute'))->by($user->id);
            }

            return Limit::perMinute(config('pipe.rate_limits.guest.create_per_minute'))->by($request->ip());
        });

        RateLimiter::for('locker-payload', fn (Request $request) => Limit::perMinutes(
            config('lockers.rate_limit.payload_fetch.decay_minutes', 5),
            config('lockers.rate_limit.payload_fetch.max_attempts', 1)
        )
            ->by('locker:'.$request->route('accountId'))
            ->response(fn () => response()->json(['error' => 'Too many attempts. Please wait 5 minutes.'], 429))
        );

        // Documentation anchors for locker unlock rate limiting. These are NOT wired as route
        // middleware. The controller calls RateLimiter::hit/tooManyAttempts/clear directly using
        // the key patterns below (e.g. 'locker-ip:{ip}'). If these are ever wired as middleware,
        // note that the framework generates a different key format ('locker-ip-unlock|{ip}'), so
        // the controller key strings must be updated to match before switching to middleware.
        // Controller key pattern: 'locker-ip:{ip}' — NOT the key this closure generates via middleware ('locker-ip-unlock|{ip}').
        RateLimiter::for('locker-ip-unlock', fn (Request $request) => Limit::perHour(3)->by($request->ip()));
        // Controller key pattern: 'locker-account-lock:{accountId}' — NOT 'locker-account-lock|{accountId}'.
        RateLimiter::for('locker-account-lock', fn (Request $request) => Limit::perHour(3)->by($request->route('accountId')));
        // Controller key pattern: 'locker-account-cooldown:{accountId}' — NOT 'locker-account-cooldown|{accountId}'.
        RateLimiter::for('locker-account-cooldown', fn (Request $request) => Limit::perMinutes(5, 1)->by($request->route('accountId')));

        RateLimiter::for('call-sessions-challenge', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip().'|'.$request->route('callSession'));
        });

        RateLimiter::for('call-sessions-join', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().'|'.$request->route('callSession'));
        });
    }

    private function planThrottleLimit(User $user): Limit
    {
        $plan = $user->resolvePlan();
        $config = $plan?->features['throttling']['config'] ?? [];

        if (isset($config['per_minute'])) {
            return Limit::perMinute((int) $config['per_minute'])->by($user->id);
        }

        return Limit::perMinute(config('secrets.rate_limit.user.per_minute'))->by($user->id);
    }
}
