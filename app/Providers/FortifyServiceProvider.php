<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Route;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use App\Actions\Fortify\UpdateUserProfileInformation;

class FortifyServiceProvider extends ServiceProvider
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
        $this->defaultFortifyBoot();

        $this->bootPasskey();
    }

    private function defaultFortifyBoot()
    {
        $this->defineFortifyClasses();

        $this->defineDefaultFortifyRateLimiters();

        $this->defineSignupRateLimiter();
    }

    private function defineSignupRateLimiter()
    {
        RateLimiter::for('signup', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }

    private function defineDefaultFortifyRateLimiters()
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    private function defineFortifyClasses()
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    private function bootPasskey()
    {
        Fortify::loginView(function () {
            return Inertia::render('Auth/LoginWithPasskey', [
                'canResetPassword' => Route::has('password.request'),
                'status' => session('status'),
            ]);
        });
    }
}
