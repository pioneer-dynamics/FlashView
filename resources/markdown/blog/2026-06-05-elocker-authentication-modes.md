---
title: eLocker — Choose How You Unlock Your Vault
date: 2026-06-05
slug: elocker-authentication-modes
excerpt: eLocker now supports three authentication modes — passphrase, key file, or both — plus a setting to control whether the unlock page reveals any hints about what credentials are required.
author: FlashView Team
tags: [elocker, encryption, authentication, security, privacy]
---

When eLocker launched, your vault was secured by a single passphrase — something you know. That model works well for most people. But for some use cases, a file you possess is a better key than a phrase you remember. And for the highest-security requirements, requiring both together means that neither stolen credential alone is enough.

Today, eLocker supports three authentication modes. You choose when you create your locker, and you can rotate to a different mode at any time.

## The Three Authentication Modes

### Passphrase

The original mode. You choose a passphrase (or use the built-in generator), and that phrase is the sole key to your locker. It derives the encryption key for your content, authorises updates and deletes, and is never transmitted to — or seen by — the server.

This is the right mode for personal use where you want something memorable and portable. No files to carry around, no extra steps.

### Key File

Instead of a passphrase, one or more files you already possess serve as the key. When you create the locker, you load the files. When you unlock, you load the same files in the same order. The cryptographic key is derived from the file contents via SHA-256; what matters is that the bytes are bit-for-bit identical every time.

A good key file is something unique to you and unlikely to change — a personal photo taken on a specific date, a document you generated and archived, or a purpose-generated binary file. Widely-shared files like stock images or downloaded attachments are poor choices, because if someone else obtains the same file, they can derive the same key.

**The order of key files is significant.** If you add three files, they must be loaded in exactly that order at unlock time. The credential download records the sequence, so save it carefully.

**There is no recovery.** If you lose your key file(s), the locker cannot be unlocked by anyone, including FlashView support. This is not a limitation we can work around — the file contents are the key, and we never see them.

### Combined (Passphrase + Key Files)

The most secure option: both your passphrase and all your key files are required to unlock. Compromising only one factor — an attacker who has your files but not your phrase, or who guesses your phrase but lacks the files — gains nothing.

This mode suits high-value or shared-access scenarios where defence in depth matters. Credential rotation lets you switch to a different passphrase, different files, or a different combination — all without losing access to your existing content.

## How Key Derivation Works

Regardless of which mode you choose, the unlock process is the same under the hood: all your credentials are combined into a single derived key before any crypto operation. For key-file modes, each file's contents are hashed with SHA-256 to produce a fixed-length material string. Multiple files are concatenated in order. In combined mode, the passphrase is prepended to the list of file materials.

That combined material is then fed through PBKDF2 — the same key-stretching algorithm used in passphrase-only mode — before it touches your encrypted content. So key files benefit from the same protections as passphrases: the derivation is expensive to brute-force, and the resulting key is independent of the raw file size or format.

## Show or Hide Unlock Hints

Every locker has a setting called **Show unlock hints**. When enabled (the default), the unlock page adapts to your authentication mode:

- It shows a passphrase input if your mode requires one.
- It shows a key file loader if your mode requires files, including a counter showing how many files are still needed.
- For combined mode, it explains that both credentials are required.

When you **disable** unlock hints, the page shows a generic interface: a passphrase field and a key file loader are both present, with no indication of what is actually required. Visitors who don't already know your credentials receive no clues about what to try.

This option exists for the cases where the credential type itself is sensitive information — for example, when you share access instructions through a separate secure channel, and you don't want the unlock page to confirm or deny anything about your setup to someone who only has your account ID.

### Controlling Hints During Creation

When you create a locker using key file or combined mode, you'll see a **Show unlock hints** checkbox in the form. Unchecking it configures the locker in hidden-hint mode from the start. If you choose passphrase-only mode, hints are always shown (there is nothing sensitive to reveal about the credential type in that case).

### Changing Hints After Creation

After unlocking your locker, a **Locker Settings** panel appears at the bottom of the page. The *Show unlock hints* toggle lets you change this setting at any time. The change takes effect immediately — the next visitor to your unlock page will see the updated interface.

## Rotating Credentials

You are not locked in to the credentials you chose at creation. The **Change Credentials** panel (visible after unlocking) lets you:

- Switch authentication mode (e.g., from passphrase to combined)
- Change your passphrase
- Replace your key files with a new set

When you rotate credentials, your content is re-encrypted with the new derived key. For text lockers this is immediate. For file lockers, the data encryption key (DEK) is re-wrapped with your new credentials — the encrypted file itself does not need to be downloaded and re-uploaded, which makes rotation fast even for large files.

After rotation, only the new credentials will unlock your locker. The old passphrase and old key files no longer work.

## Choosing a Mode

| Scenario | Recommended mode |
|---|---|
| Personal notes, memorable access | Passphrase |
| Shared access, file-based identity | Key file |
| High-value content, defence in depth | Combined |
| You want no hint of your setup visible | Any mode + disable hints |

If you're not sure, passphrase mode is a sensible default. You can always rotate to a different mode later without losing your content.

## Getting Started

Create a new locker at **eLocker → Buy a Locker**, or open an existing locker at **eLocker → Access My Locker**. The authentication mode selector appears in the creation form, and the hints toggle appears in Locker Settings after you unlock.

---

*All key derivation and encryption happens in your browser using the open-source [`flashview-crypto`](https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-crypto) package. The server sees only ciphertext — never your passphrase, your file contents, or the derived encryption key.*
