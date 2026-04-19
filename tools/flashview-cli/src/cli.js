import { Command } from 'commander';
import crypto from 'node:crypto';
import qrcode from 'qrcode-terminal';
import { execSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { hostname } from 'node:os';
import { createInterface } from 'node:readline';
import { readFileSync, writeFileSync } from 'node:fs';
import { basename, extname, resolve } from 'node:path';

function humanBytes(bytes) {
    if (bytes < 1024) { return `${bytes} B`; }
    if (bytes < 1024 * 1024) { return `${(bytes / 1024).toFixed(1)} KB`; }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function renderProgressBar(sent, total) {
    const width = 25;
    const pct = Math.min(1, sent / total);
    const filled = Math.round(width * pct);
    const bar = '█'.repeat(filled) + '░'.repeat(width - filled);
    const percent = Math.round(pct * 100).toString().padStart(3);
    process.stderr.write(`\r  Uploading  [${bar}] ${percent}%  ${humanBytes(sent)} / ${humanBytes(total)}`);
}

function createProgressBody(buffer, onProgress) {
    const total = buffer.byteLength;
    let offset = 0;
    const CHUNK = 65536;
    return new ReadableStream({
        pull(controller) {
            if (offset >= total) { controller.close(); return; }
            const end = Math.min(offset + CHUNK, total);
            const chunk = buffer.subarray(offset, end);
            offset = end;
            onProgress(offset, total);
            controller.enqueue(chunk);
        },
    });
}

const MIME_TYPES = {
    '.pdf': 'application/pdf',
    '.zip': 'application/zip',
    '.doc': 'application/msword',
    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    '.xls': 'application/vnd.ms-excel',
    '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    '.ppt': 'application/vnd.ms-powerpoint',
    '.pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    '.txt': 'text/plain',
    '.csv': 'text/csv',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.png': 'image/png',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
    '.mp4': 'video/mp4',
    '.mov': 'video/quicktime',
    '.mp3': 'audio/mpeg',
    '.wav': 'audio/wav',
};
import { encryptMessage, decryptMessage, encryptBuffer, decryptBuffer } from './crypto.js';
import { FlashViewClient, ApiError } from './api.js';
import { getConfig, getConfigInfo, setConfig, clearConfig, getCachedLatestVersion } from './config.js';
import { parseExpiry, getServerConfig, FALLBACK_EXPIRY_OPTIONS } from './expiry.js';
import { renameHashIdKey } from './transform.js';
import { fetchLatestVersion, isNewerVersion, refreshVersionCache } from './version.js';

/* eslint-disable no-undef */
const VERSION = typeof __VERSION__ !== 'undefined'
    ? __VERSION__
    : createRequire(import.meta.url)('../package.json').version;
/* eslint-enable no-undef */

let isSea = false;
try { isSea = require('node:sea').isSea(); } catch {}

/**
 * Read all data from stdin.
 *
 * @returns {Promise<string>}
 */
async function readStdin() {
    const chunks = [];
    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }
    return Buffer.concat(chunks).toString('utf8');
}

/**
 * Prompt for a yes/no confirmation.
 *
 * @param {string} question
 * @returns {Promise<boolean>}
 */
function confirm(question) {
    return new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin, output: process.stdout });
        rl.question(question, (answer) => {
            rl.close();
            resolve(answer.toLowerCase() === 'y');
        });
    });
}

/**
 * Wrap an async action with standard API error handling.
 *
 * @param {Function} fn
 * @returns {Function}
 */
