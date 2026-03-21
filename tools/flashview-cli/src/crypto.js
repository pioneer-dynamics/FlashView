import { randomBytes, pbkdf2Sync, createCipheriv, createDecipheriv } from 'node:crypto';
import { generate } from 'random-words';

const PBKDF2_ITERATIONS = 64000;
const KEY_LENGTH = 32; // 256-bit key for AES-256-GCM
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
 * Encrypt a plaintext message using AES-256-GCM with PBKDF2 key derivation.
 *
 * Output format matches the browser's OpenCrypto implementation:
 * hex(8-byte salt) + base64(12-byte IV + encrypted data + 16-byte auth tag)
 *
 * @param {string} plaintext
 * @param {string|null} passphrase - Auto-generated if null
 * @returns {{ passphrase: string, secret: string }}
 */
export function encryptMessage(plaintext, passphrase = null) {
    if (!passphrase) {
        passphrase = generatePassphrase();
    }

    const salt = randomBytes(SALT_LENGTH);
    const key = pbkdf2Sync(passphrase, salt, PBKDF2_ITERATIONS, KEY_LENGTH, 'sha512');
    const iv = randomBytes(IV_LENGTH);

    const cipher = createCipheriv('aes-256-gcm', key, iv);
    const encrypted = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
    const authTag = cipher.getAuthTag(); // 16 bytes

    // Combine IV + encrypted + authTag (matches OpenCrypto format)
    const ciphertext = Buffer.concat([iv, encrypted, authTag]);

    // Format: hex(salt) + base64(IV + encrypted + authTag)
    const saltHex = salt.toString('hex');
    const ciphertextBase64 = ciphertext.toString('base64');

    return {
        passphrase,
        secret: saltHex + ciphertextBase64,
    };
}

/**
 * Decrypt a ciphertext string using AES-256-GCM with PBKDF2 key derivation.
 *
 * @param {string} ciphertextString - hex(salt) + base64(IV + encrypted + authTag)
 * @param {string} passphrase
 * @returns {string}
 */
export function decryptMessage(ciphertextString, passphrase) {
    const saltHex = ciphertextString.substring(0, 16);
    const salt = Buffer.from(saltHex, 'hex');

    const ciphertext = Buffer.from(ciphertextString.substring(16), 'base64');

    const key = pbkdf2Sync(passphrase, salt, PBKDF2_ITERATIONS, KEY_LENGTH, 'sha512');

    const iv = ciphertext.subarray(0, 12);
    const authTag = ciphertext.subarray(ciphertext.length - 16);
    const encryptedData = ciphertext.subarray(12, ciphertext.length - 16);

    const decipher = createDecipheriv('aes-256-gcm', key, iv);
    decipher.setAuthTag(authTag);
    const decrypted = Buffer.concat([decipher.update(encryptedData), decipher.final()]);

    return decrypted.toString('utf8');
}
