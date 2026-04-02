import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';
import { statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cliRoot = join(__dirname, '..');

/**
 * PIO-43 Regression: Verify esbuild upgrade does not break the build pipeline
 *
 * esbuild was upgraded from ^0.24.2 to ^0.25.0 to fix CORS vulnerability
 * GHSA-67mh-4wv8-2f99. This test ensures the bundle step still produces
 * valid output after the upgrade.
 */
describe('PIO-43 Regression: esbuild upgrade does not break build pipeline', () => {
    it('esbuild version is >= 0.25.0', async () => {
        const esbuild = await import('esbuild');
        const [major, minor] = esbuild.version.split('.').map(Number);
        assert.ok(
            major > 0 || (major === 0 && minor >= 25),
            `Expected esbuild >= 0.25.0, got ${esbuild.version}`
        );
    });

    it('build script produces a non-empty bundle', () => {
        execSync('node scripts/build.js', {
            cwd: cliRoot,
            encoding: 'utf-8',
            stdio: 'pipe',
        });

        const bundlePath = join(cliRoot, 'dist', 'flashview.cjs');
        const stat = statSync(bundlePath);
        assert.ok(stat.size > 0, 'dist/flashview.cjs should be non-empty');
    });
});
