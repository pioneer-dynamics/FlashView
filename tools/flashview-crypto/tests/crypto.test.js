import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
    encryptMessage, decryptMessage, generatePassphrase,
    encryptBuffer, decryptBuffer,
    encryptFileToBuffer, decryptFileFromBuffer,
    generateFileKey, wrapFileKey, unwrapFileKey,
    deriveSigningKeypair, signChallenge,
    deriveKeyFromFile, combineLockerKeyMaterials,
    encryptKeyForDevice, decryptKeyFromDevice,
    generateCallEphemeralKeypair, generateCallSessionAesKey,
    wrapCallSessionKey, unwrapCallSessionKey,
} from '../src/index.js';

// Known vectors generated from the pre-migration CLI implementation (tools/flashview-cli/src/crypto.js)
// using Node.js `node:crypto` (pbkdf2Sync + createCipheriv AES-256-GCM).
// These prove that the new crypto.subtle implementation is backward-compatible with
// secrets encrypted before this migration.
const KNOWN_VECTORS = [
    {
        description: 'ASCII message',
        passphrase: 'known-vector-test-passphrase',
        message: 'Hello, FlashView!',
        secret: '5eda762c4276f47dv8wVoOefRcB/5dbyX8NP0SeKd9ktm0u/AZOp4CdspI9ORmzSKIhlXuAvE/em',
    },
    {
        description: 'empty string',
        passphrase: 'known-vector-test-passphrase',
        message: '',
        secret: '5c20fd84285f08ceP+KjColRPnuCkPD9fkT/YSIZlM3QgrkMU92xCw==',
    },
    {
        description: 'unicode characters',
        passphrase: 'known-vector-test-passphrase',
        message: 'Hello 🚀 World éèê 你好',
        secret: 'ec66b0a45c4ddb69goJSVO8USRcnFg9h0AmHi7BbwGq0gtzX5kLKrRuzSxBm4Tw5SEOQpR745ML73U2IS9GlYqSAI/m6Og==',
    },
];

describe('generatePassphrase', () => {
    it('generates an 8-word hyphenated passphrase', () => {
        const passphrase = generatePassphrase();
        const words = passphrase.split('-');
        assert.equal(words.length, 8, 'Passphrase should have 8 words');
        for (const word of words) {
            assert.ok(word.length > 0, 'Each word should be non-empty');
        }
    });

    it('generates unique passphrases', () => {
        const a = generatePassphrase();
        const b = generatePassphrase();
        assert.notEqual(a, b, 'Two generated passphrases should differ');
    });
});

describe('encryptMessage', () => {
    it('returns passphrase and encrypted secret', async () => {
        const result = await encryptMessage('Hello, World!');
        assert.ok(result.passphrase, 'Should return a passphrase');
        assert.ok(result.secret, 'Should return an encrypted secret');
    });

    it('uses provided passphrase when given', async () => {
        const passphrase = 'my-custom-passphrase';
        const result = await encryptMessage('Hello', passphrase);
        assert.equal(result.passphrase, passphrase);
    });

    it('auto-generates passphrase when not provided', async () => {
        const result = await encryptMessage('Hello');
        const words = result.passphrase.split('-');
        assert.equal(words.length, 8);
    });

    it('produces format: 16 hex chars + base64 data', async () => {
        const result = await encryptMessage('Test message');
        const saltHex = result.secret.substring(0, 16);
        const base64Part = result.secret.substring(16);

        assert.match(saltHex, /^[0-9a-f]{16}$/, 'Salt should be 16 hex characters');

        const decoded = Buffer.from(base64Part, 'base64');
        assert.ok(decoded.length > 0, 'Base64 should decode to non-empty buffer');

        // "Test message" = 12 bytes, so total = 12 (IV) + 12 (plaintext) + 16 (authTag) = 40
        assert.equal(decoded.length, 40, 'Decoded ciphertext should be IV + encrypted + authTag');
    });

    it('produces different ciphertext for same plaintext (random salt/IV)', async () => {
        const a = await encryptMessage('Same message', 'same-passphrase');
        const b = await encryptMessage('Same message', 'same-passphrase');
        assert.notEqual(a.secret, b.secret, 'Different salt/IV should produce different ciphertext');
    });
});

