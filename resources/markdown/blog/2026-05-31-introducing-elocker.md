---
title: Introducing eLocker — Anonymous Encrypted Long-Term Storage
date: 2026-05-31
slug: introducing-elocker
excerpt: eLocker is a zero-knowledge, anonymous, pre-paid storage locker. Like a Swiss numbered bank account, online — accessed by a 10-digit ID and passphrase, with no account, no email, and no way for us to see what's inside.
author: FlashView Team
tags: [elocker, encryption, privacy, anonymous]
---

## The Problem With Encrypted Storage

Most encrypted storage services have a fundamental tension with the privacy they promise. To store something securely, you sign up with an email address. To retrieve it later, you authenticate with a password tied to that account. The service now knows who you are, what you stored, and when you accessed it — even if they can't read the contents.

One-time secrets solve a different problem: ephemeral sharing. You create a secret, share the link, it's destroyed on read. That's the right tool for transmitting credentials or sensitive messages. But what if you need the secret to persist? What if you need to read it tomorrow, update it next month, and renew it next year — without creating an account?

That's what **eLocker** is for.

## What Is eLocker?

An eLocker is a long-term, anonymous, encrypted storage container. You access it with two things only:

- **Account ID** — a 10-digit number you choose (like `4815162342`)
- **Passphrase** — the decryption key, which never leaves your device

There is no username, no email address, no user account, and no way for us to recover anything if you lose your credentials. The server stores only ciphertext. We have never seen your passphrase, and we cannot read your content — by design.

Think of it like a Swiss numbered bank account, online. The number is your identity. The passphrase is the key to the vault. Nothing else is required.

## How It Works

### Buying a Locker

eLockers are pre-paid via a one-time Stripe payment. No subscription, no recurring charges.

| | 1 Year | 3 Years | 5 Years |
|---|---|---|---|
| Text (up to 100 KB — about 50 pages) | $20 | $50 | $80 |
| File (up to 50 MB — documents, images, archives) | $35 | $88 | $140 |

After payment, Stripe delivers a one-time credit token to your browser. That token is used exactly once to create your locker.

### Creating Your Locker

Creating a locker takes three steps in the browser:

1. **Choose your account ID** — any 10-digit number you can remember
2. **Choose your passphrase** — use the generator for a strong random one, or bring your own
3. **Enter your content or upload your file**

When you click *Encrypt & Create*, everything happens locally in your browser. Your content is encrypted with AES-256-GCM using a key derived from your passphrase via PBKDF2 (100,000 iterations, SHA-512). The resulting ciphertext — a self-contained blob with a version byte, type byte, salt, and IV all embedded — is what gets stored on our server.

We never see your passphrase. We never see your plaintext.

After creation, save your two credentials:

- **Account ID** — your locker's address
- **Passphrase** — the only key to decrypt, update, or delete your content

That's it. Your passphrase does everything. There is no separate "update token" to track — the same passphrase you use to read your locker is used to authorise modifications.

### Unlocking Your Locker

Click **eLocker → Access My Locker** in the navigation, enter your 10-digit account ID, and click *Open Locker*. On the locker page, enter your passphrase and click *Unlock*.

The unlock process uses a cryptographic challenge-response: the server issues a random challenge; your browser derives an HMAC-SHA-256 verifier from your passphrase and the challenge; only the verifier is sent to the server. Your passphrase never leaves your device. If the verifier matches, the encrypted payload is returned and decrypted locally in your browser.

Because passphrase verification is challenge-based, there is no rate limit for correct passwords. Only wrong attempts count toward the protection thresholds:

- Three wrong attempts → 5-minute cooldown per attempt
- After three consecutive failures → locker locked for 1 hour

Entering an account ID that doesn't exist produces the same response as a wrong passphrase, preventing account enumeration.

### Updating Your Locker

Once unlocked, an *Update Content* panel appears below the decrypted content. For text lockers, paste new text. For file lockers, pick a replacement file. Your passphrase — already entered during unlock — authorises the update. No extra token is needed.

### Renewing Your Locker

When your expiry is approaching, click **Renew** next to the expiry badge, or use **eLocker → Access My Locker** and choose *Renew* before opening. Enter your passphrase to complete a challenge-response authentication, choose a duration, and you'll be directed to Stripe to complete a new one-time payment.

## Zero-Knowledge by Design

We can verify this claim concretely. Here is the full list of what the server stores for each locker:

- `account_id` — the 10-digit number you chose
- `payload` — an opaque encrypted blob, useless without your passphrase
- `auth_challenge` — a random nonce, rotated after each renewal
- `auth_verifier` — an HMAC-SHA-256 of the challenge, derived from your passphrase (proves identity without revealing the passphrase)
- `update_token_hash` — a SHA-256 hash of a key derived from your passphrase (used to authorise updates and deletes)
- `expires_at` — the expiry timestamp

The server **does not store**: your passphrase, the encryption salt, the encryption IV, or any plaintext. The salt and IV are embedded inside the ciphertext blob itself, so they travel with the data and never exist separately on the server.

This means that even a complete compromise of our database would expose only encrypted ciphertext and a challenge verifier — both useless without the passphrase that never left your device.

## Fully Anonymous

There is no user account associated with an eLocker. No email address. No phone number. No identity verification. This is intentional: anonymity is the product, not a side effect.

Because there is no contact information on file, we cannot send expiry reminders. If your locker expires, it becomes inaccessible. We recommend noting your expiry date somewhere safe. Renewal is straightforward — click *Renew* on your locker page, enter your passphrase, and complete the Stripe payment.

## What Lockers Are Not

eLockers are not a replacement for one-time secrets. If you need to share a password with someone once, use [the secret flow](/). That's still the right tool for ephemeral, self-destructing messages.

eLockers are for data you want to persist, access repeatedly, and update over time — without tying that data to an identity.

## Get Started

Click **eLocker → Buy a Locker** in the navigation to see pricing and get started.

Text lockers start at $20 for one year. No account required.

---

*eLockers use the same `flashview-crypto` package that powers all FlashView encryption. The source is open on [GitHub](https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-crypto).*
