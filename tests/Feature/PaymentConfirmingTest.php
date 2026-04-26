<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class PaymentConfirmingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('payment.confirming'))
            ->assertRedirectToRoute('login');
    }

    public function test_user_without_active_subscription_sees_confirming_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('payment.confirming'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Plan/PaymentConfirming'));
    }

    public function test_user_with_active_subscription_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->create(['price_per_month' => 10]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_confirming',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('payment.confirming'))
            ->assertRedirectToRoute('dashboard');
    }

    public function test_session_id_is_passed_to_page_props(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('payment.confirming').'?session_id=cs_test_abc123')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Plan/PaymentConfirming')
                ->where('sessionId', 'cs_test_abc123')
            );
    }

    public function test_success_url_points_to_confirming_route(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $plan = Plan::factory()->create(['price_per_month' => 10]);

        $fakeCustomer = (object) ['id' => 'cus_test_fake'];

        $fakeSession = (object) [
            'id' => 'cs_test_fake',
            'url' => 'https://checkout.stripe.com/pay/cs_test_fake',
        ];

        $capturedData = null;

        $mockCustomersService = Mockery::mock();
        $mockCustomersService->shouldReceive('create')->andReturn($fakeCustomer);
        $mockCustomersService->shouldReceive('retrieve')->andReturn($fakeCustomer);
        $mockCustomersService->shouldReceive('update')->andReturn($fakeCustomer);

        $mockCheckoutService = Mockery::mock();
        $mockCheckoutService->shouldReceive('create')
            ->once()
            ->andReturnUsing(function ($data) use (&$capturedData, $fakeSession) {
                $capturedData = $data;

                return $fakeSession;
            });

        $mockStripeClient = new \stdClass;
        $mockStripeClient->customers = $mockCustomersService;
        $mockStripeClient->checkout = new \stdClass;
        $mockStripeClient->checkout->sessions = $mockCheckoutService;

        $this->app->bind(StripeClient::class, fn () => $mockStripeClient);

        $this->actingAs($user)
            ->get(route('plans.subscribe', ['plan' => $plan->id, 'period' => 'monthly']));

        $this->assertNotNull($capturedData, 'Stripe checkout sessions create was not called.');
        $this->assertStringContainsString(
            route('payment.confirming'),
            $capturedData['success_url'] ?? ''
        );
    }
}
