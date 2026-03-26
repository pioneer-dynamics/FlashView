import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const helperScript = join(__dirname, 'helpers', 'read-stdin.js');

/**
 * PIO-35 Regression: stdin content preservation
 *
 * The readStdin() function was calling .trim() on piped input,
 * stripping trailing newlines and making retrieved secrets
 * not byte-identical to the original input.
 */
describe('PIO-35 Regression: stdin content preservation', () => {
    /**
     * Pipes the given string into the helper script and returns the raw output.
     *
     * @param {string} input
     * @returns {string}
     */
    function pipeThrough(input) {
        return execSync(`node "${helperScript}"`, {
            input,
            cwd: join(__dirname, '..'),
            encoding: 'utf-8',
        });
    }

    it('preserves a trailing newline', () => {
        const input = 'hello world\n';
        const output = pipeThrough(input);
        assert.strictEqual(output, input);
    });

    it('preserves multiple trailing newlines', () => {
        const input = 'hello world\n\n\n';
        const output = pipeThrough(input);
        assert.strictEqual(output, input);
    });

    it('preserves leading whitespace', () => {
        const input = '  hello world';
        const output = pipeThrough(input);
        assert.strictEqual(output, input);
    });

    it('preserves content with no trailing newline', () => {
        const input = 'hello world';
        const output = pipeThrough(input);
        assert.strictEqual(output, input);
    });

    it('preserves content resembling an SSH key with trailing newline', () => {
        const input = '-----BEGIN OPENSSH PRIVATE KEY-----\ndata\n-----END OPENSSH PRIVATE KEY-----\n';
        const output = pipeThrough(input);
        assert.strictEqual(output, input);
    });
});
