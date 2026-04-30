import { homedir } from 'node:os';
import { createInterface } from 'node:readline';

import {
    generatePipeSeed,
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
 * Run the pipe sender flow: encrypt stdin and upload to S3 (or server fallback).
 *
 * @param {FlashViewClient} client
 * @param {{ verbose: boolean, expiresIn: number|null }} options
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

    try {
        await client.createPipeSession(sessionId, 'relay', options.expiresIn ?? null);
    } catch (err) {
        if (err instanceof ApiError && err.status === 409) {
            console.error('A session for this counter already exists. The counter has already advanced — try running again.');
            process.exit(1);
        }
        throw err;
    }

    const chunks = [];
    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }
    const stdinBuffer = Buffer.concat(chunks);

    if (options.verbose) {
        process.stderr.write(`  Encrypting... [${humanBytes(stdinBuffer.length)}]\n`);
    }

    const encrypted = await encryptChunk(new Uint8Array(stdinBuffer), sessionKey, 0);

    const { upload_type, upload_url, upload_headers } = await client.prepareUpload(sessionId);

    if (options.verbose) {
        process.stderr.write(`  Uploading via ${upload_type}... [${humanBytes(encrypted.length)}]\n`);
    }

    await client.uploadPayload(upload_url, upload_headers, encrypted);
    await client.completePipeSession(sessionId);

    process.stderr.write('Transfer ready. Run \'flashview pipe\' on the receiving machine.\n');
}

/**
 * Run the pipe receiver flow: discover session via look-ahead, download, decrypt, stream to stdout.
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

    const expiresAt = new Date(sessionStatus.expires_at).getTime();
    let pollDelay = PIPE_POLL_INITIAL_MS;

    while (!sessionStatus.is_complete) {
        if (Date.now() > expiresAt) {
            process.stderr.write('Transfer stalled: session expired. The sender may have disconnected.\n');
            process.exit(1);
        }
        await new Promise(r => setTimeout(r, pollDelay));
        pollDelay = Math.min(pollDelay * 2, PIPE_POLL_MAX_MS);
        const updated = await client.getPipeSessionStatus(sessionId);
        if (updated) { sessionStatus = updated; }
    }

    if (options.verbose) {
        process.stderr.write('  Downloading...\n');
    }

    const encrypted = await client.downloadPayload(sessionId);

    if (options.verbose) {
        process.stderr.write(`  Decrypting... [${humanBytes(encrypted.length)}]\n`);
    }

    const plaintext = await decryptChunk(encrypted, sessionKey, 0);
    process.stdout.write(Buffer.from(plaintext));

    await client.burnPipeSession(sessionId);
}

/**
 * Prompt the user to select their role when no seed exists on this machine.
 *
 * @returns {Promise<'initiate'|'join'>}
 */
async function promptRole() {
    process.stderr.write('\nNo pipe seed found on this machine.\n');
    process.stderr.write('  (1) Create a new pair — this machine generates the seed and waits for the other machine\n');
    process.stderr.write('  (2) Join an existing pair — wait for the other machine to send you the seed\n');

    const answer = await new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin, output: process.stdout });
        rl.question('Choice [1/2]: ', (a) => { rl.close(); resolve(a.trim()); });
    });

    if (answer === '1') { return 'initiate'; }
    if (answer === '2') { return 'join'; }

    process.stderr.write('Invalid selection. Please enter 1 or 2.\n');
    return promptRole();
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

    let isSeedHolder = hasSeed;

    if (!hasSeed) {
        const role = await promptRole();
        if (role === 'initiate') {
            const newSeed = await generatePipeSeed();
            savePipeConfig({ seed: newSeed, counter: 0 });
            isSeedHolder = true;
        }
    }

    const deviceResult = await client.registerDevice(keypair.publicKeyBase64);
    const deviceId = deviceResult.device_id;

    if (!isSeedHolder) {
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

        const answer = await new Promise((resolve) => {
            const rl = createInterface({ input: process.stdin, output: process.stderr });
            rl.question(`\nPairing code: ${code} — does this match what Machine A shows? [y/N] `, (a) => { rl.close(); resolve(a.trim().toLowerCase()); });
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
        const pollIntervalMs = 3000;

        process.stderr.write(`Device ${deviceId} ready. Waiting for Machine B to run 'flashview pipe setup'... (Ctrl+C to cancel)\n`);

        let devices = [];
        while (devices.length === 0) {
            await new Promise(r => setTimeout(r, pollIntervalMs));
            const waitingResult = await client.listWaitingDevices();
            devices = (waitingResult.devices ?? []).filter(d => d.device_id !== deviceId);
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

        const config = loadPipeConfig();
        const encryptedSeed = await encryptSeedForPeer(config.seed, targetDevice.public_key, keypair.privateKeyBase64);
        const pairingResult = await client.sendEncryptedSeed(targetDevice.device_id, encryptedSeed);
        const pairingId = pairingResult.pairing_id;

        const pairingCode = await computePairingCode(keypair.publicKeyBase64, targetDevice.public_key);

        await new Promise((resolve) => {
            const rl = createInterface({ input: process.stdin, output: process.stderr });
            rl.question(`\nPairing code: ${pairingCode} — confirm this matches Machine B, then press Enter to continue (Ctrl+C to abort)\n`, () => { rl.close(); resolve(); });
        });

        process.stderr.write('Waiting for Machine B to confirm...\n');

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
        .option('--verbose', 'Show upload type and transfer stats')
        .option('--expires-in <s>', 'Session TTL in seconds', '600')
        .option('--json', 'Machine-readable output')
        .action(withErrorHandling(async (options) => {
            const config = getConfig();
            const client = new FlashViewClient(config.url, config.token);
            const expiresIn = options.expiresIn ? parseInt(options.expiresIn, 10) : null;
            const verbose = !!options.verbose;

            if (!process.stdin.isTTY) {
                await runPipeSender(client, { verbose, expiresIn });
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
                console.log(`Config:    ${homedir()}/.flashview/pipe_config.json`);
            }
        }));
}
