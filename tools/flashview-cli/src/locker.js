import { createInterface } from 'node:readline';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { execSync } from 'node:child_process';

import {
    encryptToBlob,
    encryptFileToBlob,
    decryptFromBlob,
    deriveAuthKey,
    computeVerifier,
    LockerDecryptionError,
} from './crypto.js';
import { FlashViewClient, ApiError } from './api.js';
import { getConfig } from './config.js';

function prompt(question) {
    return new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin, output: process.stderr });
        rl.question(question, (answer) => { rl.close(); resolve(answer); });
    });
}

function promptPassword(question) {
    return prompt(question);
}

function openBrowser(url) {
    const platform = process.platform;
    try {
        if (platform === 'darwin') execSync(`open ${url}`);
        else if (platform === 'win32') execSync(`start ${url}`, { shell: true });
        else execSync(`xdg-open ${url}`);
    } catch {
        // ignore open errors
    }
}

async function getClient() {
    const config = getConfig();
    if (!config.url) {
        console.error('No server URL configured. Run `flashview config set` first.');
        process.exit(1);
    }
    return new FlashViewClient(config.url, config.token ?? '');
}

function withErrorHandling(fn) {
    return async (...args) => {
        try {
            await fn(...args);
        } catch (err) {
            if (err instanceof ApiError) {
                if (err.status === 404) console.error('Locker not found.');
                else if (err.status === 410) console.error('This locker has expired and is no longer accessible.');
                else if (err.status === 429) console.error('Too many requests. Please wait before trying again.');
                else if (err.status === 403) console.error('Authentication failed — wrong passphrase or invalid update token.');
                else console.error(`Error: ${err.message}`);
            } else if (err instanceof LockerDecryptionError) {
                console.error('Decryption failed — incorrect passphrase.');
            } else {
                console.error(`Unexpected error: ${err.message}`);
            }
            process.exit(1);
        }
    };
}

async function lockerBuy() {
    const config = getConfig();
    const url = `${config.url}/lockers/buy`;
    console.log(`eLocker pricing: ${url}`);
    console.log('Opening in browser…');
    openBrowser(url);
    console.log('\nNote: locker purchase requires a browser and Stripe checkout.');
    console.log('After payment, use `flashview locker create` with your credit token.');
}

async function lockerCreate(options) {
    const creditToken = await prompt('Credit token (from payment confirmation): ');
    if (!creditToken) { console.error('Credit token is required.'); process.exit(1); }

    const client = await getClient();

    // Verify the credit token to get tier and years
    let creditInfo;
    try {
        const status = await client.getLockerCreditStatus('').catch(() => null);
    } catch {
        // ignore
    }

    const accountId = await prompt('Choose a 10-digit account ID: ');
    if (!/^\d{10}$/.test(accountId)) {
        console.error('Account ID must be exactly 10 digits.');
        process.exit(1);
    }

    const passphrase = await promptPassword('Passphrase (min 8 chars): ');
    if (passphrase.length < 8) { console.error('Passphrase must be at least 8 characters.'); process.exit(1); }

    let payload;
    let storagePath = null;
    const tier = options.file ? 'file' : 'text';

    if (options.file) {
        const filePath = resolve(options.file);
        const buffer = readFileSync(filePath);
        const meta = JSON.stringify({ name: options.file, type: 'application/octet-stream', size: buffer.length });
        console.error('Encrypting…');
        payload = await encryptToBlob(meta, passphrase);
        storagePath = `lockers/${accountId}/payload`;
    } else {
        let content;
        if (!process.stdin.isTTY) {
            const chunks = [];
            for await (const chunk of process.stdin) chunks.push(chunk);
            content = Buffer.concat(chunks).toString('utf-8');
        } else {
            content = await prompt('Content: ');
        }
        console.error('Encrypting…');
        payload = await encryptToBlob(content, passphrase);
    }

    const authKey = await deriveAuthKey(passphrase, accountId);
    const challenge = Buffer.from(crypto.getRandomValues(new Uint8Array(32))).toString('hex');
    const verifier = await computeVerifier(authKey, challenge);

    const result = await client.createLocker({
        account_id:    accountId,
        credit_token:  creditToken,
        payload,
        auth_verifier: verifier,
        tier,
        storage_path:  storagePath,
    });

    console.log('\nLocker created!');
    console.log('─────────────────────────────────────────────────────────');
    console.log(`Account ID:   ${result.account_id}`);
    console.log(`Passphrase:   ${passphrase}`);
    console.log(`Update Token: ${result.update_token}`);
    console.log(`Expires:      ${new Date(result.expires_at).toLocaleDateString()}`);
    console.log('─────────────────────────────────────────────────────────');
    console.log('\nSave all three credentials — none can be recovered from the server.');
}

