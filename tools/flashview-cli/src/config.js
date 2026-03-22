import Conf from 'conf';

const DEFAULT_URL = 'https://flashview.link';

const config = new Conf({
    projectName: 'flashview-cli',
    schema: {
        url: { type: 'string' },
        token: { type: 'string' },
    },
});

/**
 * Get the stored configuration, exiting if not configured.
 *
 * @returns {{ url: string, token: string }}
 */
export function getConfig() {
    const url = config.get('url') || DEFAULT_URL;
    const token = config.get('token');

    if (!token) {
        console.error('Not configured. Run: flashview configure set --token <token>');
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
 * Save configuration.
 *
 * @param {{ url: string, token: string }} options
 */
export function setConfig({ url, token }) {
    if (url) config.set('url', url);
    if (token) config.set('token', token);
}

/**
 * Clear all stored configuration.
 */
export function clearConfig() {
    config.clear();
}
