<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use App\Notifications\DuplicateRegistrationAttemptNotification;
use App\Notifications\RegistrationVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_step1_new_email_redirects_to_success(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        Notification::fake();

        $response = $this->post('/register', [
            'email' => 'newuser@gmail.com',
        ]);

        $response->assertRedirect(route('register.success'));

        Notification::assertSentOnDemand(RegistrationVerificationNotification::class);
    }

    public function test_step1_existing_email_redirects_to_same_success(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create(['email' => 'existing@gmail.com']);

        $response = $this->post('/register', [
            'email' => 'existing@gmail.com',
        ]);

        $response->assertRedirect(route('register.success'));

        Notification::assertSentTo($user, DuplicateRegistrationAttemptNotification::class);
    }

    public function test_step1_responses_are_identical_for_new_and_existing_emails(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        Notification::fake();

        User::factory()->create(['email' => 'existing@gmail.com']);

        $responseExisting = $this->post('/register', [
            'email' => 'existing@gmail.com',
        ]);

        $responseNew = $this->post('/register', [
            'email' => 'brand-new@gmail.com',
        ]);

        $this->assertEquals($responseExisting->getStatusCode(), $responseNew->getStatusCode());
        $this->assertEquals($responseExisting->headers->get('Location'), $responseNew->headers->get('Location'));
    }

    public function test_step2_valid_signed_url_shows_registration_form(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->addMinutes(120),
            ['email' => 'newuser@gmail.com']
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
    }

    public function test_step2_expired_signed_url_is_rejected(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->subMinute(),
            ['email' => 'newuser@gmail.com']
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_step2_tampered_signed_url_is_rejected(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->addMinutes(120),
            ['email' => 'newuser@gmail.com']
        );

        // Tamper with the email parameter
        $tamperedUrl = str_replace('newuser%40gmail.com', 'hacker%40gmail.com', $signedUrl);

        $response = $this->get($tamperedUrl);

        $response->assertStatus(403);
    }

    public function test_step2_reused_link_after_registration_redirects_to_login(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $email = 'newuser@gmail.com';

        User::factory()->create(['email' => $email]);

        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->addMinutes(120),
            ['email' => $email]
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect(route('login'));
    }

    public function test_step2_unsigned_url_is_rejected(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register/complete?email=newuser@gmail.com');

        $response->assertStatus(403);
    }

    public function test_full_registration_flow(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        Notification::fake();

        // Step 1: Submit email
        $response = $this->post('/register', [
            'email' => 'newuser@gmail.com',
        ]);

        $response->assertRedirect(route('register.success'));

        // Step 2: Use signed URL to complete registration
        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->addMinutes(120),
            ['email' => 'newuser@gmail.com']
        );

        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'], $queryParams);

        $postUrl = route('register.complete.store')
            .'?email='.urlencode($queryParams['email'])
            .'&expires='.$queryParams['expires']
            .'&signature='.$queryParams['signature'];

        $response = $this->post($postUrl, [
            'email' => 'newuser@gmail.com',
            'name' => 'New User',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'newuser@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_register_route_resolves_to_custom_controller(): void
    {
        $route = app('router')->getRoutes()->getByName('register.store');

        $this->assertNotNull($route);
        $this->assertStringContainsString(
            RegisterController::class,
            $route->getActionName()
        );
    }

    public function test_success_page_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get(route('register.success'));

        $response->assertStatus(200);
    }

    public function test_step2_post_with_expired_signature_is_rejected(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->subMinute(),
            ['email' => 'newuser@gmail.com']
        );

        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'], $queryParams);

        $postUrl = route('register.complete.store')
            .'?email='.urlencode($queryParams['email'])
            .'&expires='.$queryParams['expires']
            .'&signature='.$queryParams['signature'];

        $response = $this->post($postUrl, [
            'email' => 'newuser@gmail.com',
            'name' => 'New User',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertStatus(403);
    }
}
