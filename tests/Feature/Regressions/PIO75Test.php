<?php

namespace Tests\Feature\Regressions;

use Tests\TestCase;

/**
 * PIO-75: Three UX bugs on the secret encrypt/decrypt screens.
 *
 * Bug 1: A placeholder string was stored in form.message (v-modelled state)
 * on the decrypt page, risking leakage into rendered bindings under certain
 * combinations of hasMessage / decryptionSuccess / Inertia state.
 *
 * Bug 2: On the encrypt screen, the auto-generated password was revealed the
 * instant encryption finished, while the ciphertext was still uploading. Inputs
 * and buttons also stayed interactive during in-progress operations on both
 * screens.
 *
 * Bug 3: The primary action button on the decrypt screen read "Unlock & Download"
 * instead of the specified "Download and decrypt".
 */
class PIO75Test extends TestCase
{
    public function test_placeholder_message_is_not_injected_into_form_state_on_decrypt(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretForm.vue'));

        $this->assertStringNotContainsString(
            'placeholderMessage',
            $contents,
            'SecretForm.vue must not declare a placeholderMessage const — display hints must use the HTML placeholder attribute, not v-modelled form state.'
        );

        $this->assertStringNotContainsString(
            "This isn't the actual message",
            $contents,
            'SecretForm.vue must not embed the placeholder string literal in form state — it risks leaking into rendered bindings on file-only and combined-secret decrypt pages.'
        );

        $this->assertMatchesRegularExpression(
            '/message:\s*[\'"]{2}/',
            $contents,
            'SecretForm.vue must initialise form.message to an empty string unconditionally, not to the placeholder literal.'
        );
    }

    public function test_encrypt_flow_hides_password_and_disables_inputs_while_uploading(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretForm.vue'));

        $this->assertStringContainsString(
            'isEncryptBusy',
            $contents,
            'SecretForm.vue must declare an isEncryptBusy computed so busy-state is centralised.'
        );

        $this->assertMatchesRegularExpression(
            "/stage\s*==\s*['\"]generated['\"]\s*&&\s*!isEncryptBusy/",
            $contents,
            'SecretForm.vue password reveal (CodeBlock) must be gated on both stage==\'generated\' AND !isEncryptBusy so the password is not revealed while upload is still in flight.'
        );

        $this->assertMatchesRegularExpression(
            '/:disabled="[^"]*isEncryptBusy[^"]*"/',
            $contents,
            'SecretForm.vue must bind :disabled to isEncryptBusy on form controls during the encrypt flow.'
        );

        $this->assertStringContainsString(
            'passwordInputDisabled',
            $contents,
            'SecretForm.vue must declare a passwordInputDisabled computed so the password TextInput binding is readable and greppable.'
        );
    }

    public function test_decrypt_flow_disables_inputs_while_download_in_progress(): void
    {
        $secretFormContents = file_get_contents(resource_path('js/Pages/Secret/SecretForm.vue'));

        $this->assertStringContainsString(
            'isDecryptBusy',
            $secretFormContents,
            'SecretForm.vue must declare an isDecryptBusy computed to track decrypt/download in-progress state.'
        );

        $this->assertMatchesRegularExpression(
            '/@state-change/',
            $secretFormContents,
            'SecretForm.vue must listen for @state-change from FileDecryptPanel to track its busy state.'
        );

        $this->assertMatchesRegularExpression(
            '/:disabled="[^"]*isDecryptBusy[^"]*"/',
            $secretFormContents,
            'SecretForm.vue decrypt PrimaryButton must be disabled while isDecryptBusy is true.'
        );

        $this->assertStringContainsString(
            'Download and decrypt',
            $secretFormContents,
            'SecretForm.vue decrypt button must be labelled "Download and decrypt" (not "Unlock & Download").'
        );

        $this->assertStringNotContainsString(
            'Unlock & Download',
            $secretFormContents,
            'SecretForm.vue must not use the old "Unlock & Download" button label.'
        );

        $fileDecryptPanelContents = file_get_contents(resource_path('js/Components/FileDecryptPanel.vue'));

        $this->assertMatchesRegularExpression(
            "/defineEmits\\(\\s*\\[[^\\]]*['\"]state-change['\"][^\\]]*\\]\\s*\\)/",
            $fileDecryptPanelContents,
            'FileDecryptPanel.vue must declare a "state-change" emit so SecretForm can track its busy state.'
        );

        $this->assertMatchesRegularExpression(
            "/emit\\(\\s*['\"]state-change['\"]/",
            $fileDecryptPanelContents,
            'FileDecryptPanel.vue must call emit("state-change", ...) to forward its internal fileDecryptState to the parent.'
        );
    }
}
