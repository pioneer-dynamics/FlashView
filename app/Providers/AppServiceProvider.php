<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 
    }

    private function forceHttps()
    {
        if (!app()->environment('local')) {
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
    }

    private function defineRateLimits()
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
    }
}
