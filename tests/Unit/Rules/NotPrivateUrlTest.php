<?php

use App\Rules\NotPrivateUrl;

function runNotPrivateUrlValidation(string $value): bool
{
    $rule = new NotPrivateUrl;
    $failed = false;

    $rule->validate('webhook_url', $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

test('rejects localhost', function () {
    expect(runNotPrivateUrlValidation('https://localhost/webhook'))->toBeTrue();
});

test('rejects 127 0 0 1', function () {
    expect(runNotPrivateUrlValidation('https://127.0.0.1/webhook'))->toBeTrue();
});

test('rejects ipv6 loopback', function () {
    expect(runNotPrivateUrlValidation('https://::1/webhook'))->toBeTrue();
});

test('rejects 0 0 0 0', function () {
    expect(runNotPrivateUrlValidation('https://0.0.0.0/webhook'))->toBeTrue();
});

test('rejects url without host', function () {
    expect(runNotPrivateUrlValidation('not-a-url'))->toBeTrue();
});

test('allows blank value', function () {
    expect(runNotPrivateUrlValidation(''))->toBeFalse();
});

test('allows public url', function () {
    expect(runNotPrivateUrlValidation('https://example.com/webhook'))->toBeFalse();
});

test('rejects 192 168 range', function () {
    expect(runNotPrivateUrlValidation('https://192.168.1.1/webhook'))->toBeTrue();
});

test('rejects 10 range', function () {
    expect(runNotPrivateUrlValidation('https://10.0.0.1/webhook'))->toBeTrue();
});

test('rejects 172 16 to 31 range', function () {
    expect(runNotPrivateUrlValidation('https://172.16.0.1/webhook'))->toBeTrue();
});

test('rejects 169 254 link local', function () {
    expect(runNotPrivateUrlValidation('https://169.254.1.1/webhook'))->toBeTrue();
});
