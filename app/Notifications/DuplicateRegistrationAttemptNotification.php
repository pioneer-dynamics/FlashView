<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DuplicateRegistrationAttemptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ipAddress) {}

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
            ->subject('Already Have an Account?')
            ->line('It looks like you (or someone else) tried to create an account with this email address. You already have an account with us.')
            ->line('If you were trying to sign up again, you can log in instead.')
            ->action('Log In', route('login'))
            ->line('Forgot your password? [Reset it here]('.route('password.request').').')
            ->line('If this was not you, no action is needed — your account is secure.');
    }
}
