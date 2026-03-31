import { getCachedLatestVersion, setCachedLatestVersion } from './config.js';

const NPM_REGISTRY_URL = 'https://registry.npmjs.org/@pioneer-dynamics/flashview-cli/latest';
const FETCH_TIMEOUT = 5000;

/**
 * Fetch the latest version from the npm registry.
 * Returns null on any error (network, timeout, parse).
 *
 * @returns {Promise<string|null>}
 */
export async function fetchLatestVersion() {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), FETCH_TIMEOUT);

    try {
        const response = await fetch(NPM_REGISTRY_URL, {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) return null;

        const data = await response.json();
        return data.version || null;
    } catch {
        return null;
    } finally {
        clearTimeout(timeout);
    }
}

/**
 * Get the latest version, using cache-first strategy.
 * Returns null if unavailable.
 *
 * @returns {Promise<string|null>}
 */
export async function getLatestVersion() {
    const cached = getCachedLatestVersion();
    if (cached) return cached;

    const latest = await fetchLatestVersion();
    if (latest) {
        setCachedLatestVersion(latest);
    }
    return latest;
}

/**
 * Compare two semver strings. Returns true if latest > current.
 *
 * @param {string} current
 * @param {string} latest
 * @returns {boolean}
 */
export function isNewerVersion(current, latest) {
    const parse = (v) => v.replace(/^v/, '').split('.').map(Number);
    const [cMajor, cMinor, cPatch] = parse(current);
    const [lMajor, lMinor, lPatch] = parse(latest);

    if (lMajor !== cMajor) return lMajor > cMajor;
    if (lMinor !== cMinor) return lMinor > cMinor;
    return lPatch > cPatch;
}

/**
 * Refresh the version cache in the background (fire-and-forget).
 * Does not block, does not throw.
 */
export function refreshVersionCache() {
    fetchLatestVersion()
        .then((version) => {
            if (version) setCachedLatestVersion(version);
        })
        .catch(() => {});
}
