<?php

namespace App\Contracts;

interface TurnProvider
{
    /**
     * @param  int|null  $ttlSeconds  Credential lifetime in seconds. Null = driver default. Ignored by providers that manage TTL server-side (Metered, Xirsys).
     * @return array<int, array{urls: string|string[], username?: string, credential?: string}>
     */
    public function getIceServers(?int $ttlSeconds = null): array;
}
