<?php

use App\Features\ExpiryFeature;
use App\Features\FileUploadFeature;
use App\Features\MessagesFeature;
use App\Features\ThrottlingFeature;

test('messages feature within limit returns true when under limit', function () {
    $feature = new MessagesFeature;

    expect($feature->withinLimit(500, ['message_length' => 1000]))->toBeTrue();
});

test('messages feature within limit returns true when equal to limit', function () {
    $feature = new MessagesFeature;

    expect($feature->withinLimit(1000, ['message_length' => 1000]))->toBeTrue();
});

test('messages feature within limit returns false when over limit', function () {
    $feature = new MessagesFeature;

    expect($feature->withinLimit(1001, ['message_length' => 1000]))->toBeFalse();
});

test('messages feature within limit returns true when config missing', function () {
    $feature = new MessagesFeature;

    expect($feature->withinLimit(PHP_INT_MAX - 1, []))->toBeTrue();
});

test('messages feature can be limit', function () {
    expect((new MessagesFeature)->canBeLimit())->toBeTrue();
});

test('expiry feature within limit returns true when under limit', function () {
    $feature = new ExpiryFeature;

    expect($feature->withinLimit(1440, ['expiry_minutes' => 43200]))->toBeTrue();
});

test('expiry feature within limit returns false when over limit', function () {
    $feature = new ExpiryFeature;

    expect($feature->withinLimit(43201, ['expiry_minutes' => 43200]))->toBeFalse();
});

test('expiry feature within limit returns true when config missing', function () {
    $feature = new ExpiryFeature;

    expect($feature->withinLimit(PHP_INT_MAX - 1, []))->toBeTrue();
});

test('expiry feature can be limit', function () {
    expect((new ExpiryFeature)->canBeLimit())->toBeTrue();
});

test('expiry feature resolves label in weeks', function () {
    expect((new ExpiryFeature)->resolveLabel(['expiry_minutes' => 20160]))->toBe('Up to 2 weeks expiry');
});

test('expiry feature resolves label in days', function () {
    expect((new ExpiryFeature)->resolveLabel(['expiry_minutes' => 43200]))->toBe('Up to 30 days expiry');
});

test('expiry feature resolves label singular day', function () {
    expect((new ExpiryFeature)->resolveLabel(['expiry_minutes' => 1440]))->toBe('Up to 1 day expiry');
});

test('expiry feature resolves label in hours', function () {
    expect((new ExpiryFeature)->resolveLabel(['expiry_minutes' => 360]))->toBe('Up to 6 hours expiry');
});

test('expiry feature resolves label in minutes', function () {
    expect((new ExpiryFeature)->resolveLabel(['expiry_minutes' => 90]))->toBe('Up to 90 minutes expiry');
});

test('expiry feature resolves label with missing config', function () {
    expect((new ExpiryFeature)->resolveLabel([]))->toBe('Up to 0 minutes expiry');
});

test('throttling feature within limit returns true when under limit', function () {
    $feature = new ThrottlingFeature;

    expect($feature->withinLimit(30, ['per_minute' => 60]))->toBeTrue();
});

test('throttling feature within limit returns false when over limit', function () {
    $feature = new ThrottlingFeature;

    expect($feature->withinLimit(61, ['per_minute' => 60]))->toBeFalse();
});

test('throttling feature can be limit', function () {
    expect((new ThrottlingFeature)->canBeLimit())->toBeTrue();
});

test('file upload feature within limit returns true when under limit', function () {
    $feature = new FileUploadFeature;
    $tenMbInBytes = 10 * 1024 * 1024;

    expect($feature->withinLimit($tenMbInBytes - 1, ['max_file_size_mb' => 10]))->toBeTrue();
});

test('file upload feature within limit returns true when equal to limit', function () {
    $feature = new FileUploadFeature;
    $tenMbInBytes = 10 * 1024 * 1024;

    expect($feature->withinLimit($tenMbInBytes, ['max_file_size_mb' => 10]))->toBeTrue();
});

test('file upload feature within limit returns false when over limit', function () {
    $feature = new FileUploadFeature;
    $tenMbInBytes = 10 * 1024 * 1024;

    expect($feature->withinLimit($tenMbInBytes + 1, ['max_file_size_mb' => 10]))->toBeFalse();
});

test('file upload feature within limit returns false when config missing', function () {
    $feature = new FileUploadFeature;

    // max_file_size_mb defaults to 0, so any size > 0 fails
    expect($feature->withinLimit(1, []))->toBeFalse();
});

test('file upload feature can be limit', function () {
    expect((new FileUploadFeature)->canBeLimit())->toBeTrue();
});
