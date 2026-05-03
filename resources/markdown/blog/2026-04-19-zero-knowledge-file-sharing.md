---
title: Zero-Knowledge File Sharing — Send Files That Self-Destruct
date: 2026-04-19
slug: zero-knowledge-file-sharing
excerpt: Share sensitive files with the same zero-knowledge encryption and one-time delivery that FlashView has always applied to text secrets. The server never sees your file content.
author: FlashView Team
tags: [files, encryption, zero-knowledge]
---

## Files Deserve the Same Privacy as Passwords

You've been using FlashView to share passwords, API keys, and sensitive notes. But what about the onboarding document with the employee's SSN? The signed NDA? The client credentials PDF?

Until today, those had to go through email or a file host — both of which hold on to your data indefinitely and can be subpoenaed, breached, or accidentally shared.

Today we're launching **zero-knowledge file sharing**: the same one-time, self-destructing model you rely on for text, now for files.

## How It Works

File secrets work exactly like text secrets, with one addition: the file content is encrypted in your browser before it leaves your device. The server receives an encrypted blob. It has no idea what the file contains, what it's called in plaintext, or who created it.

When the recipient opens the link, their browser decrypts the file using the passphrase. They can optionally add a note alongside the file — both are encrypted together.

```
Secret created.
  Link:       https://flashview.link/secret/abc123
  Passphrase: coral-badge-river-light

The file and any accompanying note will be permanently deleted after first access.
```

## From the CLI

```bash
# Share a file
flashview create --file report.pdf

# Share a file with an accompanying note
flashview create --file report.pdf --message "Q1 financials — please delete after review"

# Email the link directly to the recipient
flashview create --file onboarding-pack.pdf --email new-hire@company.com

# Show upload progress for large files
flashview create --file large-video.mp4 --verbose
```

The `--verbose` flag shows a real-time progress bar for the encryption and upload steps — useful for large files.

## Supported File Types

`pdf`, `zip`, `gz`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`, `txt`, `csv`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `mp4`, `mov`, `mp3`, `wav`

The CLI will exit with an error if you attempt to share an unsupported type.

## Retrieve From the CLI or the Browser

Recipients with the CLI installed can retrieve the file directly:

```bash
flashview get abc123 --passphrase "coral-badge-river-light"
# Saves the file to the current directory using its original filename
```

Recipients without the CLI can open the link in any browser — no account required to decrypt.

## What the Server Never Sees

- The plaintext file content
- The original filename
- The encryption passphrase
- Any accompanying note

The server stores an encrypted blob keyed to the one-time session. When the recipient accesses the link, the encrypted blob is destroyed — regardless of whether decryption succeeds.

## Use Cases

**HR**: Share offer letters, I-9 forms, or SSN verification documents without them sitting in email.

**Finance**: Deliver signed contracts, financial statements, or account credentials to auditors.

**DevOps**: Send `.env` files, TLS certificates, or SSH private keys to contractors without leaving them in a chat history.

**Legal**: Transfer case files or discovery documents that should only be read once and disposed of.

## Getting Started

File secrets are available in both the web interface and the CLI. A FlashView account with API access is required to create file secrets. [See pricing](/plans) for plan details.
