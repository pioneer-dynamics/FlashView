import { generate } from 'random-words';
import { p256 } from '@noble/curves/p256';
import { ed25519 } from '@noble/curves/ed25519';

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
 * Encrypt a binary buffer using AES-256-GCM with PBKDF2-SHA-512 key derivation.
 *
 * Output format (raw binary): [8 bytes salt][12 bytes IV][ciphertext + 16 bytes auth tag]
 *
 * @param {Uint8Array} buffer
 * @param {string|null} passphrase - Auto-generated if null
 * @returns {Promise<{ passphrase: string, encrypted: Uint8Array }>}
 */
export async function encryptBuffer(buffer, passphrase = null) {
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
        buffer
    );

    const encrypted = new Uint8Array(SALT_LENGTH + IV_LENGTH + ciphertext.byteLength);
    encrypted.set(salt, 0);
    encrypted.set(iv, SALT_LENGTH);
    encrypted.set(new Uint8Array(ciphertext), SALT_LENGTH + IV_LENGTH);

    return { passphrase, encrypted };
}

/**
 * Decrypt a binary buffer produced by encryptBuffer.
 *
 * @param {Uint8Array} encryptedBuffer
 * @param {string} passphrase
 * @returns {Promise<Uint8Array>}
 */
export async function decryptBuffer(encryptedBuffer, passphrase) {
    const salt = encryptedBuffer.slice(0, SALT_LENGTH);
    const iv = encryptedBuffer.slice(SALT_LENGTH, SALT_LENGTH + IV_LENGTH);
    const ciphertext = encryptedBuffer.slice(SALT_LENGTH + IV_LENGTH);

    const key = await deriveKey(passphrase, salt, ['decrypt']);

    const decrypted = await globalThis.crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        key,
        ciphertext
    );

    return new Uint8Array(decrypted);
}

// ─── Pipe Crypto Primitives ───────────────────────────────────────────────────

const PIPE_CHUNK_IV_LENGTH = 12;

/**
 * Generate a cryptographically random 32-byte transfer key (base64-encoded).
 * Used once per transfer; never reused or stored.
 *
 * @returns {string}
 */
export function generateTransferKey() {
    const key = new Uint8Array(32);
    globalThis.crypto.getRandomValues(key);
    return uint8ArrayToBase64(key);
}

/**
 * Generate a cryptographically random session ID (32-char lowercase hex).
 *
 * @returns {string}
 */
export function generateSessionId() {
    const bytes = new Uint8Array(16);
    globalThis.crypto.getRandomValues(bytes);
    return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Encrypt a chunk with AES-256-GCM; chunkIndex in AAD prevents reordering attacks.
 *
 * Output format: [12 bytes random IV][ciphertext + 16 bytes auth tag]
 *
 * @param {Uint8Array} plaintextBuffer
 * @param {string} sessionKeyBase64
 * @param {number} chunkIndex
 * @returns {Promise<Uint8Array>}
 */
export async function encryptChunk(plaintextBuffer, sessionKeyBase64, chunkIndex) {
    const keyBytes = base64ToUint8Array(sessionKeyBase64);
    const aesKey = await globalThis.crypto.subtle.importKey('raw', keyBytes, { name: 'AES-GCM' }, false, ['encrypt']);

    const iv = new Uint8Array(PIPE_CHUNK_IV_LENGTH);
    globalThis.crypto.getRandomValues(iv);

    const aad = new TextEncoder().encode(`flashview-pipe-chunk-v1:${chunkIndex}`);
    const ciphertext = await globalThis.crypto.subtle.encrypt(
        { name: 'AES-GCM', iv, additionalData: aad },
        aesKey,
        plaintextBuffer,
    );

    const result = new Uint8Array(PIPE_CHUNK_IV_LENGTH + ciphertext.byteLength);
    result.set(iv);
    result.set(new Uint8Array(ciphertext), PIPE_CHUNK_IV_LENGTH);
    return result;
}

/**
 * Decrypt a chunk encrypted by encryptChunk; validates AAD chunkIndex.
 *
 * @param {Uint8Array} ciphertextBuffer - [12-byte IV][ciphertext + 16-byte auth tag]
 * @param {string} sessionKeyBase64
 * @param {number} chunkIndex
 * @returns {Promise<Uint8Array>}
 */
export async function decryptChunk(ciphertextBuffer, sessionKeyBase64, chunkIndex) {
    const keyBytes = base64ToUint8Array(sessionKeyBase64);
    const aesKey = await globalThis.crypto.subtle.importKey('raw', keyBytes, { name: 'AES-GCM' }, false, ['decrypt']);

    const iv = ciphertextBuffer.slice(0, PIPE_CHUNK_IV_LENGTH);
    const ciphertext = ciphertextBuffer.slice(PIPE_CHUNK_IV_LENGTH);
    const aad = new TextEncoder().encode(`flashview-pipe-chunk-v1:${chunkIndex}`);

    const decrypted = await globalThis.crypto.subtle.decrypt(
        { name: 'AES-GCM', iv, additionalData: aad },
        aesKey,
        ciphertext,
    );
    return new Uint8Array(decrypted);
}

// ─── Identity Keypair + ECIES Pairing ─────────────────────────────────────────

const PAIRING_HKDF_SALT = new TextEncoder().encode('flashview-pipe-pairing-v1');

/**
 * Generate a P-256 ECDH identity keypair for PKI-based pairing.
 *
 * @returns {Promise<{ publicKeyBase64: string, privateKeyBase64: string }>} JWK-format, base64-encoded
 */
export async function generateIdentityKeypair() {
    const keypair = await globalThis.crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveBits'],
    );

    const [publicJwk, privateJwk] = await Promise.all([
        globalThis.crypto.subtle.exportKey('jwk', keypair.publicKey),
        globalThis.crypto.subtle.exportKey('jwk', keypair.privateKey),
    ]);

    return {
        publicKeyBase64: btoa(JSON.stringify(publicJwk)),
        privateKeyBase64: btoa(JSON.stringify(privateJwk)),
    };
}

