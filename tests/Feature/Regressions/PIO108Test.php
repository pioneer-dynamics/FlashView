<?php

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('renew get with html returns 404 to prevent account number in url', function () {
    Locker::factory()->create(['account_id' => '1080000001']);

    $response = $this->get(route('lockers.renew.challenge', '1080000001'));

    $response->assertStatus(404);
});

test('renew page renders without account id props', function () {
    $response = $this->get(route('lockers.renew'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Locker/Renew')
        ->missing('account_id')
    );
});
