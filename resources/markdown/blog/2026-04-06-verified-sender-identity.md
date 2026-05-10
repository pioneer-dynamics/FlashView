---
title: Verified Sender Identity — Recipients Know the Secret Is Really From You
date: 2026-04-06
slug: verified-sender-identity
excerpt: Add a verified badge to your secrets so recipients can confirm the link genuinely came from your organisation — not a phishing attempt. Now supported in the CLI too.
author: FlashView Team
tags: [security, verified-sender, cli]
---

## The Trust Problem With One-Time Links

One-time secret links are intentionally opaque — that's part of what makes them secure. But it creates a UX problem: when a recipient receives `https://flashview.link/secret/abc123`, how do they know it's from you and not a phishing attempt? They have to trust the channel it arrived through — email, Slack, whatever.

**Verified Sender Identity** solves this at the application layer.

## What Is a Verified Sender Badge?

When you verify your domain with FlashView, any secret you create can carry a badge on its decrypt page. The recipient sees your company name, domain, and a verified checkmark — signed and confirmed by FlashView — before they click "Reveal Secret".

It's the difference between:

> *"You have received a one-time secret."*

and:

> *"You have received a one-time secret from **Acme Corp** (acme.com) ✓ Verified"*

The recipient can immediately confirm the origin without relying solely on the email header or the Slack display name.

## How Domain Verification Works

1. Add a DNS TXT record to your domain (shown in your account settings).
2. FlashView checks the record — with automatic retry and exponential back-off if DNS propagation is slow.
3. Once verified, your domain and company name are locked to your account.
4. When you send a secret with `--with-verified-badge`, the badge appears on the decrypt page.

The verification is cryptographically tied to your account — no one else can impersonate your domain on FlashView.

## Using It From the CLI

Pass `--with-verified-badge` when creating a secret:

```bash
flashview create --message "your-api-key" --with-verified-badge
flashview create --file onboarding-credentials.pdf --with-verified-badge --email new-hire@company.com
```

You can also email the secret directly to the recipient using `--email`. FlashView sends the link on your behalf, and the decrypt page displays your verified sender badge alongside the recipient's address.

```bash
flashview create \
  --message "Temporary VPN password: Tr0ub4dor&3" \
  --email contractor@example.com \
  --with-verified-badge
```

## Why This Matters for Security Teams

Phishing attacks routinely abuse legitimate services to deliver malicious links. A recipient trained to look for the verified badge on FlashView secrets knows instantly if something is off — a secret from your company without the badge is a red flag.

For internal secrets (credentials, access tokens, one-time codes), the badge provides a lightweight audit confirmation: "yes, this came from our FlashView account."

## Getting Started

1. Go to **Settings → Sender Identity** and enter your company name and domain.
2. Add the TXT record shown to your DNS provider.
3. FlashView polls until the record resolves (usually within a few minutes).
4. Start using `--with-verified-badge` in the CLI or toggle it in the web interface.

Verified Sender Identity is available on all paid plans.
