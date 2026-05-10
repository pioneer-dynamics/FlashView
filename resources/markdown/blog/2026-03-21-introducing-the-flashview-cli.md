---
title: Introducing the FlashView CLI — Secrets From Your Terminal
date: 2026-03-21
slug: introducing-the-flashview-cli
excerpt: Create and retrieve end-to-end encrypted secrets without ever leaving your terminal. The FlashView CLI brings the same zero-knowledge encryption to your scripts and workflows.
author: FlashView Team
tags: [cli, encryption, terminal]
---

## Your Terminal, Now Encrypted

We've always believed that sensitive information shouldn't live in chat logs, emails, or notes apps. FlashView gives you a one-time link that self-destructs on first read — and now that same capability is available directly from your terminal.

Today we're launching the **FlashView CLI**: a command-line tool that lets you create, retrieve, and manage encrypted secrets without ever touching a browser.

## Zero-Knowledge, Right in Your Shell

The CLI follows the same security model as the web interface. Secrets are encrypted on your machine before they're sent anywhere. The server never sees your plaintext — only ciphertext. The passphrase never leaves your device.

```bash
# Create a secret with an inline message
flashview create --message "db_password: s3cr3t!"

# Or pipe from stdin
echo "my-api-key" | flashview create

# Retrieve a secret
flashview get abc123 --passphrase "word-word-word-word"
```

When you create a secret, the CLI prints the link and passphrase:

```
Secret created.
  Link:       https://flashview.link/secret/abc123
  Passphrase: coral-badge-river-light
```

Save these immediately — the passphrase is not stored anywhere, and the secret is gone after its first retrieval.

## Built for Scripts and Automation

The CLI is designed to slot into pipelines without friction. Pass `--json` to get machine-readable output:

```bash
flashview create --message "$(cat .env)" --json
# → {"link":"https://...","passphrase":"...","message_id":"..."}
```

This makes it straightforward to generate a secret and pipe the link directly into a Slack notification, a deployment script, or a CI workflow.

## Expiry Control

Secrets expire automatically. The default is 1 day, but you can set anything from 5 minutes to 30 days:

```bash
flashview create -m "temporary token" --expires-in 30m
flashview create -m "quarterly report" --expires-in 14d
```

**Available expiry options:** `5m`, `30m`, `1h`, `4h`, `12h`, `1d`, `3d`, `7d`, `14d`, `30d`

## Check and Manage Your Secrets

```bash
# Check whether a secret has been read
flashview status abc123

# List your recent secrets
flashview list

# Delete a secret before it's been read
flashview burn abc123
```

## Installation

The CLI is available as a pre-built binary (no Node.js required) or via npm:

```bash
npm install -g @pioneer-dynamics/flashview-cli
```

Download binaries for macOS, Linux, and Windows from the [GitHub Releases](https://github.com/pioneer-dynamics/FlashView/releases) page.

Once installed, authenticate with your FlashView account:

```bash
flashview login
```

This opens your browser, authenticates you (including 2FA and passkeys), and saves a token locally. You're ready to go.

---

The CLI is available today for all paid plan subscribers. Full documentation is on the [CLI page](/cli). We'd love to hear how you're using it — join us on [Discord](https://discord.gg/hGv6XKHuKR).
