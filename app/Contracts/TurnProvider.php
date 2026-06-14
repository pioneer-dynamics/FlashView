<?php

namespace App\Contracts;

interface TurnProvider
{
    /**
     * @return array<int, array{urls: string|string[], username?: string, credential?: string}>
     */
    public function getIceServers(): array;
}
