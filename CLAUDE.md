# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FlashView (formerly OneTimeSecret) — a Laravel 12 + Vue 3 application for secure, encrypted secret sharing. Secrets are encrypted client-side using OpenCrypto (AES-GCM with PBKDF2), stored server-side, and accessed via signed URLs with automatic expiration.

## Commands

```bash
# Development (starts PHP server, queue, log tail, and Vite concurrently)
composer dev

# Frontend
npm run dev              # Vite dev server with HMR
npm run build            # Production build

# Tests
php artisan test                              # All tests
php artisan test --testsuite Unit             # Unit only
php artisan test --testsuite Feature          # Feature only
php artisan test tests/Feature/SomeTest.php   # Single file

# Linting
./vendor/bin/pint        # Laravel Pint (PHP code style fixer)

# Database
php artisan migrate      # Run migrations (SQLite by default)

# Setup
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
```

## Architecture

**Backend:** Laravel 12 with Inertia.js 2.0 bridge to Vue 3. Routes are in `routes/web.php` (primary) and `routes/api.php` (minimal, Sanctum-protected).

**Frontend:** Vue 3 Composition API with Inertia.js for SPA-like navigation. Pages in `resources/js/Pages/`, layouts in `resources/js/Layouts/`, components in `resources/js/Components/`. Styled with Tailwind CSS.

**Key Models:**
- `Secret` — core model; stores encrypted messages with expiry, IP tracking, and signed URL access. Scopes: `active()`, `expired()`, `readyToPrune()`
- `User` — extends Authenticatable with Jetstream (profiles/teams), Cashier (Stripe billing), Sanctum, and Passkey traits
- `Plan` — subscription plans with Stripe price IDs

**Client-Side Encryption (`resources/js/encryption.js`):**
- OpenCrypto library with AES-GCM encryption
- PBKDF2 key derivation (64,000 iterations)
- Random passphrase generation (8 words via `random-words`)
- Encryption happens in the browser before data reaches the server

**Config of note:**
- `config/secrets.php` — expiry options/limits, message length limits, prune settings, and rate limits (all vary by guest vs. authenticated user)
- `config/share.php` — Inertia shared data configuration

**Background Jobs:**
- `ClearExpiredSecrets` — clears message content from expired secrets
- `PurgeMetadataForExpiredMessages` — removes metadata after retention period

**Auth:** Laravel Fortify + Sanctum, with 2FA and WebAuthn/Passkey support (via `pioneer-dynamics/laravel-passkey`).

**Billing:** Laravel Cashier with Stripe for monthly/yearly subscription plans.

**Validation Rules:** `MessageLength` and `ValidExpiry` — both enforce different limits for guest vs. authenticated users.

## Git Workflow

- `master` is the main/release branch
- `develop` is the active development branch
- Uses git-flow style with `release/` branches
