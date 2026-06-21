<?php

namespace App\Turn;

use App\Contracts\TurnProvider;
use Illuminate\Support\Manager;

class TurnManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('turn.default', 'metered');
    }

    public function createFlashviewDriver(): TurnProvider
    {
        return new FlashviewTurnProvider(config('turn.drivers.flashview'));
    }

    public function createMeteredDriver(): TurnProvider
    {
        return new MeteredTurnProvider(config('turn.drivers.metered'));
    }

    public function createXirsysDriver(): TurnProvider
    {
        return new XirsysTurnProvider(config('turn.drivers.xirsys'));
    }
}
