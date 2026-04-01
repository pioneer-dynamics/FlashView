import { readFileSync, writeFileSync, mkdirSync, existsSync, unlinkSync, renameSync } from 'node:fs';
import { join } from 'node:path';
import { homedir, platform } from 'node:os';

const APP_NAME = 'flashview-cli';
const CONFIG_FILE = 'config.json';
const DEFAULT_URL = 'https://flashview.link';

const CONFIG_CACHE_TTL = 60 * 60 * 1000; // 1 hour in milliseconds
const VERSION_CHECK_TTL = 60 * 60 * 1000; // 1 hour in milliseconds

function getConfigDir() {
    switch (platform()) {
        case 'win32':
            return join(process.env.APPDATA || join(homedir(), 'AppData', 'Roaming'), APP_NAME);
        case 'darwin':
            return join(homedir(), 'Library', 'Application Support', APP_NAME);
        default:
            return join(process.env.XDG_CONFIG_HOME || join(homedir(), '.config'), APP_NAME);
    }
}

function getConfigPath() {
    return join(getConfigDir(), CONFIG_FILE);
}

/**
 * Get the old conf package config path for migration.
 * The conf package (v13) uses env-paths which appends '-nodejs' suffix.
 */
function getOldConfigPath() {
    switch (platform()) {
        case 'win32':
            return join(process.env.APPDATA || join(homedir(), 'AppData', 'Roaming'), `${APP_NAME}-nodejs`, 'config.json');
        case 'darwin':
            return join(homedir(), 'Library', 'Preferences', `${APP_NAME}-nodejs`, 'config.json');
        default:
            return join(process.env.XDG_CONFIG_HOME || join(homedir(), '.config'), `${APP_NAME}-nodejs`, 'config.json');
    }
}

function readConfigFile() {
    try {
        return JSON.parse(readFileSync(getConfigPath(), 'utf8'));
    } catch {
        // Try migrating from old conf path
        try {
            const oldPath = getOldConfigPath();
            if (existsSync(oldPath)) {
                const data = JSON.parse(readFileSync(oldPath, 'utf8'));
                writeConfigFile(data);
                return data;
            }
        } catch {
            // Ignore migration errors
        }
        return {};
    }
}

function writeConfigFile(data) {
    const dir = getConfigDir();
    mkdirSync(dir, { recursive: true, mode: 0o700 });
    writeFileSync(getConfigPath(), JSON.stringify(data, null, 2), { encoding: 'utf8', mode: 0o600 });
}

/**
 * Get the stored configuration, exiting if not configured.
 *
 * @returns {{ url: string, token: string }}
 */
export function getConfig() {
    const data = readConfigFile();
    const url = data.url || DEFAULT_URL;
    const token = data.token;

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
    const data = readConfigFile();
    return {
        url: data.url,
        token: data.token,
        path: getConfigPath(),
    };
}

/**
 * Get cached server configuration if still valid.
 *
 * @returns {Object|null}
 */
export function getCachedServerConfig() {
    const data = readConfigFile();
    const fetchedAt = data.serverConfigFetchedAt;
    const cached = data.serverConfig;

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
    const data = readConfigFile();
    data.serverConfig = serverConfig;
    data.serverConfigFetchedAt = Date.now();
    writeConfigFile(data);
}

/**
 * Clear cached server configuration.
 */
export function clearCachedServerConfig() {
    const data = readConfigFile();
    delete data.serverConfig;
    delete data.serverConfigFetchedAt;
    writeConfigFile(data);
}

/**
 * Override the cache timestamp (for testing purposes).
 *
 * @param {number} timestamp
 */
export function setServerConfigFetchedAt(timestamp) {
    const data = readConfigFile();
    data.serverConfigFetchedAt = timestamp;
    writeConfigFile(data);
}

/**
 * Save configuration.
 *
 * @param {{ url: string, token: string }} options
 */
export function setConfig({ url, token }) {
    const data = readConfigFile();
    if (url) data.url = url;
    if (token) data.token = token;
    delete data.serverConfig;
    delete data.serverConfigFetchedAt;
    writeConfigFile(data);
}

/**
 * Clear all stored configuration.
 */
export function clearConfig() {
    const path = getConfigPath();
    try {
        unlinkSync(path);
    } catch {
        // File may not exist
    }
}

/**
 * Get cached latest version if still valid.
 *
 * @returns {string|null}
 */
export function getCachedLatestVersion() {
    const data = readConfigFile();
    const checkedAt = data.latestVersionCheckedAt;
    const version = data.latestVersion;

    if (version && checkedAt && (Date.now() - checkedAt) < VERSION_CHECK_TTL) {
        return version;
    }

    return null;
}

/**
 * Store latest version in cache.
 *
 * @param {string} version
 */
export function setCachedLatestVersion(version) {
    const data = readConfigFile();
    data.latestVersion = version;
    data.latestVersionCheckedAt = Date.now();
    writeConfigFile(data);
}
