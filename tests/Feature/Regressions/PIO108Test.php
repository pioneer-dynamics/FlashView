<?php

namespace Tests\Feature\Regressions;

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PIO108Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug 2: GET /{accountId}/renew with HTML accept header should return 404
     * after fix (renewChallenge becomes JSON-only).
     */
    public function test_renew_get_with_html_returns_404_to_prevent_account_number_in_url(): void
    {
        Locker::factory()->create(['account_id' => '1080000001']);

        $response = $this->get(route('lockers.renew.challenge', '1080000001'));

        $response->assertStatus(404);
    }

    /**
     * Bug 2: GET /lockers/renew (static page, no account ID in URL) should render
     * the Inertia Locker/Renew component without exposing account_id as a prop.
     */
    public function test_renew_page_renders_without_account_id_props(): void
    {
        $response = $this->get(route('lockers.renew'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Locker/Renew')
            ->missing('account_id')
        );
    }
}
