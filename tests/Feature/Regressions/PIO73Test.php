<?php

test('code block component preserves whitespace', function () {
    $componentPath = resource_path('js/Components/CodeBlock.vue');
    $contents = file_get_contents($componentPath);

    $this->assertStringContainsString(
        'whitespace-pre-wrap',
        $contents,
        'CodeBlock.vue <code> element must have whitespace-pre-wrap to preserve newlines in decrypted secrets'
    );
});
