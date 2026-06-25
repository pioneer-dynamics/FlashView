<?php

use App\Turn\XirsysTurnProvider;
use Illuminate\Support\Facades\Http;

test('calls correct xirsys endpoint with basic auth', function () {
    Http::fake(['*.xirsys.net/*' => Http::response(['v' => ['iceServers' => []]], 200)]);

    $provider = new XirsysTurnProvider([
        'api_key' => 'user',
        'secret' => 'pass',
        'channel' => 'flashview',
    ]);
    $provider->getIceServers();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'xirsys.net/_turn/flashview')
        && $request->hasHeader('Authorization')
    );
});

test('returns ice servers from response', function () {
    $servers = [['urls' => 'turn:relay.example.com']];
    Http::fake(['*' => Http::response(['v' => ['iceServers' => $servers]], 200)]);

    $provider = new XirsysTurnProvider([
        'api_key' => 'user',
        'secret' => 'pass',
        'channel' => 'flashview',
    ]);

    expect($provider->getIceServers())->toEqual($servers);
});

test('throws runtime exception on http error', function () {
    Http::fake(['*' => Http::response('error', 503)]);

    $provider = new XirsysTurnProvider([
        'api_key' => 'user',
        'secret' => 'pass',
        'channel' => 'flashview',
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('503');

    $provider->getIceServers();
});
