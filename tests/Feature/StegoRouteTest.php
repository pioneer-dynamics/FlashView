<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StegoRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_stego_page_is_accessible_to_guests(): void
    {
        $response = $this->get(route('stego.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Secret/StegoPage'));
    }

    public function test_stego_page_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('stego.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Secret/StegoPage'));
    }
}
