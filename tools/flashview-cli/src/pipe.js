import { createInterface } from 'node:readline';

import {
    generateIdentityKeypair,
    encryptSeedForPeer,
    decryptSeedFromPeer,
    computePairingCode,
    deriveSessionKey,
    deriveSessionId,
    encryptChunk,
    decryptChunk,
} from './crypto.js';
import { FlashViewClient, ApiError } from './api.js';
import { getConfig } from './config.js';
import {
    loadPipeConfig,
    savePipeConfig,
    incrementCounter,
    advanceCounterTo,
    exportConfig,
    importConfig,
    cleanStaleLock,
} from './pipeConfig.js';
import { loadIdentityKeypair, saveIdentityKeypair } from './identityConfig.js';

const PIPE_CHUNK_SIZE = 65536; // 64 KB
const PIPE_LOOK_AHEAD = 20;
const PIPE_POLL_INITIAL_MS = 100;
const PIPE_POLL_MAX_MS = 2000;

function humanBytes(bytes) {
    if (bytes < 1024) { return `${bytes} B`; }
    if (bytes < 1024 * 1024) { return `${(bytes / 1024).toFixed(1)} KB`; }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

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
                    console.error('This link is no longer valid. It may have expired, or it has already been opened.');
                    console.error('Ask the person who sent you this link to create a new one.');
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

/**
 * Uint8Array → base64 string (Node.js compat).
 *
 * @param {Uint8Array} bytes
 * @returns {string}
 */
function uint8ToBase64(bytes) {
    return Buffer.from(bytes).toString('base64');
}

/**
 * base64 string → Uint8Array.
 *
 * @param {string} b64
 * @returns {Uint8Array}
 */
function base64ToUint8(b64) {
    return new Uint8Array(Buffer.from(b64, 'base64'));
}

/**
 * Run the pipe sender flow: encrypt stdin in chunks and upload to server.
 *
 * @param {FlashViewClient} client
 * @param {{ verbose: boolean, chunkSize: number, expiresIn: number|null }} options
 */
async function runPipeSender(client, options) {
    const config = loadPipeConfig();
    if (!config) {
        console.error('Pipe seed not found. Run \'flashview pipe setup\' to get started.');
        process.exit(1);
    }

    cleanStaleLock();
    let counter;
    try {
        counter = await incrementCounter();
    } catch (err) {
        console.error(err.message);
        process.exit(1);
    }

    const sessionKey = await deriveSessionKey(config.seed, counter);
    const sessionId = await deriveSessionId(config.seed, counter);

    let session;
    try {
        session = await client.createPipeSession(sessionId, 'relay', options.expiresIn ?? null);
    } catch (err) {
        if (err instanceof ApiError && err.status === 409) {
            console.error('A session for this counter already exists. The counter has already advanced — try running again.');
            process.exit(1);
        }
        throw err;
    }

    const chunkSize = options.chunkSize ?? PIPE_CHUNK_SIZE;
    const chunks = [];
    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }
    const stdinBuffer = Buffer.concat(chunks);

    let totalBytes = 0;
    let chunkIndex = 0;

    for (let offset = 0; offset < stdinBuffer.length || (stdinBuffer.length === 0 && chunkIndex === 0); offset += chunkSize) {
        const slice = stdinBuffer.slice(offset, Math.min(offset + chunkSize, stdinBuffer.length));
        const encrypted = await encryptChunk(new Uint8Array(slice), sessionKey, chunkIndex);
        const payload = uint8ToBase64(encrypted);

        await client.uploadChunk(sessionId, chunkIndex, payload);
        totalBytes += slice.length;
        chunkIndex++;

        if (options.verbose) {
            process.stderr.write(`\r  Uploading... [${chunkIndex} chunk${chunkIndex !== 1 ? 's' : ''}, ${humanBytes(totalBytes)}]`);
        }

        if (stdinBuffer.length === 0) { break; }
    }

    if (options.verbose) { process.stderr.write('\n'); }

    await client.completePipeSession(sessionId, chunkIndex);

    if (session) {
        process.stderr.write('Transfer ready. Run \'flashview pipe\' on the receiving machine.\n');
    }
}

/**
 * Run the pipe receiver flow: discover session via look-ahead, decrypt, stream to stdout.
 *
 * @param {FlashViewClient} client
 * @param {{ verbose: boolean }} options
 */
