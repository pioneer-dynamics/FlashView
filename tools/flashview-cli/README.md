# FlashView CLI

A command-line tool for creating and managing encrypted secrets via the FlashView API. Secrets are encrypted locally on your machine before being sent to the server — your plaintext never leaves your device.

## Requirements

- Node.js 18 or later

## Installation

```bash
# From the repository
cd tools/flashview-cli
npm install
npm link

# Or install globally (when published)
npm install -g flashview-cli
```

## Setup

Configure your API token and server URL:

```bash
flashview configure set --url https://your-flashview-server.com --token your-api-token
```

You can generate an API token from your FlashView dashboard under **API Tokens** (requires a plan with API access).

### View current configuration

```bash
flashview configure show
```

### Clear stored configuration

```bash
flashview configure clear
```

## Usage

### Create a secret

```bash
# With inline message
flashview create --message "my secret password"

# Pipe from stdin
echo "my secret" | flashview create

# With custom expiry (default: 1d)
flashview create -m "secret" --expires-in 7d

# With a specific passphrase
flashview create -m "secret" --passphrase "my-custom-passphrase"

# JSON output for scripting
flashview create -m "secret" --json
```

**Expiry options:** `5m`, `30m`, `1h`, `4h`, `12h`, `1d`, `3d`, `7d`, `14d`, `30d`

After creating a secret, save the URL and passphrase immediately — they cannot be retrieved later.

### List secrets

```bash
flashview list

# Paginated
flashview list --page 2

# JSON output
flashview list --json
```

### Burn (delete) a secret

```bash
flashview burn <hash_id>

# Skip confirmation
flashview burn <hash_id> --yes

# JSON output
flashview burn <hash_id> --json
```

## Security Notes

- Encryption is performed locally using AES-256-GCM with PBKDF2 key derivation (SHA-512, 64,000 iterations). The server never sees your plaintext.
- API tokens are stored in plaintext in your OS config directory (e.g., `~/.config/flashview-cli/config.json`). On shared systems, set appropriate file permissions: `chmod 600 ~/.config/flashview-cli/config.json`.
- Passphrases are never sent to the server.