/**
 * ECIES: encrypt a transfer key for a peer device using ECDH + HKDF + AES-256-GCM.
 *
 * Algorithm:
 *   1. sharedSecret = ECDH(ownPrivateKey, peerPublicKey)
 *   2. key = HKDF-SHA-256(ikm=sharedSecret, salt=UTF-8("flashview-pipe-pairing-v1"), info=UTF-8("encryptKeyForDevice"), length=32)
 *   3. iv = 12 random bytes
 *   4. ciphertext+tag = AES-256-GCM encrypt(plaintext=transferKeyBytes, key, iv, aad=none)
 *   5. blob = base64( iv[12] || ciphertext || tag[16] )
 *
 * @param {string} transferKeyBase64 - 32-byte transfer key, base64-encoded
 * @param {string} peerPublicKeyBase64 - JWK base64
 * @param {string} ownPrivateKeyBase64 - JWK base64
 * @returns {Promise<string>} base64-encoded ECIES blob
 */
export async function encryptKeyForDevice(transferKeyBase64, peerPublicKeyBase64, ownPrivateKeyBase64) {
    const peerPublicKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(peerPublicKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        [],
    );
    const ownPrivateKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(ownPrivateKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        ['deriveBits'],
    );

    const sharedBits = await globalThis.crypto.subtle.deriveBits(
        { name: 'ECDH', public: peerPublicKey },
        ownPrivateKey,
        256,
    );

    const sharedKey = await globalThis.crypto.subtle.importKey('raw', sharedBits, 'HKDF', false, ['deriveKey']);
    const aesKey = await globalThis.crypto.subtle.deriveKey(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt: PAIRING_HKDF_SALT,
            info: new TextEncoder().encode('encryptKeyForDevice'),
        },
        sharedKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt'],
    );

    const iv = new Uint8Array(12);
    globalThis.crypto.getRandomValues(iv);

    const keyBytes = base64ToUint8Array(transferKeyBase64);
    const ciphertext = await globalThis.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, keyBytes);

    const blob = new Uint8Array(12 + ciphertext.byteLength);
    blob.set(iv);
    blob.set(new Uint8Array(ciphertext), 12);
    return uint8ArrayToBase64(blob);
}

/**
 * ECIES: decrypt a transfer key from a peer device using ECDH + HKDF + AES-256-GCM.
 *
 * Blob format: first 12 bytes = IV, remainder = ciphertext + 16-byte auth tag.
 *
 * @param {string} encryptedBase64 - base64-encoded blob
 * @param {string} ownPrivateKeyBase64 - JWK base64
 * @param {string} peerPublicKeyBase64 - JWK base64
 * @returns {Promise<string>} transfer key as base64
 */
export async function decryptKeyFromDevice(encryptedBase64, ownPrivateKeyBase64, peerPublicKeyBase64) {
    const peerPublicKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(peerPublicKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        [],
    );
    const ownPrivateKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(ownPrivateKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        ['deriveBits'],
    );

    const sharedBits = await globalThis.crypto.subtle.deriveBits(
        { name: 'ECDH', public: peerPublicKey },
        ownPrivateKey,
        256,
    );

    const sharedKey = await globalThis.crypto.subtle.importKey('raw', sharedBits, 'HKDF', false, ['deriveKey']);
    const aesKey = await globalThis.crypto.subtle.deriveKey(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt: PAIRING_HKDF_SALT,
            info: new TextEncoder().encode('encryptKeyForDevice'),
        },
        sharedKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['decrypt'],
    );

    const blob = base64ToUint8Array(encryptedBase64);
    const iv = blob.slice(0, 12);
    const ciphertext = blob.slice(12);

    const decrypted = await globalThis.crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aesKey, ciphertext);
    return uint8ArrayToBase64(new Uint8Array(decrypted));
}

