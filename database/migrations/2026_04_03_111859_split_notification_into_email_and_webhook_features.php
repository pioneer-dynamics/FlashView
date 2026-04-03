<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Plan::all()->each(function (Plan $plan) {
            $features = $plan->features;

            if (! isset($features['notification'])) {
                return;
            }

            $notification = $features['notification'];
            $emailEnabled = $notification['config']['email'] ?? false;
            $webhookEnabled = $notification['config']['webhook'] ?? false;
            $baseOrder = $notification['order'] ?? 4.5;

            $features['email_notification'] = [
                'order' => $baseOrder,
                'label' => 'Email Notifications',
                'config' => ['email' => $emailEnabled],
                'type' => $emailEnabled ? 'feature' : 'missing',
            ];

            $features['webhook_notification'] = [
                'order' => $baseOrder + 0.1,
                'label' => 'Webhook Notifications',
                'config' => ['webhook' => $webhookEnabled],
                'type' => $webhookEnabled ? 'feature' : 'missing',
            ];

            unset($features['notification']);

            $plan->update(['features' => $features]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Plan::all()->each(function (Plan $plan) {
            $features = $plan->features;

            if (! isset($features['email_notification']) && ! isset($features['webhook_notification'])) {
                return;
            }

            $emailEnabled = $features['email_notification']['config']['email'] ?? false;
            $webhookEnabled = $features['webhook_notification']['config']['webhook'] ?? false;
            $order = $features['email_notification']['order'] ?? 4.5;

            $type = ($emailEnabled || $webhookEnabled) ? 'feature' : 'missing';

            if ($emailEnabled && $webhookEnabled) {
                $label = 'Get notified via email or webhook when a message is retrieved';
            } elseif ($emailEnabled) {
                $label = 'Get notified via email when a message is retrieved';
            } else {
                $label = 'Get notified when a message is retrieved';
            }

            $features['notification'] = [
                'order' => $order,
                'label' => $label,
                'config' => ['email' => $emailEnabled, 'webhook' => $webhookEnabled],
                'type' => $type,
            ];

            unset($features['email_notification'], $features['webhook_notification']);

            $plan->update(['features' => $features]);
        });
    }
};
