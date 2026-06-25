<?php

use App\Models\Locker;
use App\Models\LockerCredit;
use App\Models\User;

/**
 * Creates a LockerCredit and returns it so the test can access its token.
 * Credits are token-scoped — use $credit->token to navigate to /lockers/create?token=...
 *
 * @param  array<string, mixed>  $overrides
 */
function createLockerCredit(array $overrides = []): LockerCredit
{
    return LockerCredit::factory()->create(array_merge([
        'tier' => 'text',
        'years' => 1,
    ], $overrides));
}

/**
 * Creates a legacy (HMAC/non-ECDSA) locker directly in the database, bypassing the UI.
 * Used for testing upgrade flows — the UI always creates ECDSA lockers.
 *
 * The payload is prefixed with '01546' (legacy format marker) and padded with zeroes
 * to match the format expected by the legacy locker reader.
 *
 * @param  User  $user  The owning user (currently unused by the Locker model — lockers are account-ID-scoped)
 * @param  string  $passphrase  The passphrase to associate (stored as auth_verifier placeholder)
 */
function createLegacyLockerViaDB(User $user, string $passphrase = 'test-passphrase'): Locker
{
    return Locker::create([
        'account_id' => fake()->numerify('##########'),
        'payload' => '01546'.bin2hex(random_bytes(20)).str_repeat('0', 40),
        'auth_challenge' => str_repeat('c', 64),
        'auth_verifier' => str_repeat('a', 64),
        'update_token_hash' => hash('sha256', 'legacytoken'),
        'expires_at' => now()->addYear(),
    ]);
}

/**
 * Returns the path to a temporary file on disk containing the "alpha" key fixture.
 * The content is a deterministic binary blob that uniquely identifies this key in tests.
 *
 * Callers MUST unlink the returned path in afterEach:
 *   afterEach(fn () => @unlink($keyPath));
 *
 * @return string Absolute path to the temp file
 */
function keyFileAlpha(): string
{
    $path = tempnam(sys_get_temp_dir(), 'e2e_key_');
    file_put_contents($path, 'e2e-key-file-alpha-content-unique-v1');

    return $path;
}

/**
 * Returns the path to a temporary file on disk containing the "beta" key fixture.
 * Use a different key fixture from keyFileAlpha() in multi-key-file tests.
 *
 * Callers MUST unlink the returned path in afterEach:
 *   afterEach(fn () => @unlink($keyPath));
 *
 * @return string Absolute path to the temp file
 */
function keyFileBeta(): string
{
    $path = tempnam(sys_get_temp_dir(), 'e2e_key_');
    file_put_contents($path, 'e2e-key-file-beta-content-unique-v1');

    return $path;
}

/**
 * Returns the path to a temporary file on disk containing obviously wrong/invalid key content.
 * Use this in tests that verify key-file authentication rejection.
 *
 * Callers MUST unlink the returned path in afterEach:
 *   afterEach(fn () => @unlink($keyPath));
 *
 * @return string Absolute path to the temp file
 */
function keyFileWrong(): string
{
    $path = tempnam(sys_get_temp_dir(), 'e2e_key_');
    file_put_contents($path, 'e2e-key-file-wrong-content-should-fail');

    return $path;
}
