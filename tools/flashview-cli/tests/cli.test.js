import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseExpiry, FALLBACK_EXPIRY_OPTIONS, SHORTHAND_MAP } from '../src/expiry.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const pkg = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf-8'));

describe('CLI version', () => {
    it('outputs the version from package.json', () => {
        const output = execSync('node bin/flashview.js --version', {
            cwd: join(__dirname, '..'),
            encoding: 'utf-8',
        });
        assert.strictEqual(output.trim(), pkg.version);
    });
});

describe('parseExpiry', () => {
    const defaultAllowed = FALLBACK_EXPIRY_OPTIONS.map(o => o.value);

    it('parses shorthand labels with default options', () => {
        assert.strictEqual(parseExpiry('1h', defaultAllowed), 60);
        assert.strictEqual(parseExpiry('7d', defaultAllowed), 10080);
        assert.strictEqual(parseExpiry('30m', defaultAllowed), 30);
    });

    it('parses raw minute values', () => {
        assert.strictEqual(parseExpiry('60', defaultAllowed), 60);
        assert.strictEqual(parseExpiry('1440', defaultAllowed), 1440);
    });

    it('is case-insensitive for shorthand labels', () => {
        assert.strictEqual(parseExpiry('1H', defaultAllowed), 60);
        assert.strictEqual(parseExpiry('7D', defaultAllowed), 10080);
    });

    it('returns null for invalid values', () => {
        assert.strictEqual(parseExpiry('2h', defaultAllowed), null);
        assert.strictEqual(parseExpiry('abc', defaultAllowed), null);
        assert.strictEqual(parseExpiry('999', defaultAllowed), null);
    });

    it('works with dynamically provided options', () => {
        const customAllowed = [15, 120, 480];
        assert.strictEqual(parseExpiry('120', customAllowed), 120);
        assert.strictEqual(parseExpiry('15', customAllowed), 15);
        // Shorthand '1h' maps to 60, which is not in customAllowed
        assert.strictEqual(parseExpiry('1h', customAllowed), null);
    });

    it('rejects shorthand when mapped value is not in allowed list', () => {
        const restrictedAllowed = [5, 30]; // only 5m and 30m
        assert.strictEqual(parseExpiry('5m', restrictedAllowed), 5);
        assert.strictEqual(parseExpiry('1h', restrictedAllowed), null);
        assert.strictEqual(parseExpiry('7d', restrictedAllowed), null);
    });

    it('fallback expiry options match shorthand map values', () => {
        const fallbackValues = FALLBACK_EXPIRY_OPTIONS.map(o => o.value);
        const shorthandValues = Object.values(SHORTHAND_MAP);
        assert.deepStrictEqual(shorthandValues.sort((a, b) => a - b), fallbackValues.sort((a, b) => a - b));
    });
});