function withErrorHandling(fn) {
    return async (...args) => {
        try {
            await fn(...args);
        } catch (err) {
            if (err instanceof ApiError) {
                if (err.status === 401) {
                    console.error('Authentication failed. Run `flashview config set` to update your token.');
                } else if (err.status === 403) {
                    console.error(err.message);
                } else if (err.status === 410) {
                    console.error('This message has expired or has already been retrieved.');
                } else if (err.status === 422 && err.errors) {
                    console.error('Validation errors:');
                    for (const [field, messages] of Object.entries(err.errors)) {
                        for (const msg of messages) {
                            console.error(`  ${field}: ${msg}`);
                        }
                    }
                } else if (err.status === 0) {
                    console.error(err.message);
                } else {
                    console.error(`Error: ${err.message}`);
                }
                process.exit(1);
            }

            if (err.code === 'ECONNREFUSED' || err.code === 'ENOTFOUND') {
                console.error('Could not connect to server. Check your URL and network.');
                process.exit(1);
            }

            throw err;
        }
    };
}

const program = new Command();

program
    .name('flashview')
    .description('FlashView CLI — Manage encrypted secrets')
    .version(VERSION);

// --- Config ---

const configCmd = program
    .command('config')
    .description('Manage CLI configuration');

configCmd
    .command('set')
    .description('Set API token and server URL')
    .option('--url <url>', 'FlashView server URL (default: https://flashview.link)')
    .requiredOption('--token <token>', 'API token (from FlashView dashboard)')
    .action(({ url, token }) => {
        setConfig({ url, token });
        console.log('Configuration saved.');
    });

configCmd
    .command('show')
    .description('Show current configuration')
    .action(() => {
        const { url, token, path } = getConfigInfo();
        console.log(`Server URL: ${url || '(not set)'}`);
        console.log(`API Token:  ${token ? token.substring(0, 8) + '...' : '(not set)'}`);
        console.log(`Config:     ${path}`);
    });

configCmd
    .command('clear')
    .description('Clear stored configuration')
    .action(() => {
        clearConfig();
        console.log('Configuration cleared.');
    });

// --- Create ---

