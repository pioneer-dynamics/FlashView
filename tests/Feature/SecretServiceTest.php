<?php

namespace Tests\Feature;

use App\Mail\NewSecretNotification;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use App\Models\User;
use App\Services\SecretService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecretServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecretService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SecretService::class);
    }

    public function test_create_secret_returns_secret_and_signed_url(): void
    {
        $result = $this->service->createSecret('encrypted-message', 60);

        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertInstanceOf(Secret::class, $result['secret']);
        $this->assertStringContainsString('signature=', $result['url']);
    }

    public function test_create_secret_with_null_user_id(): void
    {
        $result = $this->service->createSecret('encrypted-message', 60, null);

        $this->assertNull($result['secret']->user_id);
    }

    public function test_create_secret_sets_correct_expiry(): void
    {
        $result = $this->service->createSecret('encrypted-message', 120);

        $this->assertTrue(
            $result['secret']->expires_at->between(
                now()->addMinutes(119),
                now()->addMinutes(121)
            )
        );
    }

    public function test_create_secret_for_user(): void
    {
        $user = User::factory()->create();
        $result = $this->service->createSecret('encrypted-message', 60, $user->id);

        $this->assertEquals($user->id, $result['secret']->user_id);
    }

    public function test_list_secrets_returns_paginated_results(): void
    {
        $user = User::factory()->create();
        Secret::factory()->forUser($user)->count(3)->create();

        $result = $this->service->listSecrets($user);

        $this->assertCount(3, $result->items());
    }

    public function test_list_secrets_includes_expired_secrets(): void
    {
        $user = User::factory()->create();
        Secret::factory()->forUser($user)->create();
        Secret::factory()->forUser($user)->expired()->create();

        $result = $this->service->listSecrets($user);

        $this->assertCount(2, $result->items());
    }

    public function test_burn_secret_marks_as_retrieved(): void
    {
        $secret = Secret::factory()->create();

        $this->service->burnSecret($secret);

        $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
        $this->assertNull($secret->message);
        $this->assertNotNull($secret->retrieved_at);
    }

    public function test_notify_recipient_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->service->notifyRecipient($user, 'recipient@example.com', 'https://example.com/secret', 'abc123');

        Mail::assertQueued(NewSecretNotification::class, function ($mail) {
            return $mail->hasTo('recipient@example.com');
        });
    }
}
