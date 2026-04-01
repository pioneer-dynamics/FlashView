# FlashView CLI

A command-line tool for creating and managing encrypted secrets via the FlashView API. Secrets are encrypted locally on your machine before being sent to the server — your plaintext never leaves your device.

## Installation

### Option 1: Download pre-built binary (recommended if you don't have Node.js)

Download the latest binary for your platform from the [GitHub Releases](https://github.com/pioneer-dynamics/FlashView/releases) page.

The binary includes a bundled Node.js runtime and is approximately 70-90MB depending on your platform.

**macOS (Apple Silicon):**
```bash
chmod +x flashview-darwin-arm64
sudo mv flashview-darwin-arm64 /usr/local/bin/flashview
```

**Linux (x64):**
```bash
chmod +x flashview-linux-x64
sudo mv flashview-linux-x64 /usr/local/bin/flashview
```

**Linux (arm64):**
```bash
chmod +x flashview-linux-arm64
sudo mv flashview-linux-arm64 /usr/local/bin/flashview
```

**Windows:**
Rename `flashview-windows-x64.exe` to `flashview.exe` and add its directory to your PATH.

**Verify download integrity:**
Each release includes a `checksums-sha256.txt` file. Verify your download:
```bash
sha256sum --check checksums-sha256.txt
```

**macOS Gatekeeper:** If you see "this app is from an unidentified developer", right-click the binary and select "Open", or run:
```bash
xattr -d com.apple.quarantine /usr/local/bin/flashview
```

**Windows SmartScreen:** If Windows Defender SmartScreen blocks the binary, click "More info" then "Run anyway". The binary is a modified Node.js executable which may trigger false positives.

### Option 2: Install via npm (requires Node.js 20+)

```bash
npm install -g @pioneer-dynamics/flashview-cli
```

### How to upgrade

**Binary users:** Download the latest release from the [Releases](https://github.com/pioneer-dynamics/FlashView/releases) page and replace your existing binary. Run `flashview --version` to check your current version, or run `flashview update` to see if a newer version is available.

**npm users:**
```bash
flashview update
```

## Setup

The easiest way to get started is to log in via your browser:

```
flashview login
```

This opens your browser, lets you authenticate with your existing credentials (including 2FA and passkeys), choose token permissions, and automatically saves the API token to your CLI config. To update permissions later, just run `flashview login` again.

Alternatively, you can manually create a token from your [API Tokens](https://flashview.link/user/api-tokens) page and configure the CLI directly:

```
flashview config set --token your-api-token
```

The default server URL is `https://flashview.link`. To use a self-hosted instance, pass `--url`:

```
flashview login --url https://your-server.com
flashview config set --token your-api-token --url https://your-server.com
```

### View current configuration

```bash
flashview config show
```

### Clear stored configuration

```bash
flashview config clear
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
flashview burn <message_id>

# Skip confirmation
flashview burn <message_id> --yes

# JSON output
flashview burn <message_id> --json
```

## Security Notes

- Encryption is performed locally using AES-256-GCM with PBKDF2 key derivation (SHA-512, 64,000 iterations). The server never sees your plaintext.
- API tokens are stored in plaintext in your OS config directory (e.g., `~/.config/flashview-cli/config.json`). On shared systems, set appropriate file permissions: `chmod 600 ~/.config/flashview-cli/config.json`.
- Passphrases are never sent to the server.
