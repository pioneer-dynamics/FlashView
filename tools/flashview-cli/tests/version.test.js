import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { isNewerVersion } from '../src/version.js';

describe('isNewerVersion', () => {
    it('returns true when latest is a higher major version', () => {
        assert.strictEqual(isNewerVersion('1.0.0', '2.0.0'), true);
    });

    it('returns true when latest is a higher minor version', () => {
        assert.strictEqual(isNewerVersion('1.2.0', '1.3.0'), true);
    });

    it('returns true when latest is a higher patch version', () => {
        assert.strictEqual(isNewerVersion('1.2.3', '1.2.4'), true);
    });

    it('returns false when versions are equal', () => {
        assert.strictEqual(isNewerVersion('1.3.1', '1.3.1'), false);
    });

    it('returns false when current is newer (higher major)', () => {
        assert.strictEqual(isNewerVersion('2.0.0', '1.9.9'), false);
    });

    it('returns false when current is newer (higher minor)', () => {
        assert.strictEqual(isNewerVersion('1.4.0', '1.3.9'), false);
    });

    it('returns false when current is newer (higher patch)', () => {
        assert.strictEqual(isNewerVersion('1.3.2', '1.3.1'), false);
    });

    it('handles v prefix on current version', () => {
        assert.strictEqual(isNewerVersion('v1.3.1', '1.4.0'), true);
    });

    it('handles v prefix on latest version', () => {
        assert.strictEqual(isNewerVersion('1.3.1', 'v1.4.0'), true);
    });

    it('handles v prefix on both versions', () => {
        assert.strictEqual(isNewerVersion('v1.3.1', 'v1.3.1'), false);
    });

    it('handles major version jump correctly', () => {
        assert.strictEqual(isNewerVersion('1.9.9', '2.0.0'), true);
    });

    it('handles minor version jump correctly', () => {
        assert.strictEqual(isNewerVersion('1.9.9', '1.10.0'), true);
    });
});
