<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegisterPageDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders_correct_component(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Register')
        );
    }

    public function test_register_complete_page_renders_correct_component(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->addMinutes(120),
            ['email' => 'newuser@example.com']
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/RegisterComplete')
            ->has('email')
            ->has('signedUrl')
        );
    }

    public function test_register_success_page_renders_correct_component(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get(route('register.success'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/RegisterSuccess')
        );
    }
}
