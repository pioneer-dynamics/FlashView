<?php

use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use App\Notifications\SecretRetrievedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    // Allow the Secret retrieved event to fire during tests
    $reflection = new ReflectionProperty($this->app, 'isRunningInConsole');
    $reflection->setValue($this->app, false);
});

test('notification sent when plan allows and user opted in', function () {
    Notification::fake();

    $plan = createPlanWithNotifications(true);
    $user = User::factory()->withSecretRetrievedNotifications()->create();
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Notification::assertSentTo($user, SecretRetrievedNotification::class);
});

test('notification not sent when user opted out', function () {
    Notification::fake();

    $plan = createPlanWithNotifications(true);
    $user = User::factory()->create(['notify_secret_retrieved' => false]);
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Notification::assertNotSentTo($user, SecretRetrievedNotification::class);
});

test('notification not sent when plan disallows', function () {
    Notification::fake();

    $plan = createPlanWithNotifications(false);
    $user = User::factory()->withSecretRetrievedNotifications()->create();
    createSubscriptionForUser($user, $plan);

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Notification::assertNotSentTo($user, SecretRetrievedNotification::class);
});

test('notification not sent for guest secrets', function () {
    Notification::fake();

    $secret = createActiveSecret();
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Notification::assertNothingSent();
});

test('notification not sent when user has no plan', function () {
    Notification::fake();

    $user = User::factory()->withSecretRetrievedNotifications()->create();

    $secret = createActiveSecret($user);
    $url = URL::signedRoute('secret.decrypt', ['secret' => $secret->hash_id]);

    $this->get($url);

    Notification::assertNotSentTo($user, SecretRetrievedNotification::class);
});

function createPlanWithNotifications(bool $enabled): Plan
{
    return Plan::factory()->create([
        'name' => $enabled ? 'Pro' : 'Free',
        'features' => [
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => ['email' => $enabled],
                'type' => $enabled ? 'feature' : 'missing',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => ['webhook' => false],
                'type' => 'missing',
            ],
        ],
        'stripe_product_id' => 'prod_test',
        'stripe_monthly_price_id' => 'price_monthly_test',
        'stripe_yearly_price_id' => 'price_yearly_test',
        'price_per_month' => 9.99,
        'price_per_year' => 99.99,
    ]);
}
