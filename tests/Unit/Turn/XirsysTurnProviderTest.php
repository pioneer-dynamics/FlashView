<?php

namespace Tests\Unit\Turn;

use App\Turn\XirsysTurnProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XirsysTurnProviderTest extends TestCase
{
    public function test_calls_correct_xirsys_endpoint_with_basic_auth(): void
    {
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
    }

    public function test_returns_ice_servers_from_response(): void
    {
        $servers = [['urls' => 'turn:relay.example.com']];
        Http::fake(['*' => Http::response(['v' => ['iceServers' => $servers]], 200)]);

        $provider = new XirsysTurnProvider([
            'api_key' => 'user',
            'secret' => 'pass',
            'channel' => 'flashview',
        ]);

        $this->assertEquals($servers, $provider->getIceServers());
    }

    public function test_throws_runtime_exception_on_http_error(): void
    {
        Http::fake(['*' => Http::response('error', 503)]);

        $provider = new XirsysTurnProvider([
            'api_key' => 'user',
            'secret' => 'pass',
            'channel' => 'flashview',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('503');

        $provider->getIceServers();
    }
}
