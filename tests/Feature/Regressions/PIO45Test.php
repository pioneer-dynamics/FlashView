<?php

namespace Tests\Feature\Regressions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

/**
 * PIO-45: Email enumeration on signup page — POST /register with an existing
 * email returns "The email has already been taken", allowing attackers to
 * enumerate registered users.
 */
class PIO45Test extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_reveal_existing_email(): void
    {
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
    }
}
