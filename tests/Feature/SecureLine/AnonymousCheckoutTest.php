<?php

namespace Tests\Feature\SecureLine;

use App\Listeners\HandleSecureLineStripeWebhook;
use App\Models\CallSession;
use App\Models\SecureLineCredit;
use App\Models\SecureLineProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class AnonymousCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // ── Buy page ────────────────────────────────────────────────────────────

    public function test_buy_page_shows_active_products_with_stripe_price_id(): void
    {
        $product = SecureLineProduct::factory()->withStripePrice()->create(['is_active' => true]);

        $response = $this->get(route('calls.buy'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Call/Buy')
                ->has('products', 1)
                ->where('products.0.id', $product->id)
            );
    }

    public function test_buy_page_excludes_inactive_products(): void
    {
        SecureLineProduct::factory()->withStripePrice()->inactive()->create();

        $response = $this->get(route('calls.buy'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Call/Buy')
                ->has('products', 0)
            );
    }

    public function test_buy_page_excludes_products_without_stripe_price_id(): void
    {
        SecureLineProduct::factory()->create(['is_active' => true, 'stripe_price_id' => null]);

        $response = $this->get(route('calls.buy'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Call/Buy')
                ->has('products', 0)
            );
    }

    // ── Checkout ─────────────────────────────────────────────────────────────

    public function test_checkout_rejects_inactive_product(): void
    {
        $product = SecureLineProduct::factory()->withStripePrice()->inactive()->create();

        $response = $this->post(route('calls.checkout'), ['product_id' => $product->id]);

        $response->assertSessionHasErrors('product_id');
    }

    public function test_checkout_rejects_product_without_stripe_price_id(): void
    {
        $product = SecureLineProduct::factory()->create(['is_active' => true, 'stripe_price_id' => null]);

        $response = $this->post(route('calls.checkout'), ['product_id' => $product->id]);

        $response->assertSessionHasErrors('product_id');
    }

    // ── Credit Status ─────────────────────────────────────────────────────────

    public function test_credit_status_returns_missing_session_error_without_param(): void
    {
        $response = $this->getJson(route('calls.credit-status'));

        $response->assertStatus(422)->assertJson(['error' => 'Missing session parameter.']);
    }

    public function test_credit_status_returns_pending_when_no_credit(): void
    {
        $response = $this->getJson(route('calls.credit-status').'?session=nonexistent_session');

        $response->assertStatus(200)->assertJson(['pending' => true]);
    }

    public function test_credit_status_returns_token_when_credit_exists(): void
    {
        $product = SecureLineProduct::factory()->withStripePrice()->create();
        $credit = SecureLineCredit::factory()->create([
            'stripe_session_id' => 'cs_test_abc123',
            'secure_line_product_id' => $product->id,
            'token' => 'sometoken',
        ]);

        $response = $this->getJson(route('calls.credit-status').'?session=cs_test_abc123');

        $response->assertStatus(200)->assertJson(['token' => 'sometoken']);
    }

    // ── Create page ──────────────────────────────────────────────────────────

    public function test_create_page_requires_valid_unused_token(): void
    {
        $response = $this->get(route('calls.create').'?token=invalid');

        $response->assertStatus(404);
    }

    public function test_create_page_rejects_invalid_token(): void
    {
        $response = $this->get(route('calls.create'));

        $response->assertStatus(404);
    }

    public function test_create_page_rejects_used_token(): void
    {
        $product = SecureLineProduct::factory()->withStripePrice()->create();
        SecureLineCredit::factory()->used()->create([
            'token' => 'usedtoken',
            'secure_line_product_id' => $product->id,
        ]);

        $response = $this->get(route('calls.create').'?token=usedtoken');

        $response->assertStatus(404);
    }

    public function test_create_page_loads_with_valid_unused_token(): void
    {
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
    }

    public function test_create_page_handles_soft_deleted_product(): void
    {
        $product = SecureLineProduct::factory()->withStripePrice()->create();
        SecureLineCredit::factory()->create([
            'token' => 'validtoken',
            'secure_line_product_id' => $product->id,
        ]);
        $product->delete(); // soft delete

        $response = $this->get(route('calls.create').'?token=validtoken');

        // Should still work — controller loads product with withTrashed()
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Call/Create'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_rejects_invalid_token(): void
    {
        $response = $this->postJson(route('calls.store'), [
            'credit_token' => 'notavalidtoken',
            'public_key' => base64_encode(random_bytes(32)),
            'key_salt' => base64_encode(random_bytes(32)),
        ]);

        $response->assertStatus(422)->assertJson(['error' => 'Invalid or used credit token.']);
    }

    public function test_store_rejects_used_credit_token(): void
    {
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
    }

    public function test_store_creates_call_session_and_marks_credit_used(): void
    {
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
        $this->assertNotNull($credit->used_at);
        $this->assertNotNull($credit->call_session_id);
    }

    public function test_store_sets_correct_session_duration_from_product(): void
    {
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
        $this->assertEquals(45, $session->starts_at->diffInMinutes($session->ends_at));
    }

    public function test_store_loads_soft_deleted_product_correctly(): void
    {
        $product = SecureLineProduct::factory()->withStripePrice()->create(['duration_minutes' => 60]);
        SecureLineCredit::factory()->create([
            'token' => 'softdeltoken',
            'secure_line_product_id' => $product->id,
        ]);
        $product->delete(); // soft delete

        $response = $this->postJson(route('calls.store'), [
            'credit_token' => 'softdeltoken',
            'public_key' => base64_encode(random_bytes(32)),
            'key_salt' => base64_encode(random_bytes(32)),
        ]);

        $response->assertStatus(200)->assertJsonStructure(['bridge_number']);
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    public function test_webhook_creates_credit_for_secure_line_product(): void
    {
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
    }

    public function test_webhook_is_idempotent_on_duplicate_stripe_session_id(): void
    {
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
    }

    public function test_webhook_ignores_non_secure_line_events(): void
    {
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
    }

    public function test_webhook_ignores_locker_events(): void
    {
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
    }

    public function test_webhook_ignores_processing_payment_status(): void
    {
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
    }
}
