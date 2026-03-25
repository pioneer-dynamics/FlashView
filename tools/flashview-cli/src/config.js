import Conf from 'conf';

const DEFAULT_URL = 'https://flashview.link';

const config = new Conf({
    projectName: 'flashview-cli',
    schema: {
        url: { type: 'string' },
        token: { type: 'string' },
        serverConfig: { type: 'object' },
        serverConfigFetchedAt: { type: 'number' },
    },
});

const CONFIG_CACHE_TTL = 60 * 60 * 1000; // 1 hour in milliseconds

/**
 * Get the stored configuration, exiting if not configured.
 *
 * @returns {{ url: string, token: string }}
 */
export function getConfig() {
    const url = config.get('url') || DEFAULT_URL;
    const token = config.get('token');

    if (!token) {
        console.error('Not configured. Run: flashview login or flashview config set --token <token>');
        process.exit(1);
    }

    return { url, token };
}

/**
 * Get configuration info for display.
 *
 * @returns {{ url: string|undefined, token: string|undefined, path: string }}
 */
export function getConfigInfo() {
    return {
        url: config.get('url'),
        token: config.get('token'),
        path: config.path,
    };
}

/**
 * Get cached server configuration if still valid.
 *
 * @returns {Object|null}
 */
export function getCachedServerConfig() {
    const fetchedAt = config.get('serverConfigFetchedAt');
    const cached = config.get('serverConfig');

    if (cached && fetchedAt && (Date.now() - fetchedAt) < CONFIG_CACHE_TTL) {
        return cached;
    }

    return null;
}

/**
 * Store server configuration in cache.
 *
 * @param {Object} serverConfig
 */
export function setCachedServerConfig(serverConfig) {
    config.set('serverConfig', serverConfig);
    config.set('serverConfigFetchedAt', Date.now());
}

/**
 * Clear cached server configuration.
 */
export function clearCachedServerConfig() {
    config.delete('serverConfig');
    config.delete('serverConfigFetchedAt');
}

/**
 * Override the cache timestamp (for testing purposes).
 *
 * @param {number} timestamp
 */
export function setServerConfigFetchedAt(timestamp) {
    config.set('serverConfigFetchedAt', timestamp);
}

/**
 * Save configuration.
 *
 * @param {{ url: string, token: string }} options
 */
export function setConfig({ url, token }) {
    if (url) config.set('url', url);
    if (token) config.set('token', token);
    clearCachedServerConfig();
}

/**
 * Clear all stored configuration.
 */
export function clearConfig() {
    config.clear();
}
