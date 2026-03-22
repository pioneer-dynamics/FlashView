# FlashView CLI

The {CONFIG:app.name} CLI lets you create and manage encrypted secrets directly from your terminal. Just like the web interface, secrets are encrypted on your machine before being sent to the server — your plaintext never leaves your device.

The CLI requires a {CONFIG:app.name} account with API access (available on paid plans). See [Pricing]({ROUTE:plans.index}) for plan details.

## Requirements

- Node.js 18 or later
- A {CONFIG:app.name} account with a plan that includes API access

## Installation

Install the CLI globally via npm:

```
npm install -g @pioneer-dynamics/flashview-cli
```

Or run it without installing using npx:

```
npx @pioneer-dynamics/flashview-cli --help
```

## Setup

You'll need an API token to use the CLI. Generate one from your [API Tokens]({ROUTE:api-tokens.index}) page (requires a plan with API access).

Then configure the CLI:

```
flashview configure set --token your-api-token
```

The default server URL is `https://flashview.link`. To use a self-hosted instance, pass `--url`:

```
flashview configure set --token your-api-token --url https://your-server.com
```

## Usage

### Create a Secret

```
# With an inline message
flashview create --message "my secret password"

# Pipe from stdin
echo "my secret" | flashview create

# With custom expiry (default: 1 day)
flashview create -m "secret" --expires-in 7d

# JSON output for scripting
flashview create -m "secret" --json
```

**Available expiry options:** 5m, 30m, 1h, 4h, 12h, 1d, 3d, 7d, 14d, 30d

After creating a secret, save the URL and passphrase immediately — they cannot be retrieved later.

### List Your Secrets

```
flashview list
flashview list --page 2
```

### Burn (Delete) a Secret

```
flashview burn <hash_id>
flashview burn <hash_id> --yes   # skip confirmation
```

## Security

- **End-to-end encryption:** Secrets are encrypted locally using AES-256-GCM with PBKDF2 key derivation (SHA-512, 64,000 iterations). The server never sees your plaintext.
- **Passphrases stay local:** Encryption passphrases are never sent to the server.
- **Token storage:** API tokens are stored in your OS config directory. On shared systems, set appropriate file permissions.

## Source Code

The CLI is open source. View the source and report issues on <a href="https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-cli" target="_blank" rel="noopener noreferrer">GitHub</a>.
