import { describe, it, mock, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { FlashViewClient, ApiError } from '../src/api.js';

const BASE_URL = 'https://flashview.example.com';
const TOKEN = 'test-token-abc123';

function makeFetchResponse(status, body, headers = {}) {
    return Promise.resolve({
        ok: status >= 200 && status < 300,
        status,
        headers: {
            get: (name) => headers[name.toLowerCase()] ?? null,
        },
        json: () => Promise.resolve(body),
    });
}

describe('FlashViewClient', () => {
    let originalFetch;
    let client;

    beforeEach(() => {
        originalFetch = globalThis.fetch;
        client = new FlashViewClient(BASE_URL, TOKEN);
    });

    afterEach(() => {
        globalThis.fetch = originalFetch;
    });

    describe('constructor', () => {
        it('strips trailing slash from baseUrl', () => {
            const c = new FlashViewClient('https://example.com/', TOKEN);
            assert.equal(c.baseUrl, 'https://example.com');
        });

        it('stores token and default timeout', () => {
            assert.equal(client.token, TOKEN);
            assert.equal(client.timeout, 30000);
        });
    });

    describe('fetchConfig', () => {
        it('fetches config without auth token', async () => {
            globalThis.fetch = (url, opts) => {
                assert.ok(!opts.headers['Authorization'], 'No auth header for unauthenticated config fetch');
                assert.equal(url, `${BASE_URL}/api/v1/config`);
                return makeFetchResponse(200, { maxMessageLength: 10000 });
            };

            const config = await FlashViewClient.fetchConfig(BASE_URL);
            assert.equal(config.maxMessageLength, 10000);
        });

        it('fetches config with auth token when provided', async () => {
            globalThis.fetch = (url, opts) => {
                assert.equal(opts.headers['Authorization'], `Bearer ${TOKEN}`);
                return makeFetchResponse(200, { maxMessageLength: 50000 });
            };

            const config = await FlashViewClient.fetchConfig(BASE_URL, TOKEN);
            assert.equal(config.maxMessageLength, 50000);
        });

        it('throws ApiError on non-ok response', async () => {
            globalThis.fetch = () => makeFetchResponse(500, {});

            await assert.rejects(
                () => FlashViewClient.fetchConfig(BASE_URL),
                (err) => {
                    assert.ok(err instanceof ApiError);
                    assert.equal(err.status, 500);
                    return true;
                },
            );
        });
    });

    describe('createSecret', () => {
        it('posts to /api/v1/secrets with message and expires_in', async () => {
            globalThis.fetch = (url, opts) => {
                assert.equal(url, `${BASE_URL}/api/v1/secrets`);
                assert.equal(opts.method, 'POST');
                const body = JSON.parse(opts.body);
                assert.equal(body.message, 'encrypted-secret');
                assert.equal(body.expires_in, 1440);
                return makeFetchResponse(201, { data: { url: 'https://flashview.example.com/s/abc' } });
            };

            const response = await client.createSecret('encrypted-secret', 1440);
            assert.equal(response.data.url, 'https://flashview.example.com/s/abc');
        });

        it('includes Authorization header', async () => {
            globalThis.fetch = (url, opts) => {
                assert.equal(opts.headers['Authorization'], `Bearer ${TOKEN}`);
                return makeFetchResponse(201, {});
            };

            await client.createSecret('enc', 60);
        });
    });

    describe('listSecrets', () => {
        it('requests page 1 by default', async () => {
            globalThis.fetch = (url) => {
                assert.ok(url.includes('page=1'));
                return makeFetchResponse(200, { data: [] });
            };

            await client.listSecrets();
        });

        it('requests the specified page', async () => {
            globalThis.fetch = (url) => {
                assert.ok(url.includes('page=3'));
                return makeFetchResponse(200, { data: [] });
            };

            await client.listSecrets(3);
        });
    });

    describe('error handling', () => {
        it('throws ApiError with status and message on HTTP error', async () => {
            globalThis.fetch = () => makeFetchResponse(404, { message: 'Not found' });

            await assert.rejects(
                () => client.getSecretStatus('missing-hash'),
                (err) => {
                    assert.ok(err instanceof ApiError);
                    assert.equal(err.status, 404);
                    assert.equal(err.message, 'Not found');
                    return true;
                },
            );
        });

        it('captures Retry-After header on 429 response', async () => {
            globalThis.fetch = () => makeFetchResponse(
                429,
                { message: 'Too many requests' },
                { 'retry-after': '42' },
            );

            await assert.rejects(
                () => client.createSecret('enc', 60),
                (err) => {
                    assert.ok(err instanceof ApiError);
                    assert.equal(err.status, 429);
                    assert.equal(err.retryAfter, 42);
                    return true;
                },
            );
        });

        it('sets retryAfter to null when Retry-After header is absent', async () => {
            globalThis.fetch = () => makeFetchResponse(500, { message: 'Server error' });

            await assert.rejects(
                () => client.createSecret('enc', 60),
                (err) => {
                    assert.equal(err.retryAfter, null);
                    return true;
                },
            );
        });
    });

    describe('burnSecret', () => {
        it('sends DELETE to /api/v1/secrets/:hashId', async () => {
            globalThis.fetch = (url, opts) => {
                assert.equal(opts.method, 'DELETE');
                assert.ok(url.endsWith('/api/v1/secrets/abc123'));
                return makeFetchResponse(200, {});
            };

            await client.burnSecret('abc123');
        });
    });
});

describe('ApiError', () => {
    it('extends Error with status and errors', () => {
        const err = new ApiError('Something failed', 422, { field: ['invalid'] });

        assert.ok(err instanceof Error);
        assert.ok(err instanceof ApiError);
        assert.equal(err.message, 'Something failed');
        assert.equal(err.status, 422);
        assert.deepEqual(err.errors, { field: ['invalid'] });
        assert.equal(err.retryAfter, null);
    });
});
