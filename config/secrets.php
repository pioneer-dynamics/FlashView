<?php

return [

    /**
     * Expiry options
     */
    'expiry_options' => [
        [
            'label' => '5 minutes',
            'value' => 5,
            'user' => ['guest', 'user', 'basic'],
        ], 
        [
            'label' => '30 minutes',
            'value' => 30,
            'user' => ['guest', 'user', 'basic'],
        ], 
        [
            'label' => '1 hour',
            'value' => 60,
            'user' => ['guest', 'user', 'basic'],
        ], 
        [
            'label' => '4 hours',
            'value' => 240,
            'user' => ['guest', 'user', 'basic'],
        ],         
        [
            'label' => '12 hours',
            'value' => 720,
            'user' => ['guest', 'user', 'basic'],
        ], 
        [
            'label' => '1 day',
            'value' => 1440,
            'user' => ['guest', 'user', 'basic'],
        ], 
        [
            'label' => '3 days',
            'value' => 4320,
            'user' => ['basic', 'user'],
        ],
        [
            'label' => '7 days',
            'value' => 10080,
            'user' => ['basic', 'user'],
        ],
        [
            'label' => '14 days',
            'value' => 20160,
            'user' => ['basic']
        ],
        [
            'label' => '30 days',
            'value' => 43200,
            'user' => ['basic']
        ],
    ],

    'prune_after' => env('SECRET_PRUNE_AFTER_EXPIRY_DAYS_PLUS', 30),
    
    'rate_limit' => [
        'guest' => [
            'per_minute' => env('GUEST_SECRET_RATE_LIMIT_PER_MINUTE', 3),
            'per_day' => env('GUEST_SECRET_RATE_LIMIT_PER_DAY', 10),
        ],
        'user' => [
            'per_minute' => env('USER_SECRET_RATE_LIMIT_PER_MINUTE', 60),
        ],
    ],

    'message_length' => [
        'guest' => env('GUEST_SECRET_MESSAGE_LENGTH', 160),
        'user' => env('USER_SECRET_MESSAGE_LENGTH', 320),
        'basic' => env('USER_SECRET_MESSAGE_LENGTH', 100000),
    ]
];