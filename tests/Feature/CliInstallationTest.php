<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CliInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscribedUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();

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
            'stripe_id' => 'sub_cli_test_'.Str::random(8),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    public function test_unauthenticated_user_cannot_delete_cli_installation(): void
    {
        $response = $this->delete('/user/cli-installations/1');

        $response->assertRedirect(route('login'));
    }

    public function test_cli_installation_can_be_deleted(): void
    {
        $user = $this->createSubscribedUser();
        $this->actingAs($user);

        $token = $user->tokens()->create([
            'name' => 'My CLI',
            'token' => Str::random(40),
            'abilities' => ['*'],
            'type' => 'cli',
        ]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/cli-installations/'.$token->id);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_deleting_cli_installation_requires_password_confirmation(): void
    {
        $user = $this->createSubscribedUser();
        $this->actingAs($user);

        $token = $user->tokens()->create([
            'name' => 'My CLI',
            'token' => Str::random(40),
            'abilities' => ['*'],
            'type' => 'cli',
        ]);

        $response = $this->delete('/user/cli-installations/'.$token->id);

        $response->assertRedirect();
        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_mobile_installation_can_be_deleted(): void
    {
        $user = $this->createSubscribedUser();
        $this->actingAs($user);

        $token = $user->tokens()->create([
            'name' => 'My iPhone',
            'token' => Str::random(40),
            'abilities' => ['*'],
            'type' => 'mobile',
        ]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/cli-installations/'.$token->id);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_api_type_token_cannot_be_deleted_via_cli_installation_route(): void
    {
        $user = $this->createSubscribedUser();
        $this->actingAs($user);

        $token = $user->tokens()->create([
            'name' => 'My API Key',
            'token' => Str::random(40),
            'abilities' => ['*'],
            'type' => 'api',
        ]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/cli-installations/'.$token->id)
            ->assertNotFound();

        $this->assertCount(1, $user->fresh()->tokens);
    }
}
