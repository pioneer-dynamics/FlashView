<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been suspended')
            ->line('Your account on '.config('app.name').' has been suspended by an administrator.')
            ->line('Your data remains secure and will be available if your account is reinstated.')
            ->action('Contact Support', 'mailto:'.config('mail.from.address'))
            ->line('If you believe this was done in error, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