/**
 * Compute the pairing verification code for MITM detection.
 *
 * CANONICAL ARG ORDER: senderPublicKeyBase64 = device with seed (Machine A),
 *   receiverPublicKeyBase64 = device waiting for seed (Machine B).
 * Both machines must call with the same argument order.
 *
 * Algorithm: SHA-256(rawBytesA || rawBytesB) → first 3 bytes as 24-bit big-endian uint
 *   → mod 1_000_000 → zero-pad to 6 decimal digits → insert dash at position 3 → "NNN-NNN"
 *
 * @param {string} senderPublicKeyBase64 - JWK base64 (sender = device with seed)
 * @param {string} receiverPublicKeyBase64 - JWK base64 (receiver = device waiting)
 * @returns {Promise<string>} e.g. "047-283"
 */
export async function computePairingCode(senderPublicKeyBase64, receiverPublicKeyBase64) {
    const senderBytes = base64ToUint8Array(senderPublicKeyBase64);
    const receiverBytes = base64ToUint8Array(receiverPublicKeyBase64);

    const combined = new Uint8Array(senderBytes.length + receiverBytes.length);
    combined.set(senderBytes);
    combined.set(receiverBytes, senderBytes.length);

    const hashBuffer = await globalThis.crypto.subtle.digest('SHA-256', combined);
    const hashBytes = new Uint8Array(hashBuffer);

    const num = ((hashBytes[0] << 16) | (hashBytes[1] << 8) | hashBytes[2]) % 1_000_000;
    const padded = num.toString().padStart(6, '0');
    return `${padded.slice(0, 3)}-${padded.slice(3)}`;
}

// ─── Message Encryption (legacy PBKDF2 / OpenCrypto) ─────────────────────────

// ─── eLocker Crypto ───────────────────────────────────────────────────────────

const LOCKER_PBKDF2_ITERATIONS = 100_000;
const LOCKER_SALT_LENGTH = 32;       // bytes → 64 hex chars
const LOCKER_BLOB_VERSION = '01';    // 2 hex chars
const LOCKER_TYPE_TEXT = '54';       // hex for ASCII 'T'
const LOCKER_TYPE_FILE = '46';       // hex for ASCII 'F'

export class LockerBlobVersionError extends Error {}
export class LockerDecryptionError extends Error {}

function bufferToHex(bytes) {
    return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
}

function hexToBuffer(hex) {
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < hex.length; i += 2) {
        bytes[i / 2] = parseInt(hex.slice(i, i + 2), 16);
    }
    return bytes;
}

async function deriveLockerEncKey(passphrase, salt) {
    const keyMaterial = await globalThis.crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey']
    );
    return globalThis.crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations: LOCKER_PBKDF2_ITERATIONS, hash: 'SHA-512' },
        keyMaterial,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    );
}

/**
 * Encrypt a text string to a self-contained hex blob for eLocker storage.
 *
 * Blob layout: version(2) + type(2) + salt(64) + iv(24) + AES-GCM ciphertext
 *
 * @param {string} content
 * @param {string} passphrase
 * @returns {Promise<string>} hex blob
 */
export async function encryptToBlob(content, passphrase) {
    const salt = globalThis.crypto.getRandomValues(new Uint8Array(LOCKER_SALT_LENGTH));
    const iv = globalThis.crypto.getRandomValues(new Uint8Array(IV_LENGTH));
    const key = await deriveLockerEncKey(passphrase, salt);
    const ciphertext = await globalThis.crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        new TextEncoder().encode(content)
    );
    return LOCKER_BLOB_VERSION
        + LOCKER_TYPE_TEXT
        + bufferToHex(salt)
        + bufferToHex(iv)
        + bufferToHex(new Uint8Array(ciphertext));
}

/**
 * Encrypt a binary buffer (file) to a self-contained binary Uint8Array for eLocker S3 storage.
 *
 * v1 format (passphrase path): [0x01][0x46][32 salt][12 IV][AES-GCM ciphertext]
 * v2 format (DEK path):        [0x02][0x46][12 IV][AES-GCM ciphertext]
 *
 * @param {ArrayBuffer|Uint8Array} buffer
 * @param {{ passphrase?: string, dek?: Uint8Array }} options
 * @returns {Promise<Uint8Array>}
 */
