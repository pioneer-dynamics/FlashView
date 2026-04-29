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

const PIPE_SALT = new TextEncoder().encode('flashview-pipe-v1');
const PIPE_CHUNK_IV_LENGTH = 12;

/**
 * Generate a cryptographically random pipe seed (32 bytes, base64-encoded).
 *
 * @returns {Promise<string>}
 */
export async function generatePipeSeed() {
    const seed = new Uint8Array(32);
    globalThis.crypto.getRandomValues(seed);
    return uint8ArrayToBase64(seed);
}

/**
 * Derive a 32-byte AES-256-GCM key from a pipe seed and transfer counter using HKDF-SHA-256.
 *
 * ikm  = pipeSeedBytes
 * salt = UTF-8("flashview-pipe-v1")
 * info = UTF-8("flashview-pipe-key-v1") + uint64BE(counter)
 *
 * @param {string} pipeSeedBase64
 * @param {bigint|number} counter
 * @returns {Promise<string>} base64-encoded 32-byte key material
 */
export async function deriveSessionKey(pipeSeedBase64, counter) {
    const seedBytes = base64ToUint8Array(pipeSeedBase64);
    const counterBig = BigInt(counter);
    const infoPrefix = new TextEncoder().encode('flashview-pipe-key-v1');
    const counterBytes = new Uint8Array(8);
    const view = new DataView(counterBytes.buffer);
    view.setBigUint64(0, counterBig, false);
    const info = new Uint8Array(infoPrefix.length + 8);
    info.set(infoPrefix);
    info.set(counterBytes, infoPrefix.length);

    const ikmKey = await globalThis.crypto.subtle.importKey('raw', seedBytes, 'HKDF', false, ['deriveBits']);
    const bits = await globalThis.crypto.subtle.deriveBits(
        { name: 'HKDF', hash: 'SHA-256', salt: PIPE_SALT, info },
        ikmKey,
        256,
    );
    return uint8ArrayToBase64(new Uint8Array(bits));
}

/**
 * Derive a 16-byte session ID (32 hex chars) from a pipe seed and counter using HKDF-SHA-256.
 *
 * Uses info prefix "flashview-pipe-sid-v1" — cryptographically independent from deriveSessionKey.
 *
 * @param {string} pipeSeedBase64
 * @param {bigint|number} counter
 * @returns {Promise<string>} 32 hex character string
 */
export async function deriveSessionId(pipeSeedBase64, counter) {
    const seedBytes = base64ToUint8Array(pipeSeedBase64);
    const counterBig = BigInt(counter);
    const infoPrefix = new TextEncoder().encode('flashview-pipe-sid-v1');
    const counterBytes = new Uint8Array(8);
    const view = new DataView(counterBytes.buffer);
    view.setBigUint64(0, counterBig, false);
    const info = new Uint8Array(infoPrefix.length + 8);
    info.set(infoPrefix);
    info.set(counterBytes, infoPrefix.length);

    const ikmKey = await globalThis.crypto.subtle.importKey('raw', seedBytes, 'HKDF', false, ['deriveBits']);
    const bits = await globalThis.crypto.subtle.deriveBits(
        { name: 'HKDF', hash: 'SHA-256', salt: PIPE_SALT, info },
        ikmKey,
        128,
    );
    return Array.from(new Uint8Array(bits)).map(b => b.toString(16).padStart(2, '0')).join('');
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
 * ECIES: encrypt a pipe seed for a peer device using ECDH + HKDF + AES-256-GCM.
 *
 * Algorithm:
 *   1. sharedSecret = ECDH(ownPrivateKey, peerPublicKey)
 *   2. key = HKDF-SHA-256(ikm=sharedSecret, salt=UTF-8("flashview-pipe-pairing-v1"), info=UTF-8("encryptSeedForPeer"), length=32)
 *   3. iv = 12 random bytes
 *   4. ciphertext+tag = AES-256-GCM encrypt(plaintext=pipeSeedBytes, key, iv, aad=none)
 *   5. blob = base64( iv[12] || ciphertext || tag[16] )
 *
 * @param {string} pipeSeedBase64
 * @param {string} peerPublicKeyBase64 - JWK base64
 * @param {string} ownPrivateKeyBase64 - JWK base64
 * @returns {Promise<string>} base64-encoded ECIES blob
 */
export async function encryptSeedForPeer(pipeSeedBase64, peerPublicKeyBase64, ownPrivateKeyBase64) {
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
            info: new TextEncoder().encode('encryptSeedForPeer'),
        },
        sharedKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt'],
    );

    const iv = new Uint8Array(12);
    globalThis.crypto.getRandomValues(iv);

    const seedBytes = base64ToUint8Array(pipeSeedBase64);
    const ciphertext = await globalThis.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, seedBytes);

    const blob = new Uint8Array(12 + ciphertext.byteLength);
    blob.set(iv);
    blob.set(new Uint8Array(ciphertext), 12);
    return uint8ArrayToBase64(blob);
}

/**
 * ECIES: decrypt a pipe seed from a peer using ECDH + HKDF + AES-256-GCM.
 *
 * Blob format: first 12 bytes = IV, remainder = ciphertext + 16-byte auth tag.
 *
 * @param {string} encryptedBase64 - base64-encoded blob
 * @param {string} ownPrivateKeyBase64 - JWK base64
 * @param {string} peerPublicKeyBase64 - JWK base64
 * @returns {Promise<string>} pipe seed as base64
 */
export async function decryptSeedFromPeer(encryptedBase64, ownPrivateKeyBase64, peerPublicKeyBase64) {
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
            info: new TextEncoder().encode('encryptSeedForPeer'),
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
