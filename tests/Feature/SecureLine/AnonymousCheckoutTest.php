<?php

use App\Listeners\HandleSecureLineStripeWebhook;
use App\Models\CallSession;
use App\Models\SecureLineCredit;
use App\Models\SecureLineProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

test('buy page shows active products with stripe price id', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create(['is_active' => true]);

    $response = $this->get(route('calls.buy'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Call/Buy')
            ->has('products', 1)
            ->where('products.0.id', $product->id)
        );
});

test('buy page excludes inactive products', function () {
    SecureLineProduct::factory()->withStripePrice()->inactive()->create();

    $response = $this->get(route('calls.buy'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Call/Buy')
            ->has('products', 0)
        );
});

test('buy page excludes products without stripe price id', function () {
    SecureLineProduct::factory()->create(['is_active' => true, 'stripe_price_id' => null]);

    $response = $this->get(route('calls.buy'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Call/Buy')
            ->has('products', 0)
        );
});

test('checkout rejects inactive product', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->inactive()->create();

    $response = $this->post(route('calls.checkout'), ['product_id' => $product->id]);

    $response->assertSessionHasErrors('product_id');
});

test('checkout rejects product without stripe price id', function () {
    $product = SecureLineProduct::factory()->create(['is_active' => true, 'stripe_price_id' => null]);

    $response = $this->post(route('calls.checkout'), ['product_id' => $product->id]);

    $response->assertSessionHasErrors('product_id');
});

test('credit status returns missing session error without param', function () {
    $response = $this->getJson(route('calls.credit-status'));

    $response->assertStatus(422)->assertJson(['error' => 'Missing session parameter.']);
});

test('credit status returns pending when no credit', function () {
    $response = $this->getJson(route('calls.credit-status').'?session=nonexistent_session');

    $response->assertStatus(200)->assertJson(['pending' => true]);
});

test('credit status returns token when credit exists', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    $credit = SecureLineCredit::factory()->create([
        'stripe_session_id' => 'cs_test_abc123',
        'secure_line_product_id' => $product->id,
        'token' => 'sometoken',
    ]);

    $response = $this->getJson(route('calls.credit-status').'?session=cs_test_abc123');

    $response->assertStatus(200)->assertJson(['token' => 'sometoken']);
});

test('create page requires valid unused token', function () {
    $response = $this->get(route('calls.create').'?token=invalid');

    $response->assertStatus(404);
});

test('create page rejects invalid token', function () {
    $response = $this->get(route('calls.create'));

    $response->assertStatus(404);
});

test('create page rejects used token', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    SecureLineCredit::factory()->used()->create([
        'token' => 'usedtoken',
        'secure_line_product_id' => $product->id,
    ]);

    $response = $this->get(route('calls.create').'?token=usedtoken');

    $response->assertStatus(404);
});

test('create page loads with valid unused token', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create([
        'name' => 'Test Product',
        'duration_minutes' => 30,
        'max_participants' => 5,
    ]);
    SecureLineCredit::factory()->create([
        'token' => 'validtoken',
        'secure_line_product_id' => $product->id,
    ]);

    $response = $this->get(route('calls.create').'?token=validtoken');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Call/Create')
            ->where('credit_token', 'validtoken')
            ->where('product.name', 'Test Product')
            ->where('product.duration_minutes', 30)
            ->where('product.max_participants', 5)
        );
});

test('create page handles soft deleted product', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    SecureLineCredit::factory()->create([
        'token' => 'validtoken',
        'secure_line_product_id' => $product->id,
    ]);
    $product->delete();

    // soft delete
    $response = $this->get(route('calls.create').'?token=validtoken');

    // Should still work — controller loads product with withTrashed()
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Call/Create'));
});

test('store rejects invalid token', function () {
    $response = $this->postJson(route('calls.store'), [
        'credit_token' => 'notavalidtoken',
        'public_key' => base64_encode(random_bytes(32)),
        'key_salt' => base64_encode(random_bytes(32)),
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'Invalid or used credit token.']);
});

test('store rejects used credit token', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    SecureLineCredit::factory()->used()->create([
        'token' => 'usedtoken',
        'secure_line_product_id' => $product->id,
    ]);

    $response = $this->postJson(route('calls.store'), [
        'credit_token' => 'usedtoken',
        'public_key' => base64_encode(random_bytes(32)),
        'key_salt' => base64_encode(random_bytes(32)),
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'Invalid or used credit token.']);
});

test('store creates call session and marks credit used', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create([
        'duration_minutes' => 30,
        'max_participants' => 4,
    ]);
    SecureLineCredit::factory()->create([
        'token' => 'freshtoken',
        'secure_line_product_id' => $product->id,
    ]);

    $publicKey = base64_encode(random_bytes(32));
    $keySalt = base64_encode(random_bytes(32));

    $response = $this->postJson(route('calls.store'), [
        'credit_token' => 'freshtoken',
        'public_key' => $publicKey,
        'key_salt' => $keySalt,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['bridge_number', 'starts_at', 'ends_at']);

    $this->assertDatabaseHas('call_sessions', ['public_key' => $publicKey]);

    $credit = SecureLineCredit::where('token', 'freshtoken')->first();
    expect($credit->used_at)->not->toBeNull();
    expect($credit->call_session_id)->not->toBeNull();
});

