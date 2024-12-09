<?php

return [
    'plans' => [
        'basic' => [
            'name' => 'Basic',
            'price' => [
                'monthly' => [
                    'stripe_price_id' => env('PLAN_MONTHLY_PRICE_ID', 'price_1QU3kEEZ2BxtappzMaIN7nVS'),
                    'amount' => 500
                ],
                'yearly' => [
                    'stripe_price_id' => env('PLAN_YEARLY_PRICE_ID', 'price_1QU3kEEZ2Bxtappz8WPtFO9J'),
                    'amount' => 2500
                ]
            ],
            'stripe_product_id' => env('PRODUCT_ID', 'prod_RMnK2qfP5WLlVt'),
            'features' => [
                'Unlimited messages',
                '10,000 character limit per message',
            ]
        ]
    ]
];