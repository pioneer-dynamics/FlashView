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

The `pipe` command streams end-to-end encrypted data directly between two registered machines — composable with Unix pipes.

### How It Works

Each transfer uses a **fresh random key** (AES-256-GCM, 256-bit) generated by the sender. The key is encrypted with the receiver's registered public key (ECIES: ECDH P-256 + HKDF + AES-GCM) and stored server-side alongside the encrypted payload. The receiver decrypts the transfer key with their private key and then decrypts the payload.

The CLI attempts **two delivery modes** in order:

1. **P2P WebRTC (direct):** The sender posts a WebRTC offer and waits for the receiver to answer. Once both sides are connected and ICE negotiation succeeds, the encrypted payload is delivered machine-to-machine without touching your storage. There is no time limit — P2P is abandoned only if ICE negotiation genuinely fails (e.g., NAT traversal is blocked).
2. **S3 relay (fallback):** If ICE negotiation fails, the sender falls back to uploading the encrypted payload to S3. The receiver downloads it when ready. This path works across any network and does not require both machines to be online simultaneously.

In both modes the payload is encrypted before it leaves the sender — the server never sees plaintext regardless of delivery path.

- The server never holds plaintext, the encryption key, or either machine's private key.
- Each transfer is independent — compromising one reveals nothing about others (forward secrecy).
- No shared secret, no counter, no coordination needed between machines.

### Initial Setup

Run `flashview pipe setup` once on each machine. Each machine generates a P-256 identity keypair and registers its public key with the server. The two machines can run setup in any order.

```bash
# On Machine A
flashview pipe setup
→ Device registered: DEVA3F2  (expires 2027-05-01)
→ This machine is now ready to receive pipe transfers.
→ On the sending machine, run:
→   flashview pipe --to DEVA3F2

# On Machine B (independently, any order)
flashview pipe setup
→ Device registered: DEVB4E1  (expires 2027-05-01)
→ This machine is now ready to receive pipe transfers.
→   flashview pipe --to DEVB4E1
```

Both machines must be logged in to the same FlashView account. The identity keypair is stored locally at `~/.flashview/identity_key.json` — the private key never leaves the machine.

### Sending and Receiving

```bash
# Send text to a specific device
echo "hello world" | flashview pipe --to DEVB4E1

# Send a tarball
tar cz ./my-directory | flashview pipe --to DEVB4E1

# Send a file
cat large-file.bin | flashview pipe --to DEVB4E1

# Send without --to (prompts to pick from registered devices)
echo "hello" | flashview pipe

# Receive to stdout (waits for an incoming transfer)
flashview pipe

# Receive and decompress
flashview pipe | tar xz

# Receive to a file
flashview pipe > output.bin
```

The receiver waits until a transfer addressed to its device ID arrives — no token or code needs to be copy-pasted.

When ICE negotiation succeeds the transfer completes peer-to-peer and the sender prints `Transfer complete.` If ICE fails, the sender falls back to S3 and prints `Transfer ready. Run 'flashview pipe' on the receiving machine.` — the receiver can then be started at any time to download from the relay. Both sides can be started in any order; there is no connection window to race against.

### Options

| Flag | Default | Description |
|------|---------|-------------|
| `--to <deviceId>` | (prompted) | Device ID of the receiving machine |
| `--verbose` | off | Show delivery mode (`p2p_webrtc`, `s3_direct`, or `server`) and transfer size to stderr |
| `--expires-in <s>` | 600 | Session TTL in seconds (60–3600) |

```bash
# Verbose output — shows delivery mode (p2p_webrtc, s3_direct, or server) and size to stderr
echo "data" | flashview pipe --to DEVB4E1 --verbose
flashview pipe --verbose
```

### Maintenance Commands

```bash
# Show the device ID registered on this machine
flashview pipe setup show

# Unregister this machine (deletes local identity key and de-registers from server)
flashview pipe setup unregister

# Alias for unregister
flashview pipe setup reset
```

Unregistering is the correct way to decommission a machine — no new transfers can be addressed to it once de-registered. Transfers already encrypted for this machine's key are short-lived (session TTL) and will expire naturally.

You can also manage registered devices and view recent transfers from the web interface.

