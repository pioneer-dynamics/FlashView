<?php

namespace App\Turn;

use App\Contracts\TurnProvider;
use Illuminate\Support\Facades\Http;

class XirsysTurnProvider implements TurnProvider
{
    public function __construct(private readonly array $config) {}

    public function getIceServers(?int $ttlSeconds = null): array
    {
        $response = Http::withBasicAuth($this->config['api_key'], $this->config['secret'])
            ->put("https://global.xirsys.net/_turn/{$this->config['channel']}");

        if (! $response->successful()) {
            throw new \RuntimeException('Xirsys TURN API returned '.$response->status());
        }

        return data_get($response->json(), 'v.iceServers', []);
    }
}
