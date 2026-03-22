<?php

namespace App\Providers;

use App\Observers\SubscriptionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Subscription;

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

        $this->forceHttps();

        Subscription::observe(SubscriptionObserver::class);
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
