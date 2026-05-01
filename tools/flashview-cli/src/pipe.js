import { createInterface } from 'node:readline';
import { existsSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';
import { homedir } from 'node:os';

import {
    generateTransferKey,
    generateSessionId,
    generateIdentityKeypair,
    encryptKeyForDevice,
    decryptKeyFromDevice,
    encryptChunk,
    decryptChunk,
} from './crypto.js';

import { FlashViewClient, ApiError } from './api.js';
import { getConfig } from './config.js';
import { loadIdentityKeypair, saveIdentityKeypair } from './identityConfig.js';

const PIPE_POLL_INITIAL_MS = 500;
const PIPE_POLL_MAX_MS = 3000;
const IDENTITY_FILE = join(homedir(), '.flashview', 'identity_key.json');

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
 * Prompt to select a device from a list.
 *
 * @param {Array<{ device_id: string, expires_at: string }>} devices
 * @returns {Promise<string>} selected device_id
 */
async function promptDeviceSelection(devices) {
    process.stderr.write('\nRegistered devices on this account:\n');
    devices.forEach((d, i) => {
        const expires = new Date(d.expires_at).toLocaleDateString();
        process.stderr.write(`  ${i + 1}) ${d.device_id}  (expires ${expires})\n`);
    });

    const answer = await new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin, output: process.stderr });
        rl.question('Send to device number: ', (a) => { rl.close(); resolve(a.trim()); });
    });

    const idx = parseInt(answer, 10) - 1;
    if (idx < 0 || idx >= devices.length) {
        console.error('Invalid selection.');
        process.exit(1);
    }
    return devices[idx].device_id;
}

/**
 * Run the pipe sender flow: generate per-transfer key, encrypt, upload to S3.
 *
 * @param {FlashViewClient} client
 * @param {{ verbose: boolean, expiresIn: number|null, to: string|null }} options
 */
async function runPipeSender(client, options) {
    const identity = loadIdentityKeypair();
    if (!identity) {
        console.error('No device registered. Run \'flashview pipe setup\' to register this machine first.');
        process.exit(1);
    }

    let receiverDeviceId = options.to ?? null;

    if (!receiverDeviceId) {
        const result = await client.listMyDevices();
        const devices = (result.devices ?? []).filter(d => d.device_id !== identity.deviceId);

        if (devices.length === 0) {
            console.error('No other registered devices found on this account.');
            console.error('Run \'flashview pipe setup\' on the receiving machine first, then retry.');
            process.exit(1);
        }

        if (devices.length === 1) {
            receiverDeviceId = devices[0].device_id;
            process.stderr.write(`Sending to device: ${receiverDeviceId}\n`);
        } else {
            receiverDeviceId = await promptDeviceSelection(devices);
        }
    }

    const receiverDevice = await client.getDevicePublicKey(receiverDeviceId);

    const transferKey = generateTransferKey();
    const sessionId = generateSessionId();

    const encryptedTransferKey = await encryptKeyForDevice(
        transferKey,
        receiverDevice.public_key,
        identity.privateKeyBase64,
    );

    await client.createPipeSession(sessionId, 'relay', options.expiresIn ?? null, {
        receiver_device_id: receiverDeviceId,
        sender_device_id: identity.deviceId,
        encrypted_transfer_key: encryptedTransferKey,
    });

    const chunks = [];
    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }
    const stdinBuffer = Buffer.concat(chunks);

    if (options.verbose) {
        process.stderr.write(`  Encrypting... [${humanBytes(stdinBuffer.length)}]\n`);
    }

    const encrypted = await encryptChunk(new Uint8Array(stdinBuffer), transferKey, 0);

    const { upload_type, upload_url, upload_headers } = await client.prepareUpload(sessionId);

    if (options.verbose) {
        process.stderr.write(`  Uploading via ${upload_type}... [${humanBytes(encrypted.length)}]\n`);
    }

    await client.uploadPayload(upload_url, upload_headers, encrypted);
    await client.completePipeSession(sessionId);

    process.stderr.write('Transfer ready. Run \'flashview pipe\' on the receiving machine.\n');
}

/**
 * Run the pipe receiver flow: poll for pending session, decrypt transfer key, download, decrypt.
 *
 * @param {FlashViewClient} client
 * @param {{ verbose: boolean }} options
 */