export async function encryptFileToBuffer(buffer, { passphrase, dek } = {}) {
    const iv = globalThis.crypto.getRandomValues(new Uint8Array(IV_LENGTH));

    if (dek) {
        const aesKey = await globalThis.crypto.subtle.importKey('raw', dek, { name: 'AES-GCM' }, false, ['encrypt']);
        const ciphertext = await globalThis.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, buffer);
        const result = new Uint8Array(2 + IV_LENGTH + ciphertext.byteLength);
        result[0] = 0x02;
        result[1] = 0x46;
        result.set(iv, 2);
        result.set(new Uint8Array(ciphertext), 2 + IV_LENGTH);
        return result;
    }

    const salt = globalThis.crypto.getRandomValues(new Uint8Array(LOCKER_SALT_LENGTH));
    const key = await deriveLockerEncKey(passphrase, salt);
    const ciphertext = await globalThis.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, buffer);
    const result = new Uint8Array(2 + LOCKER_SALT_LENGTH + IV_LENGTH + ciphertext.byteLength);
    result[0] = 0x01;
    result[1] = 0x46;
    result.set(salt, 2);
    result.set(iv, 2 + LOCKER_SALT_LENGTH);
    result.set(new Uint8Array(ciphertext), 2 + LOCKER_SALT_LENGTH + IV_LENGTH);
    return result;
}

/**
 * Decrypt a binary eLocker file blob from S3. Detects v1 (passphrase/PBKDF2) or v2 (raw DEK).
 *
 * @param {Uint8Array} buffer
 * @param {{ passphrase?: string, dek?: Uint8Array }} options
 * @returns {Promise<Uint8Array>} plaintext bytes
 * @throws {LockerBlobVersionError} on unknown version/type byte
 * @throws {LockerDecryptionError} on AES-GCM authentication failure
 */
export async function decryptFileFromBuffer(buffer, { passphrase, dek } = {}) {
    const versionByte = buffer[0];
    const typeByte = buffer[1];

    if (typeByte !== 0x46) {
        throw new LockerBlobVersionError(`Unsupported blob type: 0x${typeByte.toString(16)}`);
    }

    if (versionByte === 0x01) {
        const salt = buffer.slice(2, 2 + LOCKER_SALT_LENGTH);
        const iv = buffer.slice(2 + LOCKER_SALT_LENGTH, 2 + LOCKER_SALT_LENGTH + IV_LENGTH);
        const ciphertext = buffer.slice(2 + LOCKER_SALT_LENGTH + IV_LENGTH);
        const key = await deriveLockerEncKey(passphrase, salt);
        const decrypted = await globalThis.crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ciphertext)
            .catch(() => { throw new LockerDecryptionError('Decryption failed — incorrect passphrase or corrupted data'); });
        return new Uint8Array(decrypted);
    }

    if (versionByte === 0x02) {
        const iv = buffer.slice(2, 2 + IV_LENGTH);
        const ciphertext = buffer.slice(2 + IV_LENGTH);
        const aesKey = await globalThis.crypto.subtle.importKey('raw', dek, { name: 'AES-GCM' }, false, ['decrypt']);
        const decrypted = await globalThis.crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aesKey, ciphertext)
            .catch(() => { throw new LockerDecryptionError('Decryption failed — incorrect DEK or corrupted data'); });
        return new Uint8Array(decrypted);
    }

    throw new LockerBlobVersionError(`Unsupported blob version: 0x${versionByte.toString(16)}`);
}

/**
 * Generate a random 256-bit Data Encryption Key (DEK) for envelope encryption.
 *
 * @returns {Uint8Array} 32 random bytes
 */
export function generateFileKey() {
    const dek = new Uint8Array(32);
    globalThis.crypto.getRandomValues(dek);
    return dek;
}

/**
 * Wrap a DEK with a passphrase-derived Key Encryption Key (KEK).
 *
 * Output: base64(randomSalt[32] + IV[12] + AES-GCM(dek)[48]) ≈ 124 base64 chars
 * KEK = PBKDF2-SHA-512(passphrase, randomSalt‖accountId, 100_000)
 *
 * @param {Uint8Array} dek
 * @param {string} passphrase
 * @param {string} accountId
 * @returns {Promise<string>} base64-encoded wrapped key
 */
