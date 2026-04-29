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
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Observers\SubscriptionObserver;
use App\Services\FeatureRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\Sanctum;

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

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
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