program
    .command('create')
    .description('Create an encrypted secret')
    .option('-m, --message <text>', 'Secret message (reads from stdin if omitted)')
    .option('-f, --file <path>', 'File to encrypt and share (requires authentication)')
    .option('-p, --passphrase <passphrase>', 'Encryption passphrase (auto-generated if omitted)')
    .option('-e, --expires-in <duration>', 'Expiry duration (5m, 30m, 1h, 4h, 12h, 1d, 3d, 7d, 14d, 30d)', '1d')
    .option('--email <address>', 'Recipient email address')
    .option('--with-verified-badge', 'Include your verified sender identity badge')
    .option('--verbose', 'Show step-by-step progress (including upload progress bar for files)')
    .option('--json', 'Output as JSON (for scripting)')
    .action(withErrorHandling(async (options) => {
        const config = getConfig();
        const client = new FlashViewClient(config.url, config.token);

        const serverConfig = await getServerConfig();
        const expiryOptions = serverConfig?.expiry_options ?? FALLBACK_EXPIRY_OPTIONS;
        const allowedValues = expiryOptions.map(o => o.value);

        const expiresIn = parseExpiry(options.expiresIn, allowedValues);
        if (!expiresIn) {
            const labels = expiryOptions.map(o => o.label).join(', ');
            console.error(`Invalid expiry value: ${options.expiresIn}`);
            console.error(`Allowed values: ${labels}`);
            console.error('You can also specify a value in minutes directly (e.g., --expires-in 120).');
            process.exit(1);
        }

        if (options.file) {
            const verbose = options.verbose && !options.json;
            const filePath = resolve(options.file);
            let fileBytes;
            try {
                fileBytes = new Uint8Array(readFileSync(filePath));
            } catch {
                console.error(`Could not read file: ${options.file}`);
                process.exit(1);
            }

            const originalFilename = basename(filePath);
            const passphrase = options.passphrase || null;

            if (verbose) { process.stderr.write(`  Encrypting ${originalFilename} (${humanBytes(fileBytes.byteLength)})...\n`); }
            const { encrypted, passphrase: resolvedPassphrase } = await encryptBuffer(fileBytes, passphrase);
            const { secret: encryptedFilename } = await encryptMessage(originalFilename, resolvedPassphrase);

            let encryptedMessage = null;
            if (options.message) {
                const { secret } = await encryptMessage(options.message, resolvedPassphrase);
                encryptedMessage = secret;
            }

            const ext = extname(originalFilename).toLowerCase();
            const mimeType = MIME_TYPES[ext] || null;
            if (!mimeType) {
                console.error(`Unsupported file type: ${ext || '(no extension)'}`);
                console.error(`Supported types: ${Object.keys(MIME_TYPES).join(', ')}`);
                process.exit(1);
            }

            // Step 1: get presigned upload URL (or server fallback URL)
            if (verbose) { process.stderr.write('  Preparing upload...\n'); }
            const prepare = await client.prepareFileUpload();

            // Step 2: upload encrypted bytes directly (S3 or server fallback)
            const uploadHeaders = { 'Content-Type': 'application/octet-stream', 'Content-Length': String(encrypted.byteLength), ...prepare.upload_headers };
            const uploadBody = verbose
                ? createProgressBody(encrypted, (sent, total) => renderProgressBar(sent, total))
                : encrypted;
            if (verbose) { renderProgressBar(0, encrypted.byteLength); }
            const uploadResponse = await fetch(prepare.upload_url, {
                method: prepare.upload_type === 's3_direct' ? 'PUT' : 'POST',
                headers: uploadHeaders,
                body: uploadBody,
                duplex: 'half',
            });
            if (verbose) { process.stderr.write('\n'); }
            if (!uploadResponse.ok) {
                throw new Error(`File upload failed (HTTP ${uploadResponse.status})`);
            }

            // Step 3: create secret with the file token
            if (verbose) { process.stderr.write('  Creating secret...\n'); }
            const result = await client.createSecretWithFileToken(
                prepare.token,
                encryptedFilename,
                fileBytes.length,
                mimeType,
                expiresIn,
                options.email || null,
                !!options.withVerifiedBadge,
                encryptedMessage,
            );

            if (options.json) {
                console.log(JSON.stringify({
                    url: result.data.url,
                    passphrase: resolvedPassphrase,
                    message_id: result.data.hash_id,
                    expires_at: result.data.expires_at,
                }));
            } else {
                console.log(options.message ? 'File + note secret created successfully!\n' : 'File secret created successfully!\n');
                console.log(`URL:        ${result.data.url}`);
                console.log(`Passphrase: ${resolvedPassphrase}`);
                console.log(`Message ID: ${result.data.hash_id}`);
                console.log(`Expires:    ${result.data.expires_at}`);
                console.log('\nSave the URL and passphrase now — they cannot be retrieved later.');
            }
            return;
        }

        let message = options.message;
        if (!message) {
            if (process.stdin.isTTY) {
                console.error('No message provided. Use --message, --file, or pipe input via stdin.');
                console.error('Example: echo "my secret" | flashview create');
                process.exit(1);
            }
            message = await readStdin();
        }

        const verbose = options.verbose && !options.json;
        if (verbose) { process.stderr.write('  Encrypting message...\n'); }
        const { passphrase, secret } = await encryptMessage(message, options.passphrase);

        if (verbose) { process.stderr.write('  Creating secret...\n'); }
        const result = await client.createSecret(secret, expiresIn, options.email, !!options.withVerifiedBadge);

        if (options.json) {
            console.log(JSON.stringify({
                url: result.data.url,
                passphrase,
                message_id: result.data.hash_id,
                expires_at: result.data.expires_at,
            }));
        } else {
            console.log('Secret created successfully!\n');
            console.log(`URL:        ${result.data.url}`);
            console.log(`Passphrase: ${passphrase}`);
            console.log(`Message ID: ${result.data.hash_id}`);
            console.log(`Expires:    ${result.data.expires_at}`);
            console.log('\nSave the URL and passphrase now — they cannot be retrieved later.');
            console.log('\nNote: CLI retrieval requires the recipient to have a FlashView account with API access.');
            console.log('      Share the URL above for recipients without CLI access.');
        }
    }));

// --- Get ---

