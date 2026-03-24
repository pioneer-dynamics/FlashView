const DEFAULT_TIMEOUT = 30000; // 30 seconds

export class FlashViewClient {
    /**
     * @param {string} baseUrl
     * @param {string} token
     * @param {number} timeout
     */
    constructor(baseUrl, token, timeout = DEFAULT_TIMEOUT) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = token;
        this.timeout = timeout;
    }

    /**
     * Fetch server configuration (public endpoint).
     *
     * @param {string} baseUrl
     * @param {string|null} token - Optional auth token for plan-specific limits
     * @returns {Promise<Object>}
     */
    static async fetchConfig(baseUrl, token = null) {
        const url = `${baseUrl.replace(/\/$/, '')}/api/v1/config`;
        const headers = { 'Accept': 'application/json' };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);

        let response;
        try {
            response = await fetch(url, { headers, signal: controller.signal });
        } catch (err) {
            if (err.name === 'AbortError') {
                throw new ApiError('Config fetch timed out', 0);
            }
            throw err;
        } finally {
            clearTimeout(timeout);
        }

        if (!response.ok) {
            throw new ApiError(`Config fetch failed (HTTP ${response.status})`, response.status);
        }

        return response.json();
    }

    /**
     * Create an encrypted secret.
     *
     * @param {string} encryptedMessage
     * @param {number} expiresIn - Minutes until expiry
     * @param {string|null} email - Optional recipient email
     * @returns {Promise<Object>}
     */
    async createSecret(encryptedMessage, expiresIn = 1440, email = null) {
        const body = { message: encryptedMessage, expires_in: expiresIn };
        if (email) {
            body.email = email;
        }

        return this.request('POST', '/api/v1/secrets', body);
    }

    /**
     * List the authenticated user's secrets.
     *
     * @param {number} page
     * @returns {Promise<Object>}
     */
    async listSecrets(page = 1) {
        return this.request('GET', `/api/v1/secrets?page=${page}`);
    }

    /**
     * Get status of a secret.
     *
     * @param {string} hashId
     * @returns {Promise<Object>}
     */
    async getSecretStatus(hashId) {
        return this.request('GET', `/api/v1/secrets/${hashId}`);
    }

    /**
     * Retrieve a secret's encrypted message (one-time access).
     *
     * @param {string} hashId
     * @returns {Promise<Object>}
     */
    async retrieveSecret(hashId) {
        return this.request('GET', `/api/v1/secrets/${hashId}/retrieve`);
    }

    /**
     * Burn (delete) a secret.
     *
     * @param {string} hashId
     * @returns {Promise<Object>}
     */
    async burnSecret(hashId) {
        return this.request('DELETE', `/api/v1/secrets/${hashId}`);
    }

    /**
     * @param {string} method
     * @param {string} path
     * @param {Object|null} body
     * @returns {Promise<Object>}
     */
    async request(method, path, body = null) {
        const url = `${this.baseUrl}${path}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);

        const options = {
            method,
            signal: controller.signal,
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        };

        if (body) {
            options.body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(url, options);
        } catch (err) {
            if (err.name === 'AbortError') {
                throw new ApiError('Request timed out', 0);
            }
            throw err;
        } finally {
            clearTimeout(timeout);
        }

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            const message = error.message || `HTTP ${response.status}`;
            throw new ApiError(message, response.status, error.errors);
        }

        return response.json();
    }
}

export class ApiError extends Error {
    /**
     * @param {string} message
     * @param {number} status
     * @param {Object|null} errors
     */
    constructor(message, status, errors = null) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}
