<?php

namespace App\Turn;

use App\Contracts\TurnProvider;
use Illuminate\Support\Facades\Http;

class MeteredTurnProvider implements TurnProvider
{
    public function __construct(private readonly array $config) {}

    public function getIceServers(): array
    {
        $domain = $this->config['domain'];
        $apiKey = $this->config['api_key'];

        $response = Http::post(
            "https://{$domain}.metered.ca/api/v1/turn/credentials?apiKey={$apiKey}"
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Metered.ca TURN API returned '.$response->status());
        }

        return $response->json();
    }
}
