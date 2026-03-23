import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

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
