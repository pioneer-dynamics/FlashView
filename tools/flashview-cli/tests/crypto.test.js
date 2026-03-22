import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { encryptMessage, decryptMessage, generatePassphrase } from '../src/crypto.js';

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
    it('returns passphrase and encrypted secret', () => {
        const result = encryptMessage('Hello, World!');
        assert.ok(result.passphrase, 'Should return a passphrase');
        assert.ok(result.secret, 'Should return an encrypted secret');
    });

    it('uses provided passphrase when given', () => {
        const passphrase = 'my-custom-passphrase';
        const result = encryptMessage('Hello', passphrase);
        assert.equal(result.passphrase, passphrase);
    });

    it('auto-generates passphrase when not provided', () => {
        const result = encryptMessage('Hello');
        const words = result.passphrase.split('-');
        assert.equal(words.length, 8);
    });

    it('produces format: 16 hex chars + base64 data', () => {
        const result = encryptMessage('Test message');
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

    it('produces different ciphertext for same plaintext (random salt/IV)', () => {
        const a = encryptMessage('Same message', 'same-passphrase');
        const b = encryptMessage('Same message', 'same-passphrase');
        assert.notEqual(a.secret, b.secret, 'Different salt/IV should produce different ciphertext');
    });
});

describe('decryptMessage', () => {
    it('round-trips encryption and decryption', () => {
        const plaintext = 'Hello, World!';
        const { passphrase, secret } = encryptMessage(plaintext);
        const decrypted = decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('round-trips with custom passphrase', () => {
        const plaintext = 'Secret data 123!@#';
        const passphrase = 'my-test-passphrase';
        const { secret } = encryptMessage(plaintext, passphrase);
        const decrypted = decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('round-trips with empty string', () => {
        const { passphrase, secret } = encryptMessage('');
        const decrypted = decryptMessage(secret, passphrase);
        assert.equal(decrypted, '');
    });

    it('round-trips with unicode characters', () => {
        const plaintext = 'Hello \u{1F680} World \u00E9\u00E8\u00EA \u4F60\u597D';
        const { passphrase, secret } = encryptMessage(plaintext);
        const decrypted = decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('round-trips with long message', () => {
        const plaintext = 'A'.repeat(10000);
        const { passphrase, secret } = encryptMessage(plaintext);
        const decrypted = decryptMessage(secret, passphrase);
        assert.equal(decrypted, plaintext);
    });

    it('fails with wrong passphrase', () => {
        const { secret } = encryptMessage('Hello', 'correct-passphrase');
        assert.throws(() => {
            decryptMessage(secret, 'wrong-passphrase');
        }, 'Should throw with wrong passphrase');
    });

    it('fails with corrupted ciphertext', () => {
        const { passphrase, secret } = encryptMessage('Hello');
        const corrupted = secret.substring(0, 16) + 'AAAA' + secret.substring(20);
        assert.throws(() => {
            decryptMessage(corrupted, passphrase);
        }, 'Should throw with corrupted ciphertext');
    });
});

describe('ciphertext format compatibility', () => {
    it('matches expected overhead for MessageLength validation', () => {
        // The MessageLength rule subtracts 28 bytes (12 IV + 16 auth tag) from the
        // decoded base64 length to estimate plaintext length.
        const plaintext = 'Hello'; // 5 bytes
        const { secret } = encryptMessage(plaintext, 'test-passphrase');

        const base64Part = secret.substring(16);
        const decoded = Buffer.from(base64Part, 'base64');

        // decoded length = IV (12) + plaintext (5) + authTag (16) = 33
        assert.equal(decoded.length, 33);

        // The estimated plaintext length (what MessageLength calculates)
        const estimatedPlaintext = decoded.length - 28;
        assert.equal(estimatedPlaintext, 5, 'Estimated plaintext length should match actual');
    });
});
