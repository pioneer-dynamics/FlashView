import { readFileSync, writeFileSync, mkdirSync, renameSync } from 'node:fs';
import { join } from 'node:path';
import { homedir } from 'node:os';

const FLASHVIEW_DIR = join(homedir(), '.flashview');
const IDENTITY_FILE = join(FLASHVIEW_DIR, 'identity_key.json');
const IDENTITY_TMP = join(FLASHVIEW_DIR, '.identity_key.json.tmp');

function ensureDir() {
    mkdirSync(FLASHVIEW_DIR, { recursive: true, mode: 0o700 });
}

/**
 * Load the identity keypair from disk.
 *
 * @returns {{ publicKeyBase64: string, privateKeyBase64: string }|null}
 */
export function loadIdentityKeypair() {
    try {
        const raw = JSON.parse(readFileSync(IDENTITY_FILE, 'utf8'));
        if (!raw.publicKeyBase64 || !raw.privateKeyBase64) {
            return null;
        }
        return { publicKeyBase64: raw.publicKeyBase64, privateKeyBase64: raw.privateKeyBase64 };
    } catch {
        return null;
    }
}

/**
 * Atomically save the identity keypair to disk (mode 0o600).
 *
 * @param {{ publicKeyBase64: string, privateKeyBase64: string }} keypair
 */
export function saveIdentityKeypair({ publicKeyBase64, privateKeyBase64 }) {
    ensureDir();
    const data = { publicKeyBase64, privateKeyBase64 };
    writeFileSync(IDENTITY_TMP, JSON.stringify(data, null, 2), { encoding: 'utf8', mode: 0o600 });
    renameSync(IDENTITY_TMP, IDENTITY_FILE);
}
