import { readFileSync, writeFileSync, mkdirSync, renameSync, openSync, closeSync, unlinkSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { homedir } from 'node:os';

const FLASHVIEW_DIR = join(homedir(), '.flashview');
const CONFIG_FILE = join(FLASHVIEW_DIR, 'pipe_config.json');
const CONFIG_TMP = join(FLASHVIEW_DIR, '.pipe_config.json.tmp');
const LOCK_FILE = join(FLASHVIEW_DIR, '.pipe_config.lock');

const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const EXPORT_PREFIX = 'FVPIPE';
const LOCK_TIMEOUT_MS = 5000;
const LOCK_RETRY_MS = 50;

function ensureDir() {
    mkdirSync(FLASHVIEW_DIR, { recursive: true, mode: 0o700 });
}

/**
 * Load the current pipe config from disk.
 *
 * @returns {{ seed: string, counter: number, created_at: string }|null}
 */
export function loadPipeConfig() {
    try {
        const raw = JSON.parse(readFileSync(CONFIG_FILE, 'utf8'));
        if (!raw.pipe_seed || raw.counter === undefined) {
            return null;
        }
        return { seed: raw.pipe_seed, counter: raw.counter, created_at: raw.created_at };
    } catch {
        return null;
    }
}

/**
 * Atomically save the pipe config to disk.
 *
 * @param {{ seed: string, counter: number }} param0
 */
export function savePipeConfig({ seed, counter }) {
    ensureDir();
    const data = {
        pipe_seed: seed,
        counter,
        created_at: new Date().toISOString(),
    };
    writeFileSync(CONFIG_TMP, JSON.stringify(data, null, 2), { encoding: 'utf8', mode: 0o600 });
    renameSync(CONFIG_TMP, CONFIG_FILE);
}

/**
 * Acquire the pipe config lock using O_EXCL (exclusive create).
 * Returns a cleanup function to release the lock.
 *
 * @returns {Promise<() => void>}
 */
async function acquireLock() {
    ensureDir();
    const deadline = Date.now() + LOCK_TIMEOUT_MS;

    while (Date.now() < deadline) {
        try {
            const fd = openSync(LOCK_FILE, 'wx');
            closeSync(fd);
            return () => {
                try { unlinkSync(LOCK_FILE); } catch {}
            };
        } catch {
            await new Promise(r => setTimeout(r, LOCK_RETRY_MS));
        }
    }

    throw new Error('Another pipe transfer is already running.');
}

/**
 * Atomically consume counter N (local counter becomes N+1) and return N.
 *
 * @returns {Promise<number>}
 */
export async function incrementCounter() {
    const release = await acquireLock();
    try {
        const config = loadPipeConfig();
        if (!config) {
            throw new Error('Pipe seed not found. Run \'flashview pipe setup\' to get started.');
        }
        const n = config.counter;
        savePipeConfig({ seed: config.seed, counter: n + 1 });
        return n;
    } finally {
        release();
    }
}

/**
 * Set local counter to n+1 (called by receiver after finding a session at counter n).
 *
 * @param {number} n
 * @returns {Promise<void>}
 */
export async function advanceCounterTo(n) {
    const release = await acquireLock();
    try {
        const config = loadPipeConfig();
        if (!config) {
            throw new Error('Pipe seed not found. Run \'flashview pipe setup\' to get started.');
        }
        savePipeConfig({ seed: config.seed, counter: n + 1 });
    } finally {
        release();
    }
}

/**
 * Encode a buffer to base32 (RFC 4648).
 *
 * @param {Buffer} buf
 * @returns {string}
 */
function base32Encode(buf) {
    let result = '';
    let bits = 0;
    let value = 0;

    for (let i = 0; i < buf.length; i++) {
        value = (value << 8) | buf[i];
        bits += 8;
        while (bits >= 5) {
            result += BASE32_CHARS[(value >>> (bits - 5)) & 0x1f];
            bits -= 5;
        }
    }

    if (bits > 0) {
        result += BASE32_CHARS[(value << (5 - bits)) & 0x1f];
    }

    return result;
}

/**
 * Decode a base32 string to a Buffer (RFC 4648).
 *
 * @param {string} str
 * @returns {Buffer}
 */
function base32Decode(str) {
    const bytes = [];
    let bits = 0;
    let value = 0;

    for (const char of str.toUpperCase()) {
        const idx = BASE32_CHARS.indexOf(char);
        if (idx < 0) {
            throw new Error(`Invalid base32 character: ${char}`);
        }
        value = (value << 5) | idx;
        bits += 5;
        if (bits >= 8) {
            bytes.push((value >>> (bits - 8)) & 0xff);
            bits -= 8;
        }
    }

    return Buffer.from(bytes);
}

/**
 * Compute a simple 1-byte XOR checksum.
 *
 * @param {Buffer} buf
 * @returns {number}
 */
function checksum(buf) {
    return buf.reduce((acc, b) => acc ^ b, 0);
}

/**
 * Export the current pipe config as a portable code.
 * Format: FVPIPE-XXXX-XXXX-XXXX-XXXX (base32 encoded seed+counter+checksum)
 *
 * @param {{ seed: string, counter: number }} param0
 * @returns {string}
 */
export function exportConfig({ seed, counter }) {
    const seedBytes = Buffer.from(seed, 'base64');
    const counterBuf = Buffer.alloc(8);
    counterBuf.writeBigUInt64BE(BigInt(counter));
    const payload = Buffer.concat([seedBytes, counterBuf]);
    const cs = checksum(payload);
    const full = Buffer.concat([payload, Buffer.from([cs])]);
    const encoded = base32Encode(full);
    const parts = encoded.match(/.{1,4}/g) ?? [];
    return `${EXPORT_PREFIX}-${parts.join('-')}`;
}

/**
 * Import a pipe config from an export code.
 *
 * @param {string} phrase
 * @returns {{ seed: string, counter: number }}
 */
export function importConfig(phrase) {
    const upper = phrase.toUpperCase().trim();
    if (!upper.startsWith(EXPORT_PREFIX + '-')) {
        throw new Error(`Invalid export code. Expected code starting with "${EXPORT_PREFIX}-".`);
    }
    const encoded = upper.slice(EXPORT_PREFIX.length + 1).replace(/-/g, '');

    let decoded;
    try {
        decoded = base32Decode(encoded);
    } catch {
        throw new Error('Invalid export code: could not decode. Check for typos.');
    }

    if (decoded.length < 41) {
        throw new Error('Invalid export code: too short.');
    }

    const payload = decoded.slice(0, -1);
    const cs = decoded[decoded.length - 1];

    if (checksum(payload) !== cs) {
        throw new Error('Invalid export code: checksum mismatch. Check for typos.');
    }

    const seedBytes = payload.slice(0, 32);
    const counterBuf = payload.slice(32, 40);
    const counter = Number(counterBuf.readBigUInt64BE());

    return {
        seed: seedBytes.toString('base64'),
        counter,
    };
}

/**
 * Check whether a stale lock file exists (from a crashed process).
 * If so, remove it. Used as a safety valve on startup.
 */
export function cleanStaleLock() {
    try {
        if (existsSync(LOCK_FILE)) {
            unlinkSync(LOCK_FILE);
        }
    } catch {}
}
