import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const pkg = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf-8'));

/**
 * PIO-40 Regression: CLI shows 0.0.0-dev after npm update instead of actual version
 *
 * When installed via npm, bin/flashview.js imports raw source where __VERSION__
 * (injected by esbuild) is never defined. The fallback was hardcoded to '0.0.0-dev'
 * instead of reading from package.json.
 */
describe('PIO-40 Regression: CLI version from source is not 0.0.0-dev', () => {
    it('does not report 0.0.0-dev when run from source', () => {
        const output = execSync('node bin/flashview.js --version', {
            cwd: join(__dirname, '..'),
            encoding: 'utf-8',
        });
        assert.notStrictEqual(output.trim(), '0.0.0-dev',
            'CLI should not report 0.0.0-dev when run from source files');
    });

    it('reports the version from package.json when run from source', () => {
        const output = execSync('node bin/flashview.js --version', {
            cwd: join(__dirname, '..'),
            encoding: 'utf-8',
        });
        assert.strictEqual(output.trim(), pkg.version);
    });
});