program
    .command('get <messageId>')
    .description('Retrieve and decrypt a secret')
    .requiredOption('-p, --passphrase <passphrase>', 'Decryption passphrase')
    .option('-o, --output <path>', 'Output file path for file secrets (defaults to original filename in current directory)')
    .option('--verbose', 'Show step-by-step progress')
    .option('--json', 'Output as JSON (for scripting)')
    .action(withErrorHandling(async (hashId, options) => {
        const config = getConfig();
        const client = new FlashViewClient(config.url, config.token);
        const verbose = options.verbose && !options.json;

        if (verbose) { process.stderr.write('  Retrieving secret...\n'); }
        let result;
        try {
            result = await client.retrieveSecret(hashId);
        } catch (err) {
            if (err instanceof ApiError && err.status === 404) {
                console.error('This message has expired or has already been retrieved.');
                process.exit(1);
            }
            throw err;
        }

        if (result.data.type === 'file' || result.data.type === 'combined') {
            if (verbose) { process.stderr.write(`  Downloading file (${humanBytes(result.data.file_size ?? 0)})...\n`); }
            let encryptedBytes;
            try {
                encryptedBytes = await client.downloadFile(hashId);
            } catch (err) {
                console.error('Failed to download encrypted file.');
                if (err instanceof ApiError && err.status === 410) {
                    console.error('The file has already been retrieved or has expired.');
                }
                process.exit(1);
            }

            // Fire-and-forget: tell the server the download succeeded so it can delete the S3 object.
            // The server will clean up automatically after the presigned URL TTL if this fails.
            await client.confirmFileDownloaded(hashId);

            if (verbose) { process.stderr.write('  Decrypting...\n'); }
            let decryptedBytes;
            let originalFilename;
            try {
                decryptedBytes = await decryptBuffer(encryptedBytes, options.passphrase);
                originalFilename = await decryptMessage(result.data.filename, options.passphrase);
            } catch {
                console.error('Decryption failed. The password may be incorrect.');
                console.error('Warning: The file has been consumed from the server and cannot be retrieved again.');
                process.exit(1);
            }

            const outputPath = options.output ? resolve(options.output) : resolve(originalFilename);
            if (verbose) { process.stderr.write(`  Saving to ${outputPath}...\n`); }
            writeFileSync(outputPath, Buffer.from(decryptedBytes));

            if (result.data.type === 'combined' && result.data.message) {
                let plaintext;
                try {
                    plaintext = await decryptMessage(result.data.message, options.passphrase);
                } catch {
                    console.error('Note decryption failed. The password may be incorrect.');
                    process.exit(1);
                }

                if (options.json) {
                    console.log(JSON.stringify({
                        message_id: result.data.hash_id,
                        message: plaintext,
                        file: outputPath,
                        original_filename: originalFilename,
                    }));
                } else {
                    console.log(`Note: ${plaintext}`);
                    console.log(`\u2713 File saved to ${outputPath}`);
                }
                return;
            }

            if (options.json) {
                console.log(JSON.stringify({
                    message_id: result.data.hash_id,
                    file: outputPath,
                    original_filename: originalFilename,
                }));
            } else {
                console.log(`\u2713 File saved to ${outputPath}`);
            }
            return;
        }

        if (verbose) { process.stderr.write('  Decrypting message...\n'); }
        const encryptedMessage = result.data.message;

        let plaintext;
        try {
            plaintext = await decryptMessage(encryptedMessage, options.passphrase);
        } catch {
            console.error('Decryption failed. The password may be incorrect.');
            console.error('Warning: The secret has been consumed from the server and cannot be retrieved again.');
            process.exit(1);
        }

        if (options.json) {
            console.log(JSON.stringify({
                message_id: result.data.hash_id,
                message: plaintext,
            }));
        } else {
            process.stdout.write(plaintext);
        }
    }));

// --- List ---

