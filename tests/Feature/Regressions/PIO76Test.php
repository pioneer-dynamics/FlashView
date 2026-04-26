<?php

namespace Tests\Feature\Regressions;

use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * PIO-76: On the decrypt screen, a wrong-password error was replaced almost
 * instantly by the generic retrieve-message screen, so the user could not read
 * it. The fix introduces a unified, persistent "destroyed" state driven by
 * local Vue refs (decryptionFailed, decryptionFailureReason), aligned CLI
 * copy, and a DestroyedSecretState.vue component consumed by SecretViewForm.vue.
 *
 * After PIO-78 the decrypt flow lives in SecretViewForm.vue and the encrypt
 * flow lives in SecretCreateForm.vue.
 *
 * Note: the shipping user-facing copy uses a U+2014 em dash between "password"
 * and "this secret". The regex assertions below deliberately use \W+ between
 * words so a future hyphen-normalisation (or smart-quote tooling) does not
 * silently break this test with no obvious cause.
 */
class PIO76Test extends TestCase
{
    use RefreshDatabase;

    public function test_destroyed_secret_state_component_exists_with_both_reasons(): void
    {
        $path = resource_path('js/Components/DestroyedSecretState.vue');
        $this->assertFileExists(
            $path,
            'DestroyedSecretState.vue must exist as the unified destroyed-state surface.'
        );

        $contents = file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/Wrong password\W+this secret has been permanently destroyed/u',
            $contents,
            'DestroyedSecretState.vue must show the "Wrong password — this secret has been permanently destroyed" headline for reason="wrong-password".'
        );

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

