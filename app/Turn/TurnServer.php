<?php

namespace App\Turn;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class TurnServer
{
    private ?\Socket $socket = null;

    /** @var array<string, TurnAllocation> keyed by "clientIp:clientPort" */
    private array $allocations = [];

    private bool $running = false;

    private string $publicIp = '';

    private string $nonce = '';

    /** @var array<int> */
    private array $usedRelayPorts = [];

    private int $lastMemoryReport = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $realm,
        private readonly string $username,
        private readonly string $password,
        private readonly int $allocationTtl,
        private readonly int $relayMinPort,
        private readonly int $relayMaxPort,
        private readonly bool $logPackets = false,
    ) {}

    public function start(): void
    {
        $this->publicIp = $this->resolvePublicIp();
        $this->nonce = bin2hex(random_bytes(16));

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            throw new RuntimeException('socket_create() failed: '.socket_strerror(socket_last_error()));
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (! socket_bind($socket, $this->host, $this->port)) {
            throw new RuntimeException(
                "socket_bind({$this->host}:{$this->port}) failed: ".socket_strerror(socket_last_error($socket))
            );
        }

        socket_set_nonblock($socket);

        $this->socket = $socket;
        $this->running = true;

        Log::info("[TURN] Relay address: {$this->publicIp}:{$this->port}");
        Log::info("[TURN] Listening on {$this->host}:{$this->port} (UDP)");
    }

    public function run(): void
    {
        while ($this->running) {
            pcntl_signal_dispatch();

            $read = [$this->socket];

            foreach ($this->allocations as $alloc) {
                $read[] = $alloc->relaySocket;
            }

            $write = null;
            $except = null;

            $ready = socket_select($read, $write, $except, 0, 100000);

            if ($ready === false) {
                Log::warning('[TURN] socket_select() error: '.socket_strerror(socket_last_error()));

                continue;
            }

            if ($ready === 0) {
                $this->expireAllocations();

                continue;
            }

            foreach ($read as $sock) {
                if ($sock === $this->socket) {
                    $this->receiveFromClient();
                } else {
                    $alloc = $this->allocationForRelay($sock);

                    if ($alloc) {
                        $this->handleRelayInbound($alloc);
                    }
                }
            }

            $this->expireAllocations();
            $this->reportMemoryIfDue();
        }
    }

    public function stop(): void
    {
        $this->running = false;

        foreach ($this->allocations as $alloc) {
            @socket_close($alloc->relaySocket);
        }

        $this->allocations = [];

        if ($this->socket !== null) {
            @socket_close($this->socket);
            $this->socket = null;
        }
    }

    public function getPublicIp(): string
    {
        return $this->publicIp;
    }

    private function resolvePublicIp(): string
    {
        $configured = config('turn.public_ip', '');

        if ($configured !== '') {
            return $configured;
        }

        // AWS instance metadata (Laravel Cloud is AWS-backed)
        $awsIp = $this->fetchUrl('http://169.254.169.254/latest/meta-data/public-ipv4', 200000);

        if ($awsIp !== null && filter_var($awsIp, FILTER_VALIDATE_IP)) {
            Log::info("[TURN] Public IP resolved via AWS metadata: {$awsIp}");

            return $awsIp;
        }

        // ipify fallback
        $ipifyIp = $this->fetchUrl('https://api.ipify.org', 2000000);

        if ($ipifyIp !== null && filter_var($ipifyIp, FILTER_VALIDATE_IP)) {
            Log::info("[TURN] Public IP resolved via ipify: {$ipifyIp}");

            return $ipifyIp;
        }

        throw new RuntimeException(
            'Cannot determine public IP. Set TURN_PUBLIC_IP in your environment. '.
            'Advertising 0.0.0.0 would cause silent relay failure.'
        );
    }

    private function fetchUrl(string $url, int $timeoutMicros): ?string
    {
        $ctx = stream_context_create(['http' => [
            'timeout' => $timeoutMicros / 1_000_000,
        ]]);

        $result = @file_get_contents($url, false, $ctx);

        return $result !== false ? trim($result) : null;
    }

    private function receiveFromClient(): void
    {
        $data = '';
        $ip = '';
        $port = 0;

        $bytes = socket_recvfrom($this->socket, $data, 65535, 0, $ip, $port);

        if ($bytes === false || $bytes === 0) {
            return;
        }

        if ($this->logPackets) {
            Log::debug("[TURN] << {$bytes}b from {$ip}:{$port}");
        }

        try {
            $msg = StunMessage::parse($data);
        } catch (RuntimeException $e) {
            Log::warning("[TURN] Malformed packet from {$ip}:{$port}: {$e->getMessage()}");

            return;
        }

        match ($msg->type) {
            StunMessage::BINDING_REQUEST => $this->handleBindingRequest($msg, $ip, $port),
            StunMessage::ALLOCATE_REQUEST => $this->handleAllocate($msg, $ip, $port),
            StunMessage::REFRESH_REQUEST => $this->handleRefresh($msg, $ip, $port),
            StunMessage::SEND_INDICATION => $this->handleSendIndication($msg, $ip, $port),
            StunMessage::CREATE_PERMISSION_REQUEST => $this->handleCreatePermission($msg, $ip, $port),
            default => Log::debug(sprintf('[TURN] Unhandled message type 0x%04X from %s:%d', $msg->type, $ip, $port)),
        };
    }

    private function handleBindingRequest(StunMessage $msg, string $ip, int $port): void
    {
        $resp = new StunMessage;
        $resp->type = StunMessage::BINDING_RESPONSE;
        $resp->transactionId = $msg->transactionId;

        $resp->addXorMappedAddress($ip, $port);
        $resp->addSoftware('FlashView TURN/0.1');

        $this->sendResponse($resp, $ip, $port);
    }

    private function handleAllocate(StunMessage $msg, string $ip, int $port): void
    {
        $username = $msg->getAttribute(StunMessage::ATTR_USERNAME);
        $realm = $msg->getAttribute(StunMessage::ATTR_REALM);
        $nonce = $msg->getAttribute(StunMessage::ATTR_NONCE);

        // Step 1: no auth attrs → 401 challenge
        if ($username === null || $realm === null || $nonce === null) {
            $this->send401($msg, $ip, $port);

            return;
        }

        // Step 2: verify credentials
        if (! $this->verifyCredentials($msg, $username, $realm)) {
            $this->send401($msg, $ip, $port);

            return;
        }

        // Step 3: verify REQUESTED-TRANSPORT = UDP (0x11)
        $transport = $msg->getAttribute(StunMessage::ATTR_REQUESTED_TRANSPORT);

        if ($transport !== 0x11) {
            $this->sendError($msg, 442, 'Unsupported Transport Protocol', $ip, $port);

            return;
        }

        // Step 4: allocate relay socket
        try {
            $relayPort = $this->allocateRelayPort();
            $relaySocket = $this->openRelaySocket($relayPort);
        } catch (RuntimeException $e) {
            Log::warning("[TURN] Allocation failed: {$e->getMessage()}");
            $this->sendError($msg, 508, 'Insufficient Capacity', $ip, $port);

            return;
        }

        $key = "$ip:$port";

        if (isset($this->allocations[$key])) {
            @socket_close($this->allocations[$key]->relaySocket);
            unset($this->usedRelayPorts[$this->allocations[$key]->relayPort]);
        }

        $this->allocations[$key] = new TurnAllocation(
            clientIp: $ip,
            clientPort: $port,
            relaySocket: $relaySocket,
            relayIp: $this->publicIp,
            relayPort: $relayPort,
            ttl: $this->allocationTtl,
            expiresAt: time() + $this->allocationTtl,
        );

        $resp = new StunMessage;
        $resp->type = StunMessage::ALLOCATE_RESPONSE;
        $resp->transactionId = $msg->transactionId;

        $resp->addXorRelayedAddress($this->publicIp, $relayPort);
        $resp->addXorMappedAddress($ip, $port);
        $resp->addLifetime($this->allocationTtl);
        $resp->addSoftware('FlashView TURN/0.1');

        $hmacKey = StunMessage::makeKey($this->username, $this->realm, $this->password);
        $resp->addMessageIntegrity($hmacKey);

        $this->sendResponse($resp, $ip, $port);

        Log::info("[TURN] Allocated relay {$this->publicIp}:{$relayPort} for {$ip}:{$port}");
    }

    private function handleRefresh(StunMessage $msg, string $ip, int $port): void
    {
        $username = $msg->getAttribute(StunMessage::ATTR_USERNAME);
        $realm = $msg->getAttribute(StunMessage::ATTR_REALM);
        $nonce = $msg->getAttribute(StunMessage::ATTR_NONCE);

        if ($username === null || $realm === null || $nonce === null) {
            $this->send401($msg, $ip, $port);

            return;
        }

        if (! $this->verifyCredentials($msg, $username, $realm)) {
            $this->send401($msg, $ip, $port);

            return;
        }

        $key = "$ip:$port";

        if (! isset($this->allocations[$key])) {
            $this->sendError($msg, 437, 'Allocation Mismatch', $ip, $port);

            return;
        }

        $lifetime = $msg->getAttribute(StunMessage::ATTR_LIFETIME) ?? $this->allocationTtl;

        if ($lifetime === 0) {
            @socket_close($this->allocations[$key]->relaySocket);
            unset($this->usedRelayPorts[$this->allocations[$key]->relayPort]);
            unset($this->allocations[$key]);

            Log::info("[TURN] Deallocated {$ip}:{$port}");
        } else {
            $this->allocations[$key]->refresh((int) $lifetime);
        }

        $resp = new StunMessage;
        $resp->type = StunMessage::REFRESH_RESPONSE;
        $resp->transactionId = $msg->transactionId;
        $resp->addLifetime((int) $lifetime);

        $hmacKey = StunMessage::makeKey($this->username, $this->realm, $this->password);
        $resp->addMessageIntegrity($hmacKey);

        $this->sendResponse($resp, $ip, $port);
    }

    private function handleSendIndication(StunMessage $msg, string $ip, int $port): void
    {
        $key = "$ip:$port";

        if (! isset($this->allocations[$key])) {
            return;
        }

        $peer = $msg->getAttribute(StunMessage::ATTR_XOR_PEER_ADDRESS);
        $data = $msg->getAttribute(StunMessage::ATTR_DATA);

        if ($peer === null || $data === null) {
            return;
        }

        $alloc = $this->allocations[$key];

        if (! isset($alloc->permissions[$peer['ip']])) {
            Log::debug("[TURN] Send to {$peer['ip']} denied — no permission");

            return;
        }

        @socket_sendto($alloc->relaySocket, $data, strlen($data), 0, $peer['ip'], $peer['port']);
    }

    private function handleCreatePermission(StunMessage $msg, string $ip, int $port): void
    {
        $username = $msg->getAttribute(StunMessage::ATTR_USERNAME);
        $realm = $msg->getAttribute(StunMessage::ATTR_REALM);
        $nonce = $msg->getAttribute(StunMessage::ATTR_NONCE);

        if ($username === null || $realm === null || $nonce === null) {
            $this->send401($msg, $ip, $port);

            return;
        }

        if (! $this->verifyCredentials($msg, $username, $realm)) {
            $this->send401($msg, $ip, $port);

            return;
        }

        $key = "$ip:$port";

        if (! isset($this->allocations[$key])) {
            $this->sendError($msg, 437, 'Allocation Mismatch', $ip, $port);

            return;
        }

        $peer = $msg->getAttribute(StunMessage::ATTR_XOR_PEER_ADDRESS);

        if ($peer !== null) {
            $this->allocations[$key]->permissions[$peer['ip']] = true;
        }

        $resp = new StunMessage;
        $resp->type = StunMessage::CREATE_PERMISSION_RESPONSE;
        $resp->transactionId = $msg->transactionId;

        $hmacKey = StunMessage::makeKey($this->username, $this->realm, $this->password);
        $resp->addMessageIntegrity($hmacKey);

        $this->sendResponse($resp, $ip, $port);
    }

    private function handleRelayInbound(TurnAllocation $alloc): void
    {
        $data = '';
        $peerIp = '';
        $peerPort = 0;

        $bytes = socket_recvfrom($alloc->relaySocket, $data, 65535, 0, $peerIp, $peerPort);

        if ($bytes === false || $bytes === 0) {
            return;
        }

        $ind = new StunMessage;
        $ind->type = StunMessage::DATA_INDICATION;
        $ind->transactionId = random_bytes(12);

        $ind->addXorMappedAddress($peerIp, $peerPort);
        $ind->attributes[StunMessage::ATTR_XOR_PEER_ADDRESS] = ['ip' => $peerIp, 'port' => $peerPort];
        $ind->attributes[StunMessage::ATTR_DATA] = $data;

        $this->sendResponse($ind, $alloc->clientIp, $alloc->clientPort);
    }

    private function verifyCredentials(StunMessage $msg, string $username, string $realm): bool
    {
        if ($username !== $this->username || $realm !== $this->realm) {
            return false;
        }

        $key = StunMessage::makeKey($this->username, $this->realm, $this->password);

        return $msg->verifyMessageIntegrity($key);
    }

    private function send401(StunMessage $req, string $ip, int $port): void
    {
        $resp = new StunMessage;
        $resp->type = StunMessage::ALLOCATE_ERROR;
        $resp->transactionId = $req->transactionId;

        $resp->addErrorCode(401, 'Unauthorised');
        $resp->addRealm($this->realm);
        $resp->addNonce($this->nonce);

        $this->sendResponse($resp, $ip, $port);
    }

    private function sendError(StunMessage $req, int $code, string $reason, string $ip, int $port): void
    {
        $resp = new StunMessage;
        $resp->type = StunMessage::ALLOCATE_ERROR;
        $resp->transactionId = $req->transactionId;

        $resp->addErrorCode($code, $reason);

        $this->sendResponse($resp, $ip, $port);
    }

    private function sendResponse(StunMessage $msg, string $ip, int $port): void
    {
        $data = $msg->serialize();

        if ($this->logPackets) {
            Log::debug('[TURN] >> '.strlen($data)."b to {$ip}:{$port}");
        }

        socket_sendto($this->socket, $data, strlen($data), 0, $ip, $port);
    }

    private function expireAllocations(): void
    {
        foreach ($this->allocations as $key => $alloc) {
            if ($alloc->isExpired()) {
                @socket_close($alloc->relaySocket);
                unset($this->usedRelayPorts[$alloc->relayPort]);
                unset($this->allocations[$key]);
                Log::info("[TURN] Expired allocation for {$alloc->clientIp}:{$alloc->clientPort}");
            }
        }
    }

    private function allocateRelayPort(): int
    {
        for ($p = $this->relayMinPort; $p <= $this->relayMaxPort; $p++) {
            if (! in_array($p, $this->usedRelayPorts, true)) {
                $this->usedRelayPorts[] = $p;

                return $p;
            }
        }

        throw new RuntimeException('Relay port pool exhausted');
    }

    private function openRelaySocket(int $port): \Socket
    {
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($sock === false) {
            throw new RuntimeException('socket_create() failed for relay: '.socket_strerror(socket_last_error()));
        }

        if (! socket_bind($sock, '0.0.0.0', $port)) {
            socket_close($sock);
            throw new RuntimeException("socket_bind(0.0.0.0:{$port}) failed: ".socket_strerror(socket_last_error($sock)));
        }

        // Must be non-blocking — a blocking relay socket stalls the entire event loop
        socket_set_nonblock($sock);

        return $sock;
    }

    private function reportMemoryIfDue(): void
    {
        if (! $this->logPackets) {
            return;
        }

        $now = time();

        if ($now - $this->lastMemoryReport < 60) {
            return;
        }

        $this->lastMemoryReport = $now;

        $mb = round(memory_get_usage(true) / 1_048_576, 2);
        $peak = round(memory_get_peak_usage(true) / 1_048_576, 2);

        Log::info("[TURN] Memory: {$mb} MB (peak {$peak} MB) | Allocations: ".count($this->allocations));
    }

    private function allocationForRelay(\Socket $sock): ?TurnAllocation
    {
        foreach ($this->allocations as $alloc) {
            if ($alloc->relaySocket === $sock) {
                return $alloc;
            }
        }

        return null;
    }
}
