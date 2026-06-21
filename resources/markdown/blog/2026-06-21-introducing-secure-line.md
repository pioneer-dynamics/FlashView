---
title: Introducing Secure Line — End-to-End Encrypted Audio Calls
date: 2026-06-21
slug: introducing-secure-line
excerpt: Secure Line is a time-limited, end-to-end encrypted audio call channel. Pay once, get a bridge number and call password, share them with your participant — no accounts, no apps, no recordings.
author: FlashView Team
tags: [secure-line, encryption, audio, privacy, webrtc]
---

Most tools that let you make a private call ask you to trust them. You sign in, they log the call, and you hope they mean it when they say end-to-end encrypted. The server always knows who you called and when.

Secure Line is different. There are no accounts to create, no logs to generate, and no recordings to subpoena. The call is encrypted between participants before it leaves their devices, and when the session ends, there is nothing left on the server.

## What Secure Line Is

Secure Line is a time-limited, end-to-end encrypted audio call channel. You purchase a session with a one-off Stripe payment — no subscription, no FlashView account required to host or to join. You receive a **bridge number** and a **call password**. You share both with the people you want to speak with. They visit the Secure Line page, enter the bridge number, enter the password, and the call connects — encrypted, directly between browsers, with no intermediary hearing the audio.

When the session window closes, the channel is gone. There is nothing to delete, nothing to revoke, and nothing to worry about.

## How It Differs From One-Time Secrets

FlashView's original feature — [one-time secrets](/) — solves a specific problem: sending a piece of sensitive data that self-destructs after it is read. That is the right tool for transmitting a password, an API key, or a private note to someone who needs it once. A one-time secret is also the recommended way to share Secure Line credentials, because the bridge number and call password together are sensitive and should not travel over an unencrypted channel.

Secure Line solves a different problem: having a private *conversation* rather than delivering a *message*. It is ephemeral in the same way — the session expires and nothing persists — but the medium is audio in real time, not text delivered asynchronously.

| | One-Time Secret | Secure Line |
|---|---|---|
| Medium | Text / file | Audio call |
| Delivery | Asynchronous | Real-time |
| Expires after | First read | Time window closes |
| Participant needs account? | No | No |

## Setting Up a Secure Line

### Step 1 — Buy a session

Visit [Secure Line](/calls) and click **Buy a Line**. No FlashView account or subscription is required — choose a session package and complete a one-off payment via Stripe.

### Step 2 — Generate your credentials

After payment, the browser immediately generates a call password and derives an encryption keypair from it — entirely in your browser. You never send your password to the server. The server only receives the public key it needs to verify participants' identity later.

You will see:

- **Bridge number** — a short code that identifies your call session
- **Call password** — the key to your session; share it only via a secure channel

Download the credentials as a text file or copy them directly. The call window starts from the moment you generate credentials, so share them promptly.

### Step 3 — Share the bridge number and password securely

The recommended way to share credentials is via a [FlashView one-time secret](/). Create a secret containing both the bridge number and call password, send your participant the link, and it self-destructs after they read it — so the credentials are never left sitting in an inbox or chat log.

### Step 4 — Your participant joins

Your participant visits [Secure Line](/calls), enters the bridge number under **Join a Line**, and enters the call password. No account, no app install, no browser extension. Just a modern browser.

Once participants are connected, the call is live.

## Use Cases

**Legal and medical consultations.** Conversations between lawyers, doctors, or counsellors and their clients carry strict confidentiality obligations. Secure Line gives both parties a channel that is encrypted end-to-end and leaves no server-side record of what was said.

**Remote teams handling sensitive topics.** Salary discussions, performance reviews, merger negotiations — conversations that should not be logged, recorded, or aggregated. Secure Line is single-use by design: there is no history to leak.

**Journalists and sources.** A source who cannot install Signal or does not want to register a phone number can join a Secure Line call from any browser. The journalist controls the session window; when it closes, the channel is gone.

**Client check-ins.** For consultants or freelancers discussing confidential client work, Secure Line provides a call that starts and ends cleanly, with no recordings retained by a third-party platform.

## Security Model

Here is exactly what the server stores for a Secure Line session:

- **Bridge number** — the session identifier, equivalent to a room name
- **Public key** — an Ed25519 public key derived from your call password, used to verify participants' identity at join time
- **Key salt** — a public PBKDF2 salt returned to the browser on demand; used alongside the call password to reproduce the keypair. It is not a secret.
- **Session window** — start and end timestamps
- **Participant IP addresses** — each participant's IP is recorded and encrypted at rest. This is the minimum required for abuse prevention and legal compliance.
- **Signal payloads** — short-lived encrypted messages used to establish the WebRTC connection (ICE candidates, SDP offers/answers, and per-participant AES key wrapping data)

The server does **not** store: your call password, any audio, or any decrypted signal data.

### How authentication works

When a participant enters the bridge number and call password to join, the server issues a random challenge. The participant's browser derives the same keypair from the call password and key salt, signs the challenge with the private key, and sends only the signature back. The server verifies the signature against the stored public key. The call password never reaches the server.

This is the same design used by modern passwordless authentication schemes — the secret that proves identity never travels over the wire.

### How audio encryption works

Once participants are authenticated, each browser generates an ephemeral ECDH keypair. These are exchanged via the signalling layer. A group AES-GCM session key is wrapped per-participant using their ECDH public key. The group key is used to encrypt audio tracks before they are transmitted via WebRTC.

The server relays signalling messages to establish the connection but never holds or derives the group AES key.

### TURN relay

WebRTC connections are peer-to-peer when the network allows it. When NAT or firewall constraints prevent a direct connection, audio is routed through a TURN relay server. TURN servers relay encrypted packets — they cannot decrypt the audio because they do not hold the AES session key. Credentials for TURN servers are time-limited and tied to the session window.

If TURN is unavailable on your network, the browser will warn you before the call connects.

## Get Started

Visit [Secure Line](/calls) to join a call you have been invited to, or click **Buy a Line** to start your own encrypted call session. No FlashView account or subscription required — Secure Line is available to anyone with a browser.

---

*Secure Line uses the same `flashview-crypto` package that powers all FlashView encryption. The source is open on [GitHub](https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-crypto).*
