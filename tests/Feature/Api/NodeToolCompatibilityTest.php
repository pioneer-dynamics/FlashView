<?php

namespace Tests\Feature\Api;

use App\Rules\MessageLength;
use Tests\TestCase;

class NodeToolCompatibilityTest extends TestCase
{
    /**
     * Validate that Node.js CLI ciphertext format (hex salt + base64 ciphertext)
     * is correctly parsed by the MessageLength validation rule.
     */
    public function test_node_encrypted_format_passes_message_length_validation(): void
    {
        // Simulate Node.js output: hex(8-byte salt) + base64(12-byte IV + encrypted + 16-byte authTag)
        $salt = random_bytes(8);
        $iv = random_bytes(12);
        $plaintextLength = 13; // "Hello, World!" = 13 bytes
        $encrypted = random_bytes($plaintextLength);
        $authTag = random_bytes(16);

        $ciphertext = bin2hex($salt).base64_encode($iv.$encrypted.$authTag);

        // The MessageLength rule should parse this and estimate plaintext = 13 bytes
        $rule = new MessageLength('guest');

        $failed = false;
        $rule->validate('message', $ciphertext, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Node.js ciphertext format should pass MessageLength validation');
    }

    /**
     * Validate that the 28-byte overhead calculation is correct.
     */
    public function test_overhead_calculation_matches_node_format(): void
    {
        $salt = random_bytes(8);
        $iv = random_bytes(12);
        $plaintextLength = 5;
        $encrypted = random_bytes($plaintextLength);
        $authTag = random_bytes(16);

        $ciphertext = bin2hex($salt).base64_encode($iv.$encrypted.$authTag);

        // Extract and verify the overhead math
        $base64Part = substr($ciphertext, 16);
        $binary = base64_decode($base64Part);
        $estimatedPlaintext = strlen($binary) - 28;

        $this->assertEquals($plaintextLength, $estimatedPlaintext);
    }

    /**
     * Validate that a message exceeding the guest limit is correctly rejected.
     */
    public function test_node_format_exceeding_limit_fails_validation(): void
    {
        $guestLimit = config('secrets.message_length.guest');

        $salt = random_bytes(8);
        $iv = random_bytes(12);
        $plaintextLength = $guestLimit + 1;
        $encrypted = random_bytes($plaintextLength);
        $authTag = random_bytes(16);

        $ciphertext = bin2hex($salt).base64_encode($iv.$encrypted.$authTag);

        $rule = new MessageLength('guest');

        $failed = false;
        $rule->validate('message', $ciphertext, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Message exceeding guest limit should fail validation');
    }

    /**
     * Validate that empty message (0-byte plaintext) is rejected by min_length.
     */
    public function test_node_format_empty_message_fails_min_length(): void
    {
        $salt = random_bytes(8);
        $iv = random_bytes(12);
        $encrypted = ''; // 0 bytes plaintext
        $authTag = random_bytes(16);

        $ciphertext = bin2hex($salt).base64_encode($iv.$encrypted.$authTag);

        $rule = new MessageLength('guest', 1);

        $failed = false;
        $rule->validate('message', $ciphertext, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Empty message should fail min_length validation');
    }
}