export async function wrapFileKey(dek, passphrase, accountId) {
    const randomSalt = globalThis.crypto.getRandomValues(new Uint8Array(LOCKER_SALT_LENGTH));
    const accountIdBytes = new TextEncoder().encode(accountId);
    const pbkdf2Salt = new Uint8Array(LOCKER_SALT_LENGTH + accountIdBytes.length);
    pbkdf2Salt.set(randomSalt, 0);
    pbkdf2Salt.set(accountIdBytes, LOCKER_SALT_LENGTH);

    const iv = globalThis.crypto.getRandomValues(new Uint8Array(IV_LENGTH));
    const kek = await deriveLockerEncKey(passphrase, pbkdf2Salt);
    const wrappedDek = await globalThis.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, kek, dek);

    const blob = new Uint8Array(LOCKER_SALT_LENGTH + IV_LENGTH + wrappedDek.byteLength);
    blob.set(randomSalt, 0);
    blob.set(iv, LOCKER_SALT_LENGTH);
    blob.set(new Uint8Array(wrappedDek), LOCKER_SALT_LENGTH + IV_LENGTH);
    return uint8ArrayToBase64(blob);
}

/**
 * Unwrap a DEK using a passphrase-derived KEK (inverse of wrapFileKey).
 *
 * @param {string} wrappedKeyBase64
 * @param {string} passphrase
 * @param {string} accountId
 * @returns {Promise<Uint8Array>} 32-byte DEK
 * @throws {LockerDecryptionError} on wrong passphrase or corrupted data
 */
export async function unwrapFileKey(wrappedKeyBase64, passphrase, accountId) {
    const blob = base64ToUint8Array(wrappedKeyBase64);
    const randomSalt = blob.slice(0, LOCKER_SALT_LENGTH);
    const iv = blob.slice(LOCKER_SALT_LENGTH, LOCKER_SALT_LENGTH + IV_LENGTH);
    const wrappedDek = blob.slice(LOCKER_SALT_LENGTH + IV_LENGTH);

    const accountIdBytes = new TextEncoder().encode(accountId);
    const pbkdf2Salt = new Uint8Array(LOCKER_SALT_LENGTH + accountIdBytes.length);
    pbkdf2Salt.set(randomSalt, 0);
    pbkdf2Salt.set(accountIdBytes, LOCKER_SALT_LENGTH);

    const kek = await deriveLockerEncKey(passphrase, pbkdf2Salt);
    const dek = await globalThis.crypto.subtle.decrypt({ name: 'AES-GCM', iv }, kek, wrappedDek)
        .catch(() => { throw new LockerDecryptionError('Decryption failed — incorrect passphrase or corrupted data'); });
    return new Uint8Array(dek);
}

/**
 * Decrypt an eLocker hex blob back to its original content.
 *
 * @param {string} blob - hex blob produced by encryptToBlob
 * @param {string} passphrase
 * @returns {Promise<{ type: 'text'|'file', data: ArrayBuffer }>}
 * @throws {LockerBlobVersionError} on unknown version or type byte
 * @throws {LockerDecryptionError} on AES-GCM authentication failure
 */
export async function decryptFromBlob(blob, passphrase) {
    const version = blob.slice(0, 2);
    if (version !== LOCKER_BLOB_VERSION) {
        throw new LockerBlobVersionError(`Unsupported blob version: ${version}`);
    }
    const typeHex = blob.slice(2, 4);
    const type = typeHex === LOCKER_TYPE_TEXT ? 'text' : typeHex === LOCKER_TYPE_FILE ? 'file' : null;
    if (!type) {
        throw new LockerBlobVersionError(`Unsupported blob type: ${typeHex}`);
    }
    const salt = hexToBuffer(blob.slice(4, 68));
    const iv = hexToBuffer(blob.slice(68, 92));
    const ciphertext = hexToBuffer(blob.slice(92));
    const key = await deriveLockerEncKey(passphrase, salt);
    const data = await globalThis.crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ciphertext)
        .catch(() => { throw new LockerDecryptionError('Decryption failed — incorrect passphrase or corrupted data'); });
    return { type, data };
}

/**
 * Derive an HMAC-SHA-256 key from passphrase and accountId for challenge-response auth.
 *
 * @param {string} passphrase
 * @param {string} accountId
 * @returns {Promise<CryptoKey>}
 */
export async function deriveAuthKey(passphrase, accountId) {
    const keyMaterial = await globalThis.crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey']
    );
    return globalThis.crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: new TextEncoder().encode(accountId + ':auth'),
            iterations: LOCKER_PBKDF2_ITERATIONS,
            hash: 'SHA-512',
        },
        keyMaterial,
        { name: 'HMAC', hash: 'SHA-256', length: 256 },
        false,
        ['sign']
    );
}

/**
 * Derive a deterministic 64-char hex update token from passphrase and accountId.
 * Domain-separated from deriveAuthKey via the ":update" suffix so the two keys
 * are cryptographically independent.
 *
 * @param {string} passphrase
 * @param {string} accountId
 * @returns {Promise<string>} 64-char hex token
 */