program
    .command('list')
    .description('List your secrets')
    .option('-p, --page <number>', 'Page number', '1')
    .option('--json', 'Output as JSON (for scripting)')
    .action(withErrorHandling(async (options) => {
        const config = getConfig();
        const client = new FlashViewClient(config.url, config.token);

        const result = await client.listSecrets(parseInt(options.page, 10));

        if (options.json) {
            const remapped = { ...result, data: result.data.map(renameHashIdKey) };
            console.log(JSON.stringify(remapped));
        } else {
            if (!result.data.length) {
                console.log('No secrets found.');
                return;
            }
            console.log('Message ID       Expires At               Status');
            console.log('\u2500'.repeat(60));
            for (const secret of result.data) {
                const status = secret.is_retrieved ? 'Retrieved' : secret.is_expired ? 'Expired' : 'Active';
                console.log(`${secret.hash_id.padEnd(17)}${secret.expires_at.padEnd(25)}${status}`);
            }
            if (result.meta?.last_page > 1) {
                console.log(`\nPage ${result.meta.current_page} of ${result.meta.last_page}`);
            }
        }
    }));

// --- Status ---

program
    .command('status <messageId>')
    .description('Show status of a secret (use message ID from create output or list)')
    .option('--json', 'Output as JSON (for scripting)')
    .action(withErrorHandling(async (hashId, options) => {
        const config = getConfig();
        const client = new FlashViewClient(config.url, config.token);

        const result = await client.getSecretStatus(hashId);
        const secret = result.data;

        if (options.json) {
            console.log(JSON.stringify({ ...result, data: renameHashIdKey(result.data) }));
        } else {
            const status = secret.is_retrieved ? 'Retrieved' : secret.is_expired ? 'Expired' : 'Active';
            console.log(`Message ID:   ${secret.hash_id}`);
            console.log(`Status:       ${status}`);
            console.log(`Created:      ${secret.created_at}`);
            console.log(`Expires:      ${secret.expires_at}`);
            if (secret.retrieved_at) {
                console.log(`Retrieved:    ${secret.retrieved_at}`);
            }
        }
    }));

// --- Burn ---

program
    .command('burn <messageId>')
    .description('Burn (delete) a secret')
    .option('--json', 'Output as JSON (for scripting)')
    .option('-y, --yes', 'Skip confirmation prompt')
    .action(withErrorHandling(async (hashId, options) => {
        if (!options.yes && !options.json && process.stdin.isTTY) {
            const confirmed = await confirm(`Burn secret ${hashId}? This cannot be undone. (y/N) `);
            if (!confirmed) {
                console.log('Cancelled.');
                return;
            }
        }

        const config = getConfig();
        const client = new FlashViewClient(config.url, config.token);

        await client.burnSecret(hashId);

        if (options.json) {
            console.log(JSON.stringify({ message: 'Secret burned successfully.' }));
        } else {
            console.log('Secret burned successfully.');
        }
    }));

// --- Login ---

/**
 * Start a temporary HTTP server on a random available port.
 *
 * @returns {Promise<{ server: import('node:http').Server, port: number }>}
 */
function startCallbackServer() {
    return new Promise((resolve, reject) => {
        const server = createServer();
        server.listen(0, '127.0.0.1', () => {
            const port = server.address().port;
            resolve({ server, port });
        });
        server.on('error', reject);
    });
}

/**
 * Wait for the authorization callback.
 *
 * @param {import('node:http').Server} server
 * @param {string} expectedState
 * @param {number} timeout
 * @returns {Promise<string>}
 */
