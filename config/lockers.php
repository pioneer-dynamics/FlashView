<?php

return [
    'limits' => [
        'text_max_bytes' => 100 * 1024,
        'file_max_bytes' => 50 * 1024 * 1024,
        'account_id_length' => 10,
    ],
    'pricing' => [
        'text' => [
            1 => ['price_id' => env('LOCKER_TEXT_1YR_PRICE_ID'), 'amount_cents' => 2000],
            3 => ['price_id' => env('LOCKER_TEXT_3YR_PRICE_ID'), 'amount_cents' => 5000],
            5 => ['price_id' => env('LOCKER_TEXT_5YR_PRICE_ID'), 'amount_cents' => 8000],
        ],
        'file' => [
            1 => ['price_id' => env('LOCKER_FILE_1YR_PRICE_ID'), 'amount_cents' => 3500],
            3 => ['price_id' => env('LOCKER_FILE_3YR_PRICE_ID'), 'amount_cents' => 8800],
            5 => ['price_id' => env('LOCKER_FILE_5YR_PRICE_ID'), 'amount_cents' => 14000],
        ],
    ],
    'webhook_secret' => env('LOCKER_STRIPE_WEBHOOK_SECRET'),
];
