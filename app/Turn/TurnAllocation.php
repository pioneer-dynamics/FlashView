<?php

namespace App\Turn;

class TurnAllocation
{
    public function __construct(
        public readonly string $clientIp,
        public readonly int $clientPort,
        public readonly \Socket $relaySocket,
        public readonly string $relayIp,
        public readonly int $relayPort,
        public readonly int $ttl,
        public int $expiresAt,
        /** @var array<string, true> keyed by peer IP */
        public array $permissions = [],
    ) {}

    public function isExpired(): bool
    {
        return time() > $this->expiresAt;
    }

    public function refresh(int $ttl): void
    {
        $this->expiresAt = time() + $ttl;
    }
}
