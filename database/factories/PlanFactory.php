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
            'is_free_plan' => false,
            'features' => $this->defaultFeatures(),
        ];
    }

    /**
     * A free plan without API, notifications, or sender identity.
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
            'is_free_plan' => true,
            'features' => $this->defaultFeatures(
                messageLength: 1000,
                expiryMinutes: 20160,
                includeApi: false,
                includeEmailNotification: false,
                includeWebhookNotification: false,
            ),
        ]);
    }

    /**
     * A plan with email notifications enabled.
     */
    public function withEmailNotifications(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $this->defaultFeatures(
                includeEmailNotification: true,
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
                includeApi: true,
                includeEmailNotification: true,
                includeWebhookNotification: true,
            ),
        ]);
    }

    /**
     * A plan with file upload enabled up to $maxMb megabytes.
     */
    public function withFileUpload(int $maxMb = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => array_merge($attributes['features'] ?? [], [
                'file_upload' => ['order' => 4, 'type' => 'limit', 'config' => ['max_file_size_mb' => $maxMb]],
            ]),
        ]);
    }

    /**
     * A plan with Sender Identity feature enabled.
     */
    public function withSenderIdentity(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => $this->defaultFeatures(includeSenderIdentity: true),
        ]);
    }

    /**
     * Build the sparse features array. Only included features are present — no missing entries, no labels.
     *
     * @return array<string, array<string, mixed>>
     */
    private function defaultFeatures(
        int $messageLength = 100000,
        int $expiryMinutes = 43200,
        bool $includeApi = true,
        bool $includeEmailNotification = true,
        bool $includeWebhookNotification = true,
        bool $includeSenderIdentity = false,
    ): array {
        $features = [
            'messages' => ['order' => 1, 'type' => 'limit',   'config' => ['message_length' => $messageLength]],
            'expiry' => ['order' => 2, 'type' => 'limit',   'config' => ['expiry_minutes' => $expiryMinutes]],
            'throttling' => ['order' => 3, 'type' => 'feature', 'config' => []],
            'support' => ['order' => 4, 'type' => 'feature', 'config' => []],
        ];

        if ($includeEmailNotification) {
            $features['email_notification'] = ['order' => 4.5, 'type' => 'feature', 'config' => []];
        }

        if ($includeWebhookNotification) {
            $features['webhook_notification'] = ['order' => 4.6, 'type' => 'feature', 'config' => []];
        }

        if ($includeApi) {
            $features['api'] = ['order' => 6, 'type' => 'feature', 'config' => []];
        }

        if ($includeSenderIdentity) {
            $features['sender_identity'] = ['order' => 7, 'type' => 'feature', 'config' => []];
        }

        return $features;
    }
}
