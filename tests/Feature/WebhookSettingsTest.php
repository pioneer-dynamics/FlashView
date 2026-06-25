<?php

use App\Jobs\SendWebhookNotification;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->primePlan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => [
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => ['email' => true],
                'type' => 'feature',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => ['webhook' => true],
                'type' => 'feature',
            ],
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => 'feature',
            ],
        ],
    ]);

    $this->user = User::factory()->withPersonalTeam()->create();
});

test('guest cannot update webhook settings', function () {
    $response = $this->put('/user/webhook-settings', [
        'webhook_url' => 'https://example.com/webhook',
    ]);

    $response->assertRedirect('/login');
});

test('user without api plan cannot update webhook settings', function () {
    $freePlan = Plan::factory()->create([
        'name' => 'Free',
        'stripe_monthly_price_id' => 'price_monthly_free',
        'stripe_yearly_price_id' => 'price_yearly_free',
        'stripe_product_id' => 'prod_free',
        'price_per_month' => 0,
        'price_per_year' => 0,
        'features' => [
            'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
        ],
    ]);

    subscribeUserToPlan($this->user, $freePlan);
    $this->actingAs($this->user);

    $response = $this->put('/user/webhook-settings', [
        'webhook_url' => 'https://example.com/webhook',
    ]);

    $response->assertStatus(403);
});

test('user can save webhook url', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->put('/user/webhook-settings', [
        'webhook_url' => 'https://example.com/webhook',
    ]);

    $response->assertSessionHasNoErrors();

    $this->user->refresh();
    expect($this->user->webhook_url)->toEqual('https://example.com/webhook');
    expect($this->user->webhook_secret)->not->toBeNull();
});

test('webhook secret auto generated on first url save', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    expect($this->user->webhook_secret)->toBeNull();

    $this->put('/user/webhook-settings', [
        'webhook_url' => 'https://example.com/webhook',
    ]);

    $this->user->refresh();
    expect($this->user->webhook_secret)->not->toBeNull();
    expect(strlen($this->user->webhook_secret))->toEqual(64);
});

test('clearing webhook url clears secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $this->put('/user/webhook-settings', [
        'webhook_url' => '',
    ]);

    $this->user->refresh();
    expect($this->user->webhook_url)->toBeNull();
    expect($this->user->webhook_secret)->toBeNull();
});

test('validation rejects non https url', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->put('/user/webhook-settings', [
        'webhook_url' => 'http://example.com/webhook',
    ]);

    $response->assertSessionHasErrors('webhook_url');
});

test('validation rejects invalid url', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->put('/user/webhook-settings', [
        'webhook_url' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('webhook_url');
});

test('user can regenerate webhook secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $oldSecret = bin2hex(random_bytes(32));
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => $oldSecret,
    ]);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/regenerate-secret');

    $response->assertRedirect();

    $this->user->refresh();
    $this->assertNotEquals($oldSecret, $this->user->webhook_secret);
    expect(strlen($this->user->webhook_secret))->toEqual(64);
});

test('regenerate secret returns secret via flash', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/regenerate-secret');

    $response->assertSessionHas('flash.webhookSecret');
});

test('reveal secret returns secret via flash', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $secret = bin2hex(random_bytes(32));
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => $secret,
    ]);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/reveal-secret');

    $response->assertSessionHas('flash.webhookSecret', $secret);
});

test('reveal secret requires password confirmation', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->post('/user/webhook-settings/reveal-secret');

    $response->assertRedirect();
    $response->assertSessionMissing('flash.webhookSecret');
});

test('regenerate secret requires password confirmation', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $oldSecret = bin2hex(random_bytes(32));
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => $oldSecret,
    ]);
    $this->actingAs($this->user);

    $response = $this->post('/user/webhook-settings/regenerate-secret');

    $response->assertRedirect();
    expect($this->user->fresh()->webhook_secret)->toEqual($oldSecret);
});

test('cannot regenerate secret without webhook configured', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/regenerate-secret');

    $response->assertStatus(422);
});

test('webhook cleared when subscription cancelled', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);

    $subscription = $this->user->subscriptions()->first();
    $subscription->update([
        'stripe_status' => 'canceled',
        'ends_at' => now(),
    ]);

    $this->user->refresh();
    expect($this->user->webhook_url)->toBeNull();
    expect($this->user->webhook_secret)->toBeNull();
});

test('webhook cleared when plan downgraded to non api', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);

    $basicPlan = Plan::factory()->create([
        'name' => 'Basic',
        'stripe_monthly_price_id' => 'price_monthly_basic_downgrade',
        'stripe_yearly_price_id' => 'price_yearly_basic_downgrade',
        'stripe_product_id' => 'prod_basic_downgrade',
        'price_per_month' => 25,
        'price_per_year' => 250,
        'features' => [
            'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
        ],
    ]);

    $subscription = $this->user->subscriptions()->first();
    $subscription->update([
        'stripe_price' => $basicPlan->stripe_monthly_price_id,
    ]);

    $this->user->refresh();
    expect($this->user->webhook_url)->toBeNull();
    expect($this->user->webhook_secret)->toBeNull();
});

test('user can delete webhook', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/webhook-settings');

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->webhook_url)->toBeNull();
    expect($this->user->webhook_secret)->toBeNull();
});

test('delete webhook requires api plan', function () {
    $freePlan = Plan::factory()->create([
        'name' => 'Free',
        'stripe_monthly_price_id' => 'price_monthly_free_del',
        'stripe_yearly_price_id' => 'price_yearly_free_del',
        'stripe_product_id' => 'prod_free_del',
        'price_per_month' => 0,
        'price_per_year' => 0,
        'features' => [
            'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
        ],
    ]);

    subscribeUserToPlan($this->user, $freePlan);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/webhook-settings');

    $response->assertStatus(403);
});

test('delete webhook requires password confirmation', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->delete('/user/webhook-settings');

    $response->assertRedirect();
    expect($this->user->fresh()->webhook_url)->not->toBeNull();
});

test('delete webhook when none configured', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/webhook-settings');

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->webhook_url)->toBeNull();
    expect($this->user->webhook_secret)->toBeNull();
});

test('user can send test webhook', function () {
    Queue::fake();

    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/test');

    $response->assertRedirect();

    Queue::assertPushed(SendWebhookNotification::class, function ($job) {
        return $job->event === 'ping'
            && str_starts_with($job->hashId, 'test-')
            && $job->webhookUrl === 'https://example.com/webhook'
            && $job->userId === $this->user->id;
    });
});

test('test webhook requires password confirmation', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->post('/user/webhook-settings/test');

    $response->assertRedirect();
});

test('test webhook requires webhook configured', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/test');

    $response->assertStatus(422);
});

test('test webhook requires api plan', function () {
    $freePlan = Plan::factory()->create([
        'name' => 'Free',
        'stripe_monthly_price_id' => 'price_monthly_free_test',
        'stripe_yearly_price_id' => 'price_yearly_free_test',
        'stripe_product_id' => 'prod_free_test',
        'price_per_month' => 0,
        'price_per_year' => 0,
        'features' => [
            'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
        ],
    ]);

    subscribeUserToPlan($this->user, $freePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $this->actingAs($this->user);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/webhook-settings/test');

    $response->assertStatus(403);
});
