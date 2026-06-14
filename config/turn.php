<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default TURN Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "metered", "xirsys"
    |
    */

    'default' => env('TURN_DRIVER', 'metered'),

    /*
    |--------------------------------------------------------------------------
    | TURN Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'metered' => [
            'api_key' => env('TURN_METERED_API_KEY', ''),
            'domain' => env('TURN_METERED_DOMAIN', ''),  // e.g. 'myapp' → myapp.metered.ca
        ],

        'xirsys' => [
            'api_key' => env('TURN_XIRSYS_API_KEY', ''),
            'secret' => env('TURN_XIRSYS_SECRET', ''),
            'channel' => env('TURN_XIRSYS_CHANNEL', 'flashview'),
        ],

    ],

];
