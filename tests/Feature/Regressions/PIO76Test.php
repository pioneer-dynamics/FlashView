<?php

use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('destroyed secret state component exists with both reasons', function () {
    $path = resource_path('js/Components/DestroyedSecretState.vue');
    expect($path)->toBeFile('DestroyedSecretState.vue must exist as the unified destroyed-state surface.');

    $contents = file_get_contents($path);

    expect($contents)->toMatch('/Wrong password\W+this secret has been permanently destroyed/u', 'DestroyedSecretState.vue must show the "Wrong password — this secret has been permanently destroyed" headline for reason="wrong-password".');

    $this->assertStringContainsString(
        'This secret is no longer available.',
        $contents,
        'DestroyedSecretState.vue must show the "This secret is no longer available." headline for reason="unavailable".'
    );

    $this->assertStringContainsString(
        'This link is no longer valid. It may have expired, or it has already been opened.',
        $contents,
        'DestroyedSecretState.vue unavailable body must use privacy-safe phrasing (no confirmation of retrieval).'
    );

    $this->assertStringContainsString(
        'Return to FlashView',
        $contents,
        'DestroyedSecretState.vue CTA must be "Return to FlashView" — a pure navigation affordance.'
    );

    expect($contents)->toMatch("/'wrong-password'\\s*\\|\\s*'unavailable'/", 'DestroyedSecretState.vue must declare a "reason" prop typed as \'wrong-password\' | \'unavailable\'.');
});

test('secret form uses local state and imports destroyed component', function () {
    $contents = file_get_contents(resource_path('js/Pages/Secret/SecretViewForm.vue'));

    $this->assertStringContainsString(
        "import DestroyedSecretState from '@/Components/DestroyedSecretState.vue'",
        $contents,
        'SecretViewForm.vue must import DestroyedSecretState.'
    );

    expect($contents)->toMatch('/v-if="[^"]*decryptionFailed[^"]*"[^>]*>\s*<[\w-]+[^>]*>\s*<DestroyedSecretState/s', 'SecretViewForm.vue must render DestroyedSecretState under a top-level v-if on decryptionFailed (single edit point).');

    expect($contents)->toMatch('/<DestroyedSecretState[^>]*:reason="decryptionFailureReason"/', 'SecretViewForm.vue must bind DestroyedSecretState :reason="decryptionFailureReason" so wrong-password and unavailable paths render the correct copy.');

    $this->assertStringContainsString(
        'decryptionFailed',
        $contents,
        'SecretViewForm.vue must declare a decryptionFailed reactive ref.'
    );

    $this->assertStringContainsString(
        'decryptionFailureReason',
        $contents,
        'SecretViewForm.vue must declare a decryptionFailureReason reactive ref to differentiate wrong-password from unavailable.'
    );

    expect($contents)->toMatch("/handleDecryptionFailure\\(\\s*['\"]wrong-password['\"]\\s*\\)/", 'SecretViewForm.vue must call handleDecryptionFailure("wrong-password") on decryption-promise rejections.');

    expect($contents)->toMatch("/handleDecryptionFailure\\(\\s*['\"]unavailable['\"]\\s*\\)/", 'SecretViewForm.vue must call handleDecryptionFailure("unavailable") on Inertia onError / missing-flash paths so expired-link users do not see "Wrong password" copy.');
});

test('combined flow sets form message from decrypted data', function () {
    $contents = file_get_contents(resource_path('js/Pages/Secret/SecretViewForm.vue'));

    // Both the text-only branch and the combined (message+file) branch must
    // set decryptedMessage.value = data directly on decryptMessage resolution.
    expect(substr_count($contents, 'decryptedMessage.value = data'))->toBeGreaterThanOrEqual(2, 'SecretViewForm.vue must assign decryptedMessage.value = data inside BOTH the combined (message+file) and text-only decryptMessage .then callbacks — otherwise the combined "Note from sender" shows the placeholder default on a correct password.');

    // The combined branch must NOT reintroduce a deferred-reveal coordinator.
    $this->assertStringNotContainsString(
        'combinedFlow.decryptedMessage',
        $contents,
        'SecretViewForm.vue must not use a combinedFlow.decryptedMessage coordinator — a previous iteration of the fix left the message as the placeholder because the coordinator never revealed the decrypted data.'
    );

    // Sanity-check: the combined branch exists and still reaches decryptMessage.
    expect($contents)->toMatch('/props\.isFileSecret\s*&&\s*props\.hasMessage/', 'SecretViewForm.vue must still contain an explicit combined-secret (file + message) branch.');
});