async function lockerOpen(accountId, options) {
    const passphrase = await promptPassword('Passphrase: ');
    const client = await getClient();

    const data = await client.getLockerPayload(accountId);
    const result = await decryptFromBlob(data.payload, passphrase);

    if (result.type === 'text') {
        const text = new TextDecoder().decode(result.data);
        if (options.output) {
            writeFileSync(options.output, text, 'utf-8');
            console.log(`Decrypted content saved to ${options.output}`);
        } else {
            process.stdout.write(text);
        }
    } else {
        if (options.output) {
            writeFileSync(options.output, Buffer.from(result.data));
            console.log(`File saved to ${options.output}`);
        } else {
            console.error('File locker detected. Use --output <path> to save the file.');
            process.exit(1);
        }
    }
}

async function lockerUpdate(accountId, options) {
    const updateToken = options.updateToken;
    if (!updateToken) { console.error('--update-token is required.'); process.exit(1); }

    const passphrase = await promptPassword('Passphrase (for re-encryption): ');
    const client = await getClient();

    let payload;

    if (options.file) {
        const filePath = resolve(options.file);
        const buffer = readFileSync(filePath);
        const meta = JSON.stringify({ name: options.file, type: 'application/octet-stream', size: buffer.length });
        console.error('Encrypting…');
        payload = await encryptToBlob(meta, passphrase);
    } else {
        let content;
        if (!process.stdin.isTTY) {
            const chunks = [];
            for await (const chunk of process.stdin) chunks.push(chunk);
            content = Buffer.concat(chunks).toString('utf-8');
        } else {
            content = await prompt('New content: ');
        }
        console.error('Encrypting…');
        payload = await encryptToBlob(content, passphrase);
    }

    await client.updateLocker(accountId, payload, updateToken);
    console.log('Locker updated.');
}

async function lockerDelete(accountId, options) {
    const updateToken = options.updateToken;
    if (!updateToken) { console.error('--update-token is required.'); process.exit(1); }

    const confirm = await prompt('Type the account ID to confirm deletion: ');
    if (confirm !== accountId) { console.error('Confirmation did not match. Aborted.'); process.exit(1); }

    const client = await getClient();
    await client.deleteLocker(accountId, updateToken);
    console.log('Locker deleted.');
}

async function lockerRenew(accountId) {
    const passphrase = await promptPassword('Passphrase: ');
    const client = await getClient();

    const challengeData = await client.getLockerRenewChallenge(accountId);
    const authKey = await deriveAuthKey(passphrase, accountId);
    const verifier = await computeVerifier(authKey, challengeData.challenge);

    const yearsStr = await prompt('Renewal duration (1, 3, or 5 years): ');
    const years = parseInt(yearsStr, 10);
    if (![1, 3, 5].includes(years)) { console.error('Invalid duration. Must be 1, 3, or 5.'); process.exit(1); }

    const tierStr = await prompt('Tier (text/file): ');
    if (!['text', 'file'].includes(tierStr)) { console.error('Tier must be text or file.'); process.exit(1); }

    const result = await client.submitRenewVerifier(accountId, verifier, years, tierStr);

    console.log(`\nRenewal payment URL: ${result.checkout_url}`);
    console.log('Opening in browser…');
    openBrowser(result.checkout_url);
    console.log('\nNote: the auth challenge is rotated after each successful renewal.');
    console.log('Your passphrase will be required again for future renewals.');
}

export function registerLockerCommands(program) {
    const lockerCmd = program
        .command('locker')
        .description('Manage anonymous encrypted eLockers\n\nNote: --name label flag is not supported in v1 (no server-side label storage).\nUse your 10-digit account ID as your reference.');

    lockerCmd
        .command('buy')
        .description('Open the eLocker pricing page in your browser')
        .action(withErrorHandling(lockerBuy));

    lockerCmd
        .command('create')
        .description('Create a new eLocker (requires credit token from a completed purchase)')
        .option('--file <path>', 'Encrypt a file instead of text (file tier only)')
        .action(withErrorHandling(lockerCreate));

    lockerCmd
        .command('open <accountId>')
        .description('Unlock and view a locker')
        .option('--output <path>', 'Save decrypted file to path (file lockers only)')
        .action(withErrorHandling(lockerOpen));

    lockerCmd
        .command('update <accountId>')
        .description('Update locker content')
        .requiredOption('--update-token <token>', 'Update token from locker creation')
        .option('--file <path>', 'New file to encrypt and store (file lockers only)')
        .action(withErrorHandling(lockerUpdate));

    lockerCmd
        .command('delete <accountId>')
        .description('Delete a locker permanently')
        .requiredOption('--update-token <token>', 'Update token from locker creation')
        .action(withErrorHandling(lockerDelete));

    lockerCmd
        .command('renew <accountId>')
        .description('Renew a locker — authenticates with passphrase, opens Stripe checkout')
        .action(withErrorHandling(lockerRenew));
}
