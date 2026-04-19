<x-mail::message>
# Webhook Delivery Failed

Hi {{ $user->name }},

We were unable to deliver webhook notifications to **{{ $webhookHost }}** after multiple retry attempts over the last 24 hours. Delivery attempts for this event have stopped.

**Your webhook integration remains active** — future secret retrievals will continue to trigger delivery attempts as normal.

**What to check:**
- Your endpoint is reachable and responding with a 2xx status code.
- If your endpoint requires an API key or token, it may have expired or been rotated.

<x-mail::button :url="route('webhooks.index')">
Review Webhook Settings
</x-mail::button>

Thanks,
{{ config('app.name') }}
</x-mail::message>
