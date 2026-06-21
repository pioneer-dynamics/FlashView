<?php

namespace Tests\Feature;

use App\Models\Locker;
use App\Models\LockerPlan;
use App\Models\SecureLineProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class CheckoutPromotionCodesTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeCheckoutCapture(?array &$capturedData): void
    {
        $fakeSession = (object) ['url' => 'https://checkout.stripe.com/fake-session'];

        $mockCheckoutService = Mockery::mock();
        $mockCheckoutService->shouldReceive('create')
            ->andReturnUsing(function (array $data) use (&$capturedData, $fakeSession) {
                $capturedData = $data;

                return $fakeSession;
            });

        $mockStripeClient = new \stdClass;
        $mockStripeClient->checkout = new \stdClass;
        $mockStripeClient->checkout->sessions = $mockCheckoutService;

        $this->app->bind(StripeClient::class, fn () => $mockStripeClient);
    }

    public function test_locker_checkout_includes_allow_promotion_codes(): void
    {
        $capturedData = null;
        $this->mockStripeCheckoutCapture($capturedData);

        LockerPlan::create([
            'tier' => 'text',
            'years' => 1,
            'amount_cents' => 2000,
            'stripe_price_id' => 'price_locker_test',
            'is_active' => true,
        ]);

        $this->post(route('lockers.checkout'), ['tier' => 'text', 'years' => 1]);

        $this->assertNotNull($capturedData);
        $this->assertTrue($capturedData['allow_promotion_codes'] ?? false);
    }

    public function test_locker_renewal_checkout_includes_allow_promotion_codes(): void
    {
        $capturedData = null;
        $this->mockStripeCheckoutCapture($capturedData);

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

        $this->assertNotNull($capturedData);
        $this->assertTrue($capturedData['allow_promotion_codes'] ?? false);
    }

    public function test_secure_line_checkout_includes_allow_promotion_codes(): void
    {
        $capturedData = null;
        $this->mockStripeCheckoutCapture($capturedData);

        $product = SecureLineProduct::factory()->create([
            'stripe_price_id' => 'price_secure_line_test',
            'is_active' => true,
        ]);

        $this->post(route('calls.checkout'), ['product_id' => $product->id]);

        $this->assertNotNull($capturedData);
        $this->assertTrue($capturedData['allow_promotion_codes'] ?? false);
    }
}
