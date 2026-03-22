import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { FlashViewClient, ApiError } from '../src/api.js';

describe('FlashViewClient', () => {
    it('constructs with correct base URL stripping trailing slash', () => {
        const client = new FlashViewClient('https://example.com/', 'test-token');
        assert.equal(client.baseUrl, 'https://example.com');
    });

    it('constructs without trailing slash', () => {
        const client = new FlashViewClient('https://example.com', 'test-token');
        assert.equal(client.baseUrl, 'https://example.com');
    });

    it('accepts custom timeout', () => {
        const client = new FlashViewClient('https://example.com', 'test-token', 5000);
        assert.equal(client.timeout, 5000);
    });

    it('uses default timeout of 30 seconds', () => {
        const client = new FlashViewClient('https://example.com', 'test-token');
        assert.equal(client.timeout, 30000);
    });
});

describe('ApiError', () => {
    it('stores status and errors', () => {
        const error = new ApiError('Not found', 404, { id: ['Invalid'] });
        assert.equal(error.message, 'Not found');
        assert.equal(error.status, 404);
        assert.deepEqual(error.errors, { id: ['Invalid'] });
    });

    it('defaults errors to null', () => {
        const error = new ApiError('Timeout', 0);
        assert.equal(error.errors, null);
    });

    it('is an instance of Error', () => {
        const error = new ApiError('Test', 500);
        assert.ok(error instanceof Error);
    });
});
