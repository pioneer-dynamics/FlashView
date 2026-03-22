# FlashView CLI

A command-line tool for creating and managing encrypted secrets via the FlashView API. Secrets are encrypted locally on your machine before being sent to the server — your plaintext never leaves your device.

## Requirements

- Node.js 18 or later

## Installation

```bash
npm install -g @pioneer-dynamics/flashview-cli
```

## Setup

The easiest way to get started is to log in via your browser:

```
flashview login
```

This opens your browser, lets you authenticate with your existing credentials (including 2FA and passkeys), choose token permissions, and automatically saves the API token to your CLI config. To update permissions later, just run `flashview login` again.

Alternatively, you can manually create a token from your [API Tokens](https://flashview.link/user/api-tokens) page and configure the CLI directly:

```
flashview configure set --token your-api-token
```

The default server URL is `https://flashview.link`. To use a self-hosted instance, pass `--url`:

```
flashview login --url https://your-server.com
flashview configure set --token your-api-token --url https://your-server.com
```

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
