# FlashView

Secure, encrypted secret sharing with zero-knowledge architecture. Share passwords, API keys, and sensitive information through self-destructing links that expire automatically.

## About

FlashView is a web application for sharing secrets securely. Messages are encrypted in the browser using AES-GCM before being sent to the server, ensuring that plaintext data never touches the backend. Secrets are accessed via signed URLs and automatically expire after a configurable time period.

## Features

- **Client-side encryption** — AES-GCM encryption with PBKDF2 key derivation happens entirely in the browser
- **Zero-knowledge architecture** — the server never sees plaintext secrets
- **Automatic expiry** — secrets self-destruct after a configurable duration (5 minutes to 30 days)
- **Signed URLs** — secure, tamper-proof links for accessing secrets
- **Optional password protection** — add an extra layer of security to shared secrets
- **Email notifications** — get notified when a secret is retrieved
- **Webhooks** — receive webhook events with HMAC-SHA256 signature verification
- **REST API** — programmatic secret management via authenticated API endpoints
- **CLI support** — create and manage secrets from the terminal
- **Passkey/WebAuthn** — passwordless authentication with biometrics and security keys
- **Two-factor authentication** — TOTP-based 2FA for account security
- **Subscription plans** — tiered access with Stripe billing

## Tech Stack

**Backend:** PHP 8.3+ / Laravel 13 / Inertia.js 2.0

**Frontend:** Vue 3 (Composition API) / Tailwind CSS 3 / Vite

**Encryption:** OpenCrypto (AES-GCM + PBKDF2, 64,000 iterations)

**Auth:** Laravel Fortify + Sanctum + WebAuthn/Passkeys

**Billing:** Laravel Cashier (Stripe)

**Queue:** Laravel Horizon

**Monitoring:** Laravel Nightwatch

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js and npm
- SQLite (default), MySQL, or PostgreSQL

### Installation

```bash
git clone https://github.com/pioneer-dynamics/FlashView.git
cd FlashView
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

### Running the Application

Start the development server with all services (PHP server, queue worker, log viewer, and Vite):

```bash
composer dev
```

This runs the following concurrently:
- `php artisan serve` — application server
- `php artisan queue:listen` — background job processing
- `php artisan pail` — real-time log viewer
- `npm run dev` — Vite dev server with HMR

## Development

### Available Commands

| Command | Description |
|---------|-------------|
| `composer dev` | Start all development services concurrently |
| `npm run dev` | Vite dev server only |
| `npm run build` | Production frontend build |
| `php artisan test` | Run the full test suite |
| `php artisan test --filter=testName` | Run a specific test |
| `./vendor/bin/pint` | Fix code style (Laravel Pint) |
| `php artisan horizon` | Start the Horizon queue dashboard |

### Docker (Laravel Sail)

As an alternative, you can use Laravel Sail for a Docker-based development environment:

```bash
./vendor/bin/sail up
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run build
```

> **Note:** When using Sail, update your `.env` database configuration to use MySQL or PostgreSQL instead of SQLite, matching the services defined in `docker-compose.yml`.

## Testing

The project uses PHPUnit for testing with SQLite in-memory databases.

```bash
# Run all tests
php artisan test

# Run unit tests only
php artisan test --testsuite Unit

# Run feature tests only
php artisan test --testsuite Feature

# Run a specific test file
php artisan test tests/Feature/SecretControllerTest.php

# Run a specific test method
php artisan test --filter=test_guest_can_create_secret
```

## Architecture Overview

### Client-Side Encryption

Secrets are encrypted in the browser before being transmitted to the server:

1. A random passphrase is generated (or provided by the user)
2. A cryptographic key is derived using PBKDF2 (64,000 iterations)
3. The message is encrypted with AES-GCM
4. Only the encrypted ciphertext is sent to and stored on the server
5. The decryption key is embedded in the shared URL fragment (never sent to the server)

### Background Jobs

- **ClearExpiredSecrets** — removes message content from expired secrets
- **PurgeMetadataForExpiredMessages** — cleans up metadata after a configurable retention period (default: 30 days)
- **SendWebhookNotification** — delivers webhook events with HMAC-SHA256 signatures

## Security

For information about reporting vulnerabilities, see [SECURITY.md](SECURITY.md).

## License

This project is licensed under the MIT License. See the [license page](resources/markdown/license.md) for details.
