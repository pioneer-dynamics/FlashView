import { describe, it, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { getCachedServerConfig, setCachedServerConfig, clearCachedServerConfig, setConfig, clearConfig, setServerConfigFetchedAt } from '../src/config.js';

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
