import { Command } from 'commander';
import crypto from 'node:crypto';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { hostname } from 'node:os';
import { dirname, join } from 'node:path';
import { createInterface } from 'node:readline';
import { fileURLToPath } from 'node:url';
import { encryptMessage, decryptMessage } from './crypto.js';
import { FlashViewClient, ApiError } from './api.js';
import { getConfig, getConfigInfo, setConfig, clearConfig, getCachedLatestVersion } from './config.js';
import { parseExpiry, getServerConfig, FALLBACK_EXPIRY_OPTIONS } from './expiry.js';
import { renameHashIdKey } from './transform.js';
import { fetchLatestVersion, isNewerVersion, refreshVersionCache } from './version.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const pkg = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf-8'));

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
    .description('FlashView CLI — Create and manage encrypted secrets')
    .version(pkg.version);

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
    .option('-p, --passphrase <passphrase>', 'Encryption passphrase (auto-generated if omitted)')
    .option('-e, --expires-in <duration>', 'Expiry duration (5m, 30m, 1h, 4h, 12h, 1d, 3d, 7d, 14d, 30d)', '1d')
    .option('--email <address>', 'Recipient email address')
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

        let message = options.message;
        if (!message) {
            if (process.stdin.isTTY) {
                console.error('No message provided. Use --message or pipe input via stdin.');
                console.error('Example: echo "my secret" | flashview create');
                process.exit(1);
            }
            message = await readStdin();
        }

        const { passphrase, secret } = encryptMessage(message, options.passphrase);

        const result = await client.createSecret(secret, expiresIn, options.email);

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
    .description('Retrieve and decrypt a secret (text secrets only)')
    .requiredOption('-p, --passphrase <passphrase>', 'Decryption passphrase')
    .option('--json', 'Output as JSON (for scripting)')
    .action(withErrorHandling(async (hashId, options) => {
        const config = getConfig();
        const client = new FlashViewClient(config.url, config.token);

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

        const encryptedMessage = result.data.message;

        let plaintext;
        try {
            plaintext = decryptMessage(encryptedMessage, options.passphrase);
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

program
    .command('login')
    .description('Authenticate with FlashView via your browser')
    .option('--url <url>', 'FlashView server URL (default: https://flashview.link)')
    .option('--timeout <seconds>', 'Login timeout in seconds', '120')
    .action(async (options) => {
        const serverUrl = (options.url || getConfigInfo().url || 'https://flashview.link').replace(/\/+$/, '');
        const timeoutMs = parseInt(options.timeout, 10) * 1000;
        const state = crypto.randomUUID().replace(/-/g, '');

        const { server, port } = await startCallbackServer();

        const authorizeUrl = `${serverUrl}/cli/authorize?port=${port}&state=${state}&name=${encodeURIComponent(hostname())}`;

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
        const currentVersion = pkg.version;

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

        if (cached && isNewerVersion(pkg.version, cached)) {
            process.stderr.write(
                `\nUpdate available: v${pkg.version} → v${cached}. Run \`flashview update\` to upgrade.\n`
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
