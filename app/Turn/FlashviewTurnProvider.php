<?php

namespace App\Turn;

use App\Contracts\TurnProvider;

class FlashviewTurnProvider implements TurnProvider
{
    public function __construct(private readonly array $config) {}

    public function getIceServers(): array
    {
        $host = $this->config['host'];
        $ttl = $this->config['ttl'] ?? 3600;

        $expiry = time() + $ttl;
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
