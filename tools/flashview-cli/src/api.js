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
     * Request a presigned upload URL for direct client-to-S3 upload.
     *
     * @returns {Promise<{upload_type: string, upload_url: string, upload_headers: Object, token: string}>}
     */
    async prepareFileUpload() {
        return this.request('POST', '/api/v1/secrets/file/prepare');
    }

    /**
     * Create a file secret using a pre-uploaded file token (presigned flow).
     *
     * @param {string} fileToken
     * @param {string} encryptedFilename
     * @param {number} fileSize
     * @param {string} fileMimeType
     * @param {number} expiresIn
     * @param {string|null} email
     * @param {boolean} withVerifiedBadge
     * @param {string|null} encryptedMessage
     * @returns {Promise<Object>}
     */
    async createSecretWithFileToken(fileToken, encryptedFilename, fileSize, fileMimeType, expiresIn = 1440, email = null, withVerifiedBadge = false, encryptedMessage = null) {
        const body = {
            file_token: fileToken,
            file_original_name: encryptedFilename,
            file_size: fileSize,
            file_mime_type: fileMimeType,
            expires_in: expiresIn,
        };
        if (encryptedMessage) { body.message = encryptedMessage; }
        if (email) { body.email = email; }
        if (withVerifiedBadge) { body.include_sender_identity = true; }
        return this.request('POST', '/api/v1/secrets', body);
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
     * @param {((received: number, total: number) => void)|null} onProgress
     * @returns {Promise<Uint8Array>}
     */
    async downloadFile(hashId, onProgress = null) {
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
            return this._readResponseWithProgress(s3Response, onProgress);
        }

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status, error.errors);
        }
        return this._readResponseWithProgress(response, onProgress);
    }

    /**
     * Stream a response body, calling onProgress on each chunk if provided.
     *
     * @param {Response} response
     * @param {((received: number, total: number) => void)|null} onProgress
     * @returns {Promise<Uint8Array>}
     */
    async _readResponseWithProgress(response, onProgress) {
        if (!onProgress || !response.body) {
            return new Uint8Array(await response.arrayBuffer());
        }

        const total = parseInt(response.headers.get('Content-Length') ?? '0', 10);
        const reader = response.body.getReader();
        const chunks = [];
        let received = 0;

        onProgress(0, total);
        while (true) {
            const { done, value } = await reader.read();
            if (done) { break; }
            chunks.push(value);
            received += value.byteLength;
            onProgress(received, total || received);
        }

        const result = new Uint8Array(received);
        let offset = 0;
        for (const chunk of chunks) {
            result.set(chunk, offset);
            offset += chunk.byteLength;
        }
        return result;
    }

    // ─── Pipe Pairing API ────────────────────────────────────────────────────────

    /**
     * Register this device's P-256 public key for PKI-based pairing.
     *
     * @param {string} publicKeyBase64 - JWK base64
     * @returns {Promise<{ device_id: string, expires_at: string }>}
     */
    async registerDevice(publicKeyBase64) {
        return this.request('POST', '/api/v1/pipe/devices', { public_key: publicKeyBase64 });
    }

    /**
     * List devices registered to this account that are waiting to be paired.
     *
     * @returns {Promise<{ devices: Array<{ device_id: string, public_key: string, created_at: string }> }>}
     */
    async listWaitingDevices() {
        return this.request('GET', '/api/v1/pipe/devices/waiting');
    }

    /**
     * De-register a device (used when the receiver rejects the pairing code).
     *
     * @param {string} deviceId
     * @returns {Promise<void>}
     */
    async destroyDevice(deviceId) {
        const url = `${this.baseUrl}/api/v1/pipe/devices/${encodeURIComponent(deviceId)}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                signal: controller.signal,
                headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
            });
            if (!response.ok && response.status !== 204) {
                const error = await response.json().catch(() => ({}));
                throw new ApiError(error.message || `HTTP ${response.status}`, response.status, error.errors);
            }
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
    }

    /**
     * Send an ECIES-encrypted pipe seed to a waiting device.
     *
     * @param {string} receiverDeviceId
     * @param {string} encryptedSeedBase64
     * @returns {Promise<{ pairing_id: number }>}
     */
    async sendEncryptedSeed(receiverDeviceId, encryptedSeedBase64) {
        return this.request('POST', '/api/v1/pipe/pairings', {
            receiver_device_id: receiverDeviceId,
            encrypted_seed: encryptedSeedBase64,
        });
    }

    /**
     * Poll for an incoming pairing offer for a specific device.
     *
     * @param {string} deviceId
     * @returns {Promise<{ pairing_id: number, sender_device_id: string, sender_public_key: string, encrypted_seed: string }|null>}
     */
    async pollPairingPending(deviceId) {
        const url = `${this.baseUrl}/api/v1/pipe/pairings/pending?device_id=${encodeURIComponent(deviceId)}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                signal: controller.signal,
                headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
        if (response.status === 204) { return null; }
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status);
        }
        return response.json();
    }

    /**
     * Get pairing status (sender polls until receiver accepts).
     *
     * @param {number} pairingId
     * @returns {Promise<{ pairing_id: number, is_accepted: boolean, expires_at: string }>}
     */
    async getPairingStatus(pairingId) {
        return this.request('GET', `/api/v1/pipe/pairings/${pairingId}`);
    }

    /**
     * Mark a pairing as accepted (receiver confirms the pairing code).
     *
     * @param {number} pairingId
     * @returns {Promise<{ accepted: boolean }>}
     */
    async acceptPairing(pairingId) {
        return this.request('POST', `/api/v1/pipe/pairings/${pairingId}/accept`);
    }

    // ─── Pipe Transfer API ───────────────────────────────────────────────────────

    /**
     * Create a new pipe transfer session.
     *
     * @param {string} sessionId - HKDF-derived hex session ID
     * @param {'relay'|'p2p'} transferMode
     * @returns {Promise<{ session_id: string, expires_at: string, transfer_mode: string }>}
     */
    async createPipeSession(sessionId, transferMode = 'relay') {
        return this.request('POST', '/api/v1/pipe', { session_id: sessionId, transfer_mode: transferMode });
    }

    /**
     * Get pipe session status.
     *
     * @param {string} sessionId
     * @returns {Promise<{ session_id: string, is_complete: boolean, chunk_count: number, total_chunks: number|null, expires_at: string }|null>}
     */
    async getPipeSessionStatus(sessionId) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                signal: controller.signal,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
        if (response.status === 404) { return null; }
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status);
        }
        return response.json();
    }

    /**
     * Upload an encrypted chunk.
     *
     * @param {string} sessionId
     * @param {number} chunkIndex
     * @param {string} chunkBase64
     * @returns {Promise<{ chunk_index: number }>}
     */
    async uploadChunk(sessionId, chunkIndex, chunkBase64) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}/chunk`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ chunk_index: chunkIndex, payload: chunkBase64 }),
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
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
     * Download a chunk, returning null if not yet available (202 Accepted).
     *
     * @param {string} sessionId
     * @param {number} chunkIndex
     * @returns {Promise<string|null>} base64 payload or null if pending
     */
    async downloadChunk(sessionId, chunkIndex) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}/chunk/${chunkIndex}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                signal: controller.signal,
                headers: { 'Accept': 'application/json' },
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
        if (response.status === 202) { return null; }
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status);
        }
        const data = await response.json();
        return data.payload;
    }

    /**
     * Mark the upload complete.
     *
     * @param {string} sessionId
     * @param {number} totalChunks
     * @returns {Promise<{ total_chunks: number, is_complete: boolean }>}
     */
    async completePipeSession(sessionId, totalChunks) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}/complete`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ total_chunks: totalChunks }),
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
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
     * Burn (delete) a pipe session.
     *
     * @param {string} sessionId
     * @returns {Promise<void>}
     */
    async burnPipeSession(sessionId) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        try {
            await fetch(url, {
                method: 'DELETE',
                signal: controller.signal,
                headers: {
                    ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
                    'Accept': 'application/json',
                },
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
    }

    /**
     * Send a WebRTC signaling message.
     *
     * @param {string} sessionId
     * @param {'sender'|'receiver'} role
     * @param {'offer'|'answer'|'ice-candidate'} type
     * @param {object} payload
     * @returns {Promise<{ signal_id: number }>}
     */
    async sendSignal(sessionId, role, type, payload) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}/signal`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                method: 'POST',
                signal: controller.signal,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ role, type, payload }),
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
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
     * Poll for WebRTC signals.
     *
     * @param {string} sessionId
     * @param {'sender'|'receiver'} role
     * @param {number} afterId - Return only signals with ID greater than this
     * @returns {Promise<{ signals: Array<{ id: number, type: string, payload: object }> }>}
     */
    async pollSignals(sessionId, role, afterId = 0) {
        const url = `${this.baseUrl}/api/v1/pipe/${encodeURIComponent(sessionId)}/signal?role=${encodeURIComponent(role)}&after=${afterId}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeout);
        let response;
        try {
            response = await fetch(url, {
                signal: controller.signal,
                headers: { 'Accept': 'application/json' },
            });
        } catch (err) {
            if (err.name === 'AbortError') { throw new ApiError('Request timed out', 0); }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new ApiError(error.message || `HTTP ${response.status}`, response.status);
        }
        return response.json();
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