test('file decrypt panel emits failure with reason', function () {
    $contents = file_get_contents(resource_path('js/Components/FileDecryptPanel.vue'));

    expect($contents)->toMatch('/defineEmits\s*[(<][\s\S]*?failure/s', 'FileDecryptPanel.vue must declare a "failure" emit.');

    expect($contents)->toMatch("/emit\\(\\s*['\"]failure['\"]\\s*,\\s*['\"]wrong-password['\"]\\s*\\)/", 'FileDecryptPanel.vue must emit "failure" with reason="wrong-password" when decrypt of the downloaded ciphertext rejects.');

    expect($contents)->toMatch("/emit\\(\\s*['\"]failure['\"]\\s*,\\s*['\"]unavailable['\"]\\s*\\)/", 'FileDecryptPanel.vue must emit "failure" with reason="unavailable" when the initial retrieve fails or no download URL is present.');
});

test('cli uses aligned copy via shared helper', function () {
    $contents = file_get_contents(base_path('tools/flashview-cli/src/cli.js'));

    $this->assertStringContainsString(
        'function printDestroyedSecretError()',
        $contents,
        'flashview-cli must DRY the three wrong-password branches into a shared printDestroyedSecretError() helper.'
    );

    expect($contents)->toMatch('/Wrong password\W+this secret has been permanently destroyed\./u', 'flashview-cli must use the aligned "Wrong password — this secret has been permanently destroyed." headline.');

    expect($contents)->toMatch('/Secrets self-destruct after one retrieval attempt\W+even on a wrong password\./u', 'flashview-cli must include the aligned explanatory sentence "Secrets self-destruct after one retrieval attempt — even on a wrong password."');

    $this->assertStringContainsString(
        'Ask the person who sent you this link to create a new one.',
        $contents,
        'flashview-cli must tell the recipient to ask the sender for a new link (web/CLI parity).'
    );

    $this->assertStringNotContainsString(
        'Decryption failed. The password may be incorrect.',
        $contents,
        'flashview-cli must not contain the old "Decryption failed" copy on any wrong-password branch.'
    );
    $this->assertStringNotContainsString(
        'Note decryption failed. The password may be incorrect.',
        $contents,
        'flashview-cli must not contain the old "Note decryption failed" copy on the combined-secret note branch.'
    );

    expect(substr_count($contents, 'printDestroyedSecretError()'))->toBeGreaterThanOrEqual(3, 'printDestroyedSecretError() must be invoked at each of the three CLI wrong-password catch sites.');

    $this->assertStringContainsString(
        'This link is no longer valid. It may have expired, or it has already been opened.',
        $contents,
        'flashview-cli must align the unavailable-path copy with the web (privacy-safe phrasing, no confirmation of retrieval).'
    );
    $this->assertStringNotContainsString(
        'This message has expired or has already been retrieved.',
        $contents,
        'flashview-cli must not retain the old unavailable copy that confirms retrieval occurred (privacy oracle).'
    );
    $this->assertStringNotContainsString(
        'The file has already been retrieved or has expired.',
        $contents,
        'flashview-cli must not retain the old file-download unavailable copy that confirms retrieval occurred (privacy oracle).'
    );
});

test('file upload surfaces short passphrase error on password field', function () {
    $encryptionContents = file_get_contents(resource_path('js/encryption.js'));

    expect($encryptionContents)->toMatch('/async\s+encryptFile\s*\([^)]*\)\s*\{\s*this\.validatePassphrase\(/s', 'encryption.js encryptFile() must call this.validatePassphrase() so a short passphrase is rejected before upload (parity with encryptMessage()).');

    $formContents = file_get_contents(resource_path('js/Pages/Secret/SecretCreateForm.vue'));

    expect($formContents)->toMatch('/encryptFileData\s*=\s*async[\s\S]*?\.validatePassphrase\(\s*passphrase\s*\)[\s\S]*?other\.setError\(\s*[\'"]password[\'"]\s*,[\s\S]*?\.message\s*\)/s', 'SecretCreateForm.vue encryptFileData() must validate the passphrase upfront and route the error to other.errors.password so the "Passphrase must be at least 8 characters" message renders under the password input when a file is attached.');
});

test('clearing password field clears stale passphrase error', function () {
    $contents = file_get_contents(resource_path('js/Pages/Secret/SecretCreateForm.vue'));

    expect($contents)->toMatch('/watch\(\s*\(\)\s*=>\s*other\.password[\s\S]*?other\.clearErrors\(\s*[\'"]password[\'"]\s*\)/s', 'SecretCreateForm.vue must watch other.password and call other.clearErrors("password") when the value becomes falsy — so the "Passphrase must be at least 8 characters" error disappears once the user clears the field (empty password triggers auto-generation which bypasses the min-length check).');
});

test('burned secret cannot be decrypted again', function () {
    // Use markAsRetrieved() directly to simulate a real retrieval. The
    // Secret::retrieved model event short-circuits under PHPUnit
    // (App::runningInConsole()), so hitting the decrypt route in a test
    // does NOT null the message. Calling the public method directly is
    // the faithful equivalent.
    $secret = Secret::factory()->create();
    $secret->markAsRetrieved();

    $signedUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);
    $response = $this->get($signedUrl);

    $response->assertStatus(404);
});
