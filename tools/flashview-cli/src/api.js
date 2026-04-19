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
     * Fetch server configuration (requires authentication).
     *
     * @param {string} baseUrl
     * @param {string|null} token - Auth token for API access
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
    async createSecret(encryptedMessage, expiresIn = 1440, email = null, withVerifiedBadge = false) {
        const body = { message: encryptedMessage, expires_in: expiresIn };
        if (email) {
            body.email = email;
        }
        if (withVerifiedBadge) {
            body.include_sender_identity = true;
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
     * Upload an encrypted file as a file secret.
     *
     * @param {Uint8Array} encryptedBuffer
     * @param {string} encryptedFilename
     * @param {number} fileSize
     * @param {string} fileMimeType
     * @param {number} expiresIn
     * @param {string|null} email
     * @param {boolean} withVerifiedBadge
     * @returns {Promise<Object>}
     */
    async uploadFile(encryptedBuffer, encryptedFilename, fileSize, fileMimeType, expiresIn = 1440, email = null, withVerifiedBadge = false, encryptedMessage = null) {
        const formData = new FormData();
        formData.append('file', new Blob([encryptedBuffer], { type: 'application/octet-stream' }), 'encrypted.bin');
        formData.append('file_original_name', encryptedFilename);
        formData.append('file_size', String(fileSize));
        formData.append('file_mime_type', fileMimeType);
        formData.append('expires_in', String(expiresIn));
        if (encryptedMessage) { formData.append('message', encryptedMessage); }
        if (email) { formData.append('email', email); }
        if (withVerifiedBadge) { formData.append('include_sender_identity', 'true'); }

        return this.requestMultipart('POST', '/api/v1/secrets', formData);
    }

    /**
     * Download an encrypted file secret as raw bytes.
     *
     * @param {string} hashId
     * @returns {Promise<Uint8Array>}
     */
    async downloadFile(hashId) {
        const url = `${this.baseUrl}/api/v1/secrets/${hashId}/file`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            // Use redirect: 'manual' so we can follow the S3 presigned URL redirect
            // without forwarding the Authorization header (AWS rejects dual-auth).
            response = await fetch(url, {
                headers: { 'Authorization': `Bearer ${this.token}` },
                signal: controller.signal,
                redirect: 'manual',
            });
        } catch (err) {
            if (err.name === 'AbortError') {
                throw new ApiError('Request timed out', 0);
            }
            throw err;
        } finally {
            clearTimeout(timeout);
        }

        // Follow presigned URL redirect without Authorization header.
        if (response.status === 301 || response.status === 302) {
            const location = response.headers.get('Location');
            const s3Response = await fetch(location);
            if (!s3Response.ok) {
                throw new ApiError(`Download failed (HTTP ${s3Response.status})`, s3Response.status);
            }
            return new Uint8Array(await s3Response.arrayBuffer());
        }

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status, error.errors);
        }
        return new Uint8Array(await response.arrayBuffer());
    }

    /**
     * Confirm that the client has downloaded the file so the server can delete the S3 object.
     *
     * @param {string} hashId
     * @returns {Promise<void>}
     */
    async confirmFileDownloaded(hashId) {
        const url = `${this.baseUrl}/api/v1/secrets/${hashId}/file/downloaded`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        try {
            await fetch(url, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
                signal: controller.signal,
            });
        } catch {
            // Best-effort — server will clean up via the orphaned-file job after TTL.
        } finally {
            clearTimeout(timeout);
        }
    }

    /**
     * Perform a multipart/form-data request (no explicit Content-Type — browser/Node sets boundary).
     *
     * @param {string} method
     * @param {string} path
     * @param {FormData} formData
     * @returns {Promise<Object>}
     */
    async requestMultipart(method, path, formData) {
        const url = `${this.baseUrl}${path}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                method,
                signal: controller.signal,
                headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
                body: formData,
            });
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
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status, error.errors);
        }
        return response.json();
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
