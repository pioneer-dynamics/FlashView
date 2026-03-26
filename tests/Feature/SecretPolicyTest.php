<?php

namespace Tests\Feature;

use App\Models\Secret;
use App\Models\User;
use App\Policies\SecretPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretPolicyTest extends TestCase
{
    use RefreshDatabase;

    private SecretPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SecretPolicy;
    }

    public function test_owner_can_view_own_secret(): void
    {
        $user = User::factory()->create();
        $secret = Secret::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->view($user, $secret));
    }

    public function test_user_cannot_view_other_users_secret(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $secret = Secret::factory()->forUser($owner)->create();

        $this->assertFalse($this->policy->view($other, $secret));
    }

    public function test_owner_can_delete_own_secret(): void
    {
        $user = User::factory()->create();
        $secret = Secret::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->delete($user, $secret));
    }

    public function test_user_cannot_delete_other_users_secret(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $secret = Secret::factory()->forUser($owner)->create();

        $this->assertFalse($this->policy->delete($other, $secret));
    }

    public function test_web_session_user_can_view_any(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_web_session_user_can_create(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($user));
    }

    public function test_api_token_with_correct_ability_can_view(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['secrets:list']);
        $user->withAccessToken($token->accessToken);

        $secret = Secret::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->view($user, $secret));
    }

    public function test_api_token_without_list_ability_cannot_view(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['secrets:create']);
        $user->withAccessToken($token->accessToken);

        $secret = Secret::factory()->forUser($user)->create();

        $this->assertFalse($this->policy->view($user, $secret));
    }

    public function test_api_token_without_delete_ability_cannot_delete(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['secrets:list']);
        $user->withAccessToken($token->accessToken);

        $secret = Secret::factory()->forUser($user)->create();

        $this->assertFalse($this->policy->delete($user, $secret));
    }

    public function test_api_token_with_delete_ability_can_delete_own(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['secrets:delete']);
        $user->withAccessToken($token->accessToken);

        $secret = Secret::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->delete($user, $secret));
    }
}
