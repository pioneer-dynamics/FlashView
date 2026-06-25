<?php

use App\Services\EmailMaskingService;

beforeEach(function () {
    $this->service = new EmailMaskingService;
});

test('masks standard email', function () {
    $result = $this->service->mask('john.doe@example.com');

    expect($result)->toStartWith('j');
    $this->assertStringContainsString('@', $result);
    expect($result)->toEndWith('.com');
    $this->assertStringNotContainsString('ohn', $result);
    $this->assertStringNotContainsString('xample', $result);
});

test('masks single char local part', function () {
    $result = $this->service->mask('a@example.com');

    expect($result)->toStartWith('a');
    expect($result)->toEndWith('.com');
    $this->assertStringContainsString('@', $result);
});

test('masks subdomain email', function () {
    $result = $this->service->mask('user@mail.example.com');

    expect($result)->toStartWith('u');
    $this->assertStringContainsString('@', $result);
    $this->assertStringContainsString('.example.com', $result);
    $this->assertStringNotContainsString('ser', $result);
    $this->assertStringNotContainsString('ail', $result);
});

test('masks short domain label', function () {
    $result = $this->service->mask('user@ab.io');

    expect($result)->toStartWith('u');
    expect($result)->toEndWith('.io');
    $this->assertStringContainsString('@', $result);
});

test('masks bare hostname defensively', function () {
    // Bare hostname (no dot in domain) — guarded defensively
    $result = $this->service->mask('user@localhost');

    expect($result)->toStartWith('u');
    $this->assertStringContainsString('@', $result);
});

test('masked email contains asterisks', function () {
    $result = $this->service->mask('john@example.com');

    $this->assertStringContainsString('*', $result);
});

test('original email not in masked output', function () {
    $email = 'john.doe@example.com';
    $result = $this->service->mask($email);

    $this->assertNotEquals($email, $result);
});
