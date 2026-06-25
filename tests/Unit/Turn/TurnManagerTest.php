<?php

use App\Contracts\TurnProvider;
use App\Turn\FlashviewTurnProvider;
use App\Turn\MeteredTurnProvider;
use App\Turn\TurnManager;
use App\Turn\XirsysTurnProvider;

test('default driver comes from config', function () {
    config(['turn.default' => 'metered']);

    $manager = new TurnManager($this->app);

    expect($manager->getDefaultDriver())->toEqual('metered');
});

test('can resolve flashview driver', function () {
    config(['turn.drivers.flashview' => ['host' => 'turn.flashview.io', 'auth_secret' => 's', 'ttl' => 3600]]);

    $manager = new TurnManager($this->app);

    expect($manager->driver('flashview'))->toBeInstanceOf(FlashviewTurnProvider::class);
});

test('can resolve metered driver', function () {
    config(['turn.drivers.metered' => ['api_key' => 'k', 'domain' => 'd']]);

    $manager = new TurnManager($this->app);

    expect($manager->driver('metered'))->toBeInstanceOf(MeteredTurnProvider::class);
});

test('can resolve xirsys driver', function () {
    config(['turn.drivers.xirsys' => ['api_key' => 'k', 'secret' => 's', 'channel' => 'c']]);

    $manager = new TurnManager($this->app);

    expect($manager->driver('xirsys'))->toBeInstanceOf(XirsysTurnProvider::class);
});

test('can switch driver at runtime', function () {
    config([
        'turn.default' => 'metered',
        'turn.drivers.metered' => ['api_key' => 'k', 'domain' => 'd'],
        'turn.drivers.xirsys' => ['api_key' => 'k', 'secret' => 's', 'channel' => 'c'],
    ]);

    $manager = new TurnManager($this->app);

    expect($manager->driver('xirsys'))->toBeInstanceOf(TurnProvider::class);
    expect($manager->driver('xirsys'))->toBeInstanceOf(XirsysTurnProvider::class);
});
