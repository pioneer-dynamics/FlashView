<?php

namespace Tests\Unit\Turn;

use App\Turn\TurnAllocation;
use PHPUnit\Framework\TestCase;

class TurnAllocationTest extends TestCase
{
    private function makeSocket(): \Socket
    {
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->assertNotFalse($sock, 'socket_create() must succeed in the test environment');

        return $sock;
    }

    private function makeAllocation(int $ttl = 600, ?int $expiresAt = null): TurnAllocation
    {
        $sock = $this->makeSocket();

        return new TurnAllocation(
            clientIp: '127.0.0.1',
            clientPort: 12345,
            relaySocket: $sock,
            relayIp: '203.0.113.1',
            relayPort: 49152,
            ttl: $ttl,
            expiresAt: $expiresAt ?? time() + $ttl,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_is_not_expired_when_fresh(): void
    {
        $alloc = $this->makeAllocation(ttl: 600);
        socket_close($alloc->relaySocket);

        $this->assertFalse($alloc->isExpired());
    }

    public function test_is_expired_when_ttl_elapsed(): void
    {
        $alloc = $this->makeAllocation(ttl: 600, expiresAt: time() - 1);
        socket_close($alloc->relaySocket);

        $this->assertTrue($alloc->isExpired());
    }

    public function test_refresh_extends_expiry(): void
    {
        $alloc = $this->makeAllocation(ttl: 600, expiresAt: time() - 1);

        $this->assertTrue($alloc->isExpired());

        $alloc->refresh(300);

        $this->assertFalse($alloc->isExpired());
        $this->assertGreaterThan(time(), $alloc->expiresAt);

        socket_close($alloc->relaySocket);
    }

    public function test_refresh_sets_correct_expiry(): void
    {
        $alloc = $this->makeAllocation(ttl: 600);
        $before = time();
        $alloc->refresh(120);
        $after = time();

        $this->assertGreaterThanOrEqual($before + 120, $alloc->expiresAt);
        $this->assertLessThanOrEqual($after + 120, $alloc->expiresAt);

        socket_close($alloc->relaySocket);
    }

    public function test_permissions_default_empty(): void
    {
        $alloc = $this->makeAllocation();
        socket_close($alloc->relaySocket);

        $this->assertEmpty($alloc->permissions);
    }

    public function test_permissions_can_be_set(): void
    {
        $alloc = $this->makeAllocation();
        $alloc->permissions['192.168.1.1'] = true;

        $this->assertArrayHasKey('192.168.1.1', $alloc->permissions);
        $this->assertTrue($alloc->permissions['192.168.1.1']);

        socket_close($alloc->relaySocket);
    }

    public function test_readonly_properties_accessible(): void
    {
        $alloc = $this->makeAllocation();

        $this->assertSame('127.0.0.1', $alloc->clientIp);
        $this->assertSame(12345, $alloc->clientPort);
        $this->assertSame('203.0.113.1', $alloc->relayIp);
        $this->assertSame(49152, $alloc->relayPort);
        $this->assertSame(600, $alloc->ttl);

        socket_close($alloc->relaySocket);
    }
}
