<?php

namespace Tests\Feature;

use App\Jobs\ClearExpiredSecrets;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecretFileTest extends TestCase
{
    use RefreshDatabase;

    // --- Unit: Secret model helpers ---

    public function test_is_file_secret_returns_true_when_filepath_set(): void
    {
        $secret = Secret::factory()->fileSecret()->make();

        $this->assertTrue($secret->isFileSecret());
    }

    public function test_is_file_secret_returns_false_for_text_secret(): void
    {
        $secret = Secret::factory()->make();

        $this->assertFalse($secret->isFileSecret());
    }

    public function test_delete_file_removes_file_from_storage(): void
    {
        Storage::fake();

        Storage::put('secrets/test.bin', 'fake-encrypted-content');

        $secret = Secret::factory()->fileSecret('secrets/test.bin')->create();

        $this->assertTrue(Storage::exists('secrets/test.bin'));

        $secret->deleteFile();

        $this->assertFalse(Storage::exists('secrets/test.bin'));
    }

    public function test_delete_file_is_safe_when_file_does_not_exist(): void
    {
        Storage::fake();

        $secret = Secret::factory()->fileSecret('secrets/nonexistent.bin')->create();

        // Should not throw
        $secret->deleteFile();

        $this->assertTrue(true);
    }

    // --- ActiveScope fix: file secrets are visible ---

    public function test_active_scope_includes_file_secrets(): void
    {
        $user = User::factory()->create();

        Storage::fake();
        Storage::put('secrets/file.bin', 'content');

        $fileSecret = Secret::factory()->fileSecret('secrets/file.bin')->forUser($user)->create();
        $textSecret = Secret::factory()->forUser($user)->create();

        $secrets = Secret::where('user_id', $user->id)->get();

        $this->assertCount(2, $secrets);
        $ids = $secrets->pluck('id')->toArray();
        $this->assertContains($fileSecret->id, $ids);
        $this->assertContains($textSecret->id, $ids);
    }

    public function test_file_secret_show_page_is_accessible(): void
    {
        Storage::fake();
        Storage::put('secrets/file.bin', 'content');

        $secret = Secret::factory()->fileSecret('secrets/file.bin')->create();

        $showUrl = URL::temporarySignedRoute('secret.show', now()->addHour(), ['secret' => $secret->hash_id]);

        $response = $this->get($showUrl);

        $response->assertStatus(200);
    }

    // --- Guest cannot upload files ---

    public function test_guest_cannot_upload_file(): void
    {
        Storage::fake();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->post(route('secret.store'), [
            'file' => $file,
            'file_original_name' => 'encrypted-filename',
            'file_size' => 102400,
            'file_mime_type' => 'application/pdf',
            'expires_in' => 60,
        ]);

        $response->assertStatus(403);
    }

    // --- Authenticated user can upload encrypted file ---

    public function test_authenticated_user_can_upload_encrypted_file(): void
    {
        Storage::fake();

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('encrypted.bin', 100, 'application/octet-stream');

        $response = $this->actingAs($user)->post(route('secret.store'), [
            'file' => $file,
            'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
            'file_size' => 102400,
            'file_mime_type' => 'application/pdf',
            'expires_in' => 60,
        ]);

        $response->assertSessionHas('flash.secret.url');
        $response->assertSessionHas('flash.secret.is_file', true);

        $secret = Secret::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $this->assertNotNull($secret);
        $this->assertNotNull($secret->filepath);
        $this->assertNull($secret->message);
        $this->assertEquals(102400, $secret->file_size);
        $this->assertEquals('application/pdf', $secret->file_mime_type);

        Storage::assertExists($secret->filepath);
    }

    // --- File deleted from storage after download ---

    public function test_file_is_marked_retrieved_after_download(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        Storage::put('secrets/file.bin', 'fake-encrypted-content');

        $secret = Secret::factory()->fileSecret('secrets/file.bin')->forUser($user)->create();

        $downloadUrl = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);

        // S3 path: redirect to presigned URL; local disk may redirect too via Storage::fake
        $response = $this->get($downloadUrl);
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $refreshed = Secret::withoutGlobalScopes()->find($secret->id);
        $this->assertNotNull($refreshed->retrieved_at);
    }

    public function test_file_is_deleted_from_storage_on_local_disk_fallback(): void
    {
        Storage::fake();

        Storage::shouldReceive('temporaryUrl')
            ->once()
            ->andThrow(new \RuntimeException('Local driver does not support temporary URLs.'));

        Storage::shouldReceive('get')->with('secrets/file.bin')->andReturn('fake-encrypted-content');
        Storage::shouldReceive('delete')->with('secrets/file.bin')->once();

        $user = User::factory()->create();

        $secret = Secret::factory()->fileSecret('secrets/file.bin')->forUser($user)->create();

        $downloadUrl = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);

        $this->get($downloadUrl)->assertOk();

        $refreshed = Secret::withoutGlobalScopes()->find($secret->id);
        $this->assertNull($refreshed->filepath);
        $this->assertNotNull($refreshed->retrieved_at);
    }

    // --- Double download returns 410 ---

    public function test_second_download_attempt_returns_410(): void
    {
        Storage::fake();

        Storage::put('secrets/file.bin', 'fake-content');

        $secret = Secret::factory()->fileSecret('secrets/file.bin')->create();

        $downloadUrl = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);

        // First attempt marks retrieved (redirect or stream)
        $this->get($downloadUrl);
        // Second attempt should return 410
        $this->get($downloadUrl)->assertStatus(410);
    }

    // --- ClearExpiredSecrets deletes files ---

    public function test_clear_expired_secrets_deletes_file_from_storage(): void
    {
        Storage::fake();

        Storage::put('secrets/old.bin', 'old-encrypted-content');

        Secret::factory()
            ->fileSecret('secrets/old.bin')
            ->expired()
            ->create();

        (new ClearExpiredSecrets)->handle();

        $this->assertFalse(Storage::exists('secrets/old.bin'));

        $secret = Secret::withoutGlobalScopes()->first();
        $this->assertNull($secret->filepath);
        $this->assertNull($secret->filename);
    }

    // --- File size validation ---

    public function test_file_exceeding_size_limit_is_rejected(): void
    {
        Storage::fake();

        $user = User::factory()->create();

        // Create a fake file that is larger than config allows (default 10MB for user)
        $oversizedFile = UploadedFile::fake()->create('big.bin', 11 * 1024, 'application/octet-stream');

        $response = $this->actingAs($user)->post(route('secret.store'), [
            'file' => $oversizedFile,
            'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
            'file_size' => 11 * 1024 * 1024,
            'file_mime_type' => 'application/pdf',
            'expires_in' => 60,
        ]);

        $response->assertSessionHasErrors('file');
    }

    // --- Unsupported MIME type is rejected ---

    public function test_disallowed_mime_type_is_rejected(): void
    {
        Storage::fake();

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)->post(route('secret.store'), [
            'file' => $file,
            'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
            'file_size' => 102400,
            'file_mime_type' => 'application/x-msdownload',
            'expires_in' => 60,
        ]);

        $response->assertSessionHasErrors('file_mime_type');
    }

    // --- Existing text secret store still works ---

    public function test_text_secret_store_still_works(): void
    {
        $message = (new SecretFactory)->generateEncryptedMessage(50);

        $response = $this->post(route('secret.store'), [
            'message' => $message,
            'expires_in' => 5,
        ]);

        $response->assertSessionHas('flash.secret.url');
    }

    // --- Decrypt endpoint returns file metadata for file secrets ---

    public function test_decrypt_endpoint_returns_file_metadata_for_file_secret(): void
    {
        Storage::fake();
        Storage::put('secrets/file.bin', 'content');

        $secret = Secret::factory()->fileSecret('secrets/file.bin')->create();

        $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);

        $response = $this->get($decryptUrl);
        $response->assertRedirect();
        $response->assertSessionHas('flash.secret.is_file', true);
        $response->assertSessionHas('flash.secret.file_download_url');
    }

    // --- readyToPrune excludes live file secrets ---

    public function test_ready_to_prune_excludes_active_file_secrets(): void
    {
        Storage::fake();
        Storage::put('secrets/live.bin', 'content');

        Secret::factory()->fileSecret('secrets/live.bin')->create([
            'expires_at' => now()->subDays(config('secrets.prune_after') + 1),
        ]);

        $prunable = Secret::withoutGlobalScopes()->readyToPrune()->get();
        $this->assertCount(0, $prunable);
    }
}
