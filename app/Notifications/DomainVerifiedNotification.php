<?php

namespace App\Notifications;

use App\Models\SenderIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainVerifiedNotification extends Notification implements ShouldQueue
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
            ->subject(__('Your domain :domain has been verified', ['domain' => $this->identity->domain]))
            ->line(__('Your domain :domain has been successfully verified.', ['domain' => $this->identity->domain]))
            ->line(__('Your :company sender badge is now active on shared links.', ['company' => $this->identity->company_name]))
            ->action(__('View Settings'), url(route('user.settings.index)))
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
