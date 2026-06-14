<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\JetstreamServiceProvider;
use App\Providers\TurnServiceProvider;
use Monicahq\Cloudflare\TrustedProxyServiceProvider;
use PioneerDynamics\LaravelPasskey\Providers\PasskeyServiceProvider;
use Vinkla\Hashids\HashidsServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    JetstreamServiceProvider::class,
    TurnServiceProvider::class,

    /*
     * Package providers that require manual registration due to
     * Composer package repository overrides (no auto-discovery).
     * Remove these once upstream packages release L13-compatible versions.
     */
    HashidsServiceProvider::class,
    TrustedProxyServiceProvider::class,
    PasskeyServiceProvider::class,
];
