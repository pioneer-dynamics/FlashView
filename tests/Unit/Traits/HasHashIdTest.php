<?php

use App\Exceptions\InvalidHashIdException;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

test('hash id is appended on initialization', function () {
    $secret = Secret::factory()->create();

    expect($secret->toArray())->toHaveKey('hash_id');
    expect($secret->hash_id)->not->toBeEmpty();
});

test('hash id encodes and decodes correctly', function () {
    $secret = Secret::factory()->create();

    $decoded = Secret::decodeHashId($secret->hash_id);

    expect($decoded)->toEqual($secret->id);
});

test('decode hash id returns null for invalid hash', function () {
    $result = Secret::decodeHashId('');

    expect($result)->toBeNull();
});

test('find by hash id returns model', function () {
    $secret = Secret::factory()->create();

    $found = Secret::findByHashID($secret->hash_id);

    expect($found->is($secret))->toBeTrue();
});

test('find by hash id throws on invalid hash', function () {
    $this->expectException(InvalidHashIdException::class);

    Secret::findByHashID('');
});

test('resolve route binding returns model', function () {
    $secret = Secret::factory()->create();

    $resolved = $secret->resolveRouteBinding($secret->hash_id);

    expect($resolved->is($secret))->toBeTrue();
});

test('resolve route binding aborts for invalid hash', function () {
    $secret = Secret::factory()->create();

    $this->expectException(NotFoundHttpException::class);

    $secret->resolveRouteBinding('');
});
