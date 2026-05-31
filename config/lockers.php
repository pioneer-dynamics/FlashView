<?php

return [
    'rate_limit' => [
        'payload_fetch' => [
            'max_attempts' => 1,
            'decay_minutes' => 5,
        ],
    ],
    'limits' => [
        'text_max_bytes' => 100 * 1024,
        'file_max_bytes' => 50 * 1024 * 1024,
        'account_id_length' => 10,
    ],
    'webhook_secret' => env('STRIPE_LOCKER_WEBHOOK_SECRET'),
];