        $this->assertMatchesRegularExpression(
            '/defineProps\(\s*\{[^}]*reason\s*:\s*\{[^}]*validator/s',
            $contents,
            'DestroyedSecretState.vue must declare a "reason" prop with a validator accepting "wrong-password" and "unavailable".'
        );
    }

    public function test_secret_form_uses_local_state_and_imports_destroyed_component(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretViewForm.vue'));

        $this->assertStringContainsString(
            "import DestroyedSecretState from '@/Components/DestroyedSecretState.vue'",
            $contents,
            'SecretViewForm.vue must import DestroyedSecretState.'
        );

        $this->assertMatchesRegularExpression(
            '/v-if="[^"]*decryptionFailed[^"]*"[^>]*>\s*<[\w-]+[^>]*>\s*<DestroyedSecretState/s',
            $contents,
            'SecretViewForm.vue must render DestroyedSecretState under a top-level v-if on decryptionFailed (single edit point).'
        );

        $this->assertMatchesRegularExpression(
            '/<DestroyedSecretState[^>]*:reason="decryptionFailureReason"/',
            $contents,
            'SecretViewForm.vue must bind DestroyedSecretState :reason="decryptionFailureReason" so wrong-password and unavailable paths render the correct copy.'
        );

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

        $this->assertMatchesRegularExpression(
            "/handleDecryptionFailure\\(\\s*['\"]wrong-password['\"]\\s*\\)/",
            $contents,
            'SecretViewForm.vue must call handleDecryptionFailure("wrong-password") on decryption-promise rejections.'
        );

        $this->assertMatchesRegularExpression(
            "/handleDecryptionFailure\\(\\s*['\"]unavailable['\"]\\s*\\)/",
            $contents,
            'SecretViewForm.vue must call handleDecryptionFailure("unavailable") on Inertia onError / missing-flash paths so expired-link users do not see "Wrong password" copy.'
        );
    }

    /**
     * Regression guard for a bug introduced during PIO-76 where the combined
     * (message + file) flow deferred updating the decrypted message via a
     * coordinator. On a correct password the "Note from sender" block could
     * render with the placeholder default because the message was never set.
     *
     * The combined branch MUST update decryptedMessage.value directly inside the
     * message-decryption .then callback so the rendered note always reflects
     * the decrypted content.
     *
     * After PIO-78 the decrypted message is stored in a dedicated
     * decryptedMessage ref (not form.message) in SecretViewForm.vue.
     */
    public function test_combined_flow_sets_form_message_from_decrypted_data(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretViewForm.vue'));

        // Both the text-only branch and the combined (message+file) branch must
        // set decryptedMessage.value = data directly on decryptMessage resolution.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($contents, 'decryptedMessage.value = data'),
            'SecretViewForm.vue must assign decryptedMessage.value = data inside BOTH the combined (message+file) and text-only decryptMessage .then callbacks — otherwise the combined "Note from sender" shows the placeholder default on a correct password.'
        );

        // The combined branch must NOT reintroduce a deferred-reveal coordinator.
        $this->assertStringNotContainsString(
            'combinedFlow.decryptedMessage',
            $contents,
            'SecretViewForm.vue must not use a combinedFlow.decryptedMessage coordinator — a previous iteration of the fix left the message as the placeholder because the coordinator never revealed the decrypted data.'
        );

        // Sanity-check: the combined branch exists and still reaches decryptMessage.
        $this->assertMatchesRegularExpression(
            '/props\.isFileSecret\s*&&\s*props\.hasMessage/',
            $contents,
            'SecretViewForm.vue must still contain an explicit combined-secret (file + message) branch.'
        );
    }

    public function test_file_decrypt_panel_emits_failure_with_reason(): void
    {
        $contents = file_get_contents(resource_path('js/Components/FileDecryptPanel.vue'));

        $this->assertMatchesRegularExpression(
            "/defineEmits\\(\\s*\\[[^\\]]*['\"]failure['\"][^\\]]*\\]\\s*\\)/",
            $contents,
            'FileDecryptPanel.vue must declare a "failure" emit.'
        );

        $this->assertMatchesRegularExpression(
            "/emit\\(\\s*['\"]failure['\"]\\s*,\\s*['\"]wrong-password['\"]\\s*\\)/",
            $contents,
            'FileDecryptPanel.vue must emit "failure" with reason="wrong-password" when decrypt of the downloaded ciphertext rejects.'
        );

        $this->assertMatchesRegularExpression(
            "/emit\\(\\s*['\"]failure['\"]\\s*,\\s*['\"]unavailable['\"]\\s*\\)/",
            $contents,
            'FileDecryptPanel.vue must emit "failure" with reason="unavailable" when the initial retrieve fails or no download URL is present.'
        );
    }

    public function test_cli_uses_aligned_copy_via_shared_helper(): void
    {
        $contents = file_get_contents(base_path('tools/flashview-cli/src/cli.js'));

        $this->assertStringContainsString(
            'function printDestroyedSecretError()',
            $contents,
            'flashview-cli must DRY the three wrong-password branches into a shared printDestroyedSecretError() helper.'
        );

        $this->assertMatchesRegularExpression(
            '/Wrong password\W+this secret has been permanently destroyed\./u',
            $contents,
            'flashview-cli must use the aligned "Wrong password — this secret has been permanently destroyed." headline.'
        );

        $this->assertMatchesRegularExpression(
            '/Secrets self-destruct after one retrieval attempt\W+even on a wrong password\./u',
            $contents,
            'flashview-cli must include the aligned explanatory sentence "Secrets self-destruct after one retrieval attempt — even on a wrong password."'
        );

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

        $this->assertGreaterThanOrEqual(
            3,
            substr_count($contents, 'printDestroyedSecretError()'),
            'printDestroyedSecretError() must be invoked at each of the three CLI wrong-password catch sites.'
        );

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
    }

    /**
     * Regression guard for a bug where a too-short passphrase on the create
     * screen surfaced an inline error only for text-only secrets — when a
     * file was attached the error was either swallowed into fileError or not
     * shown at all. encryptFile() must validate the passphrase (parity with
     * encryptMessage()) and encryptFileData() must route validation errors
     * to other.errors.password like the text-only flow does.
     */
    public function test_file_upload_surfaces_short_passphrase_error_on_password_field(): void
    {
        $encryptionContents = file_get_contents(resource_path('js/encryption.js'));

        $this->assertMatchesRegularExpression(
            '/async\s+encryptFile\s*\([^)]*\)\s*\{\s*this\.validatePassphrase\(/s',
            $encryptionContents,
            'encryption.js encryptFile() must call this.validatePassphrase() so a short passphrase is rejected before upload (parity with encryptMessage()).'
        );

        $formContents = file_get_contents(resource_path('js/Pages/Secret/SecretCreateForm.vue'));

        $this->assertMatchesRegularExpression(
            '/encryptFileData\s*=\s*async[\s\S]*?e\.validatePassphrase\(\s*passphrase\s*\)[\s\S]*?other\.setError\(\s*[\'"]password[\'"]\s*,\s*err\.message\s*\)/s',
            $formContents,
            'SecretCreateForm.vue encryptFileData() must validate the passphrase upfront and route the error to other.errors.password so the "Passphrase must be at least 8 characters" message renders under the password input when a file is attached.'
        );
    }

    /**
     * Regression guard: after showing the "Passphrase must be at least 8
     * characters" error, clearing the password field switches the flow into
     * auto-generate mode (which bypasses the min-length check), so the stale
     * error must be cleared. A watcher on other.password must call
     * other.clearErrors('password') when the value becomes falsy.
     */
    public function test_clearing_password_field_clears_stale_passphrase_error(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretCreateForm.vue'));

        $this->assertMatchesRegularExpression(
            '/watch\(\s*\(\)\s*=>\s*other\.password[\s\S]*?other\.clearErrors\(\s*[\'"]password[\'"]\s*\)/s',
            $contents,
            'SecretCreateForm.vue must watch other.password and call other.clearErrors("password") when the value becomes falsy — so the "Passphrase must be at least 8 characters" error disappears once the user clears the field (empty password triggers auto-generation which bypasses the min-length check).'
        );
    }

    public function test_burned_secret_cannot_be_decrypted_again(): void
    {
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
    }
}
