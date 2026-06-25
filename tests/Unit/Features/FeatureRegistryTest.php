<?php

use App\Features\ApiFeature;
use App\Features\ExpiryFeature;
use App\Features\MessagesFeature;
use App\Services\FeatureRegistry;

function makeRegistry(): FeatureRegistry
{
    return new FeatureRegistry([
        new MessagesFeature,
        new ExpiryFeature,
        new ApiFeature,
    ]);
}

test('all returns all registered features', function () {
    $registry = makeRegistry();

    expect($registry->all())->toHaveCount(3);
});

test('has returns true for known key', function () {
    $registry = makeRegistry();

    expect($registry->has('messages'))->toBeTrue();
    expect($registry->has('expiry'))->toBeTrue();
    expect($registry->has('api'))->toBeTrue();
});

test('has returns false for unknown key', function () {
    $registry = makeRegistry();

    expect($registry->has('nonexistent_feature'))->toBeFalse();
});

test('get returns correct feature class', function () {
    $registry = makeRegistry();

    $feature = $registry->get('messages');

    expect($feature)->toBeInstanceOf(MessagesFeature::class);
    expect($feature->key())->toEqual('messages');
});

test('get throws for unknown key', function () {
    $registry = makeRegistry();

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Unknown plan feature key: [nonexistent]');

    $registry->get('nonexistent');
});

test('for frontend returns correct shape', function () {
    $registry = makeRegistry();

    $result = $registry->forFrontend();

    expect($result)->toHaveCount(3);

    $messagesEntry = collect($result)->firstWhere('key', 'messages');
    expect($messagesEntry)->not->toBeNull();
    expect($messagesEntry)->toHaveKey('key');
    expect($messagesEntry)->toHaveKey('label');
    expect($messagesEntry)->toHaveKey('description');
    expect($messagesEntry)->toHaveKey('defaultOrder');
    expect($messagesEntry)->toHaveKey('canBeLimit');
    expect($messagesEntry)->toHaveKey('configSchema');
    expect($messagesEntry['canBeLimit'])->toBeTrue();
    expect($messagesEntry['configSchema'])->not->toBeEmpty();
});

test('for frontend marks boolean features as not can be limit', function () {
    $registry = makeRegistry();

    $result = $registry->forFrontend();
    $apiEntry = collect($result)->firstWhere('key', 'api');

    expect($apiEntry)->not->toBeNull();
    expect($apiEntry['canBeLimit'])->toBeFalse();
    expect($apiEntry['configSchema'])->toBeEmpty();
});

test('singleton is registered in container', function () {
    $registry = app(FeatureRegistry::class);

    expect($registry)->toBeInstanceOf(FeatureRegistry::class);
    expect(app(FeatureRegistry::class))->toBe($registry);
});
