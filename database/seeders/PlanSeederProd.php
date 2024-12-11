<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PlanSeederProd extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::updateOrCreate(['name' => 'Free'],
        [
            'stripe_monthly_price_id' => '',
            'stripe_yearly_price_id' => '',
            'stripe_product_id' => '',
            'features' => [
                'untracked' => [
                    'order' => 1,
                    'label' => 'Unlimited messages',
                    'config' => [],
                    'type' => 'feature'
                ],
                'messages' => [
                    'order' => 2,
                    'label' => ':message_length character limit per message',
                    'config' => [
                        'message_length' => 1000
                    ],
                    'type' => 'feature'
                ],
                'expiry' => [
                    'order' => 3,
                    'label' => 'Maximum expiry of :expiry_label',
                    'config' => [
                        'expiry_label' => '14 days',
                        'expiry_minutes' => 20160
                    ],
                    'type' => 'limit'
                ],
                'throttling' => [
                    'order' => 4,
                    'label' => 'Throttled at :per_minute messages per minute',
                    'config' => [
                        'per_minute' => 60
                    ],
                    'type' => 'limit'
                ],
                'support' => [
                    'order' => 5,
                    'label' => 'Support',
                    'config' => [],
                    'type' => 'missing'
                ],
                'api' => [
                    'order' => 6,
                    'label' => 'API Access (coming soon)',
                    'config' => [],
                    'type' => 'missing'
                ]
            ]
        ]);

        Plan::updateOrCreate(['name' => 'Basic'],
        [
            'stripe_monthly_price_id' => 'price_1QUNLuEZ2BxtappzU6frEVBn',
            'stripe_yearly_price_id' => 'price_1QUNMyEZ2BxtappzLtgG8JRR',
            'stripe_product_id' => 'prod_RMrMjfVfzxi4jD',
            'features' => [
                'untracked' => [
                    'order' => 1,
                    'label' => 'Unlimited messages',
                    'config' => [],
                    'type' => 'feature'
                ],
                'messages' => [
                    'order' => 2,
                    'label' => ':message_length character limit per message',
                    'config' => [
                        'message_length' => 100000
                    ],
                    'type' => 'feature'
                ],
                'expiry' => [
                    'order' => 3,
                    'label' => 'Maximum expiry of :expiry_label',
                    'config' => [
                        'expiry_label' => '30 days',
                        'expiry_minutes' => 43200
                    ],
                    'type' => 'feature'
                ],
                'throttling' => [
                    'order' => 4,
                    'label' => 'No rate limits',
                    'config' => [],
                    'type' => 'feature'
                ],
                'support' => [
                    'order' => 5,
                    'label' => 'Standard Support',
                    'config' => [],
                    'type' => 'feature'
                ],
                'api' => [
                    'order' => 6,
                    'label' => 'API Access (coming soon)',
                    'config' => [],
                    'type' => 'missing'
                ]
            ]
        ]);

        Plan::updateOrCreate(['name' => 'Prime'],
        [
            'stripe_monthly_price_id' => 'price_1QUNNrEZ2BxtappzMeIeREsc',
            'stripe_yearly_price_id' => 'price_1QUNODEZ2BxtappzY4gqKygq',
            'stripe_product_id' => 'prod_RMrMnAF1n071Ki',
            'features' => [
                'untracked' => [
                    'order' => 1,
                    'label' => 'Unlimited messages',
                    'config' => [],
                    'type' => 'feature'
                ],
                'messages' => [
                    'order' => 2,
                    'label' => ':message_length character limit per message',
                    'config' => [
                        'message_length' => 100000
                    ],
                    'type' => 'feature'
                ],
                'expiry' => [
                    'order' => 3,
                    'label' => 'Maximum expiry of :expiry_label',
                    'config' => [
                        'expiry_label' => '30 days',
                        'expiry_minutes' => 43200
                    ],
                    'type' => 'feature'
                ],
                'throttling' => [
                    'order' => 4,
                    'label' => 'No rate limits',
                    'config' => [],
                    'type' => 'feature'
                ],
                'support' => [
                    'order' => 5,
                    'label' => 'Premium Support',
                    'config' => [],
                    'type' => 'feature'
                ],
                'api' => [
                    'order' => 6,
                    'label' => 'API Access (coming soon)',
                    'config' => [],
                    'type' => 'feature'
                ]
            ]
        ]);
    }
}
