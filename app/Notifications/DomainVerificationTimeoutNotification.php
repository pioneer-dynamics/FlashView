<?php

namespace App\Notifications;

use App\Models\SenderIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainVerificationTimeoutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SenderIdentity $identity) {}

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
            ->subject(__('Domain verification failed for :domain', ['domain' => $this->identity->domain]))
            ->line(__('We were unable to verify your domain :domain after 24 hours of retrying.', ['domain' => $this->identity->domain]))
            ->line(__('Please check that your DNS TXT record is correctly published, then click "Verify Domain" again from your settings.'))
            ->action(__('View Settings'), url(route('profile.show')))
            ->line(__('Thank you for using :app', ['app' => config('app.name')]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