async function runPipeReceiver(client, options) {
    const identity = loadIdentityKeypair();
    if (!identity || !identity.deviceId) {
        console.error('No device registered. Run \'flashview pipe setup\' to register this machine first.');
        process.exit(1);
    }

    process.stderr.write('Waiting for a transfer...\n');

    let pollDelay = PIPE_POLL_INITIAL_MS;
    let pending = null;

    while (!pending) {
        pending = await client.pollPendingSessions(identity.deviceId);
        if (!pending) {
            await new Promise(r => setTimeout(r, pollDelay));
            pollDelay = Math.min(pollDelay * 2, PIPE_POLL_MAX_MS);
        }
    }

    const { session_id: sessionId, encrypted_transfer_key, sender_public_key } = pending;

    if (!sender_public_key) {
        console.error('Transfer received but sender public key is missing. Cannot decrypt.');
        process.exit(1);
    }

    const transferKey = await decryptKeyFromDevice(
        encrypted_transfer_key,
        identity.privateKeyBase64,
        sender_public_key,
    );

    let sessionStatus = await client.getPipeSessionStatus(sessionId);
    const expiresAt = new Date(sessionStatus?.expires_at ?? 0).getTime();

    while (!sessionStatus?.is_complete) {
        if (Date.now() > expiresAt) {
            process.stderr.write('Transfer stalled: session expired. The sender may have disconnected.\n');
            process.exit(1);
        }
        await new Promise(r => setTimeout(r, pollDelay));
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

    const plaintext = await decryptChunk(encrypted, transferKey, 0);
    process.stdout.write(Buffer.from(plaintext));

    await client.burnPipeSession(sessionId);
}

/**
 * Run the setup flow: generate identity keypair and register device.
 *
 * @param {FlashViewClient} client
 */
async function runSetup(client) {
    let identity = loadIdentityKeypair();

    if (!identity) {
        process.stderr.write('Generating identity keypair...\n');
        identity = await generateIdentityKeypair();
        saveIdentityKeypair(identity);
    }

    process.stderr.write('Registering device with server...\n');
    const result = await client.registerDevice(identity.publicKeyBase64);
    const deviceId = result.device_id;

    saveIdentityKeypair({ ...identity, deviceId });

    const expiresDate = new Date(result.expires_at).toLocaleDateString();
    process.stderr.write(`\nDevice registered: ${deviceId}  (expires ${expiresDate})\n`);
    process.stderr.write(`\nThis machine is now ready to receive pipe transfers.\n`);
    process.stderr.write(`On the sending machine, run:\n`);
    process.stderr.write(`  flashview pipe --to ${deviceId}\n`);
}

/**
 * Run the unregister (reset) flow: de-register device and delete local identity.
 *
 * @param {FlashViewClient} client
 */
async function runReset(client) {
    const identity = loadIdentityKeypair();

    if (!identity || !identity.deviceId) {
        console.error('No device found. Nothing to unregister.');
        process.exit(1);
    }

    const answer = await new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin, output: process.stderr });
        rl.question(
            `\nThis will unregister this machine from all pipe transfers.\nThe other machine(s) in any existing pair will need to run 'flashview pipe setup' again.\nAre you sure? [y/N] `,
            (a) => { rl.close(); resolve(a.trim().toLowerCase()); }
        );
    });

    if (answer !== 'y') {
        process.stderr.write('Cancelled.\n');
        process.exit(0);
    }

    await client.destroyDevice(identity.deviceId).catch(() => {});

    try {
        if (existsSync(IDENTITY_FILE)) {
            unlinkSync(IDENTITY_FILE);
        }
    } catch {
        // file already gone — that's fine
    }

    process.stderr.write(`This machine has been unregistered. Future senders cannot address transfers to it. Run 'flashview pipe setup' to re-register.\n`);
}

/**
 * Register all pipe-related commands onto the given Commander program.
 *
 * @param {import('commander').Command} program
 */
export function registerPipeCommands(program) {
    const pipeCmd = program
        .command('pipe')
        .description('Send or receive encrypted data between registered machines')
        .option('--verbose', 'Show upload type and transfer stats')
        .option('--expires-in <s>', 'Session TTL in seconds', '600')
        .option('--to <deviceId>', 'Device ID of the receiving machine')
        .option('--json', 'Machine-readable output')
        .action(withErrorHandling(async (options) => {
            const config = getConfig();
            const client = new FlashViewClient(config.url, config.token);
            const expiresIn = options.expiresIn ? parseInt(options.expiresIn, 10) : null;
            const verbose = !!options.verbose;

            if (!process.stdin.isTTY) {
                await runPipeSender(client, { verbose, expiresIn, to: options.to ?? null });
            } else {
                await runPipeReceiver(client, { verbose });
            }
        }));

    const pipeSetupCmd = pipeCmd
        .command('setup')
        .description('Register this machine for pipe transfers.\nEach machine has its own registered device ID.');

    pipeSetupCmd
        .action(withErrorHandling(async () => {
            const config = getConfig();
            const client = new FlashViewClient(config.url, config.token);
            await runSetup(client);
        }));

    pipeSetupCmd
        .command('unregister')
        .alias('reset')
        .description('Unregister this machine from pipe transfers and delete local identity key')
        .action(withErrorHandling(async () => {
            const config = getConfig();
            const client = new FlashViewClient(config.url, config.token);
            await runReset(client);
        }));

    pipeSetupCmd
        .command('show')
        .description('Show registered device ID for this machine')
        .action(withErrorHandling(async () => {
            const identity = loadIdentityKeypair();
            if (!identity || !identity.deviceId) {
                console.log('No device registered.');
                console.log('Run \'flashview pipe setup\' to register this machine.');
            } else {
                console.log(`Device ID: ${identity.deviceId}`);
                console.log(`Identity:  ${IDENTITY_FILE}`);
            }
        }));
}
