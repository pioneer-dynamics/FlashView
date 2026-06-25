<?php

use App\Mail\NewSecretNotification;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use App\Models\User;
use App\Services\SecretService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SecretService::class);
});

test('create secret returns secret and signed url', function () {
    $result = $this->service->createSecret('encrypted-message', 60);

    expect($result)->toHaveKey('secret');
    expect($result)->toHaveKey('url');
    expect($result['secret'])->toBeInstanceOf(Secret::class);
    $this->assertStringContainsString('signature=', $result['url']);
});

test('create secret with null user id', function () {
    $result = $this->service->createSecret('encrypted-message', 60, null);

    expect($result['secret']->user_id)->toBeNull();
});

test('create secret sets correct expiry', function () {
    $result = $this->service->createSecret('encrypted-message', 120);

    expect($result['secret']->expires_at->between(
        now()->addMinutes(119),
        now()->addMinutes(121)
    ))->toBeTrue();
});

test('create secret for user', function () {
    $user = User::factory()->create();
    $result = $this->service->createSecret('encrypted-message', 60, $user->id);

    expect($result['secret']->user_id)->toEqual($user->id);
});

test('list secrets returns paginated results', function () {
    $user = User::factory()->create();
    Secret::factory()->forUser($user)->count(3)->create();

    $result = $this->service->listSecrets($user);

    expect($result->items())->toHaveCount(3);
});

test('list secrets includes expired secrets', function () {
    $user = User::factory()->create();
    Secret::factory()->forUser($user)->create();
    Secret::factory()->forUser($user)->expired()->create();

    $result = $this->service->listSecrets($user);

    expect($result->items())->toHaveCount(2);
});

test('burn secret marks as retrieved', function () {
    $secret = Secret::factory()->create();

    $this->service->burnSecret($secret);

    $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
    expect($secret->message)->toBeNull();
    expect($secret->retrieved_at)->not->toBeNull();
});

test('notify recipient sends email', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->service->notifyRecipient($user, 'recipient@example.com', 'https://example.com/secret', 'abc123');

    Mail::assertQueued(NewSecretNotification::class, function ($mail) {
        return $mail->hasTo('recipient@example.com');
    });
});
