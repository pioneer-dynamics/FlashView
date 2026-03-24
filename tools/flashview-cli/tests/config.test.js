import { describe, it, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { getCachedServerConfig, setCachedServerConfig, clearCachedServerConfig, setConfig, clearConfig } from '../src/config.js';

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
