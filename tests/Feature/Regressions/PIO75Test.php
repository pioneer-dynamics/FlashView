<?php

test('placeholder message is not injected into form state on decrypt', function () {
    $contents = file_get_contents(resource_path('js/Pages/Secret/SecretCreateForm.vue'));

    $this->assertStringNotContainsString(
        'placeholderMessage',
        $contents,
        'SecretCreateForm.vue must not declare a placeholderMessage const — display hints must use the HTML placeholder attribute, not v-modelled form state.'
    );

    $this->assertStringNotContainsString(
        "This isn't the actual message",
        $contents,
        'SecretCreateForm.vue must not embed the placeholder string literal in form state — it risks leaking into rendered bindings on file-only and combined-secret decrypt pages.'
    );

    expect($contents)->toMatch('/message:\s*[\'"]{2}/', 'SecretCreateForm.vue must initialise form.message to an empty string unconditionally, not to the placeholder literal.');
});

test('encrypt flow blanks password input and disables inputs while uploading', function () {
    $contents = file_get_contents(resource_path('js/Pages/Secret/SecretCreateForm.vue'));

    $this->assertStringContainsString(
        'isEncryptBusy',
        $contents,
        'SecretCreateForm.vue must declare an isEncryptBusy computed so busy-state is centralised.'
    );

    expect($contents)->toMatch("/stage\s*==\s*['\"]generated['\"]\s*&&\s*!isEncryptBusy/", 'SecretCreateForm.vue password reveal (CodeBlock) must be gated on both stage==\'generated\' AND !isEncryptBusy so the password is not revealed while upload is still in flight.');

    expect($contents)->toMatch('/:disabled="[^"]*isEncryptBusy[^"]*"/', 'SecretCreateForm.vue must bind :disabled to isEncryptBusy on form controls during the encrypt flow.');

    $this->assertStringContainsString(
        'passwordInputDisabled',
        $contents,
        'SecretCreateForm.vue must declare a passwordInputDisabled computed so the password TextInput binding is readable and greppable.'
    );

    expect($contents)->toMatch('/isEncryptBusy\s*\?\s*[\'""]{2}\s*:\s*other\.password/', 'SecretCreateForm.vue password TextInput must display an empty string while isEncryptBusy so the password is not visible during upload.');

    $this->assertStringNotContainsString(
        'v-else-if="!(isEncryptBusy',
        $contents,
        'SecretCreateForm.vue must not conditionally hide the password input — keep it visible but blank it during upload instead.'
    );
});

test('decrypt flow disables inputs while download in progress', function () {
    $secretFormContents = file_get_contents(resource_path('js/Pages/Secret/SecretViewForm.vue'));

    $this->assertStringContainsString(
        'isDecryptBusy',
        $secretFormContents,
        'SecretViewForm.vue must declare an isDecryptBusy computed to track decrypt/download in-progress state.'
    );

    expect($secretFormContents)->toMatch('/@state-change/', 'SecretViewForm.vue must listen for @state-change from FileDecryptPanel to track its busy state.');

    expect($secretFormContents)->toMatch('/:disabled="[^"]*isDecryptBusy[^"]*"/', 'SecretViewForm.vue decrypt PrimaryButton must be disabled while isDecryptBusy is true.');

    $this->assertStringContainsString(
        'Download and decrypt',
        $secretFormContents,
        'SecretViewForm.vue decrypt button must be labelled "Download and decrypt" (not "Unlock & Download").'
    );

    $this->assertStringNotContainsString(
        'Unlock & Download',
        $secretFormContents,
        'SecretViewForm.vue must not use the old "Unlock & Download" button label.'
    );

    $fileDecryptPanelContents = file_get_contents(resource_path('js/Components/FileDecryptPanel.vue'));

    expect($fileDecryptPanelContents)->toMatch('/defineEmits\s*[(<][\s\S]*?state-change/s', 'FileDecryptPanel.vue must declare a "state-change" emit so SecretViewForm can track its busy state.');

    expect($fileDecryptPanelContents)->toMatch("/emit\\(\\s*['\"]state-change['\"]/", 'FileDecryptPanel.vue must call emit("state-change", ...) to forward its internal fileDecryptState to the parent.');
});

test('send new link button is disabled while decrypt busy', function () {
    $contents = file_get_contents(resource_path('js/Pages/Secret/SecretViewForm.vue'));

    expect($contents)->toMatch('/Send a new secret link/', 'SecretViewForm.vue must contain the "Send a new secret link" button.');

    expect($contents)->toMatch('/isDecryptBusy\s*\?\s*null\s*:\s*route\([\'"]welcome[\'"]\)/', 'SecretViewForm.vue "Send a new secret link" button must suppress its href while isDecryptBusy to prevent navigation during download/decrypt.');

    expect($contents)->toMatch('/:disabled="[^"]*isDecryptBusy[^"]*"[^>]*>\s*Send a new secret link/', 'SecretViewForm.vue "Send a new secret link" button must be disabled while isDecryptBusy is true.');
});
