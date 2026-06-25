<?php

use App\Rules\MessageLength;

test('node encrypted format passes message length validation', function () {
    // Simulate Node.js output: hex(8-byte salt) + base64(12-byte IV + encrypted + 16-byte authTag)
    $salt = random_bytes(8);
    $iv = random_bytes(12);
    $plaintextLength = 13;
    // "Hello, World!" = 13 bytes
    $encrypted = random_bytes($plaintextLength);
    $authTag = random_bytes(16);

    $ciphertext = bin2hex($salt).base64_encode($iv.$encrypted.$authTag);

    // The MessageLength rule should parse this and estimate plaintext = 13 bytes
    $rule = new MessageLength('guest');

    $failed = false;
    $rule->validate('message', $ciphertext, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse('Node.js ciphertext format should pass MessageLength validation');
});

test('overhead calculation matches node format', function () {
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

    expect($estimatedPlaintext)->toEqual($plaintextLength);
});

test('node format exceeding limit fails validation', function () {
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

    expect($failed)->toBeTrue('Message exceeding guest limit should fail validation');
});

test('node format empty message fails min length', function () {
    $salt = random_bytes(8);
    $iv = random_bytes(12);
    $encrypted = '';
    // 0 bytes plaintext
    $authTag = random_bytes(16);

    $ciphertext = bin2hex($salt).base64_encode($iv.$encrypted.$authTag);

    $rule = new MessageLength('guest', 1);

    $failed = false;
    $rule->validate('message', $ciphertext, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue('Empty message should fail min_length validation');
});
