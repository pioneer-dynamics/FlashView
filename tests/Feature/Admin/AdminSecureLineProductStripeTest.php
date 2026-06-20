<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdminSecureLineProductController;
use App\Models\SecureLineProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AdminSecureLineProductStripeTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Config::set('admin.emails', [$user->email]);

        return $user;
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Quick Call',
            'duration_minutes' => 30,
            'max_participants' => 5,
            'amount_cents' => 2000,
            'stripe_price_id' => '',
            'create_stripe_price' => true,
            'is_active' => true,
        ], $overrides);
    }

    private function mockControllerWithStripeResult(?string $priceId): void
    {
        $mock = Mockery::mock(AdminSecureLineProductController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('createStripePrice')->andReturn($priceId);
        $this->app->instance(AdminSecureLineProductController::class, $mock);
    }

    public function test_store_with_create_stripe_price_saves_returned_price_id(): void
    {
        $admin = $this->adminUser();
        $this->mockControllerWithStripeResult('price_test456');

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            $this->basePayload(['name' => 'Quick Call'])
        );

        $response->assertRedirect(route('admin.secure-line-products.index'));
        $this->assertDatabaseHas('secure_line_products', [
            'name' => 'Quick Call',
            'stripe_price_id' => 'price_test456',
        ]);
    }

    public function test_store_with_stripe_failure_redirects_back_with_error(): void
    {
        $admin = $this->adminUser();
        $this->mockControllerWithStripeResult(null);

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            $this->basePayload()
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('secure_line_products', ['name' => 'Quick Call']);
    }

    public function test_update_with_create_stripe_price_replaces_existing_price_id(): void
    {
        $admin = $this->adminUser();
        $product = SecureLineProduct::factory()->withStripePrice()->create([
            'stripe_price_id' => 'price_old789',
        ]);
        $this->mockControllerWithStripeResult('price_new123');

        $this->actingAs($admin)->putJson(
            route('admin.secure-line-products.update', $product),
            $this->basePayload(['name' => $product->name])
        );

        $product->refresh();
        $this->assertEquals('price_new123', $product->stripe_price_id);
    }

    public function test_update_without_create_stripe_price_retains_existing_price_id(): void
    {
        $admin = $this->adminUser();
        $product = SecureLineProduct::factory()->withStripePrice()->create([
            'stripe_price_id' => 'price_existing999',
        ]);

        // Omit stripe_price_id from the payload to simulate admin not re-entering it
        $response = $this->actingAs($admin)->putJson(
            route('admin.secure-line-products.update', $product),
            [
                'name' => $product->name,
                'duration_minutes' => $product->duration_minutes,
                'max_participants' => $product->max_participants,
                'amount_cents' => $product->amount_cents,
                'create_stripe_price' => false,
                'is_active' => true,
            ]
        );

        $response->assertRedirect(route('admin.secure-line-products.index'));
        $product->refresh();
        $this->assertEquals('price_existing999', $product->stripe_price_id);
    }
}
