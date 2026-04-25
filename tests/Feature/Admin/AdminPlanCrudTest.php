<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminPlanCrudTest extends TestCase
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

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Plan',
            'price_per_month' => 10.00,
            'price_per_year' => 100.00,
            'create_stripe_product' => false,
            'stripe_product_id' => '',
            'stripe_monthly_price_id' => '',
            'stripe_yearly_price_id' => '',
            'features' => [
                'messages' => ['order' => 1, 'type' => 'limit',   'config' => ['message_length' => 5000]],
                'expiry' => ['order' => 3, 'type' => 'limit',   'config' => ['expiry_minutes' => 20160, 'expiry_label' => '14 days']],
                'throttling' => ['order' => 4, 'type' => 'feature', 'config' => []],
                'support' => ['order' => 5, 'type' => 'feature', 'config' => []],
                'api' => ['order' => 6, 'type' => 'feature', 'config' => []],
            ],
        ], $overrides);
    }

    public function test_unauthenticated_user_is_redirected_from_admin_plans(): void
    {
        $response = $this->get(route('admin.plans.index'));

        $response->assertRedirect('/login');
    }

    public function test_non_admin_receives_403_on_admin_plans(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('admin.plans.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_plans_index(): void
    {
        $admin = $this->adminUser();
        Plan::factory()->create(['name' => 'Starter']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Plans/Index')
            ->has('plans', 1)
        );
    }

    public function test_admin_can_create_plan_with_mapped_stripe_ids(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->planPayload([
            'name' => 'Growth',
            'price_per_month' => 25.00,
        ]));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseHas('plans', ['name' => 'Growth', 'price_per_month' => 25.00]);
    }

    public function test_admin_can_edit_plan_features(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create();

        $updatedFeatures = $this->planPayload()['features'];
        $updatedFeatures['messages'] = ['order' => 2, 'type' => 'limit', 'config' => ['message_length' => 99999]];

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->planPayload([
            'name' => $plan->name,
            'price_per_month' => $plan->price_per_month,
            'price_per_year' => $plan->price_per_year,
            'features' => $updatedFeatures,
        ]));

        $response->assertRedirect(route('admin.plans.index'));
        $plan->refresh();
        $this->assertEquals(99999, $plan->features['messages']['config']['message_length']);
        $this->assertEquals(2, $plan->features['messages']['order']);
        $this->assertEquals('limit', $plan->features['messages']['type']);
    }

    public function test_admin_cannot_save_plan_with_empty_features(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->planPayload([
            'features' => [],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['features']);
    }

    public function test_admin_cannot_save_plan_with_missing_type_feature(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->planPayload([
            'features' => [
                'api' => ['order' => 1, 'type' => 'missing', 'config' => []],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['features.api.type']);
    }

    public function test_admin_can_delete_plan_with_no_subscribers(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_admin_cannot_delete_plan_with_active_subscribers(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create([
            'stripe_monthly_price_id' => 'price_monthly_abc',
            'stripe_yearly_price_id' => 'price_yearly_abc',
        ]);

        $subscriber = User::factory()->withPersonalTeam()->create();
        $subscription = $subscriber->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
        $subscription->items()->create([
            'stripe_id' => 'si_test_'.uniqid(),
            'stripe_product' => 'prod_abc',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertStatus(422);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_is_admin_is_true_in_inertia_props_for_admin_user(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.is_admin', true)
        );
    }

    public function test_is_admin_is_false_in_inertia_props_for_non_admin(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.is_admin', false)
        );
    }

    public function test_admin_store_validates_required_fields(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), []);

        $response->assertSessionHasErrors(['name', 'price_per_month', 'price_per_year', 'create_stripe_product', 'features']);
    }

    public function test_non_admin_cannot_create_plan(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->postJson(route('admin.plans.store'), $this->planPayload());

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_update_plan(): void
    {
        $user = $this->nonAdminUser();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($user)->putJson(route('admin.plans.update', $plan), $this->planPayload());

        $response->assertStatus(403);
    }
}
