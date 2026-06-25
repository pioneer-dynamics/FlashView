<?php

use App\Jobs\ClearExpiredSecrets;
use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->free()->withFileUpload(10)->create();
});

test('is file secret returns true when filepath set', function () {
    $secret = Secret::factory()->fileSecret()->make();

    expect($secret->isFileSecret())->toBeTrue();
});

test('is file secret returns false for text secret', function () {
    $secret = Secret::factory()->make();

    expect($secret->isFileSecret())->toBeFalse();
});

test('delete file removes file from storage', function () {
    Storage::fake();

    Storage::put('secrets/test.bin', 'fake-encrypted-content');

    $secret = Secret::factory()->fileSecret('secrets/test.bin')->create();

    expect(Storage::exists('secrets/test.bin'))->toBeTrue();

    $secret->deleteFile();

    expect(Storage::exists('secrets/test.bin'))->toBeFalse();
});

test('delete file is safe when file does not exist', function () {
    Storage::fake();

    $secret = Secret::factory()->fileSecret('secrets/nonexistent.bin')->create();

    // Should not throw
    $secret->deleteFile();

    expect(true)->toBeTrue();
});

test('active scope includes file secrets', function () {
    $user = User::factory()->create();

    Storage::fake();
    Storage::put('secrets/file.bin', 'content');

    $fileSecret = Secret::factory()->fileSecret('secrets/file.bin')->forUser($user)->create();
    $textSecret = Secret::factory()->forUser($user)->create();

    $secrets = Secret::where('user_id', $user->id)->get();

    expect($secrets)->toHaveCount(2);
    $ids = $secrets->pluck('id')->toArray();
    expect($ids)->toContain($fileSecret->id);
    expect($ids)->toContain($textSecret->id);
});

test('file secret show page is accessible', function () {
    Storage::fake();
    Storage::put('secrets/file.bin', 'content');

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->create();

    $showUrl = URL::temporarySignedRoute('secret.show', now()->addHour(), ['secret' => $secret->hash_id]);

    $response = $this->get($showUrl);

    $response->assertStatus(200);
});

test('guest cannot upload file', function () {
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
});

test('authenticated user can upload encrypted file', function () {
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
    expect($secret)->not->toBeNull();
    expect($secret->filepath)->not->toBeNull();
    expect($secret->message)->toBeNull();
    expect($secret->file_size)->toEqual(102400);
    expect($secret->file_mime_type)->toEqual('application/pdf');

    Storage::assertExists($secret->filepath);
});

test('prepare caches token with filepath for authenticated user', function () {
    Storage::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('secret.file.prepare'));

    $response->assertOk()
        ->assertJsonStructure(['upload_type', 'upload_url', 'upload_headers', 'token']);

    $pending = Cache::get('pending_file_upload:'.$response->json('token'));
    expect($pending)->not->toBeNull();
    expect($pending['user_id'])->toEqual($user->id);
    expect($pending['filepath'])->toStartWith('secrets/');
});

test('store with file token creates secret using pre uploaded file', function () {
    Storage::fake();

    $user = User::factory()->create();
    $filepath = 'secrets/'.Str::uuid().'.bin';
    $token = Str::uuid()->toString();

    Cache::put("pending_file_upload:{$token}", [
        'filepath' => $filepath,
        'user_id' => $user->id,
    ], now()->addMinutes(30));

    Storage::put($filepath, 'fake-encrypted-content');

    $response = $this->actingAs($user)->post(route('secret.store'), [
        'file_token' => $token,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 512,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ]);

    $response->assertSessionHas('flash.secret.url');
    $response->assertSessionHas('flash.secret.is_file', true);

    $secret = Secret::withoutGlobalScopes()->where('user_id', $user->id)->first();
    expect($secret->filepath)->not->toBeNull();
    expect($secret->filepath)->toEqual($filepath);
});

test('file token cannot be reused', function () {
    Storage::fake();

    $user = User::factory()->create();
    $filepath = 'secrets/'.Str::uuid().'.bin';
    $token = Str::uuid()->toString();

    Cache::put("pending_file_upload:{$token}", [
        'filepath' => $filepath,
        'user_id' => $user->id,
    ], now()->addMinutes(30));

    Storage::put($filepath, 'fake-content');

    $payload = [
        'file_token' => $token,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 512,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ];

    // First use succeeds.
    $this->actingAs($user)->post(route('secret.store'), $payload)
        ->assertSessionHas('flash.secret.url');

    // Token consumed — second use returns an error.
    $this->actingAs($user)->post(route('secret.store'), $payload)
        ->assertSessionHasErrors('file_token');
});

test('file token from different user is rejected', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $filepath = 'secrets/'.Str::uuid().'.bin';
    $token = Str::uuid()->toString();

    Cache::put("pending_file_upload:{$token}", [
        'filepath' => $filepath,
        'user_id' => $owner->id,
    ], now()->addMinutes(30));

    Storage::put($filepath, 'fake-content');

    $this->actingAs($attacker)->post(route('secret.store'), [
        'file_token' => $token,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 512,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ])->assertSessionHasErrors('file_token');
});

