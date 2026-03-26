<?php

namespace Tests\Unit\Mail;

use App\Mail\NewSecretNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewSecretNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->create(['email' => 'sender@example.com']);
        $mailable = new NewSecretNotification($user, 'https://example.com/secret', 'abc123');

        $envelope = $mailable->envelope();

        $this->assertStringContainsString('sender@example.com', $envelope->subject);
    }

    public function test_content_uses_correct_markdown_template(): void
    {
        $user = User::factory()->create();
        $mailable = new NewSecretNotification($user, 'https://example.com/secret', 'abc123');

        $content = $mailable->content();

        $this->assertEquals('emails.NewSecretNotification', $content->markdown);
    }

    public function test_mailable_has_correct_properties(): void
    {
        $user = User::factory()->create();
        $url = 'https://example.com/secret/xyz';
        $secretId = 'hash123';

        $mailable = new NewSecretNotification($user, $url, $secretId);

        $this->assertEquals($url, $mailable->url);
        $this->assertEquals($secretId, $mailable->secret_id);
        $this->assertTrue($mailable->user->is($user));
    }

    public function test_attachments_returns_empty_array(): void
    {
        $user = User::factory()->create();
        $mailable = new NewSecretNotification($user, 'https://example.com/secret', 'abc123');

        $this->assertEmpty($mailable->attachments());
    }
}