export async function deriveUpdateToken(passphrase, accountId) {
    const keyMaterial = await globalThis.crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(passphrase),
        'PBKDF2',
        false,
        ['deriveBits']
    );
    const bits = await globalThis.crypto.subtle.deriveBits(
        {
            name: 'PBKDF2',
            salt: new TextEncoder().encode(accountId + ':update'),
            iterations: LOCKER_PBKDF2_ITERATIONS,
            hash: 'SHA-512',
        },
        keyMaterial,
        256
    );
    return bufferToHex(new Uint8Array(bits));
}

/**
 * Generate a cryptographically random 64-char hex challenge for HMAC auth.
 *
 * @returns {string} 32-byte challenge as hex
 */
export function generateChallenge() {
    const bytes = new Uint8Array(32);
    globalThis.crypto.getRandomValues(bytes);
    return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Compute HMAC-SHA-256 verifier for the given challenge.
 *
 * @param {CryptoKey} authKey - from deriveAuthKey
 * @param {string} challenge
 * @returns {Promise<string>} hex verifier
 */
export async function computeVerifier(authKey, challenge) {
    const sig = await globalThis.crypto.subtle.sign(
        'HMAC',
        authKey,
        new TextEncoder().encode(challenge)
    );
    return bufferToHex(new Uint8Array(sig));
}

/**
 * Derive a deterministic P-256 ECDSA signing keypair from passphrase + accountId.
 *
 * Derivation: PBKDF2(passphrase, accountId+':signing-v1', 100k, SHA-512, 64 bytes)
 *             → HKDF(ikm=pbkdf2, salt='locker-signing-v1', info='ecdsa-private-key', length=32)
 *             → private scalar (normalised mod n to [1, n-1])
 *             → p256.getPublicKey(scalar) → uncompressed point
 *             → JWK imported as CryptoKey
 *
 * @param {string} passphrase
 * @param {string} accountId
 * @returns {Promise<{ privateKey: CryptoKey, publicKeyJwkBase64: string }>}
 */
export async function deriveSigningKeypair(passphrase, accountId) {
    const passphraseKey = await globalThis.crypto.subtle.importKey(
        'raw', new TextEncoder().encode(passphrase), 'PBKDF2', false, ['deriveBits']
    );
    const pbkdf2Bits = await globalThis.crypto.subtle.deriveBits(
        {
            name: 'PBKDF2',
            salt: new TextEncoder().encode(accountId + ':signing-v1'),
            iterations: LOCKER_PBKDF2_ITERATIONS,
            hash: 'SHA-512',
        },
        passphraseKey,
        512
    );
    const hkdfKey = await globalThis.crypto.subtle.importKey('raw', pbkdf2Bits, 'HKDF', false, ['deriveBits']);
    const scalar32 = await globalThis.crypto.subtle.deriveBits(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt: new TextEncoder().encode('locker-signing-v1'),
            info: new TextEncoder().encode('ecdsa-private-key'),
        },
        hkdfKey,
        256
    );
    let privateScalar = new Uint8Array(scalar32);

    // Normalise scalar to [1, n-1] via BigInt mod — required because @noble/curves
    // getPublicKey() throws for scalar >= n, and ~1-in-4.3B HKDF outputs exceed n.
    const n = p256.CURVE.n;
    let scalarBig = BigInt('0x' + bufferToHex(privateScalar));
    scalarBig = scalarBig % n;
    if (scalarBig === 0n) { scalarBig = 1n; }
    privateScalar = hexToBuffer(scalarBig.toString(16).padStart(64, '0'));

    const publicUncompressed = p256.getPublicKey(privateScalar, false); // 65 bytes: 04 || x || y
    const x = publicUncompressed.slice(1, 33);
    const y = publicUncompressed.slice(33, 65);

    const toBase64Url = (bytes) =>
        btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');

    const jwk = { kty: 'EC', crv: 'P-256', x: toBase64Url(x), y: toBase64Url(y), d: toBase64Url(privateScalar) };

    const privateKey = await globalThis.crypto.subtle.importKey(
        'jwk', jwk, { name: 'ECDSA', namedCurve: 'P-256' }, false, ['sign']
    );

    const publicJwk = { kty: 'EC', crv: 'P-256', x: jwk.x, y: jwk.y };
    const publicKeyJwkBase64 = btoa(JSON.stringify(publicJwk));

    return { privateKey, publicKeyJwkBase64 };
}

/**
 * Sign a hex-encoded challenge with an ECDSA P-256 private key.
 * Returns a base64-encoded IEEE P1363 signature (64 bytes: r || s).
 *
 * @param {CryptoKey} privateKey
 * @param {string} challengeHex
 * @returns {Promise<string>} base64 signature
 */
