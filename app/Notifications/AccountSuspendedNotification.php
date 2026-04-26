<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject(__('Your account has been suspended'))
            ->line(__('Your account on :app has been suspended by an administrator.', ['app' => config('app.name')]))
            ->line(__('Your data remains secure and will be available if your account is reinstated.'))
            ->action(__('Contact Support'), 'mailto:'.config('support.email'))
            ->line(__('If you believe this was done in error, please contact our support team.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
