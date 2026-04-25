<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeederLocal extends Seeder
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
                'features' => [
                    'messages' => [
                        'order' => 1,
                        'config' => ['message_length' => 1000],
                        'type' => 'limit',
                    ],
                    'expiry' => [
                        'order' => 2,
                        'config' => ['expiry_label' => '14 days', 'expiry_minutes' => 20160],
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
                'stripe_monthly_price_id' => 'price_1QUGImEZ2BxtappzUogdfoAC',
                'stripe_yearly_price_id' => 'price_1QUGIUEZ2BxtappzdKIHkpjj',
                'stripe_product_id' => 'prod_RMpATUwRFFsDOm',
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
                        'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200],
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
                        'config' => [],
                        'type' => 'feature',
                    ],
                ],
            ]);

        Plan::updateOrCreate(['name' => 'Prime'],
            [
                'stripe_monthly_price_id' => 'price_1QUGGeEZ2Bxtappztl6dcGiw',
                'stripe_yearly_price_id' => 'price_1QUGH7EZ2Bxtappzq3amWBoI',
                'stripe_product_id' => 'prod_RMnK2qfP5WLlVt',
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
                        'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200],
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
                        'config' => [],
                        'type' => 'feature',
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