test('store sets correct session duration from product', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create(['duration_minutes' => 45]);
    SecureLineCredit::factory()->create([
        'token' => 'durationtoken',
        'secure_line_product_id' => $product->id,
    ]);

    $response = $this->postJson(route('calls.store'), [
        'credit_token' => 'durationtoken',
        'public_key' => base64_encode(random_bytes(32)),
        'key_salt' => base64_encode(random_bytes(32)),
    ]);

    $response->assertStatus(200);

    $bridgeNumber = $response->json('bridge_number');
    $session = CallSession::whereNotNull('public_key')->latest()->first();
    expect($session->starts_at->diffInMinutes($session->ends_at))->toEqual(45);
});

test('store loads soft deleted product correctly', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create(['duration_minutes' => 60]);
    SecureLineCredit::factory()->create([
        'token' => 'softdeltoken',
        'secure_line_product_id' => $product->id,
    ]);
    $product->delete();

    // soft delete
    $response = $this->postJson(route('calls.store'), [
        'credit_token' => 'softdeltoken',
        'public_key' => base64_encode(random_bytes(32)),
        'key_salt' => base64_encode(random_bytes(32)),
    ]);

    $response->assertStatus(200)->assertJsonStructure(['bridge_number']);
});

test('webhook creates credit for secure line product', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    $listener = new HandleSecureLineStripeWebhook;

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_webhook001',
                'payment_status' => 'paid',
                'metadata' => [
                    'product_type' => 'secure_line',
                    'secure_line_product_id' => $product->id,
                ],
            ],
        ],
    ]);

    $listener->handle($event);

    $this->assertDatabaseHas('secure_line_credits', [
        'stripe_session_id' => 'cs_test_webhook001',
        'secure_line_product_id' => $product->id,
    ]);
});

test('webhook is idempotent on duplicate stripe session id', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    $listener = new HandleSecureLineStripeWebhook;

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_duplicate',
                'payment_status' => 'paid',
                'metadata' => [
                    'product_type' => 'secure_line',
                    'secure_line_product_id' => $product->id,
                ],
            ],
        ],
    ];

    $listener->handle(new WebhookReceived($payload));
    $listener->handle(new WebhookReceived($payload));

    $this->assertDatabaseCount('secure_line_credits', 1);
});

test('webhook ignores non secure line events', function () {
    $listener = new HandleSecureLineStripeWebhook;

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_other',
                'payment_status' => 'paid',
                'metadata' => [
                    'product_type' => 'something_else',
                ],
            ],
        ],
    ]);

    $listener->handle($event);

    $this->assertDatabaseCount('secure_line_credits', 0);
});

test('webhook ignores locker events', function () {
    $listener = new HandleSecureLineStripeWebhook;

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_locker',
                'payment_status' => 'paid',
                'metadata' => [
                    'action' => 'create',
                    'tier' => 'text',
                    'years' => '1',
                ],
            ],
        ],
    ]);

    $listener->handle($event);

    $this->assertDatabaseCount('secure_line_credits', 0);
});

test('webhook ignores processing payment status', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    $listener = new HandleSecureLineStripeWebhook;

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_processing',
                'payment_status' => 'unpaid',
                'metadata' => [
                    'product_type' => 'secure_line',
                    'secure_line_product_id' => $product->id,
                ],
            ],
        ],
    ]);

    $listener->handle($event);

    $this->assertDatabaseCount('secure_line_credits', 0);
});

test('webhook creates credit for 100 percent off coupon', function () {
    $product = SecureLineProduct::factory()->withStripePrice()->create();
    $listener = new HandleSecureLineStripeWebhook;

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_free_coupon',
                'payment_status' => 'no_payment_required',
                'metadata' => [
                    'product_type' => 'secure_line',
                    'secure_line_product_id' => $product->id,
                ],
            ],
        ],
    ]);

    $listener->handle($event);

    $this->assertDatabaseHas('secure_line_credits', [
        'stripe_session_id' => 'cs_test_free_coupon',
        'secure_line_product_id' => $product->id,
    ]);
});

test('webhook skips when product not found in database', function () {
    $listener = new HandleSecureLineStripeWebhook;

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_missing_product',
                'payment_status' => 'paid',
                'metadata' => [
                    'product_type' => 'secure_line',
                    'secure_line_product_id' => 99999,
                ],
            ],
        ],
    ]);

    $listener->handle($event);

    $this->assertDatabaseCount('secure_line_credits', 0);
});
