<?php

use App\Turn\MeteredTurnProvider;
use Illuminate\Support\Facades\Http;

test('calls correct metered endpoint', function () {
    Http::fake(['*.metered.ca/*' => Http::response([['urls' => 'stun:stun.example.com']], 200)]);

    $provider = new MeteredTurnProvider(['domain' => 'myapp', 'api_key' => 'key123']);
    $provider->getIceServers();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'myapp.metered.ca'));
});

test('returns formatted ice servers', function () {
    $servers = [
        ['urls' => 'turn:relay.example.com', 'username' => 'u', 'credential' => 'p'],
    ];
    Http::fake(['*' => Http::response($servers, 200)]);

    $provider = new MeteredTurnProvider(['domain' => 'myapp', 'api_key' => 'key123']);
    $result = $provider->getIceServers();

    expect($result)->toEqual($servers);
});

test('throws runtime exception on http error', function () {
    Http::fake(['*' => Http::response('error', 503)]);

    $provider = new MeteredTurnProvider(['domain' => 'myapp', 'api_key' => 'key123']);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('503');

    $provider->getIceServers();
});
