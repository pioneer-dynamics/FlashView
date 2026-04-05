<?php

namespace App\Notifications;

use App\Models\SenderIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainLapsedNotification extends Notification implements ShouldQueue
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
            ->subject(__('Action needed: re-verify your domain :domain', ['domain' => $this->identity->domain]))
            ->line(__('We were unable to confirm your domain :domain during a routine check — your DNS TXT record could not be found.', ['domain' => $this->identity->domain]))
            ->line(__('Your sender badge has been paused until the domain is re-verified.'))
            ->line(__('Links you have already shared will continue to display your verified badge — only new links are affected.'))
            ->line(__('Please ensure your DNS TXT record is still correctly published, then click "Verify Domain" from your settings.'))
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