async function runPipeReceiver(client, options) {
    const config = loadPipeConfig();
    if (!config) {
        console.error('Pipe seed not found. Run \'flashview pipe setup\' to get started.');
        process.exit(1);
    }

    let foundCounter = -1;
    let sessionStatus = null;

    for (let i = 0; i < PIPE_LOOK_AHEAD; i++) {
        const candidate = config.counter + i;
        const candidateSessionId = await deriveSessionId(config.seed, candidate);
        const status = await client.getPipeSessionStatus(candidateSessionId);
        if (status) {
            foundCounter = candidate;
            sessionStatus = status;
            break;
        }
    }

    if (foundCounter === -1) {
        console.error('No pending transfer found. The sender may not have run yet, or the session expired. Ask the sender to run \'flashview pipe\' again.');
        console.error('If transfers were missed, run \'flashview pipe setup sync\' on the sender and \'flashview pipe setup import <code>\' on this machine to resync.');
        process.exit(1);
    }

    const sessionKey = await deriveSessionKey(config.seed, foundCounter);
    const sessionId = await deriveSessionId(config.seed, foundCounter);

    await advanceCounterTo(foundCounter);

    const totalChunks = sessionStatus.total_chunks;
    const expiresAt = new Date(sessionStatus.expires_at).getTime();
    let chunkIndex = 0;
    let totalBytes = 0;
    let pollDelay = PIPE_POLL_INITIAL_MS;

    while (true) {
        if (Date.now() > expiresAt) {
            process.stderr.write('\nTransfer stalled: session expired. The sender may have disconnected.\n');
            process.exit(1);
        }

        const payloadBase64 = await client.downloadChunk(sessionId, chunkIndex);

        if (payloadBase64 === null) {
            await new Promise(r => setTimeout(r, pollDelay));
            pollDelay = Math.min(pollDelay * 2, PIPE_POLL_MAX_MS);
            continue;
        }

        pollDelay = PIPE_POLL_INITIAL_MS;

        const encrypted = base64ToUint8(payloadBase64);
        const plaintext = await decryptChunk(encrypted, sessionKey, chunkIndex);
        process.stdout.write(Buffer.from(plaintext));

        totalBytes += plaintext.length;
        chunkIndex++;

        if (options.verbose) {
            process.stderr.write(`\r  Receiving... [${chunkIndex} chunk${chunkIndex !== 1 ? 's' : ''}, ${humanBytes(totalBytes)}]`);
        }

        if (sessionStatus.is_complete && totalChunks !== null && chunkIndex >= totalChunks) {
            break;
        }

        if (!sessionStatus.is_complete || totalChunks === null) {
            // Re-fetch status to see if complete flag was set
            const updated = await client.getPipeSessionStatus(sessionId);
            if (updated) {
                sessionStatus = updated;
            }
            if (sessionStatus.is_complete && totalChunks !== null && chunkIndex >= sessionStatus.total_chunks) {
                break;
            }
        }
    }

    if (options.verbose) { process.stderr.write('\n'); }
}

/**
 * Run the PKI pairing setup flow.
 *
 * @param {FlashViewClient} client
 * @param {{ hasSeed: boolean }} param1
 */
async function runPkiSetup(client, { hasSeed }) {
    let keypair = loadIdentityKeypair();
    if (!keypair) {
        process.stderr.write('Generating identity keypair...\n');
        keypair = await generateIdentityKeypair();
        saveIdentityKeypair(keypair);
    }

    const deviceResult = await client.registerDevice(keypair.publicKeyBase64);
    const deviceId = deviceResult.device_id;

    if (!hasSeed) {
        process.stderr.write(`Device ${deviceId} ready. Waiting for Machine A to pair... (Ctrl+C to cancel)\n`);

        const pollIntervalMs = 3000;
        let pairingOffer = null;

        while (!pairingOffer) {
            await new Promise(r => setTimeout(r, pollIntervalMs));
            pairingOffer = await client.pollPairingPending(deviceId);
        }

        const senderPublicKey = pairingOffer.sender_public_key;
        const encryptedSeed = pairingOffer.encrypted_seed;
        const pairingId = pairingOffer.pairing_id;

        const seedBase64 = await decryptSeedFromPeer(encryptedSeed, keypair.privateKeyBase64, senderPublicKey);

        const code = await computePairingCode(senderPublicKey, keypair.publicKeyBase64);

        process.stderr.write(`\nPairing code: ${code} — does this match what Machine A shows? [y/N] `);

        const answer = await new Promise((resolve) => {
            const rl = createInterface({ input: process.stdin, output: process.stdout });
            rl.question('', (a) => { rl.close(); resolve(a.trim().toLowerCase()); });
        });

        if (answer !== 'y') {
            process.stderr.write('Pairing rejected. The codes do not match — this may indicate a server MITM attack.\n');
            process.stderr.write('Pairing cancelled. Run \'flashview pipe setup\' again to retry.\n');
            await client.destroyDevice(deviceId).catch(() => {});
            process.exit(1);
        }

        savePipeConfig({ seed: seedBase64, counter: 0 });
        await client.acceptPairing(pairingId);

        process.stderr.write('\nPaired! You can now use \'flashview pipe\'.\n');

    } else {
        const waitingResult = await client.listWaitingDevices();
        const devices = waitingResult.devices ?? [];

        if (devices.length === 0) {
            console.error('No devices waiting to pair. Run \'flashview pipe setup\' on Machine B first, then retry.');
            process.exit(1);
        }

        let targetDevice;
        if (devices.length === 1) {
            targetDevice = devices[0];
        } else {
            process.stderr.write('Multiple devices waiting to pair. Select one:\n');
            devices.forEach((d, i) => {
                process.stderr.write(`  ${i + 1}) ${d.device_id} (registered ${d.created_at})\n`);
            });
            const answer = await new Promise((resolve) => {
                const rl = createInterface({ input: process.stdin, output: process.stdout });
                rl.question('Enter number: ', (a) => { rl.close(); resolve(a.trim()); });
            });
            const idx = parseInt(answer, 10) - 1;
            if (idx < 0 || idx >= devices.length) {
                console.error('Invalid selection.');
                process.exit(1);
            }
            targetDevice = devices[idx];
        }

        const pairingCode = await computePairingCode(keypair.publicKeyBase64, targetDevice.public_key);

        process.stderr.write(`\nPairing code: ${pairingCode} — confirm this matches Machine B, then press Enter to continue (Ctrl+C to abort)\n`);

        await new Promise((resolve) => {
            const rl = createInterface({ input: process.stdin, output: process.stdout });
            rl.question('', () => { rl.close(); resolve(); });
        });

        const config = loadPipeConfig();
        const encryptedSeed = await encryptSeedForPeer(config.seed, targetDevice.public_key, keypair.privateKeyBase64);
        const pairingResult = await client.sendEncryptedSeed(targetDevice.device_id, encryptedSeed);
        const pairingId = pairingResult.pairing_id;

        process.stderr.write('Waiting for Machine B to confirm...\n');

        const pollIntervalMs = 3000;
        let isAccepted = false;

        while (!isAccepted) {
            await new Promise(r => setTimeout(r, pollIntervalMs));
            const status = await client.getPairingStatus(pairingId);
            isAccepted = status.is_accepted;
        }

        process.stderr.write('Machine B confirmed. Pairing complete.\n');
    }
}

