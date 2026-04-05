<?php

namespace Tests\Unit\Services;

use App\Services\EmailMaskingService;
use PHPUnit\Framework\TestCase;

class EmailMaskingServiceTest extends TestCase
{
    private EmailMaskingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailMaskingService;
    }

    public function test_masks_standard_email(): void
    {
        $result = $this->service->mask('john.doe@example.com');

        $this->assertStringStartsWith('j', $result);
        $this->assertStringContainsString('@', $result);
        $this->assertStringEndsWith('.com', $result);
        $this->assertStringNotContainsString('ohn', $result);
        $this->assertStringNotContainsString('xample', $result);
    }

    public function test_masks_single_char_local_part(): void
    {
        $result = $this->service->mask('a@example.com');

        $this->assertStringStartsWith('a', $result);
        $this->assertStringEndsWith('.com', $result);
        $this->assertStringContainsString('@', $result);
    }

    public function test_masks_subdomain_email(): void
    {
        $result = $this->service->mask('user@mail.example.com');

        $this->assertStringStartsWith('u', $result);
        $this->assertStringContainsString('@', $result);
        $this->assertStringContainsString('.example.com', $result);
        $this->assertStringNotContainsString('ser', $result);
        $this->assertStringNotContainsString('ail', $result);
    }

    public function test_masks_short_domain_label(): void
    {
        $result = $this->service->mask('user@ab.io');

        $this->assertStringStartsWith('u', $result);
        $this->assertStringEndsWith('.io', $result);
        $this->assertStringContainsString('@', $result);
    }

    public function test_masks_bare_hostname_defensively(): void
    {
        // Bare hostname (no dot in domain) — guarded defensively
        $result = $this->service->mask('user@localhost');

        $this->assertStringStartsWith('u', $result);
        $this->assertStringContainsString('@', $result);
    }

    public function test_masked_email_contains_asterisks(): void
    {
        $result = $this->service->mask('john@example.com');

        $this->assertStringContainsString('*', $result);
    }

    public function test_original_email_not_in_masked_output(): void
    {
        $email = 'john.doe@example.com';
        $result = $this->service->mask($email);

        $this->assertNotEquals($email, $result);
    }
}
