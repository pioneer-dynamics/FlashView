import { FlashViewClient } from './api.js';
import { getConfigInfo, getCachedServerConfig, setCachedServerConfig } from './config.js';

/** Fallback expiry options used when the server is unreachable and no cache exists. */
export const FALLBACK_EXPIRY_OPTIONS = [
    { label: '5 minutes', value: 5 },
    { label: '30 minutes', value: 30 },
    { label: '1 hour', value: 60 },
    { label: '4 hours', value: 240 },
    { label: '12 hours', value: 720 },
    { label: '1 day', value: 1440 },
    { label: '3 days', value: 4320 },
    { label: '7 days', value: 10080 },
    { label: '14 days', value: 20160 },
    { label: '30 days', value: 43200 },
];

// Intentionally static — shorthand labels are a CLI convenience and don't need
// to be dynamically generated from the server. New server-side expiry options
// can still be used via raw minute values (e.g., `--expires-in 120`).
export const SHORTHAND_MAP = {
    '5m': 5, '30m': 30, '1h': 60, '4h': 240, '12h': 720,
    '1d': 1440, '3d': 4320, '7d': 10080, '14d': 20160, '30d': 43200,
};

const DEFAULT_URL = 'https://flashview.link';

/**
 * Fetch server configuration with cache-first strategy.
 *
 * @returns {Promise<Object|null>}
 */
export async function getServerConfig() {
    const cached = getCachedServerConfig();
    if (cached) {
        return cached;
    }

    try {
        process.stderr.write('Fetching configuration from server...\n');
        const { url, token } = getConfigInfo();
        const baseUrl = url || DEFAULT_URL;
        const serverConfig = await FlashViewClient.fetchConfig(baseUrl, token || null);
        setCachedServerConfig(serverConfig);
        return serverConfig;
    } catch {
        return null;
    }
}

/**
 * Parse an expiry value from human-readable label or raw minutes.
 *
 * @param {string} value
 * @param {number[]} allowedValues
 * @returns {number|null}
 */
export function parseExpiry(value, allowedValues) {
    const shorthand = SHORTHAND_MAP[value.toLowerCase()];
    if (shorthand !== undefined && allowedValues.includes(shorthand)) {
        return shorthand;
    }

    const minutes = parseInt(value, 10);
    if (!isNaN(minutes) && allowedValues.includes(minutes)) {
        return minutes;
    }

    return null;
}
