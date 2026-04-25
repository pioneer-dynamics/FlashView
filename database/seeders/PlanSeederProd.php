<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeederProd extends Seeder
{
    /**
     * Bootstrap-only seeder. Superseded by the admin UI at /admin/plans.
     * Only runs when the plans table is empty to avoid overwriting admin-managed data.
     */
    public function run(): void
    {
        if (Plan::count() > 0) {
            return;
        }

        Plan::updateOrCreate(['name' => 'Free'],
            [
                'stripe_monthly_price_id' => '',
                'stripe_yearly_price_id' => '',
                'stripe_product_id' => '',
                'price_per_month' => 0,
                'price_per_year' => 0,
                'is_free_plan' => true,
                'features' => [
                    'messages' => [
                        'order' => 1,
                        'config' => ['message_length' => 1000],
                        'type' => 'limit',
                    ],
                    'expiry' => [
                        'order' => 2,
                        'config' => ['expiry_minutes' => 20160],
                        'type' => 'limit',
                    ],
                    'throttling' => [
                        'order' => 3,
                        'config' => ['per_minute' => 60],
                        'type' => 'limit',
                    ],
                    'file_upload' => [
                        'order' => 4,
                        'config' => ['max_file_size_mb' => 10],
                        'type' => 'limit',
                    ],
                ],
            ]);

        Plan::updateOrCreate(['name' => 'Basic'],
            [
                'stripe_monthly_price_id' => 'price_1QUNLuEZ2BxtappzU6frEVBn',
                'stripe_yearly_price_id' => 'price_1QUNMyEZ2BxtappzLtgG8JRR',
                'stripe_product_id' => 'prod_RMrMjfVfzxi4jD',
                'price_per_month' => 25,
                'price_per_year' => 250,
                'features' => [
                    'messages' => [
                        'order' => 1,
                        'config' => ['message_length' => 100000],
                        'type' => 'limit',
                    ],
                    'expiry' => [
                        'order' => 2,
                        'config' => ['expiry_minutes' => 43200],
                        'type' => 'limit',
                    ],
                    'throttling' => [
                        'order' => 3,
                        'config' => ['per_minute' => 600],
                        'type' => 'limit',
                    ],
                    'file_upload' => [
                        'order' => 4,
                        'config' => ['max_file_size_mb' => 50],
                        'type' => 'limit',
                    ],
                    'email_notification' => [
                        'order' => 5,
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'support' => [
                        'order' => 6,
                        'config' => ['support_type' => 'standard'],
                        'type' => 'limit',
                    ],
                ],
            ]);

        Plan::updateOrCreate(['name' => 'Prime'],
            [
                'stripe_monthly_price_id' => 'price_1QUNNrEZ2BxtappzMeIeREsc',
                'stripe_yearly_price_id' => 'price_1QUNODEZ2BxtappzY4gqKygq',
                'stripe_product_id' => 'prod_RMrMnAF1n071Ki',
                'price_per_month' => 50,
                'price_per_year' => 500,
                'features' => [
                    'messages' => [
                        'order' => 1,
                        'config' => ['message_length' => 100000],
                        'type' => 'limit',
                    ],
                    'expiry' => [
                        'order' => 2,
                        'config' => ['expiry_minutes' => 43200],
                        'type' => 'limit',
                    ],
                    'throttling' => [
                        'order' => 3,
                        'config' => ['per_minute' => 600],
                        'type' => 'limit',
                    ],
                    'file_upload' => [
                        'order' => 4,
                        'config' => ['max_file_size_mb' => 500],
                        'type' => 'limit',
                    ],
                    'email_notification' => [
                        'order' => 5,
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'webhook_notification' => [
                        'order' => 6,
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'support' => [
                        'order' => 7,
                        'config' => ['support_type' => 'priority'],
                        'type' => 'limit',
                    ],
                    'api' => [
                        'order' => 8,
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'sender_identity' => [
                        'order' => 9,
                        'config' => [],
                        'type' => 'feature',
                    ],
                ],
            ]);
    }
}
