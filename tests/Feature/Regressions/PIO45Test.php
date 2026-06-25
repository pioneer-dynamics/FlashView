<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

uses(RefreshDatabase::class);

test('registration does not reveal existing email', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    User::factory()->create(['email' => 'taken@gmail.com']);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'taken@gmail.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    // The response must NOT expose validation errors revealing the email is taken.
    // With Fortify + Inertia, duplicate email returns a redirect with session errors.
    $response->assertSessionHasNoErrors();

    // Should not return a 422 validation error for duplicate email
    $this->assertNotEquals(422, $response->getStatusCode());
});
