<?php

namespace Tests\Feature\Turn;

use App\Turn\StunMessage;
use App\Turn\TurnServer;
use Tests\TestCase;

class TurnServerTest extends TestCase
{
    private const TEST_PORT = 14478; // Dedicated test port — not 3478 to avoid conflicts

    private const TEST_RELAY_MIN = 59000;

    private const TEST_RELAY_MAX = 59100;

    private const TEST_REALM = 'test.local';

    private const TEST_USERNAME = 'testuser';

    private const TEST_PASSWORD = 'testpass';

    private function makeServer(): TurnServer
    {
        // Set public_ip so resolvePublicIp() returns immediately without HTTP calls
        config(['turn.public_ip' => '127.0.0.1']);

        return new TurnServer(
            host: '127.0.0.1',
            port: self::TEST_PORT,
            realm: self::TEST_REALM,
            username: self::TEST_USERNAME,
            password: self::TEST_PASSWORD,
            allocationTtl: 600,
            relayMinPort: self::TEST_RELAY_MIN,
            relayMaxPort: self::TEST_RELAY_MAX,
        );
    }

    private function forkServer(TurnServer $server): int
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('pcntl_fork() failed — cannot run loopback server test');
        }

        if ($pid === 0) {
            // Child process: start and run the server until SIGTERM
            pcntl_signal(SIGTERM, fn () => $server->stop());
            try {
                $server->start();
                $server->run();
            } catch (\Throwable) {
                // swallow — child must exit cleanly
            }
            exit(0);
        }

        return $pid; // Return child PID to parent
    }

    private function stopServer(int $pid): void
    {
        posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
    }

    private function makeClientSocket(): \Socket
    {
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->assertNotFalse($sock);

        // 2-second receive timeout
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);

        return $sock;
    }

    private function send(string $data, \Socket $sock): void
    {
        socket_sendto($sock, $data, strlen($data), 0, '127.0.0.1', self::TEST_PORT);
    }

    private function recv(\Socket $sock): ?StunMessage
    {
        $data = '';
        $ip = '';
        $port = 0;
        $bytes = socket_recvfrom($sock, $data, 65535, 0, $ip, $port);

        if ($bytes === false || $bytes === 0) {
            return null;
        }

        return StunMessage::parse($data);
    }

    // -------------------------------------------------------------------------
    // STUN Binding Request / Response
    // -------------------------------------------------------------------------

    public function test_stun_binding_request_returns_xor_mapped_address(): void
    {
        $server = $this->makeServer();
        $pid = $this->forkServer($server);

        // Give the child process time to bind the socket
        usleep(200_000);

        $sock = $this->makeClientSocket();

        try {
            $req = new StunMessage;
            $req->type = StunMessage::BINDING_REQUEST;
            $req->transactionId = random_bytes(12);

            $this->send($req->serialize(), $sock);
            $resp = $this->recv($sock);

            $this->assertNotNull($resp, 'Server must respond to Binding Request');
            $this->assertSame(StunMessage::BINDING_RESPONSE, $resp->type);
            $this->assertSame($req->transactionId, $resp->transactionId);

            $addr = $resp->getAttribute(StunMessage::ATTR_XOR_MAPPED_ADDRESS);
            $this->assertNotNull($addr, 'Response must contain XOR-MAPPED-ADDRESS');
            $this->assertSame('127.0.0.1', $addr['ip']);
            $this->assertIsInt($addr['port']);
            $this->assertGreaterThan(0, $addr['port']);
        } finally {
            socket_close($sock);
            $this->stopServer($pid);
        }
    }

    // -------------------------------------------------------------------------
    // TURN Allocate: 401 challenge → authenticated success
    // -------------------------------------------------------------------------

    public function test_allocate_without_auth_returns_401(): void
    {
        $server = $this->makeServer();
        $pid = $this->forkServer($server);

        usleep(200_000);

        $sock = $this->makeClientSocket();

        try {
            $req = new StunMessage;
            $req->type = StunMessage::ALLOCATE_REQUEST;
            $req->transactionId = random_bytes(12);
            $req->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;

            $this->send($req->serialize(), $sock);
            $resp = $this->recv($sock);

            $this->assertNotNull($resp, 'Server must respond to unauthenticated Allocate');
            $this->assertSame(StunMessage::ALLOCATE_ERROR, $resp->type);

            $err = $resp->getAttribute(StunMessage::ATTR_ERROR_CODE);
            $this->assertSame(401, $err['code']);

            $this->assertNotNull($resp->getAttribute(StunMessage::ATTR_REALM));
            $this->assertNotNull($resp->getAttribute(StunMessage::ATTR_NONCE));
        } finally {
            socket_close($sock);
            $this->stopServer($pid);
        }
    }

    public function test_allocate_full_auth_flow_returns_relay_address(): void
    {
        $server = $this->makeServer();
        $pid = $this->forkServer($server);

        usleep(200_000);

        $sock = $this->makeClientSocket();

        try {
            // Step 1: unauthenticated Allocate → 401
            $req1 = new StunMessage;
            $req1->type = StunMessage::ALLOCATE_REQUEST;
            $req1->transactionId = random_bytes(12);
            $req1->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;

            $this->send($req1->serialize(), $sock);
            $challenge = $this->recv($sock);

            $this->assertNotNull($challenge);
            $this->assertSame(StunMessage::ALLOCATE_ERROR, $challenge->type);
            $err = $challenge->getAttribute(StunMessage::ATTR_ERROR_CODE);
            $this->assertSame(401, $err['code']);

            $realm = $challenge->getAttribute(StunMessage::ATTR_REALM);
            $nonce = $challenge->getAttribute(StunMessage::ATTR_NONCE);

            // Step 2: authenticated Allocate
            $req2 = new StunMessage;
            $req2->type = StunMessage::ALLOCATE_REQUEST;
            $req2->transactionId = random_bytes(12);
            $req2->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;
            $req2->attributes[StunMessage::ATTR_USERNAME] = self::TEST_USERNAME;
            $req2->attributes[StunMessage::ATTR_REALM] = $realm;
            $req2->attributes[StunMessage::ATTR_NONCE] = $nonce;

            $key = StunMessage::makeKey(self::TEST_USERNAME, self::TEST_REALM, self::TEST_PASSWORD);
            $req2->addMessageIntegrity($key);

            $this->send($req2->serialize(), $sock);
            $resp = $this->recv($sock);

            $this->assertNotNull($resp, 'Server must respond to authenticated Allocate');
            $this->assertSame(StunMessage::ALLOCATE_RESPONSE, $resp->type);
            $this->assertSame($req2->transactionId, $resp->transactionId);

            $relay = $resp->getAttribute(StunMessage::ATTR_XOR_RELAYED_ADDRESS);
            $this->assertNotNull($relay, 'Allocate response must include XOR-RELAYED-ADDRESS');
            $this->assertSame('127.0.0.1', $relay['ip'], 'Relay IP must be the configured public IP, not 0.0.0.0');
            $this->assertGreaterThanOrEqual(self::TEST_RELAY_MIN, $relay['port']);
            $this->assertLessThanOrEqual(self::TEST_RELAY_MAX, $relay['port']);

            $mapped = $resp->getAttribute(StunMessage::ATTR_XOR_MAPPED_ADDRESS);
            $this->assertNotNull($mapped);
            $this->assertSame('127.0.0.1', $mapped['ip']);

            $lifetime = $resp->getAttribute(StunMessage::ATTR_LIFETIME);
            $this->assertSame(600, $lifetime);
        } finally {
            socket_close($sock);
            $this->stopServer($pid);
        }
    }

    public function test_allocate_with_wrong_credentials_returns_401(): void
    {
        $server = $this->makeServer();
        $pid = $this->forkServer($server);

        usleep(200_000);

        $sock = $this->makeClientSocket();

        try {
            // Get a nonce first
            $req1 = new StunMessage;
            $req1->type = StunMessage::ALLOCATE_REQUEST;
            $req1->transactionId = random_bytes(12);
            $req1->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;

            $this->send($req1->serialize(), $sock);
            $challenge = $this->recv($sock);

            $realm = $challenge->getAttribute(StunMessage::ATTR_REALM);
            $nonce = $challenge->getAttribute(StunMessage::ATTR_NONCE);

            // Authenticated request with wrong password
            $req2 = new StunMessage;
            $req2->type = StunMessage::ALLOCATE_REQUEST;
            $req2->transactionId = random_bytes(12);
            $req2->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;
            $req2->attributes[StunMessage::ATTR_USERNAME] = self::TEST_USERNAME;
            $req2->attributes[StunMessage::ATTR_REALM] = $realm;
            $req2->attributes[StunMessage::ATTR_NONCE] = $nonce;

            $wrongKey = StunMessage::makeKey(self::TEST_USERNAME, self::TEST_REALM, 'wrongpassword');
            $req2->addMessageIntegrity($wrongKey);

            $this->send($req2->serialize(), $sock);
            $resp = $this->recv($sock);

            $this->assertNotNull($resp);
            $this->assertSame(StunMessage::ALLOCATE_ERROR, $resp->type);

            $err = $resp->getAttribute(StunMessage::ATTR_ERROR_CODE);
            $this->assertSame(401, $err['code']);
        } finally {
            socket_close($sock);
            $this->stopServer($pid);
        }
    }

    public function test_relay_socket_is_non_blocking(): void
    {
        $server = $this->makeServer();
        $pid = $this->forkServer($server);

        usleep(200_000);

        $sock = $this->makeClientSocket();

        try {
            // Perform a full allocation — this creates the relay socket on the server
            $req1 = new StunMessage;
            $req1->type = StunMessage::ALLOCATE_REQUEST;
            $req1->transactionId = random_bytes(12);
            $req1->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;

            $this->send($req1->serialize(), $sock);
            $challenge = $this->recv($sock);

            $realm = $challenge->getAttribute(StunMessage::ATTR_REALM);
            $nonce = $challenge->getAttribute(StunMessage::ATTR_NONCE);

            $req2 = new StunMessage;
            $req2->type = StunMessage::ALLOCATE_REQUEST;
            $req2->transactionId = random_bytes(12);
            $req2->attributes[StunMessage::ATTR_REQUESTED_TRANSPORT] = 0x11;
            $req2->attributes[StunMessage::ATTR_USERNAME] = self::TEST_USERNAME;
            $req2->attributes[StunMessage::ATTR_REALM] = $realm;
            $req2->attributes[StunMessage::ATTR_NONCE] = $nonce;

            $key = StunMessage::makeKey(self::TEST_USERNAME, self::TEST_REALM, self::TEST_PASSWORD);
            $req2->addMessageIntegrity($key);

            $this->send($req2->serialize(), $sock);
            $resp = $this->recv($sock);

            // If we got here without hanging, the relay socket is non-blocking
            // (a blocking relay socket in socket_select() would cause the server to stall)
            $this->assertSame(StunMessage::ALLOCATE_RESPONSE, $resp->type);

            // Send a second Binding Request — if the server is still responding, the
            // relay socket did not block the event loop
            $ping = new StunMessage;
            $ping->type = StunMessage::BINDING_REQUEST;
            $ping->transactionId = random_bytes(12);

            $this->send($ping->serialize(), $sock);
            $pong = $this->recv($sock);

            $this->assertNotNull($pong, 'Server must still be responsive after allocation (relay socket must be non-blocking)');
            $this->assertSame(StunMessage::BINDING_RESPONSE, $pong->type);
        } finally {
            socket_close($sock);
            $this->stopServer($pid);
        }
    }
}
