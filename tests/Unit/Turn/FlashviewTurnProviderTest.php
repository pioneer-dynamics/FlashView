<?php

use App\Turn\FlashviewTurnProvider;

function makeProvider(array $overrides = []): FlashviewTurnProvider
{
    return new FlashviewTurnProvider(array_merge([
        'host' => 'turn.flashview.io',
        'auth_secret' => 'testsecret',
        'ttl' => 3600,
    ], $overrides));
}

test('returns turn and stun ice servers', function () {
    $result = makeProvider()->getIceServers();

    $urls = array_column($result, 'urls');
    expect($urls)->toContain('turn:turn.flashview.io:3478');
    expect($urls)->toContain('stun:turn.flashview.io:3478');
});

test('generates hmac sha1 credentials', function () {
    $result = makeProvider()->getIceServers();

    $turnServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'turn:'));

    expect($turnServer)->toHaveKey('username');
    expect($turnServer)->toHaveKey('credential');

    // Username is "{expiry}:flashview"
    expect($turnServer['username'])->toMatch('/^\d+:flashview$/');

    // Credential is base64 HMAC-SHA1 of username with the secret
    $expectedCredential = base64_encode(
        hash_hmac('sha1', $turnServer['username'], 'testsecret', true)
    );
    expect($turnServer['credential'])->toEqual($expectedCredential);
});

test('stun server has no credentials', function () {
    $result = makeProvider()->getIceServers();

    $stunServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'stun:'));

    $this->assertArrayNotHasKey('username', $stunServer);
    $this->assertArrayNotHasKey('credential', $stunServer);
});

test('credential expiry reflects passed ttl', function () {
    $before = time();
    $result = makeProvider()->getIceServers(7200);
    $after = time();

    $turnServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'turn:'));
    $expiry = (int) explode(':', $turnServer['username'])[0];

    expect($expiry)->toBeGreaterThanOrEqual($before + 7200);
    expect($expiry)->toBeLessThanOrEqual($after + 7200);
});

test('credential expiry falls back to one hour when ttl not passed', function () {
    $before = time();
    $result = makeProvider()->getIceServers();
    $after = time();

    $turnServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'turn:'));
    $expiry = (int) explode(':', $turnServer['username'])[0];

    expect($expiry)->toBeGreaterThanOrEqual($before + 3600);
    expect($expiry)->toBeLessThanOrEqual($after + 3600);
});
