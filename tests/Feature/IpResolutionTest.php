<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class IpResolutionTest extends TestCase
{
    public function test_cf_connecting_ip_is_used_when_replace_ip_enabled(): void
    {
        config(['laravelcloudflare.replace_ip' => true]);

        $response = $this->call('GET', '/up', [], [], [], [
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $response->assertSuccessful();
    }

    public function test_request_ip_returns_real_ip_with_cloudflare_header(): void
    {
        config(['laravelcloudflare.replace_ip' => true]);

        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $middleware = app(TrustProxies::class);
        $middleware->handle($request, function ($req) {
            $this->assertEquals('203.0.113.42', $req->ip());
            return new Response();
        });
    }

    public function test_fallback_to_remote_addr_without_cloudflare_header(): void
    {
        config(['laravelcloudflare.replace_ip' => true]);

        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $middleware = app(TrustProxies::class);
        $middleware->handle($request, function ($req) {
            $this->assertEquals('10.0.0.1', $req->ip());
            return new Response();
        });
    }
}
