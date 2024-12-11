<?php

namespace App\Notifications;

use App\Models\Secret;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SecretRetrievedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Secret $secret)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject(__('Your secret with Message ID :message_id was retrieved', ['message_id' => $this->secret->hash_id]))
                    ->line(__('Your secret with Message ID :message_id was retrieved.', ['message_id' => $this->secret->hash_id]))
                    ->line('The message has now been deleted from our server.')
                    ->action('My Message History', url(route('secrets.index')))
                    ->line('Thank you for using :app', ['app' => config('app.name')]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
