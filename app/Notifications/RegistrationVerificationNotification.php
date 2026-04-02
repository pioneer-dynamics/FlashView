<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $signedUrl) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email to Complete Registration')
            ->line('Click the button below to verify your email address and complete your registration.')
            ->action('Complete Registration', $this->signedUrl)
            ->line('This link will expire in 2 hours.')
            ->line('If you did not request this, no action is needed.');
    }
}
