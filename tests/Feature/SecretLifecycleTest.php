<?php

namespace Tests\Feature;

use App\Jobs\ClearExpiredSecrets;
use App\Jobs\PurgeMetadataForExpiredMessages;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecretLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_create_and_retrieve(): void
    {
        $message = (new SecretFactory)->generateEncryptedMessage(50);

        $response = $this->post(route('secret.store'), [
            'message' => $message,
            'expires_in' => 5,
        ]);

        $response->assertSessionHas('flash.secret.url');

        $secret = Secret::first();
        $this->assertNotNull($secret);
        $this->assertNotNull($secret->message);

        // Retrieve via decrypt route — returns the message as flash data
        $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);
        $response = $this->get($decryptUrl);
        $response->assertRedirect();

        // Note: The Secret::retrieved event skips in console (App::runningInConsole()),
        // so message is NOT auto-cleared during tests. Test the clearing via explicit job instead.
    }

    public function test_manual_retrieval_clears_message(): void
    {
        $secret = Secret::factory()->create();
        $this->assertNotNull($secret->message);

        $secret->markAsRetrieved();

        $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
        $this->assertNull($secret->message);
        $this->assertNotNull($secret->retrieved_at);
    }

    public function test_expiry_lifecycle_create_expire_clear_prune(): void
    {
        $secret = Secret::factory()->create([
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertNotNull($secret->message);

        $this->travel(10)->minutes();

        (new ClearExpiredSecrets)->handle();

        $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
        $this->assertNull($secret->message);
        $this->assertNotNull($secret);

        $this->travel(config('secrets.prune_after') + 1)->days();

        (new PurgeMetadataForExpiredMessages)->handle();

        $this->assertNull(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id));
    }

    public function test_guest_secret_lifecycle_no_notifications(): void
    {
        Notification::fake();

        $message = (new SecretFactory)->generateEncryptedMessage(50);
        $this->post(route('secret.store'), [
            'message' => $message,
            'expires_in' => 5,
        ]);

        $secret = Secret::first();
        $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);
        $this->get($decryptUrl);

        Notification::assertNothingSent();
    }
}
