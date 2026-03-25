<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GuestInertiaPropsTest extends TestCase
{
    public function test_guest_does_not_have_auth_user_in_inertia_props(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user', null)
        );
    }
}
