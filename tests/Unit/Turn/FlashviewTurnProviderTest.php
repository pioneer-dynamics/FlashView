<?php

namespace Tests\Unit\Turn;

use App\Turn\FlashviewTurnProvider;
use Tests\TestCase;

class FlashviewTurnProviderTest extends TestCase
{
    private function makeProvider(array $overrides = []): FlashviewTurnProvider
    {
        return new FlashviewTurnProvider(array_merge([
            'host' => 'turn.flashview.io',
            'auth_secret' => 'testsecret',
            'ttl' => 3600,
        ], $overrides));
    }

    public function test_returns_turn_and_stun_ice_servers(): void
    {
        $result = $this->makeProvider()->getIceServers();

        $urls = array_column($result, 'urls');
        $this->assertContains('turn:turn.flashview.io:3478', $urls);
        $this->assertContains('stun:turn.flashview.io:3478', $urls);
    }

    public function test_generates_hmac_sha1_credentials(): void
    {
        $result = $this->makeProvider()->getIceServers();

        $turnServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'turn:'));

        $this->assertArrayHasKey('username', $turnServer);
        $this->assertArrayHasKey('credential', $turnServer);

        // Username is "{expiry}:flashview"
        $this->assertMatchesRegularExpression('/^\d+:flashview$/', $turnServer['username']);

        // Credential is base64 HMAC-SHA1 of username with the secret
        $expectedCredential = base64_encode(
            hash_hmac('sha1', $turnServer['username'], 'testsecret', true)
        );
        $this->assertEquals($expectedCredential, $turnServer['credential']);
    }

    public function test_stun_server_has_no_credentials(): void
    {
        $result = $this->makeProvider()->getIceServers();

        $stunServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'stun:'));

        $this->assertArrayNotHasKey('username', $stunServer);
        $this->assertArrayNotHasKey('credential', $stunServer);
    }

    public function test_credential_expiry_reflects_passed_ttl(): void
    {
        $before = time();
        $result = $this->makeProvider()->getIceServers(7200);
        $after = time();

        $turnServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'turn:'));
        $expiry = (int) explode(':', $turnServer['username'])[0];

        $this->assertGreaterThanOrEqual($before + 7200, $expiry);
        $this->assertLessThanOrEqual($after + 7200, $expiry);
    }

    public function test_credential_expiry_falls_back_to_one_hour_when_ttl_not_passed(): void
    {
        $before = time();
        $result = $this->makeProvider()->getIceServers();
        $after = time();

        $turnServer = collect($result)->first(fn ($s) => str_starts_with($s['urls'], 'turn:'));
        $expiry = (int) explode(':', $turnServer['username'])[0];

        $this->assertGreaterThanOrEqual($before + 3600, $expiry);
        $this->assertLessThanOrEqual($after + 3600, $expiry);
    }
}