export async function signChallenge(privateKey, challengeHex) {
    const challengeBytes = hexToBuffer(challengeHex);
    const sigBuffer = await globalThis.crypto.subtle.sign(
        { name: 'ECDSA', hash: 'SHA-256' },
        privateKey,
        challengeBytes
    );
    return uint8ArrayToBase64(new Uint8Array(sigBuffer));
}

/**
 * Compute the SHA-256 digest of a file buffer as a hex string.
 * Used as key material for key-file authentication — file contents are never stored or transmitted.
 *
 * @param {ArrayBuffer|Uint8Array} fileBuffer
 * @returns {Promise<string>} 64-char hex
 */
export async function deriveKeyFromFile(fileBuffer) {
    const hashBuffer = await globalThis.crypto.subtle.digest('SHA-256', fileBuffer);
    return bufferToHex(new Uint8Array(hashBuffer));
}

/**
 * Fold N key materials (passphrase string and/or file hash hex strings) into a
 * single deterministic hex string for use as the effective passphrase.
 *
 * Algorithm: SHA-256(materials joined by null byte) → 64-char hex.
 * Order matters — the same materials in a different order produce a different output.
 *
 * @param {string[]} materials  One or more strings: passphrase and/or file hashes
 * @returns {Promise<string>} 64-char hex
 */
export async function combineLockerKeyMaterials(materials) {
    if (!materials || materials.length === 0) {
        throw new Error('combineLockerKeyMaterials requires at least one material');
    }
    const combined = new TextEncoder().encode(materials.join('\x00'));
    const hashBuffer = await globalThis.crypto.subtle.digest('SHA-256', combined);
    return bufferToHex(new Uint8Array(hashBuffer));
}

// ─── Message Decryption (legacy PBKDF2 / OpenCrypto) ─────────────────────────

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

// ─── Call Session Auth ────────────────────────────────────────────────────────

/**
 * Derives a deterministic Ed25519 key pair from a password and PBKDF2 salt.
 *
 * SHA-256 is used here (not SHA-512 like other PBKDF2 derivations in this module)
 * because Ed25519 private key seeds are 32 bytes, and deriving 256 bits with SHA-256
 * is marginally faster. Security margin is equivalent — PBKDF2 iteration count
 * (64,000) is the primary hardening factor, not the hash choice.
 *
 * @param {string} password
 * @param {string} saltBase64 - base64-encoded 32-byte salt (from session's key_salt column)
 * @returns {Promise<{ privateKey: Uint8Array, publicKey: Uint8Array, publicKeyBase64: string }>}
 */
export async function deriveCallKeyPair(password, saltBase64) {
    const enc = new TextEncoder();
    const keyMaterial = await globalThis.crypto.subtle.importKey(
        'raw',
        enc.encode(password),
        'PBKDF2',
        false,
        ['deriveBits'],
    );

    const privateKeyBytes = new Uint8Array(
        await globalThis.crypto.subtle.deriveBits(
            {
                name: 'PBKDF2',
                salt: Uint8Array.from(atob(saltBase64), (c) => c.charCodeAt(0)),
                iterations: 64000,
                hash: 'SHA-256',
            },
            keyMaterial,
            256,
        ),
    );

    const publicKeyBytes = ed25519.getPublicKey(privateKeyBytes);

    return {
        privateKey: privateKeyBytes,
        publicKey: publicKeyBytes,
        publicKeyBase64: uint8ArrayToBase64(publicKeyBytes),
    };
}

/**
 * Signs a challenge hex string with an Ed25519 private key.
 *
 * @param {Uint8Array} privateKeyBytes
 * @param {string} challengeHex - hex string from GET /call-sessions/{hash_id}/challenge
 * @returns {string} base64-encoded 64-byte signature
 */
export function signCallChallenge(privateKeyBytes, challengeHex) {
    const signature = ed25519.sign(hexToBuffer(challengeHex), privateKeyBytes);
    return uint8ArrayToBase64(new Uint8Array(signature));
}

// ─── Call Key Exchange (E2E) ──────────────────────────────────────────────────

const CALL_KEY_EXCHANGE_HKDF_SALT = new TextEncoder().encode('flashview-call-key-exchange-v1');

/**
 * Generate an ephemeral P-256 ECDH key pair for call E2E key exchange.
 * Each participant generates a fresh pair on join; the private key never leaves the browser.
 *
 * @returns {Promise<{ publicKeyBase64: string, privateKeyBase64: string }>} JWK-format, base64-encoded
 */
export async function generateCallEphemeralKeypair() {
    const keypair = await globalThis.crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveBits'],
    );

    const [publicJwk, privateJwk] = await Promise.all([
        globalThis.crypto.subtle.exportKey('jwk', keypair.publicKey),
        globalThis.crypto.subtle.exportKey('jwk', keypair.privateKey),
    ]);

    return {
        publicKeyBase64: btoa(JSON.stringify(publicJwk)),
        privateKeyBase64: btoa(JSON.stringify(privateJwk)),
    };
}