function waitForCallback(server, expectedState, timeout) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            server.close();
            reject(new Error('TIMEOUT'));
        }, timeout);

        server.on('request', (req, res) => {
            const url = new URL(req.url, `http://${req.headers.host}`);

            if (url.pathname === '/callback') {
                const code = url.searchParams.get('code');
                const error = url.searchParams.get('error');
                const state = url.searchParams.get('state');

                clearTimeout(timer);

                if (state !== expectedState) {
                    res.writeHead(400, { 'Content-Type': 'text/html' });
                    res.end('<html><body><h1>State mismatch</h1><p>Authorization failed. Please try again.</p></body></html>');
                    reject(new Error('State parameter mismatch. Please try again.'));
                    return;
                }

                if (error) {
                    const errorMessages = {
                        denied: 'Authorization denied.',
                        no_api_access: 'Your plan does not include API access. Visit your account to upgrade.',
                    };
                    const message = errorMessages[error] || `Authorization failed: ${error}`;

                    res.writeHead(200, { 'Content-Type': 'text/html' });
                    res.end(`<html><body><h1>Authorization failed</h1><p>${message} You can close this window.</p></body></html>`);
                    reject(new Error(message));
                    return;
                }

                res.writeHead(200, { 'Content-Type': 'text/html' });
                res.end('<html><body><h1>Authentication successful!</h1><p>You can close this window and return to the terminal.</p></body></html>');
                resolve(code);
            } else {
                res.writeHead(404);
                res.end();
            }
        });
    });
}

/**
 * Exchange the authorization code for an API token.
 *
 * @param {string} serverUrl
 * @param {string} code
 * @param {string} state
 * @returns {Promise<{ token: string, user: { name: string, email: string } }>}
 */
async function exchangeCode(serverUrl, code, state) {
    const response = await fetch(`${serverUrl}/cli/token`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ code, state }),
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.message || `Token exchange failed (HTTP ${response.status})`);
    }

    return response.json();
}

/**
 * Try to open a URL in the default browser.
 *
 * @param {string} url
 * @returns {Promise<boolean>}
 */
async function openBrowser(url) {
    try {
        const { exec } = await import('node:child_process');
        const { promisify } = await import('node:util');
        const execAsync = promisify(exec);

        if (process.platform === 'darwin') {
            await execAsync(`open "${url}"`);
        } else if (process.platform === 'win32') {
            await execAsync(`start "" "${url}"`);
        } else {
            if (!process.env.DISPLAY && !process.env.WAYLAND_DISPLAY) {
                return false;
            }
            await execAsync(`xdg-open "${url}"`);
        }
        return true;
    } catch {
        return false;
    }
}

/**
 * Detect whether the current environment can open a browser the user will see.
 * Returns true when the CLI is running in a pure TTY (e.g. SSH session without
 * a display server), meaning browser-based login is not possible.
 *
 * Note: SSH with X11 forwarding (ssh -X/-Y) sets SSH_TTY/SSH_CONNECTION, so
 * this will return true even though a forwarded browser could technically open.
 * The device code flow is fully functional in that case, so this is an acceptable
 * trade-off.
 *
 * @returns {boolean}
 */
function isHeadlessEnvironment() {
    if (process.platform === 'linux') {
        return !process.env.DISPLAY && !process.env.WAYLAND_DISPLAY;
    }
    // macOS / Windows: headless only when accessed via SSH; local GUI sessions
    // can always open a browser. SSH_CONNECTION covers Windows OpenSSH (no SSH_TTY).
    return !!(process.env.SSH_TTY || process.env.SSH_CONNECTION || process.env.SSH_CLIENT);
}

/**
 * Headless device code login flow (OAuth 2.0 Device Authorization Grant).
 *
 * @param {string} serverUrl
 * @param {string} name
 * @param {string|null} tokenId
 * @returns {Promise<{ token: string, user: { name: string, email: string }, installation_name: string }>}
 */
