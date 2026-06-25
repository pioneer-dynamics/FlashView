<?php

use App\Mail\NewSecretNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('envelope has correct subject', function () {
    $user = User::factory()->create(['email' => 'sender@example.com']);
    $mailable = new NewSecretNotification($user, 'https://example.com/secret', 'abc123');

    $envelope = $mailable->envelope();

    $this->assertStringContainsString('sender@example.com', $envelope->subject);
});

test('content uses correct markdown template', function () {
    $user = User::factory()->create();
    $mailable = new NewSecretNotification($user, 'https://example.com/secret', 'abc123');

    $content = $mailable->content();

    expect($content->markdown)->toEqual('emails.NewSecretNotification');
});

test('mailable has correct properties', function () {
    $user = User::factory()->create();
    $url = 'https://example.com/secret/xyz';
    $secretId = 'hash123';

    $mailable = new NewSecretNotification($user, $url, $secretId);

    expect($mailable->url)->toEqual($url);
    expect($mailable->secret_id)->toEqual($secretId);
    expect($mailable->user->is($user))->toBeTrue();
});

test('attachments returns empty array', function () {
    $user = User::factory()->create();
    $mailable = new NewSecretNotification($user, 'https://example.com/secret', 'abc123');

    expect($mailable->attachments())->toBeEmpty();
});
