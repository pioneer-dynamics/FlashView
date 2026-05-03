---
title: Webhook Notifications — Know When Your Secrets Are Read
date: 2026-03-25
slug: webhook-notifications
excerpt: Get an instant HTTP POST to your own endpoint the moment a recipient opens your secret. Build automations, audit trails, and integrations on top of FlashView's zero-knowledge delivery.
author: FlashView Team
tags: [webhooks, api, automation]
---

## The Problem With One-Time Links

One-time secrets are powerful precisely because they self-destruct on first read. But that leaves a gap: how do you know when the recipient actually opened it? Did the link arrive? Has it been retrieved yet? Was it read by the right person?

Until now, the only answer was to check the status manually. Today we're closing that gap with **webhook notifications**.

## How Webhooks Work

Configure a webhook endpoint once from your account settings. From that point on, FlashView sends an HTTP `POST` to your URL the moment any of your secrets is retrieved.

The payload is a JSON object with the key details:

```json
{
  "event": "secret.retrieved",
  "message_id": "abc123xyz",
  "retrieved_at": "2026-03-25T14:32:00Z",
  "recipient_email": "recipient@example.com"
}
```

You can inspect the delivery log, resend failed attempts, and rotate your webhook secret at any time from your settings page.

## What You Can Build

**Audit trail**: Write every retrieval event to your own database or logging service. Know exactly when sensitive credentials were accessed and by whom.

**Workflow triggers**: Kick off an automation the moment an onboarding link is opened — provision the account, send the follow-up email, rotate the credential.

**Alerts**: Post a message to Slack or Teams when a high-value secret is retrieved, so your team knows the handoff happened.

**Revocation pipelines**: If a secret carries a single-use token, trigger a rotation flow the instant it's been delivered — the window where the token is usable is minimised.

## Signature Verification

Every webhook delivery is signed with HMAC-SHA256 using a secret you control. Verify the `X-FlashView-Signature` header in your handler before processing the event — this prevents anyone other than FlashView from triggering your endpoint.

```php
$signature = hash_hmac('sha256', $rawBody, $webhookSecret);
if (!hash_equals('sha256='.$signature, $request->header('X-FlashView-Signature'))) {
    abort(403);
}
```

## Delivery Guarantees

FlashView retries failed deliveries with exponential back-off for up to 24 hours. If all retries are exhausted, you'll receive an email notification so you can investigate. The full delivery log — including HTTP status codes and response bodies — is available in your settings.

## Getting Started

1. Go to your [API Tokens](/user/api-tokens) page and create a token with `webhook:manage` permissions.
2. Navigate to **Settings → Webhooks** and enter your endpoint URL.
3. Copy the generated signing secret and add it to your handler.
4. Use the **Send Test** button to verify delivery end-to-end.

Webhook notifications are available on all paid plans. Full documentation is in [Webhooks](/webhooks).
