<?php

namespace Tests\Unit\Rules;

use App\Rules\NotPrivateUrl;
use Tests\TestCase;

class NotPrivateUrlTest extends TestCase
{
    private NotPrivateUrl $rule;

    private bool $failed;

    private string $failMessage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new NotPrivateUrl;
        $this->failed = false;
        $this->failMessage = '';
    }

    private function validate(string $value): void
    {
        $this->failed = false;
        $this->failMessage = '';

        $this->rule->validate('webhook_url', $value, function ($message) {
            $this->failed = true;
            $this->failMessage = $message;
        });
    }

    public function test_rejects_localhost(): void
    {
        $this->validate('https://localhost/webhook');

        $this->assertTrue($this->failed);
    }

    public function test_rejects_127_0_0_1(): void
    {
        $this->validate('https://127.0.0.1/webhook');

        $this->assertTrue($this->failed);
    }

    public function test_rejects_ipv6_loopback(): void
    {
        $this->validate('https://::1/webhook');

        $this->assertTrue($this->failed);
    }

    public function test_rejects_0_0_0_0(): void
    {
        $this->validate('https://0.0.0.0/webhook');

        $this->assertTrue($this->failed);
    }

    public function test_rejects_url_without_host(): void
    {
        $this->validate('not-a-url');

        $this->assertTrue($this->failed);
    }

    public function test_allows_blank_value(): void
    {
        $this->validate('');

        $this->assertFalse($this->failed);
    }

    public function test_allows_public_url(): void
    {
        $this->validate('https://example.com/webhook');

        $this->assertFalse($this->failed);
    }
}
