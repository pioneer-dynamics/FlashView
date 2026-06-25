<?php

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('cf connecting ip is used when replace ip enabled', function () {
    config(['laravelcloudflare.replace_ip' => true]);

    $response = $this->call('GET', '/up', [], [], [], [
        'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
        'REMOTE_ADDR' => '10.0.0.1',
    ]);

    $response->assertSuccessful();
});

test('request ip returns real ip with cloudflare header', function () {
    config(['laravelcloudflare.replace_ip' => true]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
        'REMOTE_ADDR' => '10.0.0.1',
    ]);

    $middleware = app(TrustProxies::class);
    $middleware->handle($request, function ($req) {
        expect($req->ip())->toEqual('203.0.113.42');

        return new Response;
    });
});

test('fallback to remote addr without cloudflare header', function () {
    config(['laravelcloudflare.replace_ip' => true]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.1',
    ]);

    $middleware = app(TrustProxies::class);
    $middleware->handle($request, function ($req) {
        expect($req->ip())->toEqual('10.0.0.1');

        return new Response;
    });
});
