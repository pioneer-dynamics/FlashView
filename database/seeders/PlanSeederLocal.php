<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PlanSeederLocal extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::updateOrCreate(['name' => 'Free'],
        [
            'price_per_month' => 0,
            'price_per_year' => 0,
            'stripe_monthly_price_id' => '',
            'stripe_yearly_price_id' => '',
            'stripe_product_id' => '',
            'features' => [
                'has' => [
                    'Unlimited messages',
                    '320 character limit per message',
                ],
                'does_not_have' => [
                    'Support',
                    'API Access (coming soon)',
                ]
            ]
        ]);
        
        Plan::updateOrCreate(['name' => 'Basic'],
        [
            'price_per_month' => 2.5,
            'price_per_year' => 18,
            'stripe_monthly_price_id' => 'price_1QU5W8EZ2BxtappzNyN4oUIT',
            'stripe_yearly_price_id' => 'price_1QU5W8EZ2Bxtappz3MDObXYK',
            'stripe_product_id' => 'prod_RMpATUwRFFsDOm',
            'features' => [
                'has' => [
                    'Unlimited messages',
                    '100,000 character limit per message',
                    'Standard Support',
                ],
                'does_not_have' => [
                    'API Access (coming soon)',
                ]
            ]
        ]);

        Plan::updateOrCreate(['name' => 'Prime'],
        [
            'price_per_month' => 5,
            'price_per_year' => 50,
            'stripe_monthly_price_id' => 'price_1QU5PXEZ2BxtappzNa123RCX',
            'stripe_yearly_price_id' => 'price_1QU5PtEZ2Bxtappz54FKBYiR',
            'stripe_product_id' => 'prod_RMnK2qfP5WLlVt',
            'features' => [
                'has' => [
                    'Unlimited messages',
                    '100,000 character limit per message',
                    'Premium Support',
                    'API Access (coming soon)',
                ],
                'does_not_have' => [
                ]
            ]
        ]);
    }
}
