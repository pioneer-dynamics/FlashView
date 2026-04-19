<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WebhookDeliveryFailedMail extends Mailable
{
    public function __construct(public User $user, public string $webhookUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Webhook delivery permanently failed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.WebhookDeliveryFailedMail',
            with: [
                'webhookHost' => parse_url($this->webhookUrl, PHP_URL_HOST),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