async function loginHeadless(serverUrl, name, tokenId = null) {
    const initResponse = await fetch(`${serverUrl}/cli/device/initiate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ name, ...(tokenId ? { token_id: parseInt(tokenId, 10) } : {}) }),
    });

    if (!initResponse.ok) {
        throw new Error(`Failed to initiate device login (HTTP ${initResponse.status})`);
    }

    const { device_code, user_code, device_url, expires_in } = await initResponse.json();

    console.log('');
    qrcode.generate(device_url, { small: true });
    console.log(`Scan the QR code or visit: ${device_url}`);
    console.log(`Enter code:                ${user_code}\n`);
    console.log(`Waiting for authentication (expires in ${Math.round(expires_in / 60)} minutes)...`);

    // Use server-provided TTL as polling deadline — not --timeout (designed for browser flow)
    const deadline = Date.now() + (expires_in * 1000);
    const pollInterval = 5000;

    while (Date.now() < deadline) {
        await new Promise(resolve => setTimeout(resolve, pollInterval));

        const pollResponse = await fetch(
            `${serverUrl}/cli/device/poll?device_code=${encodeURIComponent(device_code)}`,
            { headers: { 'Accept': 'application/json' } },
        );

        const result = await pollResponse.json();

        if (result.status === 'authorized') {
            return result;
        }

        if (result.status === 'expired') {
            throw new Error('Code expired. Please run `flashview login --headless` again.');
        }

        if (result.status === 'denied') {
            if (result.reason === 'no_api_access') {
                throw new Error('Your plan does not include API access. Visit your account to upgrade.');
            }
            throw new Error('Authorization denied.');
        }

        // status === 'pending': continue polling
    }

    throw new Error('TIMEOUT');
}

program
    .command('login')
    .description('Authenticate with FlashView via your browser')
    .option('--url <url>', 'FlashView server URL (default: https://flashview.link)')
    .option('--timeout <seconds>', 'Login timeout in seconds (browser flow only)', '120')
    .option('--headless', 'Authenticate without a browser using a device code')
    .action(async (options) => {
        const serverUrl = (options.url || getConfigInfo().url || 'https://flashview.link').replace(/\/+$/, '');

        const autoHeadless = !options.headless && isHeadlessEnvironment();
        if (options.headless || autoHeadless) {
            if (autoHeadless) {
                console.log('Headless environment detected. Using device code flow instead of browser.');
            }
            try {
                const { token: existingTokenHeadless } = getConfigInfo();
                const headlessTokenId = existingTokenHeadless ? existingTokenHeadless.split('|')[0] : null;
                const result = await loginHeadless(serverUrl, hostname(), headlessTokenId);
                setConfig({ url: serverUrl, token: result.token });
                console.log(`\nAuthenticated as ${result.user.name} (${result.user.email})`);
                console.log('Token saved. You can now use FlashView CLI commands.');
            } catch (err) {
                if (err.message === 'TIMEOUT') {
                    console.error('\nLogin timed out. Please try again.');
                } else if (err.code === 'ECONNREFUSED' || err.code === 'ENOTFOUND') {
                    console.error('\nCould not connect to server. Check your URL and network.');
                } else {
                    console.error(`\nLogin failed: ${err.message}`);
                }
                process.exit(1);
            }
            return;
        }

        const timeoutMs = parseInt(options.timeout, 10) * 1000;
        const state = crypto.randomUUID().replace(/-/g, '');

        const { server, port } = await startCallbackServer();

        let authorizeUrl = `${serverUrl}/cli/authorize?port=${port}&state=${state}&name=${encodeURIComponent(hostname())}`;

        // If we have an existing token, send its ID so the server can identify the device
        const { token: existingToken } = getConfigInfo();
        if (existingToken) {
            const tokenId = existingToken.split('|')[0];
            if (tokenId) {
                authorizeUrl += `&token_id=${encodeURIComponent(tokenId)}`;
            }
        }

        const opened = await openBrowser(authorizeUrl);
        if (opened) {
            console.log('Opening browser for authentication...');
            console.log('If the browser does not open, visit this URL:');
        } else {
            console.log('Open this URL in your browser to authenticate:');
        }
        console.log(`\n  ${authorizeUrl}\n`);

        const timeoutSec = parseInt(options.timeout, 10);
        console.log(`Waiting for authentication (timeout: ${timeoutSec}s)...`);

        try {
            const code = await waitForCallback(server, state, timeoutMs);

            const result = await exchangeCode(serverUrl, code, state);

            setConfig({ url: serverUrl, token: result.token });

            console.log(`\nAuthenticated as ${result.user.name} (${result.user.email})`);
            console.log('Token saved. You can now use FlashView CLI commands.');
        } catch (err) {
            if (err.message === 'TIMEOUT') {
                console.error('\nLogin timed out. Please try again.');
            } else if (err.code === 'ECONNREFUSED' || err.code === 'ENOTFOUND') {
                console.error('\nCould not connect to server. Check your URL and network.');
            } else {
                console.error(`\nLogin failed: ${err.message}`);
            }
            process.exit(1);
        } finally {
            server.close();
        }
    });
// --- Update ---

program
    .command('update')
    .description('Update FlashView CLI to the latest version')
    .option('--json', 'Output as JSON (for scripting)')
    .action(async (options) => {
        const currentVersion = VERSION;

        if (isSea) {
            const cached = getCachedLatestVersion();
            if (options.json) {
                const result = { version: currentVersion, binary: true, download: 'https://github.com/pioneer-dynamics/FlashView/releases' };
                if (cached) result.latest_version = cached;
                console.log(JSON.stringify(result));
            } else {
                console.log(`Current version: v${currentVersion}`);
                if (cached && isNewerVersion(currentVersion, cached)) {
                    console.log(`Latest available: v${cached}`);
                }
                console.log('To update, download the latest binary from:');
                console.log('  https://github.com/pioneer-dynamics/FlashView/releases');
            }
            return;
        }

        const latestVersion = await fetchLatestVersion();

        if (!latestVersion) {
            if (options.json) {
                console.log(JSON.stringify({ error: 'Could not check for updates. Check your network connection.' }));
            } else {
                console.error('Could not check for updates. Check your network connection.');
            }
            process.exit(1);
        }

        if (!isNewerVersion(currentVersion, latestVersion)) {
            if (options.json) {
                console.log(JSON.stringify({ message: 'Already up to date.', version: currentVersion }));
            } else {
                console.log(`Already up to date (v${currentVersion}).`);
            }
            return;
        }

        if (!options.json) {
            console.log(`Updating FlashView CLI: v${currentVersion} → v${latestVersion}...`);
        }

        try {
            execSync('npm install -g @pioneer-dynamics/flashview-cli@latest', {
                stdio: options.json ? 'pipe' : 'inherit',
            });
        } catch (err) {
            const stderr = err.stderr?.toString() || '';
            let message;
            if (process.platform === 'win32' && (stderr.includes('EPERM') || stderr.includes('Access is denied'))) {
                message = 'Permission denied. Try running as Administrator or use a Node version manager (nvm-windows, fnm).';
            } else if (stderr.includes('EACCES')) {
                message = 'Permission denied. Try running with sudo or use a Node version manager (nvm, fnm).';
            } else {
                message = 'Update failed. Try manually: npm install -g @pioneer-dynamics/flashview-cli@latest';
            }

            if (options.json) {
                console.log(JSON.stringify({ error: message }));
            } else {
                console.error(message);
            }
            process.exit(1);
        }

        let newVersion;
        try {
            newVersion = execSync('flashview --version', { encoding: 'utf-8' }).trim();
        } catch {
            newVersion = latestVersion;
        }

        if (options.json) {
            console.log(JSON.stringify({
                message: 'Updated successfully.',
                previous_version: currentVersion,
                current_version: newVersion,
            }));
        } else {
            console.log(`\nUpdated successfully: v${currentVersion} → v${newVersion}`);
        }
    });

// --- Update Notice (post-action hook) ---

// thisCommand = command the hook is registered on (root program)
// actionCommand = the subcommand that actually executed
program.hook('postAction', async (thisCommand, actionCommand) => {
    if (actionCommand.name() === 'update') return;

    const opts = actionCommand.opts?.() || {};
    if (opts.json) return;

    try {
        const cached = getCachedLatestVersion();

        if (cached && isNewerVersion(VERSION, cached)) {
            process.stderr.write(
                `\nUpdate available: v${VERSION} → v${cached}. Run \`flashview update\` to upgrade.\n`
            );
        }

        if (!cached) {
            refreshVersionCache();
        }
    } catch {
        // Never block on version check errors
    }
});

export function run() {
    program.parse();
}