describe('decryptMessage', () => {
    it('round-trips encryption and decryption', async () => {
        const plaintext = 'Hello, World!';
        const { passphrase, secret } = await encryptMessage(plaintext);
        const decrypted = await decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('round-trips with custom passphrase', async () => {
        const plaintext = 'Secret data 123!@#';
        const passphrase = 'my-test-passphrase';
        const { secret } = await encryptMessage(plaintext, passphrase);
        const decrypted = await decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('round-trips with empty string', async () => {
        const { passphrase, secret } = await encryptMessage('');
        const decrypted = await decryptMessage(secret, passphrase);
        assert.equal(decrypted, '');
    });

    it('round-trips with unicode characters', async () => {
        const plaintext = 'Hello \u{1F680} World \u00E9\u00E8\u00EA \u4F60\u597D';
        const { passphrase, secret } = await encryptMessage(plaintext);
        const decrypted = await decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('round-trips with long message', async () => {
        const plaintext = 'A'.repeat(10000);
        const { passphrase, secret } = await encryptMessage(plaintext);
        const decrypted = await decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('fails with wrong passphrase', async () => {
        const { secret } = await encryptMessage('Hello', 'correct-passphrase');
        await assert.rejects(
            () => decryptMessage(secret, 'wrong-passphrase'),
            'Should reject with wrong passphrase'
        );
    });

    it('fails with corrupted ciphertext', async () => {
        const { passphrase, secret } = await encryptMessage('Hello');
        const corrupted = secret.substring(0, 16) + 'AAAA' + secret.substring(20);
        await assert.rejects(
            () => decryptMessage(corrupted, passphrase),
            'Should reject with corrupted ciphertext'
        );
    });
});

describe('known-vector compatibility (backward compatibility with pre-migration CLI)', () => {
    for (const vector of KNOWN_VECTORS) {
        it(`decrypts known vector: ${vector.description}`, async () => {
            const decrypted = await decryptMessage(vector.secret, vector.passphrase);
            assert.equal(
                decrypted,
                vector.message,
                `Should decrypt known vector (${vector.description}) to expected plaintext`
            );
        });
    }

    it('new encrypted secrets can be decrypted by the same implementation', async () => {
        // Encrypts with the new crypto.subtle implementation and decrypts — confirms
        // the new format is self-consistent and matches the known vector format.
        const message = 'cross-compat test';
        const passphrase = 'cross-compat-passphrase';
        const { secret } = await encryptMessage(message, passphrase);

        // Verify format matches expected structure
        assert.match(secret.substring(0, 16), /^[0-9a-f]{16}$/);

        const decrypted = await decryptMessage(secret, passphrase);
        assert.equal(decrypted, message);
    });
});

// PIO-102 regression: encryptFileToBuffer must not throw RangeError: Invalid array length for large files.
// Before fix: encryptFileToBlob used Array.from().map().join('') which crashes for ~400 MB files.
// After fix: encryptFileToBuffer returns Uint8Array directly, no intermediate hex string.
describe('encryptFileToBuffer large file regression (PIO-102)', () => {
    it('handles a 50 MB buffer without throwing RangeError: Invalid array length', async () => {
        const bigBuffer = new Uint8Array(50 * 1024 * 1024);
        bigBuffer.fill(0xAB);
        const dek = generateFileKey();
        const result = await encryptFileToBuffer(bigBuffer, { dek });
        assert.ok(result instanceof Uint8Array, 'Result must be a Uint8Array');
        assert.ok(result.length > bigBuffer.length, 'Encrypted result must be larger than plaintext');
    });
});

describe('ciphertext format compatibility (MessageLength validation)', () => {
    it('matches expected overhead for MessageLength validation', async () => {
        // The MessageLength rule subtracts 28 bytes (12 IV + 16 auth tag) from the
        // decoded base64 length to estimate plaintext length.
        const plaintext = 'Hello'; // 5 bytes
        const { secret } = await encryptMessage(plaintext, 'test-passphrase');

        const base64Part = secret.substring(16);
        const decoded = Buffer.from(base64Part, 'base64');

        // decoded length = IV (12) + plaintext (5) + authTag (16) = 33
        assert.equal(decoded.length, 33);

        const estimatedPlaintext = decoded.length - 28;
        assert.equal(estimatedPlaintext, 5, 'Estimated plaintext length should match actual');
    });
});

describe('encryptBuffer', () => {
    it('returns passphrase and encrypted Uint8Array', async () => {
        const input = new Uint8Array([1, 2, 3, 4, 5]);
        const result = await encryptBuffer(input);
        assert.ok(result.passphrase, 'Should return a passphrase');
        assert.ok(result.encrypted instanceof Uint8Array, 'Should return a Uint8Array');
    });

    it('uses provided passphrase when given', async () => {
        const input = new Uint8Array([10, 20, 30]);
        const passphrase = 'my-buffer-passphrase';
        const result = await encryptBuffer(input, passphrase);
        assert.equal(result.passphrase, passphrase);
    });

    it('auto-generates passphrase when not provided', async () => {
        const input = new Uint8Array([1]);
        const result = await encryptBuffer(input);
        const words = result.passphrase.split('-');
        assert.equal(words.length, 8);
    });

    it('produces correct binary format: [8B salt][12B IV][ciphertext + 16B auth tag]', async () => {
        const plaintext = new Uint8Array(100);
        const { encrypted } = await encryptBuffer(plaintext);
        // 8 salt + 12 IV + 100 plaintext + 16 auth tag = 136
        assert.equal(encrypted.length, 8 + 12 + 100 + 16);
    });

    it('produces different ciphertext for same input (random salt/IV)', async () => {
        const input = new Uint8Array([1, 2, 3]);
        const a = await encryptBuffer(input, 'same-passphrase');
        const b = await encryptBuffer(input, 'same-passphrase');
        assert.notDeepEqual(a.encrypted, b.encrypted);
    });
});

describe('decryptBuffer', () => {
    it('round-trips arbitrary binary data', async () => {
        const original = new Uint8Array([0, 1, 127, 128, 255, 42, 99]);
        const { passphrase, encrypted } = await encryptBuffer(original);
        const decrypted = await decryptBuffer(encrypted, passphrase);
        assert.deepEqual(decrypted, original);
    });

    it('round-trips with custom passphrase', async () => {
        const original = new Uint8Array(50).fill(77);
        const passphrase = 'custom-buffer-passphrase';
        const { encrypted } = await encryptBuffer(original, passphrase);
        const decrypted = await decryptBuffer(encrypted, passphrase);
        assert.deepEqual(decrypted, original);
    });

    it('round-trips zero-length buffer', async () => {
        const original = new Uint8Array(0);
        const { passphrase, encrypted } = await encryptBuffer(original);
        const decrypted = await decryptBuffer(encrypted, passphrase);
        assert.deepEqual(decrypted, original);
    });

    it('round-trips large binary buffer', async () => {
        const original = new Uint8Array(1024 * 10);
        for (let i = 0; i < original.length; i++) {
            original[i] = i % 256;
        }
        const { passphrase, encrypted } = await encryptBuffer(original);
        const decrypted = await decryptBuffer(encrypted, passphrase);
        assert.deepEqual(decrypted, original);
    });

    it('fails with wrong passphrase', async () => {
        const input = new Uint8Array([1, 2, 3]);
        const { encrypted } = await encryptBuffer(input, 'correct-passphrase');
        await assert.rejects(
            () => decryptBuffer(encrypted, 'wrong-passphrase'),
            'Should reject with wrong passphrase'
        );
    });

    it('fails with corrupted ciphertext', async () => {
        const input = new Uint8Array([1, 2, 3]);
        const { passphrase, encrypted } = await encryptBuffer(input);
        const corrupted = new Uint8Array(encrypted);
        corrupted[20] ^= 0xff;
        await assert.rejects(
            () => decryptBuffer(corrupted, passphrase),
            'Should reject with corrupted ciphertext'
        );
    });
});

// ─── eLocker File Crypto (envelope encryption) ───────────────────────────────

describe('generateFileKey', () => {
    it('returns a 32-byte Uint8Array', () => {
        const dek = generateFileKey();
        assert.ok(dek instanceof Uint8Array, 'Should return Uint8Array');
        assert.equal(dek.length, 32);
    });

    it('generates unique keys each call', () => {
        const a = generateFileKey();
        const b = generateFileKey();
        assert.notDeepEqual(a, b, 'Two generated keys should differ');
    });
});

describe('encryptFileToBuffer + decryptFileFromBuffer (v2 DEK path)', () => {
    it('round-trips arbitrary binary data with DEK', async () => {
        const original = new Uint8Array([0, 1, 127, 128, 255, 42]);
        const dek = generateFileKey();
        const encrypted = await encryptFileToBuffer(original, { dek });
        const decrypted = await decryptFileFromBuffer(encrypted, { dek });
        assert.deepEqual(decrypted, original);
    });

    it('produces v2 version byte (0x02) and file type byte (0x46)', async () => {
        const dek = generateFileKey();
        const encrypted = await encryptFileToBuffer(new Uint8Array(8), { dek });
        assert.equal(encrypted[0], 0x02, 'Version byte must be 0x02');
        assert.equal(encrypted[1], 0x46, 'Type byte must be 0x46 (F)');
    });

    it('v2 blob is 2 + 12 (IV) + plaintext + 16 (tag) bytes', async () => {
        const plaintext = new Uint8Array(100);
        const dek = generateFileKey();
        const encrypted = await encryptFileToBuffer(plaintext, { dek });
        assert.equal(encrypted.length, 2 + 12 + 100 + 16);
    });

    it('round-trips empty buffer with DEK', async () => {
        const dek = generateFileKey();
        const encrypted = await encryptFileToBuffer(new Uint8Array(0), { dek });
        const decrypted = await decryptFileFromBuffer(encrypted, { dek });
        assert.deepEqual(decrypted, new Uint8Array(0));
    });

    it('fails decryption with wrong DEK', async () => {
        const dek = generateFileKey();
        const wrongDek = generateFileKey();
        const encrypted = await encryptFileToBuffer(new Uint8Array([1, 2, 3]), { dek });
        await assert.rejects(
            () => decryptFileFromBuffer(encrypted, { dek: wrongDek }),
            'Should reject with wrong DEK'
        );
    });
});

describe('encryptFileToBuffer + decryptFileFromBuffer (v1 passphrase path)', () => {
    it('round-trips arbitrary binary data with passphrase', async () => {
        const original = new Uint8Array([10, 20, 30, 40, 50]);
        const passphrase = 'test-locker-passphrase';
        const encrypted = await encryptFileToBuffer(original, { passphrase });
        const decrypted = await decryptFileFromBuffer(encrypted, { passphrase });
        assert.deepEqual(decrypted, original);
    });

    it('produces v1 version byte (0x01) and file type byte (0x46)', async () => {
        const encrypted = await encryptFileToBuffer(new Uint8Array(8), { passphrase: 'p' });
        assert.equal(encrypted[0], 0x01, 'Version byte must be 0x01');
        assert.equal(encrypted[1], 0x46, 'Type byte must be 0x46 (F)');
    });

    it('v1 blob is 2 + 32 (salt) + 12 (IV) + plaintext + 16 (tag) bytes', async () => {
        const plaintext = new Uint8Array(100);
        const encrypted = await encryptFileToBuffer(plaintext, { passphrase: 'p' });
        assert.equal(encrypted.length, 2 + 32 + 12 + 100 + 16);
    });

    it('fails decryption with wrong passphrase', async () => {
        const encrypted = await encryptFileToBuffer(new Uint8Array([1, 2]), { passphrase: 'correct' });
        await assert.rejects(
            () => decryptFileFromBuffer(encrypted, { passphrase: 'wrong' }),
            'Should reject with wrong passphrase'
        );
    });
});

describe('wrapFileKey + unwrapFileKey', () => {
    it('round-trips a DEK through wrap/unwrap', async () => {
        const dek = generateFileKey();
        const passphrase = 'my-locker-passphrase';
        const accountId = '1234567890';
        const wrapped = await wrapFileKey(dek, passphrase, accountId);
        const unwrapped = await unwrapFileKey(wrapped, passphrase, accountId);
        assert.deepEqual(unwrapped, dek);
    });

    it('wrapFileKey returns a non-empty base64 string', async () => {
        const dek = generateFileKey();
        const wrapped = await wrapFileKey(dek, 'pass', 'account1');
        assert.equal(typeof wrapped, 'string');
        assert.ok(wrapped.length > 0, 'Should return a non-empty base64 string');
    });

    it('produces different output each call (random salt)', async () => {
        const dek = generateFileKey();
        const a = await wrapFileKey(dek, 'pass', 'account1');
        const b = await wrapFileKey(dek, 'pass', 'account1');
        assert.notEqual(a, b, 'Random salt should produce different wrapped keys');
    });

    it('fails to unwrap with wrong passphrase', async () => {
        const dek = generateFileKey();
        const wrapped = await wrapFileKey(dek, 'correct-pass', 'account1');
        await assert.rejects(
            () => unwrapFileKey(wrapped, 'wrong-pass', 'account1'),
            'Should reject with wrong passphrase'
        );
    });

    it('fails to unwrap with wrong accountId', async () => {
        const dek = generateFileKey();
        const wrapped = await wrapFileKey(dek, 'pass', 'correct-account');
        await assert.rejects(
            () => unwrapFileKey(wrapped, 'pass', 'wrong-account'),
            'Should reject with wrong accountId'
        );
    });
});

describe('encryptFileToBuffer + decryptFileFromBuffer backward compat', () => {
    it('v2 encrypted can be decrypted after re-wrapping DEK with new passphrase', async () => {
        const original = new Uint8Array([1, 2, 3, 4, 5]);
        const dek = generateFileKey();
        const encrypted = await encryptFileToBuffer(original, { dek });

        const oldWrapped = await wrapFileKey(dek, 'old-passphrase', 'acct1');
        const unwrappedDek = await unwrapFileKey(oldWrapped, 'old-passphrase', 'acct1');
        const newWrapped = await wrapFileKey(unwrappedDek, 'new-passphrase', 'acct1');
        const finalDek = await unwrapFileKey(newWrapped, 'new-passphrase', 'acct1');

        const decrypted = await decryptFileFromBuffer(encrypted, { dek: finalDek });
        assert.deepEqual(decrypted, original, 'File must decrypt correctly after DEK re-wrap');
    });
});

// ─── ECDSA signing (PIO-103) ──────────────────────────────────────────────────

describe('deriveSigningKeypair', () => {
    it('returns a CryptoKey and base64 JWK public key', async () => {
        const { privateKey, publicKeyJwkBase64 } = await deriveSigningKeypair('test-passphrase', 'account-123');
        assert.ok(privateKey instanceof CryptoKey, 'privateKey should be a CryptoKey');
        assert.equal(privateKey.type, 'private', 'privateKey should be private type');
        assert.ok(typeof publicKeyJwkBase64 === 'string', 'publicKeyJwkBase64 should be a string');

        const jwk = JSON.parse(atob(publicKeyJwkBase64));
        assert.equal(jwk.kty, 'EC', 'JWK kty should be EC');
        assert.equal(jwk.crv, 'P-256', 'JWK crv should be P-256');
        assert.ok(jwk.x, 'JWK should have x coordinate');
        assert.ok(jwk.y, 'JWK should have y coordinate');
        assert.equal(jwk.d, undefined, 'Public JWK should not contain d (private scalar)');
    });

    it('is deterministic — same passphrase+accountId produces the same public key', async () => {
        const { publicKeyJwkBase64: pub1 } = await deriveSigningKeypair('deterministic-pass', 'acct-42');
        const { publicKeyJwkBase64: pub2 } = await deriveSigningKeypair('deterministic-pass', 'acct-42');
        assert.equal(pub1, pub2, 'Same inputs must produce the same public key');
    });

    it('different passphrases produce different keypairs', async () => {
        const { publicKeyJwkBase64: pub1 } = await deriveSigningKeypair('passphrase-A', 'acct-1');
        const { publicKeyJwkBase64: pub2 } = await deriveSigningKeypair('passphrase-B', 'acct-1');
        assert.notEqual(pub1, pub2, 'Different passphrases must produce different keypairs');
    });

    it('different accountIds produce different keypairs', async () => {
        const { publicKeyJwkBase64: pub1 } = await deriveSigningKeypair('shared-pass', 'acct-1');
        const { publicKeyJwkBase64: pub2 } = await deriveSigningKeypair('shared-pass', 'acct-2');
        assert.notEqual(pub1, pub2, 'Different accountIds must produce different keypairs');
    });
});

describe('signChallenge', () => {
    it('returns a base64-encoded 64-byte P1363 signature', async () => {
        const { privateKey } = await deriveSigningKeypair('sign-test-pass', 'sign-acct');
        const challengeHex = '0'.repeat(64); // 32 zero bytes
        const signatureBase64 = await signChallenge(privateKey, challengeHex);

        assert.ok(typeof signatureBase64 === 'string', 'Signature should be a string');
        const sigBytes = Uint8Array.from(atob(signatureBase64), c => c.charCodeAt(0));
        assert.equal(sigBytes.length, 64, 'P1363 signature should be exactly 64 bytes (r||s)');
    });

    it('signature verifies with the corresponding public key via Web Crypto', async () => {
        const { privateKey, publicKeyJwkBase64 } = await deriveSigningKeypair('verify-test-pass', 'verify-acct');
        const challengeHex = 'ab'.repeat(32); // 32 bytes
        const signatureBase64 = await signChallenge(privateKey, challengeHex);

        const sigBytes = Uint8Array.from(atob(signatureBase64), c => c.charCodeAt(0));
        const challengeBytes = new Uint8Array(challengeHex.match(/.{2}/g).map(b => parseInt(b, 16)));

        const pubJwk = JSON.parse(atob(publicKeyJwkBase64));
        const pubKey = await crypto.subtle.importKey(
            'jwk', pubJwk, { name: 'ECDSA', namedCurve: 'P-256' }, false, ['verify']
        );

        const isValid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' },
            pubKey,
            sigBytes,
            challengeBytes
        );
        assert.ok(isValid, 'Signature should verify correctly with the corresponding public key');
    });

    it('signature does not verify with a different keypair', async () => {
        const { privateKey: key1 } = await deriveSigningKeypair('key-1-pass', 'acct-x');
        const { publicKeyJwkBase64: pub2 } = await deriveSigningKeypair('key-2-pass', 'acct-x');
        const challengeHex = 'ff'.repeat(32);
        const signatureBase64 = await signChallenge(key1, challengeHex);

        const sigBytes = Uint8Array.from(atob(signatureBase64), c => c.charCodeAt(0));
        const challengeBytes = new Uint8Array(challengeHex.match(/.{2}/g).map(b => parseInt(b, 16)));

        const pubJwk = JSON.parse(atob(pub2));
        const pubKey = await crypto.subtle.importKey(
            'jwk', pubJwk, { name: 'ECDSA', namedCurve: 'P-256' }, false, ['verify']
        );

        const isValid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' },
            pubKey,
            sigBytes,
            challengeBytes
        );
        assert.ok(! isValid, 'Signature from key1 must not verify with key2 public key');
    });
});

describe('deriveKeyFromFile', () => {
    it('returns a deterministic 64-char hex string', async () => {
        const buf = new TextEncoder().encode('test-file-contents').buffer;
        const result = await deriveKeyFromFile(buf);
        assert.equal(typeof result, 'string', 'Result should be a string');
        assert.equal(result.length, 64, 'Result should be 64 hex chars');
        assert.match(result, /^[0-9a-f]{64}$/, 'Result should be lowercase hex');
    });

    it('is deterministic for the same input', async () => {
        const buf = new TextEncoder().encode('deterministic-file').buffer;
        const r1 = await deriveKeyFromFile(buf);
        const r2 = await deriveKeyFromFile(buf);
        assert.equal(r1, r2, 'Same input should produce same hash');
    });

    it('produces different hashes for different inputs', async () => {
        const buf1 = new TextEncoder().encode('file-a').buffer;
        const buf2 = new TextEncoder().encode('file-b').buffer;
        const r1 = await deriveKeyFromFile(buf1);
        const r2 = await deriveKeyFromFile(buf2);
        assert.notEqual(r1, r2, 'Different inputs should produce different hashes');
    });

    it('accepts Uint8Array as input', async () => {
        const arr = new TextEncoder().encode('uint8array-input');
        const result = await deriveKeyFromFile(arr);
        assert.equal(result.length, 64, 'Should work with Uint8Array');
    });
});

describe('combineLockerKeyMaterials', () => {
    it('returns a deterministic 64-char hex string', async () => {
        const result = await combineLockerKeyMaterials(['passphrase123', 'filehash456']);
        assert.equal(typeof result, 'string', 'Result should be a string');
        assert.equal(result.length, 64, 'Result should be 64 hex chars');
        assert.match(result, /^[0-9a-f]{64}$/, 'Result should be lowercase hex');
    });

    it('is deterministic for the same inputs in the same order', async () => {
        const materials = ['passphrase', 'hash1', 'hash2'];
        const r1 = await combineLockerKeyMaterials(materials);
        const r2 = await combineLockerKeyMaterials(materials);
        assert.equal(r1, r2, 'Same inputs in same order should produce same result');
    });

    it('is order-sensitive — different order produces different output', async () => {
        const r1 = await combineLockerKeyMaterials(['aaa', 'bbb']);
        const r2 = await combineLockerKeyMaterials(['bbb', 'aaa']);
        assert.notEqual(r1, r2, 'Different order should produce different results');
    });

    it('works with a single material', async () => {
        const result = await combineLockerKeyMaterials(['single-material']);
        assert.equal(result.length, 64, 'Single-material result should be 64 hex chars');
    });

    it('combining a file hash differs from hashing the raw file bytes directly', async () => {
        // Simulate key_file mode: derive hash from file bytes, then combine
        const fileBytes = new TextEncoder().encode('original-file-content').buffer;
        const fileHash = await deriveKeyFromFile(fileBytes);            // = SHA-256(fileBytes) as hex
        const effectivePassphrase = await combineLockerKeyMaterials([fileHash]); // = SHA-256(UTF-8(fileHash))

        // effective passphrase must differ from the raw file hash — it is SHA-256 of the hex string
        assert.notEqual(effectivePassphrase, fileHash, 'Effective passphrase must differ from the raw file hash');
        // effective passphrase must also differ from hashing the file bytes again (same as fileHash)
        const directHash = await deriveKeyFromFile(fileBytes);
        assert.equal(directHash, fileHash, 'deriveKeyFromFile is deterministic');
        assert.notEqual(effectivePassphrase, directHash, 'Effective passphrase must differ from direct hash of file bytes');
    });

    it('throws on empty array', async () => {
        await assert.rejects(
            () => combineLockerKeyMaterials([]),
            /at least one material/,
            'Should throw when called with empty array'
        );
    });

    it('throws on null or undefined', async () => {
        await assert.rejects(() => combineLockerKeyMaterials(null), Error);
        await assert.rejects(() => combineLockerKeyMaterials(undefined), Error);
    });
});

// ─── Call Key Exchange (E2E) ──────────────────────────────────────────────────

describe('generateCallEphemeralKeypair', () => {
    it('returns publicKeyBase64 and privateKeyBase64 strings', async () => {
        const keypair = await generateCallEphemeralKeypair();
        assert.equal(typeof keypair.publicKeyBase64, 'string', 'publicKeyBase64 should be a string');
        assert.equal(typeof keypair.privateKeyBase64, 'string', 'privateKeyBase64 should be a string');
        assert.ok(keypair.publicKeyBase64.length > 0, 'publicKeyBase64 should be non-empty');
        assert.ok(keypair.privateKeyBase64.length > 0, 'privateKeyBase64 should be non-empty');
    });

    it('publicKeyBase64 decodes to a valid P-256 JWK without d field (public only)', async () => {
        const { publicKeyBase64 } = await generateCallEphemeralKeypair();
        const jwk = JSON.parse(atob(publicKeyBase64));
        assert.equal(jwk.kty, 'EC', 'JWK kty should be EC');
        assert.equal(jwk.crv, 'P-256', 'JWK crv should be P-256');
        assert.ok(jwk.x, 'JWK should have x coordinate');
        assert.ok(jwk.y, 'JWK should have y coordinate');
        assert.equal(jwk.d, undefined, 'Public JWK must not contain d (private scalar)');
    });

    it('generates unique keypairs on each call', async () => {
        const a = await generateCallEphemeralKeypair();
        const b = await generateCallEphemeralKeypair();
        assert.notEqual(a.publicKeyBase64, b.publicKeyBase64, 'Two keypairs should have different public keys');
        assert.notEqual(a.privateKeyBase64, b.privateKeyBase64, 'Two keypairs should have different private keys');
    });
});

describe('generateCallSessionAesKey', () => {
    it('returns a base64 string that decodes to 32 bytes', () => {
        const key = generateCallSessionAesKey();
        assert.equal(typeof key, 'string', 'Should return a string');
        const bytes = Uint8Array.from(atob(key), c => c.charCodeAt(0));
        assert.equal(bytes.length, 32, 'Decoded key should be exactly 32 bytes');
    });

    it('generates unique keys on each call', () => {
        const a = generateCallSessionAesKey();
        const b = generateCallSessionAesKey();
        assert.notEqual(a, b, 'Two generated keys should differ');
    });
});

describe('wrapCallSessionKey + unwrapCallSessionKey', () => {
    it('roundtrips a session key between two participants', async () => {
        const senderKeypair = await generateCallEphemeralKeypair();
        const recipientKeypair = await generateCallEphemeralKeypair();
        const sessionKey = generateCallSessionAesKey();

        const wrapped = await wrapCallSessionKey(
            sessionKey,
            recipientKeypair.publicKeyBase64,
            senderKeypair.privateKeyBase64,
        );

        const unwrapped = await unwrapCallSessionKey(
            wrapped,
            recipientKeypair.privateKeyBase64,
            senderKeypair.publicKeyBase64,
        );

        assert.equal(unwrapped, sessionKey, 'Unwrapped key must match original session key');
    });

    it('fails to unwrap with wrong private key', async () => {
        const senderKeypair = await generateCallEphemeralKeypair();
        const recipientKeypair = await generateCallEphemeralKeypair();
        const wrongKeypair = await generateCallEphemeralKeypair();
        const sessionKey = generateCallSessionAesKey();

        const wrapped = await wrapCallSessionKey(
            sessionKey,
            recipientKeypair.publicKeyBase64,
            senderKeypair.privateKeyBase64,
        );

        await assert.rejects(
            () => unwrapCallSessionKey(wrapped, wrongKeypair.privateKeyBase64, senderKeypair.publicKeyBase64),
            'Should reject when unwrapping with the wrong private key',
        );
    });

    it('produces different output each call due to random IV', async () => {
        const senderKeypair = await generateCallEphemeralKeypair();
        const recipientKeypair = await generateCallEphemeralKeypair();
        const sessionKey = generateCallSessionAesKey();

        const wrapped1 = await wrapCallSessionKey(
            sessionKey, recipientKeypair.publicKeyBase64, senderKeypair.privateKeyBase64,
        );
        const wrapped2 = await wrapCallSessionKey(
            sessionKey, recipientKeypair.publicKeyBase64, senderKeypair.privateKeyBase64,
        );

        assert.notEqual(wrapped1, wrapped2, 'Random IV should produce different blobs each call');
    });

    it('is domain-separated from decryptKeyFromDevice (pipe pairing cannot decrypt call-wrapped key)', async () => {
        // Same ECDH key pair used for both operations — isolates the HKDF salt as the only difference
        const keypair = await generateCallEphemeralKeypair();
        const sessionKey = generateCallSessionAesKey();

        const callWrapped = await wrapCallSessionKey(
            sessionKey,
            keypair.publicKeyBase64,
            keypair.privateKeyBase64,
        );

        // Pipe pairing uses HKDF salt 'flashview-pipe-pairing-v1'; call uses 'flashview-call-key-exchange-v1'
        // Attempting to unwrap the call blob with the pipe pairing function must fail
        await assert.rejects(
            () => decryptKeyFromDevice(callWrapped, keypair.privateKeyBase64, keypair.publicKeyBase64),
            'Pipe pairing decryption must not succeed on a call-domain wrapped key',
        );
    });
});
