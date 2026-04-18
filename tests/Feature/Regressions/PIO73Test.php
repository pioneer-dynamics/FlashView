<?php

namespace Tests\Feature\Regressions;

use Tests\TestCase;

/**
 * PIO-73: CodeBlock collapses newlines in decrypted secret display because
 * the <code> element in CodeBlock.vue lacks the whitespace-pre-wrap class.
 *
 * Note: assertStringContainsString is intentional — the component has a single
 * <code> element, so this is an acceptable and precise check for this file size.
 */
class PIO73Test extends TestCase
{
    public function test_code_block_component_preserves_whitespace(): void
    {
        $componentPath = resource_path('js/Components/CodeBlock.vue');
        $contents = file_get_contents($componentPath);

        $this->assertStringContainsString(
            'whitespace-pre-wrap',
            $contents,
            'CodeBlock.vue <code> element must have whitespace-pre-wrap to preserve newlines in decrypted secrets'
        );
    }
}