---

## Security

- **End-to-end encryption:** Secrets are encrypted locally using AES-256-GCM with PBKDF2 key derivation (SHA-512, 64,000 iterations). The server never sees your plaintext.
- **Pipe encryption:** Each pipe transfer uses a fresh random 256-bit key (AES-256-GCM). The transfer key is encrypted with the receiver's P-256 public key (ECIES). The server stores only encrypted ciphertext and an encrypted key blob — never plaintext or the receiver's private key.
- **Pipe P2P transport:** When a direct WebRTC connection is established, the payload travels machine-to-machine over a DTLS-encrypted data channel. The encrypted payload is delivered without passing through server storage.
- **Pipe forward secrecy:** Compromising one transfer key reveals nothing about past or future transfers. Each transfer key is used once and discarded.
- **Passphrases stay local:** Encryption passphrases are never sent to the server.
- **Token storage:** API tokens are stored in plaintext in your OS config directory (e.g., `~/.config/flashview-cli/config.json`). On shared systems, set appropriate file permissions: `chmod 600 ~/.config/flashview-cli/config.json`.
- **Pipe identity key storage:** The machine's P-256 private key is stored at `~/.flashview/identity_key.json` with mode `0600`. Keep this file private — anyone with this key can decrypt transfers addressed to this machine.

---

## eLocker Commands

eLockers are anonymous, encrypted, long-term storage containers — like a Swiss numbered bank account, online. Access is via a 10-digit account ID and a passphrase only. No user account is required.

> **Note:** The `--name` label flag is not supported in v1 (no server-side label storage). Use your 10-digit account ID as your own reference.

### `flashview locker buy`

Open the eLocker pricing page in your default browser.

```bash
flashview locker buy
```

Pricing and purchase must be completed in the browser via Stripe. After payment, you will receive a credit token — use it with `flashview locker create`.

### `flashview locker create`

Create a new eLocker interactively. You will be prompted for your credit token, a 10-digit account ID, a passphrase, and content.

```bash
# Text locker (interactive)
flashview locker create

# File locker — encrypt a file instead of text (file tier only)
flashview locker create --file /path/to/document.pdf
```

On success, the account ID, passphrase, and update token are printed. **Save all three — none can be recovered from the server.**

### `flashview locker open <accountId>`

Unlock a locker and print its decrypted content. You will be prompted for your passphrase.

```bash
# Print decrypted text to stdout
flashview locker open 4815162342

# Save decrypted file to disk
flashview locker open 4815162342 --output ./document.pdf
```

The locker payload is fetched (rate-limited to 1 request per 5 minutes per account), then decrypted locally. Your passphrase is never sent to the server.

### `flashview locker update <accountId>`

Replace locker content. Requires your update token and passphrase (for re-encryption).

```bash
# Update with new text content from stdin
echo "Updated content" | flashview locker update 4815162342 --update-token <token>

# Update with a new file
flashview locker update 4815162342 --update-token <token> --file ./newfile.pdf
```

**Flags:**

| Flag | Required | Description |
|---|---|---|
| `--update-token <token>` | Yes | Update token from locker creation |
| `--file <path>` | No | New file to encrypt (file lockers only) |

### `flashview locker delete <accountId>`

Permanently delete a locker. This action cannot be undone.

```bash
flashview locker delete 4815162342 --update-token <token>
```

You will be prompted to type the account ID to confirm deletion.

**Flags:**

| Flag | Required | Description |
|---|---|---|
| `--update-token <token>` | Yes | Update token from locker creation |

### `flashview locker renew <accountId>`

Extend a locker's expiry via a new Stripe payment. You will be prompted for your passphrase (to compute a challenge-response verifier), the renewal duration, and the tier.

```bash
flashview locker renew 4815162342
```

A Stripe checkout URL is printed and opened in your browser. After payment, the expiry date updates within a few minutes (Stripe webhook processing). The auth challenge is rotated after each successful renewal — your passphrase will be required again for future renewals.

---

## Source Code

The CLI is open source. View the source and report issues on <a href="https://github.com/pioneer-dynamics/FlashView/tree/master/tools/flashview-cli" target="_blank" rel="noopener noreferrer">GitHub</a>.
