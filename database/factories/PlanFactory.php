<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'stripe_product_id' => 'prod_'.fake()->unique()->bothify('??????????????'),
            'stripe_monthly_price_id' => 'price_'.fake()->unique()->bothify('??????????????'),
            'stripe_yearly_price_id' => 'price_'.fake()->unique()->bothify('??????????????'),
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => $this->defaultFeatures(),
        ];
    }

    /**
     * A free plan without API, notifications, or support.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'stripe_product_id' => '',
            'stripe_monthly_price_id' => '',
            'stripe_yearly_price_id' => '',
            'features' => $this->defaultFeatures(
                apiType: 'missing',
                notificationEmail: false,
                notificationWebhook: false,
            ),
        ]);
    }

    /**
     * A plan with email notifications but no API or webhook.
     */
    public function withEmailNotifications(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $this->defaultFeatures(
                apiType: 'missing',
                notificationEmail: true,
                notificationWebhook: false,
            ),
        ]);
    }

    /**
     * A plan with full API access and all notifications.
     */
    public function withApiAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $this->defaultFeatures(
                apiType: 'feature',
                notificationEmail: true,
                notificationWebhook: true,
            ),
        ]);
    }

    /**
     * A plan with Sender Identity feature enabled.
     */
    public function withSenderIdentity(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $this->defaultFeatures(senderIdentityType: 'feature'),
        ]);
    }

    /**
     * Build the default features array with configurable options.
     *
     * @return array<string, array<string, mixed>>
     */
    /**
     * A plan with Mobile App Access feature enabled (but no API access).
     */
    public function withMobileAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $this->defaultFeatures(
                apiType: 'missing',
                mobileAppType: 'feature',
            ),
        ]);
    }

    /**
     * Build the default features array with configurable options.
     *
     * @return array<string, array<string, mixed>>
     */
    private function defaultFeatures(
        int $messageLength = 100000,
        int $expiryMinutes = 43200,
        string $expiryLabel = '30 days',
        string $apiType = 'feature',
        bool $notificationEmail = true,
        bool $notificationWebhook = true,
        string $senderIdentityType = 'missing',
        string $mobileAppType = 'missing',
    ): array {
        return [
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
                    'message_length' => $messageLength,
                ],
                'type' => 'feature',
            ],
            'expiry' => [
                'order' => 3,
                'label' => 'Maximum expiry of :expiry_label',
                'config' => [
                    'expiry_label' => $expiryLabel,
                    'expiry_minutes' => $expiryMinutes,
                ],
                'type' => 'feature',
            ],
            'throttling' => [
                'order' => 4,
                'label' => 'No rate limits',
                'config' => [],
                'type' => 'feature',
            ],
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => [
                    'email' => $notificationEmail,
                ],
                'type' => $notificationEmail ? 'feature' : 'missing',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => [
                    'webhook' => $notificationWebhook,
                ],
                'type' => $notificationWebhook ? 'feature' : 'missing',
            ],
            'support' => [
                'order' => 5,
                'label' => 'Support',
                'config' => [],
                'type' => 'feature',
            ],
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => $apiType,
            ],
            'sender_identity' => [
                'order' => 7,
                'label' => 'Verified Sender Identity (optional)',
                'config' => [],
                'type' => $senderIdentityType,
            ],
            'mobile_app' => [
                'order' => 8,
                'label' => 'Mobile App Access',
                'config' => [],
                'type' => $mobileAppType,
            ],
        ];
    }
}
