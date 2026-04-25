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
                    'untracked' => [
                        'order' => 1,
                        'label' => 'Unlimited messages',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'messages' => [
                        'order' => 2,
                        'label' => ':message_length character limit per message',
                        'config' => [
                            'message_length' => 1000,
                        ],
                        'type' => 'feature',
                    ],
                    'expiry' => [
                        'order' => 3,
                        'label' => 'Maximum expiry of :expiry_label',
                        'config' => [
                            'expiry_label' => '14 days',
                            'expiry_minutes' => 20160,
                        ],
                        'type' => 'limit',
                    ],
                    'throttling' => [
                        'order' => 4,
                        'label' => 'Throttled at :per_minute messages per minute',
                        'config' => [
                            'per_minute' => 60,
                        ],
                        'type' => 'limit',
                    ],
                    'file_upload' => [
                        'order' => 4.3,
                        'label' => 'File uploads up to :max_file_size_mb MB',
                        'config' => ['max_file_size_mb' => 10],
                        'type' => 'limit',
                    ],
                    'email_notification' => [
                        'order' => 4.5,
                        'label' => 'Email Notifications',
                        'config' => [
                            'email' => false,
                        ],
                        'type' => 'missing',
                    ],
                    'webhook_notification' => [
                        'order' => 5.5,
                        'label' => 'Webhook Notifications',
                        'config' => [
                            'webhook' => false,
                        ],
                        'type' => 'missing',
                    ],
                    'support' => [
                        'order' => 5,
                        'label' => 'Support',
                        'config' => [],
                        'type' => 'missing',
                    ],
                    'api' => [
                        'order' => 6,
                        'label' => 'API Access',
                        'config' => [],
                        'type' => 'missing',
                    ],
                    'sender_identity' => [
                        'order' => 7,
                        'label' => 'Verified Sender Identity (optional)',
                        'config' => [],
                        'type' => 'missing',
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
                    'untracked' => [
                        'order' => 1,
                        'label' => 'Unlimited messages',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'messages' => [
                        'order' => 2,
                        'label' => ':message_length character limit per message',
                        'config' => [
                            'message_length' => 100000,
                        ],
                        'type' => 'feature',
                    ],
                    'expiry' => [
                        'order' => 3,
                        'label' => 'Maximum expiry of :expiry_label',
                        'config' => [
                            'expiry_label' => '30 days',
                            'expiry_minutes' => 43200,
                        ],
                        'type' => 'feature',
                    ],
                    'throttling' => [
                        'order' => 4,
                        'label' => 'No rate limits',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'file_upload' => [
                        'order' => 4.3,
                        'label' => 'File uploads up to :max_file_size_mb MB',
                        'config' => ['max_file_size_mb' => 50],
                        'type' => 'feature',
                    ],
                    'email_notification' => [
                        'order' => 4.5,
                        'label' => 'Email Notifications',
                        'config' => [
                            'email' => true,
                        ],
                        'type' => 'feature',
                    ],
                    'webhook_notification' => [
                        'order' => 5.5,
                        'label' => 'Webhook Notifications',
                        'config' => [
                            'webhook' => false,
                        ],
                        'type' => 'missing',
                    ],
                    'support' => [
                        'order' => 5,
                        'label' => 'Standard Support',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'api' => [
                        'order' => 6,
                        'label' => 'API Access',
                        'config' => [],
                        'type' => 'missing',
                    ],
                    'sender_identity' => [
                        'order' => 7,
                        'label' => 'Verified Sender Identity (optional)',
                        'config' => [],
                        'type' => 'missing',
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
                    'untracked' => [
                        'order' => 1,
                        'label' => 'Unlimited messages',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'messages' => [
                        'order' => 2,
                        'label' => ':message_length character limit per message',
                        'config' => [
                            'message_length' => 100000,
                        ],
                        'type' => 'feature',
                    ],
                    'expiry' => [
                        'order' => 3,
                        'label' => 'Maximum expiry of :expiry_label',
                        'config' => [
                            'expiry_label' => '30 days',
                            'expiry_minutes' => 43200,
                        ],
                        'type' => 'feature',
                    ],
                    'throttling' => [
                        'order' => 4,
                        'label' => 'No rate limits',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'file_upload' => [
                        'order' => 4.3,
                        'label' => 'File uploads up to :max_file_size_mb MB',
                        'config' => ['max_file_size_mb' => 500],
                        'type' => 'feature',
                    ],
                    'email_notification' => [
                        'order' => 4.5,
                        'label' => 'Email Notifications',
                        'config' => [
                            'email' => true,
                        ],
                        'type' => 'feature',
                    ],
                    'webhook_notification' => [
                        'order' => 5.5,
                        'label' => 'Webhook Notifications',
                        'config' => [
                            'webhook' => true,
                        ],
                        'type' => 'feature',
                    ],
                    'support' => [
                        'order' => 5,
                        'label' => 'Premium Support',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'api' => [
                        'order' => 6,
                        'label' => 'API Access',
                        'config' => [],
                        'type' => 'feature',
                    ],
                    'sender_identity' => [
                        'order' => 7,
                        'label' => 'Verified Sender Identity (optional)',
                        'config' => [],
                        'type' => 'feature',
                    ],
                ],
            ]);
    }
}
