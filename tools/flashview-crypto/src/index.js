import { generate } from 'random-words';

const PBKDF2_ITERATIONS = 64000;
const KEY_LENGTH_BITS = 256;
const IV_LENGTH = 12;  // 96-bit IV for AES-GCM
const SALT_LENGTH = 8; // 8 bytes = 16 hex chars

/**
 * Generate an 8-word hyphenated passphrase.
 *
 * @returns {string}
 */
export function generatePassphrase() {
    return generate({ exactly: 8, join: '-' });
}

/**
 * Derive an AES-256-GCM key from a passphrase and salt using PBKDF2-SHA-512.
 *
 * @param {string} passphrase
 * @param {Uint8Array} salt
 * @param {string[]} keyUsages
 * @returns {Promise<CryptoKey>}
 */
async function deriveKey(passphrase, salt, keyUsages) {
    const passphraseKey = await globalThis.crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey']
    );

    return globalThis.crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-512' },
        passphraseKey,
        { name: 'AES-GCM', length: KEY_LENGTH_BITS },
        false,
        keyUsages
    );
}

/**
 * Convert a Uint8Array to a base64 string (isomorphic — works in browser and Node.js 18+).
 *
 * @param {Uint8Array} bytes
 * @returns {string}
 */
function uint8ArrayToBase64(bytes) {
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

/**
 * Convert a base64 string to a Uint8Array (isomorphic — works in browser and Node.js 18+).
 *
 * @param {string} base64
 * @returns {Uint8Array}
 */
function base64ToUint8Array(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

/**
 * Encrypt a plaintext message using AES-256-GCM with PBKDF2-SHA-512 key derivation.
 *
 * Output format: hex(8-byte salt) + base64(12-byte IV + encrypted data + 16-byte auth tag)
 * This format is byte-identical to the existing browser (OpenCrypto) and CLI implementations.
 *
 * @param {string} message
 * @param {string|null} passphrase - Auto-generated if null
 * @returns {Promise<{ passphrase: string, secret: string }>}
 */
export async function encryptMessage(message, passphrase = null) {
    if (!passphrase) {
        passphrase = generatePassphrase();
    }

    const salt = new Uint8Array(SALT_LENGTH);
    globalThis.crypto.getRandomValues(salt);

    const iv = new Uint8Array(IV_LENGTH);
    globalThis.crypto.getRandomValues(iv);

    const key = await deriveKey(passphrase, salt, ['encrypt']);

    const ciphertext = await globalThis.crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        new TextEncoder().encode(message)
    );

    // Web Crypto AES-GCM appends the 16-byte auth tag at the end of ciphertext.
    // Combine: IV (12 bytes) + ciphertext+authTag
    const combined = new Uint8Array(IV_LENGTH + ciphertext.byteLength);
    combined.set(iv);
    combined.set(new Uint8Array(ciphertext), IV_LENGTH);

    const saltHex = Array.from(salt).map(b => b.toString(16).padStart(2, '0')).join('');
    const secret = saltHex + uint8ArrayToBase64(combined);

    return { passphrase, secret };
}

/**
 * Decrypt a ciphertext string using AES-256-GCM with PBKDF2-SHA-512 key derivation.
 *
 * Accepts ciphertexts produced by this package, the browser OpenCrypto implementation,
 * or the Node.js CLI implementation — all use the same format.
 *
 * @param {string} ciphertextString - hex(salt) + base64(IV + encrypted + authTag)
 * @param {string} passphrase
 * @returns {Promise<string>}
 */
export async function decryptMessage(ciphertextString, passphrase) {
    const saltHex = ciphertextString.substring(0, 16);
    const salt = new Uint8Array(SALT_LENGTH);
    for (let i = 0; i < SALT_LENGTH; i++) {
        salt[i] = parseInt(saltHex.substr(i * 2, 2), 16);
    }

    const combined = base64ToUint8Array(ciphertextString.substring(16));

    // combined = IV (12 bytes) + encrypted data + auth tag (16 bytes)
    const iv = combined.slice(0, IV_LENGTH);
    const ciphertext = combined.slice(IV_LENGTH); // encrypted + authTag

    const key = await deriveKey(passphrase, salt, ['decrypt']);

    const decrypted = await globalThis.crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        key,
        ciphertext
    );

    return new TextDecoder().decode(decrypted);
}