/**
 * Generate a random 256-bit AES-GCM session key for call E2E encryption.
 *
 * @returns {string} base64-encoded 32-byte key
 */
export function generateCallSessionAesKey() {
    const key = new Uint8Array(32);
    globalThis.crypto.getRandomValues(key);
    return uint8ArrayToBase64(key);
}

/**
 * ECIES: wrap an AES session key for a specific call participant.
 *
 * Algorithm:
 *   1. sharedSecret = ECDH(ownPrivateKey, peerPublicKey)
 *   2. wrappingKey = HKDF-SHA-256(ikm=sharedSecret, salt='flashview-call-key-exchange-v1', info='wrapCallSessionKey', length=32)
 *   3. iv = 12 random bytes
 *   4. ciphertext+tag = AES-256-GCM(plaintext=sessionKeyBytes, key=wrappingKey, iv, aad=none)
 *   5. blob = base64( iv[12] || ciphertext || tag[16] )
 *
 * @param {string} sessionKeyBase64 - 32-byte AES session key, base64-encoded
 * @param {string} peerPublicKeyBase64 - recipient's ECDH public key, JWK base64
 * @param {string} ownPrivateKeyBase64 - sender's ECDH private key, JWK base64
 * @returns {Promise<string>} base64-encoded ECIES blob
 */
export async function wrapCallSessionKey(sessionKeyBase64, peerPublicKeyBase64, ownPrivateKeyBase64) {
    const peerPublicKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(peerPublicKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        [],
    );
    const ownPrivateKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(ownPrivateKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        ['deriveBits'],
    );

    const sharedBits = await globalThis.crypto.subtle.deriveBits(
        { name: 'ECDH', public: peerPublicKey },
        ownPrivateKey,
        256,
    );

    const sharedKey = await globalThis.crypto.subtle.importKey('raw', sharedBits, 'HKDF', false, ['deriveKey']);
    const aesKey = await globalThis.crypto.subtle.deriveKey(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt: CALL_KEY_EXCHANGE_HKDF_SALT,
            info: new TextEncoder().encode('wrapCallSessionKey'),
        },
        sharedKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt'],
    );

    const iv = new Uint8Array(12);
    globalThis.crypto.getRandomValues(iv);

    const keyBytes = base64ToUint8Array(sessionKeyBase64);
    const ciphertext = await globalThis.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, keyBytes);

    const blob = new Uint8Array(12 + ciphertext.byteLength);
    blob.set(iv);
    blob.set(new Uint8Array(ciphertext), 12);
    return uint8ArrayToBase64(blob);
}

/**
 * ECIES: unwrap an AES session key received via the signaling channel.
 *
 * ECDH is symmetric: ECDH(A_priv, B_pub) == ECDH(B_priv, A_pub).
 * The argument order is swapped vs wrapCallSessionKey — pass the recipient's
 * private key and the sender's public key.
 *
 * @param {string} wrappedKeyBase64 - base64-encoded blob (iv[12] + ciphertext + tag[16])
 * @param {string} ownPrivateKeyBase64 - recipient's ECDH private key, JWK base64
 * @param {string} peerPublicKeyBase64 - sender's ECDH public key, JWK base64
 * @returns {Promise<string>} session key as base64
 */
export async function unwrapCallSessionKey(wrappedKeyBase64, ownPrivateKeyBase64, peerPublicKeyBase64) {
    const peerPublicKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(peerPublicKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        [],
    );
    const ownPrivateKey = await globalThis.crypto.subtle.importKey(
        'jwk',
        JSON.parse(atob(ownPrivateKeyBase64)),
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        ['deriveBits'],
    );

    const sharedBits = await globalThis.crypto.subtle.deriveBits(
        { name: 'ECDH', public: peerPublicKey },
        ownPrivateKey,
        256,
    );

    const sharedKey = await globalThis.crypto.subtle.importKey('raw', sharedBits, 'HKDF', false, ['deriveKey']);
    const aesKey = await globalThis.crypto.subtle.deriveKey(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt: CALL_KEY_EXCHANGE_HKDF_SALT,
            info: new TextEncoder().encode('wrapCallSessionKey'),
        },
        sharedKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['decrypt'],
    );

    const blob = base64ToUint8Array(wrappedKeyBase64);
    const iv = blob.slice(0, 12);
    const ciphertext = blob.slice(12);

    const decrypted = await globalThis.crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aesKey, ciphertext);
    return uint8ArrayToBase64(new Uint8Array(decrypted));
}