test('file is marked retrieved after download', function () {
    Storage::fake();

    $user = User::factory()->create();
    Storage::put('secrets/file.bin', 'fake-encrypted-content');

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->forUser($user)->create();

    $downloadUrl = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    // S3 path: redirect to presigned URL; local disk may redirect too via Storage::fake
    $response = $this->get($downloadUrl);
    expect([200, 302])->toContain($response->getStatusCode());

    $refreshed = Secret::withoutGlobalScopes()->find($secret->id);
    expect($refreshed->retrieved_at)->not->toBeNull();
});

test('file is deleted from storage on local disk fallback', function () {
    Storage::fake();

    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->andThrow(new RuntimeException('Local driver does not support temporary URLs.'));

    Storage::shouldReceive('get')->with('secrets/file.bin')->andReturn('fake-encrypted-content');
    Storage::shouldReceive('delete')->with('secrets/file.bin')->once();

    $user = User::factory()->create();

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->forUser($user)->create();

    $downloadUrl = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    $this->get($downloadUrl)->assertOk();

    $refreshed = Secret::withoutGlobalScopes()->find($secret->id);
    expect($refreshed->filepath)->toBeNull();
    expect($refreshed->retrieved_at)->not->toBeNull();
});

test('second download attempt returns 410', function () {
    Storage::fake();

    Storage::put('secrets/file.bin', 'fake-content');

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->create();

    $downloadUrl = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    // First attempt marks retrieved (redirect or stream)
    $this->get($downloadUrl);

    // Second attempt should return 410
    $this->get($downloadUrl)->assertStatus(410);
});

test('clear expired secrets deletes file from storage', function () {
    Storage::fake();

    Storage::put('secrets/old.bin', 'old-encrypted-content');

    Secret::factory()
        ->fileSecret('secrets/old.bin')
        ->expired()
        ->create();

    (new ClearExpiredSecrets)->handle();

    expect(Storage::exists('secrets/old.bin'))->toBeFalse();

    $secret = Secret::withoutGlobalScopes()->first();
    expect($secret->filepath)->toBeNull();
    expect($secret->filename)->toBeNull();
});

test('file exceeding size limit is rejected', function () {
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
});

test('disallowed mime type is rejected', function () {
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
});

test('text secret store still works', function () {
    $message = (new SecretFactory)->generateEncryptedMessage(50);

    $response = $this->post(route('secret.store'), [
        'message' => $message,
        'expires_in' => 5,
    ]);

    $response->assertSessionHas('flash.secret.url');
});

test('decrypt endpoint returns file metadata for file secret', function () {
    Storage::fake();
    Storage::put('secrets/file.bin', 'content');

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->create();

    $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    $response = $this->get($decryptUrl);
    $response->assertRedirect();
    $response->assertSessionHas('flash.secret.is_file', true);
    $response->assertSessionHas('flash.secret.file_download_url');
});

test('authenticated user can create combined message and file secret', function () {
    Storage::fake();

    $user = User::factory()->create();
    $message = (new SecretFactory)->generateEncryptedMessage(50);
    $file = UploadedFile::fake()->create('encrypted.bin', 100, 'application/octet-stream');

    $response = $this->actingAs($user)->post(route('secret.store'), [
        'message' => $message,
        'file' => $file,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 102400,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ]);

    $response->assertSessionHas('flash.secret.url');

    $secret = Secret::withoutGlobalScopes()->where('user_id', $user->id)->first();
    expect($secret->message)->not->toBeNull();
    expect($secret->filepath)->not->toBeNull();
    Storage::assertExists($secret->filepath);
});

test('decrypt endpoint returns both message and file metadata for combined secret', function () {
    Storage::fake();
    Storage::put('secrets/file.bin', 'content');

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->create(['message' => 'encrypted-note']);

    $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    $response = $this->get($decryptUrl);
    $response->assertRedirect();
    $response->assertSessionHas('flash.secret.is_file', true);
    $response->assertSessionHas('flash.secret.file_download_url');
    $response->assertSessionHas('flash.secret.message', 'encrypted-note');

    // Message should be nulled in DB after decrypt
    $refreshed = Secret::withoutGlobalScopes()->find($secret->id);
    expect($refreshed->message)->toBeNull();

    // File should still exist (not consumed until download)
    expect($refreshed->filepath)->not->toBeNull();
});

test('show page passes has message true for combined secret', function () {
    Storage::fake();
    Storage::put('secrets/file.bin', 'content');

    $secret = Secret::factory()->fileSecret('secrets/file.bin')->create(['message' => 'encrypted-note']);

    $showUrl = URL::temporarySignedRoute('secret.show', now()->addMinutes(5), ['secret' => $secret->hash_id]);

    $response = $this->get($showUrl);
    $response->assertInertia(fn ($page) => $page
        ->has('isFileSecret')
        ->where('isFileSecret', true)
        ->has('hasMessage')
        ->where('hasMessage', true)
    );
});

test('ready to prune excludes active file secrets', function () {
    Storage::fake();
    Storage::put('secrets/live.bin', 'content');

    Secret::factory()->fileSecret('secrets/live.bin')->create([
        'expires_at' => now()->subDays(config('secrets.prune_after') + 1),
    ]);

    $prunable = Secret::withoutGlobalScopes()->readyToPrune()->get();
    expect($prunable)->toHaveCount(0);
});
