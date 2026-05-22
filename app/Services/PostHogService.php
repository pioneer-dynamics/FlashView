<?php

namespace App\Services;

use PostHog\PostHog;
use Throwable;

class PostHogService
{
    /**
     * Capture an analytics event.
     *
     * @param  array<string, mixed>  $properties
     */
    public function capture(string $distinctId, string $event, array $properties = []): void
    {
        if (config('posthog.disabled')) {
            return;
        }

        try {
            PostHog::capture([
                'distinctId' => $distinctId,
                'event' => $event,
                'properties' => $properties,
            ]);
        } catch (Throwable) {
            // Never let analytics failures affect the application.
        }
    }

    /**
     * Identify a user and set their person properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public function identify(string $distinctId, array $properties = []): void
    {
        if (config('posthog.disabled')) {
            return;
        }

        try {
            PostHog::identify([
                'distinctId' => $distinctId,
                'properties' => $properties,
            ]);
        } catch (Throwable) {
            // Never let analytics failures affect the application.
        }
    }
}
