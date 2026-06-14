<?php

namespace App\Facades;

use App\Turn\TurnManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getIceServers()
 * @method static \App\Contracts\TurnProvider driver(\UnitEnum|string|null $driver = null)
 *
 * @see TurnManager
 */
class Turn extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TurnManager::class;
    }
}
