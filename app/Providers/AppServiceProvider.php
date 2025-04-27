<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineRateLimits();
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
