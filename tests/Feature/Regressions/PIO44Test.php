<?php

namespace Tests\Feature\Regressions;

use Tests\TestCase;

/**
 * PIO-44: Clickjacking vulnerability — X-Frame-Options set to SAMEORIGIN instead of DENY,
 * and Content-Security-Policy frame-ancestors header is missing entirely.
 */
class PIO44Test extends TestCase
{
    public function test_web_responses_include_x_frame_options_deny(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_web_responses_include_csp_frame_ancestors(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }
}
