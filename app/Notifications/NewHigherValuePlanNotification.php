<?php

namespace App\Notifications;

use App\Models\Plan;
use App\Services\FeatureRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class NewHigherValuePlanNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Plan $plan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $registry = app(FeatureRegistry::class);

        $features = collect($this->plan->features ?? [])
            ->filter(fn ($feature) => ($feature['type'] ?? 'missing') !== 'missing')
            ->filter(fn ($feature, $key) => $registry->has($key))
            ->map(function ($feature, $key) use ($registry) {
                $class = $registry->get($key);
                $config = $feature['config'] ?? [];

                return $feature['type'] === 'limit'
                    ? $class->resolveLabel($config)
                    : $class->label();
            })
            ->sortBy(fn ($_, $key) => $this->plan->features[$key]['order'] ?? 99)
            ->values();

        $currentPlan = $notifiable->resolvePlan();
        $currentPlanName = $currentPlan?->name ?? 'your current plan';

        $message = (new MailMessage)
            ->subject(__('Upgrade available: :name is now on :app', ['name' => $this->plan->name, 'app' => config('app.name')]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__("We've just launched :planName — a new plan on :app with more of what you need.", [
                'planName' => $this->plan->name,
                'app' => config('app.name'),
            ]))
            ->line(__('Available from A$:price/month, it\'s a step up from :currentPlan and includes:', [
                'price' => number_format($this->plan->price_per_month, 2),
                'currentPlan' => $currentPlanName,
            ]));

        foreach ($features as $featureLabel) {
            $message->line('• '.$featureLabel);
        }

        if ($this->plan->end_date) {
            $message->line(__('This is a limited-time offer, available until :date.', [
                'date' => $this->plan->end_date->format('j F Y'),
            ]));
        }

        return $message
            ->action(__('View Plans'), url(route('plans.index')))
            ->line(__('Thank you for using :app', ['app' => config('app.name')]));
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
