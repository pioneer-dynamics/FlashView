<?php

use App\Models\Locker;
use App\Models\LockerPlan;
use App\Models\SecureLineProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mockStripeCheckoutCapture = function (?array &$capturedData): void {
        $fakeSession = (object) ['url' => 'https://checkout.stripe.com/fake-session'];

        $mockCheckoutService = Mockery::mock();
        $mockCheckoutService->shouldReceive('create')
            ->andReturnUsing(function (array $data) use (&$capturedData, $fakeSession) {
                $capturedData = $data;

                return $fakeSession;
            });

        $mockStripeClient = new stdClass;
        $mockStripeClient->checkout = new stdClass;
        $mockStripeClient->checkout->sessions = $mockCheckoutService;

        $this->app->bind(StripeClient::class, fn () => $mockStripeClient);
    };
});

test('locker checkout includes allow promotion codes', function () {
    $capturedData = null;
    ($this->mockStripeCheckoutCapture)($capturedData);

    LockerPlan::create([
        'tier' => 'text',
        'years' => 1,
        'amount_cents' => 2000,
        'stripe_price_id' => 'price_locker_test',
        'is_active' => true,
    ]);

    $this->post(route('lockers.checkout'), ['tier' => 'text', 'years' => 1]);

    expect($capturedData)->not->toBeNull();
    expect($capturedData['allow_promotion_codes'] ?? false)->toBeTrue();
});

test('locker renewal checkout includes allow promotion codes', function () {
    $capturedData = null;
    ($this->mockStripeCheckoutCapture)($capturedData);

    $verifier = bin2hex(random_bytes(32));

    $locker = Locker::factory()->create([
        'auth_verifier' => $verifier,
        'public_key' => null,
    ]);

    LockerPlan::create([
        'tier' => 'text',
        'years' => 1,
        'amount_cents' => 2000,
        'stripe_price_id' => 'price_locker_renew_test',
        'is_active' => true,
    ]);

    $this->post(route('lockers.renew.purchase', $locker->account_id), [
        'verifier' => $verifier,
        'years' => 1,
        'tier' => 'text',
    ]);

    expect($capturedData)->not->toBeNull();
    expect($capturedData['allow_promotion_codes'] ?? false)->toBeTrue();
});

test('secure line checkout includes allow promotion codes', function () {
    $capturedData = null;
    ($this->mockStripeCheckoutCapture)($capturedData);

    $product = SecureLineProduct::factory()->create([
        'stripe_price_id' => 'price_secure_line_test',
        'is_active' => true,
    ]);

    $this->post(route('calls.checkout'), ['product_id' => $product->id]);

    expect($capturedData)->not->toBeNull();
    expect($capturedData['allow_promotion_codes'] ?? false)->toBeTrue();
});
