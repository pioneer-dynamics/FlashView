import { describe, it, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import { getCachedServerConfig, setCachedServerConfig, clearCachedServerConfig, setConfig, clearConfig, setServerConfigFetchedAt, getConfig, getConfigInfo, getCachedLatestVersion, setCachedLatestVersion } from '../src/config.js';

describe('Server config caching', () => {
    beforeEach(() => {
        clearConfig();
    });

    it('returns null when no cache exists', () => {
        assert.strictEqual(getCachedServerConfig(), null);
    });

    it('round-trips cached server config', () => {
        const config = { expiry_options: [{ label: '1 hour', value: 60 }] };
        setCachedServerConfig(config);

        const cached = getCachedServerConfig();
        assert.deepStrictEqual(cached, config);
    });

    it('returns cached config within TTL', () => {
        const config = { expiry_options: [{ label: '5 minutes', value: 5 }] };
        setCachedServerConfig(config);
        // Set fetchedAt to 30 minutes ago (within 1h TTL)
        setServerConfigFetchedAt(Date.now() - (30 * 60 * 1000));

        assert.deepStrictEqual(getCachedServerConfig(), config);
    });

    it('returns null when cache has expired beyond TTL', () => {
        const config = { expiry_options: [{ label: '1 hour', value: 60 }] };
        setCachedServerConfig(config);
        // Set fetchedAt to 2 hours ago (beyond 1h TTL)
        setServerConfigFetchedAt(Date.now() - (2 * 60 * 60 * 1000));

        assert.strictEqual(getCachedServerConfig(), null);
    });

    it('clearCachedServerConfig removes cached data', () => {
        setCachedServerConfig({ expiry_options: [] });
        clearCachedServerConfig();

        assert.strictEqual(getCachedServerConfig(), null);
    });

    it('setConfig clears cached server config', () => {
        setCachedServerConfig({ expiry_options: [] });
        setConfig({ url: 'https://example.com', token: 'tok_test' });

        assert.strictEqual(getCachedServerConfig(), null);
    });
});

describe('Config read/write operations', () => {
    beforeEach(() => {
        clearConfig();
    });

    it('getConfigInfo returns path and stored values', () => {
        setConfig({ url: 'https://test.example.com', token: 'tok_abc123' });

        const info = getConfigInfo();
        assert.strictEqual(info.url, 'https://test.example.com');
        assert.strictEqual(info.token, 'tok_abc123');
        assert.ok(info.path.endsWith('config.json'));
    });

    it('getConfigInfo always returns a path', () => {
        const info = getConfigInfo();
        assert.ok(info.path.endsWith('config.json'));
    });

    it('clearConfig removes stored data', () => {
        setConfig({ url: 'https://test.com', token: 'tok_xyz' });
        setCachedServerConfig({ expiry_options: [] });

        clearConfig();
        // After clearing, setConfig with fresh values should work
        setConfig({ url: 'https://fresh.com', token: 'tok_fresh' });
        const info = getConfigInfo();
        assert.strictEqual(info.url, 'https://fresh.com');
        assert.strictEqual(info.token, 'tok_fresh');
    });

    it('setConfig preserves existing values when partial update', () => {
        setConfig({ url: 'https://first.com', token: 'tok_first' });
        setConfig({ token: 'tok_second' });

        const info = getConfigInfo();
        assert.strictEqual(info.url, 'https://first.com');
        assert.strictEqual(info.token, 'tok_second');
    });
});

describe('Version cache', () => {
    beforeEach(() => {
        clearConfig();
    });

    it('returns null when no version is cached', () => {
        assert.strictEqual(getCachedLatestVersion(), null);
    });

    it('round-trips cached version', () => {
        setCachedLatestVersion('2.0.0');
        assert.strictEqual(getCachedLatestVersion(), '2.0.0');
    });

    it('returns null when version cache has expired', () => {
        setCachedLatestVersion('2.0.0');
        // Manually expire by reading/rewriting the config file
        const info = getConfigInfo();
        const data = JSON.parse(readFileSync(info.path, 'utf8'));
        data.latestVersionCheckedAt = Date.now() - (2 * 60 * 60 * 1000);
        writeFileSync(info.path, JSON.stringify(data, null, 2), 'utf8');

        assert.strictEqual(getCachedLatestVersion(), null);
    });
});