/**
 * Register all pipe-related commands onto the given Commander program.
 *
 * @param {import('commander').Command} program
 */
export function registerPipeCommands(program) {
    const pipeCmd = program
        .command('pipe')
        .description('Send or receive encrypted data between paired machines')
        .option('--verbose', 'Show connection path and transfer stats')
        .option('--chunk-size <kb>', 'Chunk size in KB', '64')
        .option('--expires-in <s>', 'Session TTL in seconds', '600')
        .option('--json', 'Machine-readable output')
        .action(withErrorHandling(async (options) => {
            const config = getConfig();
            const client = new FlashViewClient(config.url, config.token);
            const chunkSize = parseInt(options.chunkSize, 10) * 1024;
            const expiresIn = options.expiresIn ? parseInt(options.expiresIn, 10) : null;
            const verbose = !!options.verbose;

            if (!process.stdin.isTTY) {
                await runPipeSender(client, { verbose, chunkSize, expiresIn });
            } else {
                await runPipeReceiver(client, { verbose });
            }
        }));

    const pipeSetupCmd = pipeCmd
        .command('setup')
        .description('Set up a shared pipe seed between two machines (PKI-based or manual export code).\nEach setup creates a dedicated two-machine pair. For a third machine, run setup again.');

    pipeSetupCmd
        .action(withErrorHandling(async () => {
            const config = getConfig();
            const client = new FlashViewClient(config.url, config.token);
            const hasSeed = loadPipeConfig() !== null;

            await runPkiSetup(client, { hasSeed });
        }));

    pipeSetupCmd
        .command('export')
        .description('Export current pipe seed as a portable code for air-gapped setup')
        .action(withErrorHandling(async () => {
            const config = loadPipeConfig();
            if (!config) {
                console.error('No pipe seed found. Run \'flashview pipe setup\' first.');
                process.exit(1);
            }
            const code = exportConfig({ seed: config.seed, counter: config.counter });
            console.log(code);
            console.log('\nCopy this code to Machine B. This is a one-time setup — you will not need to do this again.');
        }));

    pipeSetupCmd
        .command('import <code>')
        .description('Import a pipe seed from an export code')
        .action(withErrorHandling(async (code) => {
            let imported;
            try {
                imported = importConfig(code);
            } catch (err) {
                console.error(err.message);
                process.exit(1);
            }
            savePipeConfig({ seed: imported.seed, counter: imported.counter });
            console.log('Paired via export code. You can now use \'flashview pipe\'.');
        }));

    pipeSetupCmd
        .command('sync')
        .description('Re-export the current seed+counter for counter drift recovery')
        .action(withErrorHandling(async () => {
            const config = loadPipeConfig();
            if (!config) {
                console.error('No pipe seed found. Run \'flashview pipe setup\' first.');
                process.exit(1);
            }
            const code = exportConfig({ seed: config.seed, counter: config.counter });
            console.log(code);
            console.log('\nOn the out-of-sync machine, run: flashview pipe setup import <code>');
        }));

    pipeSetupCmd
        .command('show')
        .description('Show current pipe seed status and counter value')
        .action(withErrorHandling(async () => {
            const config = loadPipeConfig();
            if (!config) {
                console.log('Pipe seed: not configured');
                console.log('Run \'flashview pipe setup\' to get started.');
            } else {
                console.log(`Pipe seed: configured`);
                console.log(`Counter:   ${config.counter}`);
                console.log(`Created:   ${config.created_at}`);
                console.log(`Config:    ${require('node:os').homedir()}/.flashview/pipe_config.json`);
            }
        }));
}
