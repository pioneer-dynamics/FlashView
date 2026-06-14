<?php

namespace Tests\Unit\Turn;

use App\Contracts\TurnProvider;
use App\Turn\MeteredTurnProvider;
use App\Turn\TurnManager;
use App\Turn\XirsysTurnProvider;
use Tests\TestCase;

class TurnManagerTest extends TestCase
{
    public function test_default_driver_comes_from_config(): void
    {
        config(['turn.default' => 'metered']);

        $manager = new TurnManager($this->app);

        $this->assertEquals('metered', $manager->getDefaultDriver());
    }

    public function test_can_resolve_metered_driver(): void
    {
        config(['turn.drivers.metered' => ['api_key' => 'k', 'domain' => 'd']]);

        $manager = new TurnManager($this->app);

        $this->assertInstanceOf(MeteredTurnProvider::class, $manager->driver('metered'));
    }

    public function test_can_resolve_xirsys_driver(): void
    {
        config(['turn.drivers.xirsys' => ['api_key' => 'k', 'secret' => 's', 'channel' => 'c']]);

        $manager = new TurnManager($this->app);

        $this->assertInstanceOf(XirsysTurnProvider::class, $manager->driver('xirsys'));
    }

    public function test_can_switch_driver_at_runtime(): void
    {
        config([
            'turn.default' => 'metered',
            'turn.drivers.metered' => ['api_key' => 'k', 'domain' => 'd'],
            'turn.drivers.xirsys' => ['api_key' => 'k', 'secret' => 's', 'channel' => 'c'],
        ]);

        $manager = new TurnManager($this->app);

        $this->assertInstanceOf(TurnProvider::class, $manager->driver('xirsys'));
        $this->assertInstanceOf(XirsysTurnProvider::class, $manager->driver('xirsys'));
    }
}
