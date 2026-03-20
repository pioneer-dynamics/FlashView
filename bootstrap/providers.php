<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,

    /*
     * Package providers that require manual registration due to
     * Composer package repository overrides (no auto-discovery).
     * Remove these once upstream packages release L13-compatible versions.
     */
    Vinkla\Hashids\HashidsServiceProvider::class,
    Monicahq\Cloudflare\TrustedProxyServiceProvider::class,
    PioneerDynamics\LaravelPasskey\Providers\PasskeyServiceProvider::class,
];
