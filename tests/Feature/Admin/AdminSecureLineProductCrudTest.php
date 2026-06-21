<?php

namespace Tests\Feature\Admin;

use App\Models\SecureLineProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminSecureLineProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Config::set('admin.emails', [$user->email]);

        return $user;
    }

    private function nonAdminUser(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Secure Line',
            'duration_minutes' => 30,
            'max_participants' => 5,
            'amount_cents' => 2000,
            'stripe_price_id' => '',
            'create_stripe_price' => false,
            'is_active' => true,
        ], $overrides);
    }

    public function test_unauthenticated_user_is_redirected_from_admin_secure_line_products(): void
    {
        $response = $this->get(route('admin.secure-line-products.index'));

        $response->assertRedirect('/login');
    }

    public function test_non_admin_receives_403_on_admin_secure_line_products(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('admin.secure-line-products.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_secure_line_products_index(): void
    {
        $admin = $this->adminUser();
        SecureLineProduct::factory()->create(['name' => 'Quick Call']);

        $response = $this->actingAs($admin)->get(route('admin.secure-line-products.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/SecureLineProducts/Index')
            ->has('products', 1)
        );
    }

    public function test_admin_can_create_product_with_mapped_stripe_price_id(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            $this->productPayload([
                'name' => 'Premium Call',
                'stripe_price_id' => 'price_mapped123',
            ])
        );

        $response->assertRedirect(route('admin.secure-line-products.index'));
        $this->assertDatabaseHas('secure_line_products', [
            'name' => 'Premium Call',
            'stripe_price_id' => 'price_mapped123',
        ]);
    }

    public function test_admin_can_create_product_without_stripe_price_id(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            $this->productPayload(['stripe_price_id' => null])
        );

        $response->assertRedirect(route('admin.secure-line-products.index'));
        $this->assertDatabaseHas('secure_line_products', [
            'name' => 'Test Secure Line',
            'stripe_price_id' => null,
        ]);
    }

    public function test_admin_can_view_edit_form_for_product(): void
    {
        $admin = $this->adminUser();
        $product = SecureLineProduct::factory()->withStripePrice()->create();

        $response = $this->actingAs($admin)->get(route('admin.secure-line-products.edit', $product));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/SecureLineProducts/Form')
            ->where('product.id', $product->id)
        );
    }

    public function test_admin_can_update_a_product(): void
    {
        $admin = $this->adminUser();
        $product = SecureLineProduct::factory()->withStripePrice()->create();

        $response = $this->actingAs($admin)->putJson(
            route('admin.secure-line-products.update', $product),
            $this->productPayload([
                'name' => 'Updated Name',
                'duration_minutes' => 60,
                'stripe_price_id' => $product->stripe_price_id,
            ])
        );

        $response->assertRedirect(route('admin.secure-line-products.index'));
        $product->refresh();
        $this->assertEquals('Updated Name', $product->name);
        $this->assertEquals(60, $product->duration_minutes);
    }

    public function test_admin_can_deactivate_a_product(): void
    {
        $admin = $this->adminUser();
        $product = SecureLineProduct::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->putJson(
            route('admin.secure-line-products.update', $product),
            $this->productPayload(['is_active' => false])
        );

        $product->refresh();
        $this->assertFalse($product->is_active);
    }

    public function test_admin_can_delete_a_product(): void
    {
        $admin = $this->adminUser();
        $product = SecureLineProduct::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.secure-line-products.destroy', $product));

        $response->assertRedirect(route('admin.secure-line-products.index'));
        $this->assertSoftDeleted('secure_line_products', ['id' => $product->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'duration_minutes', 'max_participants', 'amount_cents']);
    }

    public function test_duration_minutes_must_be_at_least_1(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            $this->productPayload(['duration_minutes' => 0])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['duration_minutes']);
    }

    public function test_max_participants_must_be_at_least_2(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(
            route('admin.secure-line-products.store'),
            $this->productPayload(['max_participants' => 1])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['max_participants']);
    }

    public function test_non_admin_cannot_create_product(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->postJson(
            route('admin.secure-line-products.store'),
            $this->productPayload()
        );

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_update_product(): void
    {
        $user = $this->nonAdminUser();
        $product = SecureLineProduct::factory()->create();

        $response = $this->actingAs($user)->putJson(
            route('admin.secure-line-products.update', $product),
            $this->productPayload()
        );

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_product(): void
    {
        $user = $this->nonAdminUser();
        $product = SecureLineProduct::factory()->create();

        $response = $this->actingAs($user)->delete(route('admin.secure-line-products.destroy', $product));

        $response->assertStatus(403);
    }
}
