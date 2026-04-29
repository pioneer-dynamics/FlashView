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

# JSON output (useful for scripting version checks)
flashview update --json
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

### Headless login (SSH / CI environments)

If no display server is detected (e.g. SSH sessions, CI pipelines), the CLI automatically falls back to a device code flow. You can also force it explicitly:

```
# Headless login — shows a QR code and short code to authorise from another device
flashview login --headless

# Custom browser-flow timeout (default: 120s)
flashview login --timeout 60
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

### Create a File Secret

```
# Share a file (requires authentication)
flashview create --file report.pdf

# Share a file with an accompanying note
flashview create --file report.pdf --message "Here is the Q1 report"

# Send the secret link to a recipient's email
flashview create --message "secret" --email recipient@example.com

# Include your verified sender identity badge
flashview create --message "secret" --with-verified-badge

# Show step-by-step progress (including upload progress bar for files)
flashview create --file large-video.mp4 --verbose
```

**Supported file types:** pdf, zip, gz, doc, docx, xls, xlsx, ppt, pptx, txt, csv, jpg, jpeg, png, gif, webp, mp4, mov, mp3, wav

The CLI will exit with an error if the file extension is not in this list. File secrets require a FlashView account with API access.

`--verbose` has no effect when `--json` is also passed.

### Retrieve a Secret

```
# Retrieve and decrypt a text secret
flashview get <messageId> --passphrase <passphrase>

# Save a file secret to a specific path (defaults to original filename in current directory)
flashview get <messageId> --passphrase <passphrase> --output /path/to/file

# Show step-by-step progress (useful for large files)
flashview get <messageId> --passphrase <passphrase> --verbose

# JSON output for scripting
flashview get <messageId> --passphrase <passphrase> --json
```

**Warning: Secrets are permanently destroyed on first access.** A wrong passphrase cannot be retried — the secret is already gone by the time decryption is attempted.

The passphrase is required. File secrets are saved to disk using their original filename (in the current directory by default). Combined secrets (file + note) print the note to stdout and save the file.

Retrieving secrets via the CLI requires a FlashView account with API access. Recipients without CLI access can still open the secret link in the web browser instead.

### Check Secret Status

```
flashview status <messageId>

# JSON output
flashview status <messageId> --json
```

Shows whether a secret is **Active** (not yet retrieved, not expired), **Retrieved**, or **Expired**, along with its creation and expiry timestamps. If the secret has been retrieved, the retrieval timestamp is also shown.

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

## Pipe — E2E Encrypted Data Transfer Between Machines

The `pipe` command streams end-to-end encrypted data directly between two paired machines — composable with Unix pipes. No token is ever printed, shared, or typed after initial setup.

### How It Works

Both machines share a **pipe seed** (a 256-bit random key stored at `~/.flashview/pipe_config.json`). Each transfer consumes a monotonic counter:

- **Sender** derives a unique session key and session ID from the seed + counter, encrypts stdin in 64 KB chunks (AES-256-GCM), and uploads to the server.
- **Receiver** tries up to 20 counter values (look-ahead window), finds the session, derives the same session key, decrypts, and streams to stdout.

The server stores only `session_id` (a derived lookup key) and encrypted ciphertext — it never holds the seed or encryption key.

> **Dedicated pair model:** Each `flashview pipe setup` creates a dedicated two-machine pair. For a third machine, run `flashview pipe setup` again to create a separate pair.

### Initial Setup

**Primary: PKI-based pairing (both machines logged in to the same account)**

Run `flashview pipe setup` on both machines (Machine B first, Machine A second). The CLI exchanges identity keys via the server and displays a 6-digit pairing code on each side:

```
# Machine B (no seed yet) — run this first
flashview pipe setup
→ Device DEVB4E1 ready. Waiting for Machine A to pair... (Ctrl+C to cancel)

# Machine A (already has a seed, or first-time setup)
flashview pipe setup
→ Pairing code: 047-283 — confirm this matches Machine B, then press Enter to continue (Ctrl+C to abort)

# Machine B (after Machine A presses Enter)
→ Pairing code: 047-283 — does this match what Machine A shows? [y/N]
→ y
→ Paired! You can now use 'flashview pipe'.
```

Visually confirming the pairing code prevents server-in-the-middle attacks.

**Fallback: Manual export code (air-gapped or no account)**

```
# Machine A
flashview pipe setup export
→ FVPIPE-ABCD-EFGH-IJKL-MNOP
→ Copy this code to Machine B.

# Machine B
flashview pipe setup import FVPIPE-ABCD-EFGH-IJKL-MNOP
→ Paired via export code. You can now use 'flashview pipe'.
```

### Sending and Receiving

```bash
# Send text
echo "hello world" | flashview pipe

# Send a tarball
tar cz ./my-directory | flashview pipe

# Send a file
cat large-file.bin | flashview pipe

# Receive to stdout
flashview pipe

# Receive and decompress
flashview pipe | tar xz

# Receive to a file
flashview pipe > output.bin
```

The receiver auto-discovers the session — no token needs to be copy-pasted.

### Options

| Flag | Default | Description |
|------|---------|-------------|
| `--verbose` | off | Show chunk count and transfer stats |
| `--chunk-size <kb>` | 64 | Chunk size in KB |
| `--expires-in <s>` | 600 | Session TTL in seconds (60–3600) |
| `--json` | off | Machine-readable output (sender confirms in JSON) |

```bash
# Verbose output (stats to stderr)
echo "data" | flashview pipe --verbose
flashview pipe --verbose
```

### Counter Drift Recovery

If the sender has run many transfers the receiver never consumed (drift beyond the 20-counter window), the receiver prints a recovery message. To resync:

```bash
# On the sender machine
flashview pipe setup sync
→ FVPIPE-XXXX-XXXX-XXXX-XXXX

# On the receiver machine
flashview pipe setup import FVPIPE-XXXX-XXXX-XXXX-XXXX
```

### Maintenance Commands

```bash
# Show current seed status and counter
flashview pipe setup show

# Re-export current seed+counter (for counter drift recovery)
flashview pipe setup sync
```

---

## Security

- **End-to-end encryption:** Secrets are encrypted locally using AES-256-GCM with PBKDF2 key derivation (SHA-512, 64,000 iterations). The server never sees your plaintext.
- **Pipe encryption:** Pipe transfers use HKDF-SHA-256 (per-counter key derivation) + AES-256-GCM. The server stores only encrypted ciphertext and a derived session ID — never the seed or key.
- **Passphrases stay local:** Encryption passphrases are never sent to the server.
- **Token storage:** API tokens are stored in plaintext in your OS config directory (e.g., `~/.config/flashview-cli/config.json`). On shared systems, set appropriate file permissions: `chmod 600 ~/.config/flashview-cli/config.json`.
- **Pipe seed storage:** The pipe seed is stored at `~/.flashview/pipe_config.json` with mode `0600`. Keep this file private — anyone with the seed can derive session keys for past and future transfers.

## Source Code

The CLI is open source. View the source and report issues on <a href="https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-cli" target="_blank" rel="noopener noreferrer">GitHub</a>.
