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
 * copy, and a DestroyedSecretState.vue component consumed by SecretForm.vue.
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
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretForm.vue'));

        $this->assertStringContainsString(
            "import DestroyedSecretState from '@/Components/DestroyedSecretState.vue'",
            $contents,
            'SecretForm.vue must import DestroyedSecretState.'
        );

        $this->assertMatchesRegularExpression(
            '/v-if="[^"]*decryptionFailed[^"]*"[^>]*>\s*<[\w-]+[^>]*>\s*<DestroyedSecretState/s',
            $contents,
            'SecretForm.vue must render DestroyedSecretState under a top-level v-if on decryptionFailed (single edit point).'
        );

        $this->assertMatchesRegularExpression(
            '/<DestroyedSecretState[^>]*:reason="decryptionFailureReason"/',
            $contents,
            'SecretForm.vue must bind DestroyedSecretState :reason="decryptionFailureReason" so wrong-password and unavailable paths render the correct copy.'
        );

        $this->assertStringContainsString(
            'decryptionFailed',
            $contents,
            'SecretForm.vue must declare a decryptionFailed reactive ref.'
        );

        $this->assertStringContainsString(
            'decryptionFailureReason',
            $contents,
            'SecretForm.vue must declare a decryptionFailureReason reactive ref to differentiate wrong-password from unavailable.'
        );

        $this->assertMatchesRegularExpression(
            "/handleDecryptionFailure\\(\\s*['\"]wrong-password['\"]\\s*\\)/",
            $contents,
            'SecretForm.vue must call handleDecryptionFailure("wrong-password") on decryption-promise rejections.'
        );

        $this->assertMatchesRegularExpression(
            "/handleDecryptionFailure\\(\\s*['\"]unavailable['\"]\\s*\\)/",
            $contents,
            'SecretForm.vue must call handleDecryptionFailure("unavailable") on Inertia onError / missing-flash paths so expired-link users do not see "Wrong password" copy.'
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
