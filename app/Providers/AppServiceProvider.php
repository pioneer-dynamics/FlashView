<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Observers\SubscriptionObserver;
use App\Policies\StegoPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        //
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
        $this->definePolicies();

        $this->forceHttps();

        Subscription::observe(SubscriptionObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    private function definePolicies(): void
    {
        Gate::define('embed-stego', [StegoPolicy::class, 'embed']);
    }

    private function defineRateLimits(): void
    {
        RateLimiter::for('secrets', function (Request $request) {
            if ($user = $request->user()) {
                if ($user->subscribed()) {
                    return Limit::none();
                } else {
                    return Limit::perMinute(config('secrets.rate_limit.user.per_minute'))
                        ->by($request->ip());
                }
            } else {
                return Limit::perMinute(config('secrets.rate_limit.guest.per_minute'))
                    ->perDay(config('secrets.rate_limit.guest.per_day')
                    )->by($request->ip());
            }
        });

        RateLimiter::for('api-secrets', function (Request $request) {
            if ($request->user()->subscribed()) {
                return Limit::none();
            }

            $perMinute = config('secrets.rate_limit.user.per_minute', 60);

            return Limit::perMinute($perMinute)->by($request->user()->id);
        });
    }
}
