<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class DeleteApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_can_be_deleted(): void
    {
        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');
        }

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $plan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_delete',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['secrets:create', 'secrets:list'],
        ]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/api-tokens/'.$token->id);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_deleting_api_token_requires_password_confirmation(): void
    {
        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');
        }

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $plan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_delete_confirm',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['secrets:create'],
        ]);

        $response = $this->delete('/user/api-tokens/'.$token->id);

        $response->assertRedirect();
        $this->assertCount(1, $user->fresh()->tokens);
    }
}
