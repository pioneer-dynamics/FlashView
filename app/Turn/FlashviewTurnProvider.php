<?php

namespace App\Turn;

use App\Contracts\TurnProvider;

class FlashviewTurnProvider implements TurnProvider
{
    public function __construct(private readonly array $config) {}

    public function getIceServers(?int $ttlSeconds = null): array
    {
        $host = $this->config['host'];
        $expiry = time() + ($ttlSeconds ?? $this->config['ttl'] ?? 3600);
        $username = $expiry.':flashview';
        $credential = base64_encode(hash_hmac('sha1', $username, $this->config['auth_secret'], true));

        return [
            [
                'urls' => "turn:{$host}:3478",
                'username' => $username,
                'credential' => $credential,
            ],
            [
                'urls' => "stun:{$host}:3478",
            ],
        ];
    }
}
