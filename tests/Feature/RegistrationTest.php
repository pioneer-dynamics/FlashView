<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use App\Notifications\DuplicateRegistrationAttemptNotification;
use App\Notifications\RegistrationVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

uses(RefreshDatabase::class);

test('registration screen can be rendered', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('registration screen cannot be rendered if support is disabled', function () {
    if (Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is enabled.');
    }

    $response = $this->get('/register');

    $response->assertStatus(404);
});

test('step1 new email redirects to success', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    Notification::fake();

    $response = $this->post('/register', [
        'email' => 'newuser@gmail.com',
    ]);

    $response->assertRedirect(route('register.success'));

    Notification::assertSentOnDemand(RegistrationVerificationNotification::class);
});

test('step1 existing email redirects to same success', function () {
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
});

test('step1 responses are identical for new and existing emails', function () {
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

    expect($responseNew->getStatusCode())->toEqual($responseExisting->getStatusCode());
    expect($responseNew->headers->get('Location'))->toEqual($responseExisting->headers->get('Location'));
});

test('step2 valid signed url shows registration form', function () {
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
});

test('step2 expired signed url is rejected', function () {
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
});

test('step2 tampered signed url is rejected', function () {
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
});

test('step2 reused link after registration redirects to login', function () {
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
});

test('step2 unsigned url is rejected', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $response = $this->get('/register/complete?email=newuser@gmail.com');

    $response->assertStatus(403);
});

test('full registration flow', function () {
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
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
});

test('register route resolves to custom controller', function () {
    $route = app('router')->getRoutes()->getByName('register.store');

    expect($route)->not->toBeNull();
    $this->assertStringContainsString(
        RegisterController::class,
        $route->getActionName()
    );
});

test('success page can be rendered', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $response = $this->get(route('register.success'));

    $response->assertStatus(200);
});

test('step2 post with expired signature is rejected', function () {
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
});
