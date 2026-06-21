<?php

namespace Tests\Unit\Turn;

use App\Turn\MeteredTurnProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeteredTurnProviderTest extends TestCase
{
    public function test_calls_correct_metered_endpoint(): void
    {
        Http::fake(['*.metered.ca/*' => Http::response([['urls' => 'stun:stun.example.com']], 200)]);

        $provider = new MeteredTurnProvider(['domain' => 'myapp', 'api_key' => 'key123']);
        $provider->getIceServers();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'myapp.metered.ca'));
    }

    public function test_returns_formatted_ice_servers(): void
    {
        $servers = [
            ['urls' => 'turn:relay.example.com', 'username' => 'u', 'credential' => 'p'],
        ];
        Http::fake(['*' => Http::response($servers, 200)]);

        $provider = new MeteredTurnProvider(['domain' => 'myapp', 'api_key' => 'key123']);
        $result = $provider->getIceServers();

        $this->assertEquals($servers, $result);
    }

    public function test_throws_runtime_exception_on_http_error(): void
    {
        Http::fake(['*' => Http::response('error', 503)]);

        $provider = new MeteredTurnProvider(['domain' => 'myapp', 'api_key' => 'key123']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('503');

        $provider->getIceServers();
    }
}
