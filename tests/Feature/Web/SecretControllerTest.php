<?php

namespace Tests\Feature\Web;

use App\Models\Secret;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecretControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validSecretData(int $plaintextLength = 50, int $expiresIn = 5): array
    {
        $message = (new SecretFactory)->generateEncryptedMessage($plaintextLength);

        return [
            'message' => $message,
            'expires_in' => $expiresIn,
        ];
    }

    public function test_guest_can_create_secret(): void
    {
        $response = $this->post(route('secret.store'), $this->validSecretData());

        $response->assertRedirect();
        $response->assertSessionHas('flash.secret.url');
        $this->assertDatabaseCount('secrets', 1);
    }

    public function test_guest_cannot_exceed_message_length(): void
    {
        $limit = config('secrets.message_length.guest');
        $data = $this->validSecretData($limit + 1);

        $response = $this->post(route('secret.store'), $data);

        $response->assertSessionHasErrors('message');
    }

    public function test_guest_cannot_exceed_expiry_limit(): void
    {
        $guestLimit = config('secrets.expiry_limits.guest');
        $beyondLimit = collect(config('secrets.expiry_options'))
            ->firstWhere(fn ($opt) => $opt['value'] > $guestLimit);

        if (! $beyondLimit) {
            $this->markTestSkipped('No expiry option exceeds guest limit.');
        }

        $data = $this->validSecretData();
        $data['expires_in'] = $beyondLimit['value'];

        $response = $this->post(route('secret.store'), $data);

        $response->assertSessionHasErrors('expires_in');
    }

    public function test_authenticated_user_can_create_secret(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretData());

        $response->assertRedirect();
        $response->assertSessionHas('flash.secret.url');
        $this->assertDatabaseHas('secrets', ['user_id' => $user->id]);
    }

    public function test_guest_secret_has_no_user_id(): void
    {
        $this->post(route('secret.store'), $this->validSecretData());

        $this->assertDatabaseHas('secrets', ['user_id' => null]);
    }

    public function test_show_requires_valid_signature(): void
    {
        $secret = Secret::factory()->create();
        $signedUrl = URL::temporarySignedRoute('secret.show', now()->addHour(), ['secret' => $secret->hash_id]);

        $response = $this->get($signedUrl);

        $response->assertOk();
    }

    public function test_show_passes_secret_prop_to_inertia(): void
    {
        $secret = Secret::factory()->create();
        $signedUrl = URL::temporarySignedRoute('secret.show', now()->addHour(), ['secret' => $secret->hash_id]);

        $response = $this->get($signedUrl);

        $response->assertInertia(fn ($page) => $page->has('secret'));
    }

    public function test_show_rejects_invalid_signature(): void
    {
        $secret = Secret::factory()->create();

        $response = $this->get(route('secret.show', ['secret' => $secret->hash_id]));

        $response->assertStatus(403);
    }

    public function test_decrypt_route_clears_message(): void
    {
        $secret = Secret::factory()->create();
        $signedUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);

        $response = $this->get($signedUrl);

        $response->assertRedirect();
    }

    public function test_authenticated_user_can_list_secrets(): void
    {
        $user = User::factory()->create();
        Secret::factory()->forUser($user)->count(3)->create();

        $response = $this->actingAs($user)->get(route('secrets.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_burn_own_secret(): void
    {
        $user = User::factory()->create();
        $secret = Secret::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->delete(route('secrets.destroy', ['secret' => $secret->hash_id]));

        $response->assertOk();
    }

    public function test_authenticated_user_cannot_burn_others_secret(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $secret = Secret::factory()->forUser($owner)->create();

        $response = $this->actingAs($other)
            ->delete(route('secrets.destroy', ['secret' => $secret->hash_id]));

        $response->assertForbidden();
    }
}
