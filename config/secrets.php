<?php

return [
    /**
     * Expiry in minutes
     */
    'expiry' => env('SECRET_EXPIRY_MINUTES', 10080),

    /**
     * Expiry options
     */
    'expiry_options' => [
        [
            'label' => 'Expires in 5 minutes',
            'value' => 5,
        ],
        [
            'label' => 'Expires in 30 minutes',
            'value' => 30,
        ],
        [
            'label' => 'Expires in 1 hour',
            'value' => 60,
        ],
        [
            'label' => 'Expires in 4 hours',
            'value' => 240,
        ],        
        [
            'label' => 'Expires in 12 hours',
            'value' => 720,
        ],
        [
            'label' => 'Expires in 1 day',
            'value' => 1440,
        ],
        [
            'label' => 'Expires in 3 days',
            'value' => 4320,
        ],
        [
            'label' => 'Expires in 7 days',
            'value' => 10080,
        ],
        [
            'label' => 'Expires in 14 days',
            'value' => 20160,
        ],
        [
            'label' => 'Expires in 30 days',
            'value' => 43200,
        ],
    ],

    'prune_after' => env('SECRET_PRUNE_AFTER_EXPIRY_DAYS_PLUS', 30),
    
    'rate_limit' => [
        'guest' => env('GUEST_SECRET_LIMIT_PER_MINUTE', 3),
        'user' => env('USER_SECRET_LIMIT_PER_MINUTE', 60),
    ]
];