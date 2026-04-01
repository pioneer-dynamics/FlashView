# FlashView CLI

The {CONFIG:app.name} CLI lets you create and manage encrypted secrets directly from your terminal. Just like the web interface, secrets are encrypted on your machine before being sent to the server — your plaintext never leaves your device.

The CLI requires a {CONFIG:app.name} account with API access (available on paid plans). See [Pricing]({ROUTE:plans.index}) for plan details.

## Installation

### Option 1: Download pre-built binary (recommended if you don't have Node.js)

Download the latest binary for your platform from the <a href="https://github.com/pioneer-dynamics/FlashView/releases" target="_blank" rel="noopener noreferrer">GitHub Releases</a> page.

The binary includes a bundled Node.js runtime and is approximately 70-90MB depending on your platform.

**macOS (Apple Silicon):**
```
chmod +x flashview-darwin-arm64
sudo mv flashview-darwin-arm64 /usr/local/bin/flashview
```

**Linux (x64):**
```
chmod +x flashview-linux-x64
sudo mv flashview-linux-x64 /usr/local/bin/flashview
```

**Linux (arm64):**
```
chmod +x flashview-linux-arm64
sudo mv flashview-linux-arm64 /usr/local/bin/flashview
```

**Windows:**
Rename `flashview-windows-x64.exe` to `flashview.exe` and add its directory to your PATH.

**Verify download integrity:**
Each release includes a `checksums-sha256.txt` file. Verify your download:
```
sha256sum --check checksums-sha256.txt
```

**macOS Gatekeeper:** If you see "this app is from an unidentified developer", right-click the binary and select "Open", or run:
```
xattr -d com.apple.quarantine /usr/local/bin/flashview
```

**Windows SmartScreen:** If Windows Defender SmartScreen blocks the binary, click "More info" then "Run anyway". The binary is a modified Node.js executable which may trigger false positives.

### Option 2: Install via npm (requires Node.js 20+)

```
npm install -g @pioneer-dynamics/flashview-cli
```

Or run it without installing using npx:

```
npx @pioneer-dynamics/flashview-cli --help
```

### How to upgrade

**Binary users:** Download the latest release from the <a href="https://github.com/pioneer-dynamics/FlashView/releases" target="_blank" rel="noopener noreferrer">Releases</a> page and replace your existing binary. Run `flashview --version` to check your current version, or run `flashview update` to see if a newer version is available.

**npm users:**
```
flashview update
```

## Setup

The easiest way to get started is to log in via your browser:

```
flashview login
```

This opens your browser, lets you authenticate with your existing credentials (including 2FA and passkeys), choose token permissions, and automatically saves the API token to your CLI config. To update permissions later, just run `flashview login` again.

Alternatively, you can manually create a token from your [API Tokens]({ROUTE:api-tokens.index}) page and configure the CLI directly:

```
flashview config set --token your-api-token
```

The default server URL is `https://flashview.link`. To use a self-hosted instance, pass `--url`:

```
flashview login --url https://your-server.com
flashview config set --token your-api-token --url https://your-server.com
```

### View current configuration

```
flashview config show
```

### Clear stored configuration

```
flashview config clear
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

# With a specific passphrase
flashview create -m "secret" --passphrase "my-custom-passphrase"

# JSON output for scripting
flashview create -m "secret" --json
```

**Available expiry options:** 5m, 30m, 1h, 4h, 12h, 1d, 3d, 7d, 14d, 30d

After creating a secret, save the URL and passphrase immediately — they cannot be retrieved later.

### List Your Secrets

```
flashview list

# Paginated
flashview list --page 2

# JSON output
flashview list --json
```

### Burn (Delete) a Secret

```
flashview burn <message_id>

# Skip confirmation
flashview burn <message_id> --yes

# JSON output
flashview burn <message_id> --json
```

## Security

- **End-to-end encryption:** Secrets are encrypted locally using AES-256-GCM with PBKDF2 key derivation (SHA-512, 64,000 iterations). The server never sees your plaintext.
- **Passphrases stay local:** Encryption passphrases are never sent to the server.
- **Token storage:** API tokens are stored in plaintext in your OS config directory (e.g., `~/.config/flashview-cli/config.json`). On shared systems, set appropriate file permissions: `chmod 600 ~/.config/flashview-cli/config.json`.

## Source Code

The CLI is open source. View the source and report issues on <a href="https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-cli" target="_blank" rel="noopener noreferrer">GitHub</a>.
