import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { encryptMessage, decryptMessage, generatePassphrase, encryptBuffer, decryptBuffer } from '../src/crypto.js';

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

        // Salt should be valid hex (16 chars = 8 bytes)
        assert.match(saltHex, /^[0-9a-f]{16}$/, 'Salt should be 16 hex characters');

        // Base64 part should decode without error
        const decoded = Buffer.from(base64Part, 'base64');
        assert.ok(decoded.length > 0, 'Base64 should decode to non-empty buffer');

        // Decoded buffer should contain: 12 (IV) + plaintext length + 16 (auth tag) bytes
        // "Test message" = 12 bytes, so total = 12 + 12 + 16 = 40
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

describe('ciphertext format compatibility', () => {
    it('matches expected overhead for MessageLength validation', async () => {
        // The MessageLength rule subtracts 28 bytes (12 IV + 16 auth tag) from the
        // decoded base64 length to estimate plaintext length.
        const plaintext = 'Hello'; // 5 bytes
        const { secret } = await encryptMessage(plaintext, 'test-passphrase');

        const base64Part = secret.substring(16);
        const decoded = Buffer.from(base64Part, 'base64');

        // decoded length = IV (12) + plaintext (5) + authTag (16) = 33
        assert.equal(decoded.length, 33);

        // The estimated plaintext length (what MessageLength calculates)
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

    it('produces correct binary format: [8B salt][12B IV][ciphertext + 16B auth tag]', async () => {
        const plaintext = new Uint8Array(100);
        const { encrypted } = await encryptBuffer(plaintext);
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
