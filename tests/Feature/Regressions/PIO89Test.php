<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user update with empty stripe id does not throw stripe exception', function () {
    $this->expectNotToPerformAssertions();

    $user = User::factory()->create(['stripe_id' => '']);

    // Must not throw Stripe\Exception\InvalidArgumentException.
    $user->update(['name' => 'Updated Name']);
});
