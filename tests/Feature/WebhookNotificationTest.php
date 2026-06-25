<?php

use App\Jobs\SendWebhookNotification;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $reflection = new ReflectionProperty($this->app, 'isRunningInConsole');
    $reflection->setValue($this->app, false);
});

test('webhook dispatched when plan supports webhook and user has webhook', function () {
    Bus::fake();

    $plan = createPlanWithWebhook(true);
    $user = User::factory()->withWebhook()->create();
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Bus::assertDispatched(SendWebhookNotification::class, function ($job) use ($secret, $user) {
        return $job->hashId === $secret->hash_id
            && $job->event === 'retrieved'
            && $job->userId === $user->id;
    });
});

test('webhook not dispatched when plan does not support webhook', function () {
    Bus::fake();

    $plan = createPlanWithWebhook(false);
    $user = User::factory()->withWebhook()->create();
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Bus::assertNotDispatched(SendWebhookNotification::class);
});

test('webhook not dispatched when user has no webhook configured', function () {
    Bus::fake();

    $plan = createPlanWithWebhook(true);
    $user = User::factory()->create();
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Bus::assertNotDispatched(SendWebhookNotification::class);
});

test('webhook not dispatched for guest secrets', function () {
    Bus::fake();

    $secret = createActiveSecret();
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Bus::assertNotDispatched(SendWebhookNotification::class);
});

test('webhook not dispatched for user without plan', function () {
    Bus::fake();

    $user = User::factory()->withWebhook()->create();

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Bus::assertNotDispatched(SendWebhookNotification::class);
});

test('webhook payload contains correct fields', function () {
    Bus::fake();

    $plan = createPlanWithWebhook(true);
    $user = User::factory()->withWebhook('https://example.com/hook', 'test-secret-123')->create();
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Bus::assertDispatched(SendWebhookNotification::class, function ($job) use ($secret, $user) {
        return $job->webhookUrl === 'https://example.com/hook'
            && $job->webhookSecret === 'test-secret-123'
            && $job->hashId === $secret->hash_id
            && $job->event === 'retrieved'
            && ! empty($job->createdAt)
            && ! empty($job->retrievedAt)
            && $job->userId === $user->id;
    });
});

function createPlanWithWebhook(bool $webhookEnabled): Plan
{
    return Plan::factory()->create([
        'name' => $webhookEnabled ? 'Prime' : 'Basic',
        'features' => [
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => [
                    'email' => true,
                ],
                'type' => 'feature',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => [
                    'webhook' => $webhookEnabled,
                ],
                'type' => $webhookEnabled ? 'feature' : 'missing',
            ],
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => 'feature',
            ],
        ],
        'stripe_product_id' => 'prod_test',
        'stripe_monthly_price_id' => 'price_monthly_test_'.($webhookEnabled ? 'prime' : 'basic'),
        'stripe_yearly_price_id' => 'price_yearly_test',
        'price_per_month' => 50,
        'price_per_year' => 500,
    ]);
}
