<?php

namespace App\Providers;

use App\Turn\TurnManager;
use Illuminate\Support\ServiceProvider;

class TurnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TurnManager::class, fn ($app) => new TurnManager($app));
    }
}
