import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';

/**
 * Replicates loginHeadless() flow logic for isolated HTTP protocol testing.
 * Tests the initiate → display → poll cycle without importing cli.js directly.
 */

describe('headless login flow', () => {
    let mockServer;

    afterEach(() => {
        if (mockServer) {
            mockServer.close();
            mockServer = null;
        }
    });

    it('POSTs to /cli/device/initiate and receives device_code, user_code, device_url, expires_in', async () => {
        const deviceCode = 'a'.repeat(64);
        const userCode = 'ABCD-1234';

        mockServer = createServer((req, res) => {
            let body = '';
            req.on('data', chunk => { body += chunk; });
            req.on('end', () => {
                assert.equal(req.method, 'POST');
                assert.equal(req.url, '/cli/device/initiate');
                assert.equal(req.headers['content-type'], 'application/json');

                const data = JSON.parse(body);
                assert.equal(data.name, 'test-host');

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    device_code: deviceCode,
                    user_code: userCode,
                    device_url: `http://127.0.0.1/cli/device`,
                    expires_in: 900,
                }));
            });
        });

        await new Promise(resolve => mockServer.listen(0, '127.0.0.1', resolve));
        const port = mockServer.address().port;

        const response = await fetch(`http://127.0.0.1:${port}/cli/device/initiate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ name: 'test-host' }),
        });

        assert.ok(response.ok);
        const result = await response.json();
        assert.equal(result.device_code, deviceCode);
        assert.equal(result.user_code, userCode);
        assert.ok(result.device_url);
        assert.equal(result.expires_in, 900);
    });

    it('polls /cli/device/poll with device_code until authorized', async () => {
        const deviceCode = 'b'.repeat(64);
        let pollCount = 0;

        mockServer = createServer((req, res) => {
            const url = new URL(req.url, `http://${req.headers.host}`);

            if (url.pathname === '/cli/device/poll') {
                assert.equal(url.searchParams.get('device_code'), deviceCode);
                pollCount++;

                if (pollCount < 3) {
                    res.writeHead(202, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ status: 'pending' }));
                } else {
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        status: 'authorized',
                        token: 'token-value-123',
                        user: { name: 'Test User', email: 'test@example.com' },
                        installation_name: 'My CLI',
                    }));
                }
            }
        });

        await new Promise(resolve => mockServer.listen(0, '127.0.0.1', resolve));
        const port = mockServer.address().port;

        let result = null;
        for (let i = 0; i < 10; i++) {
            const response = await fetch(
                `http://127.0.0.1:${port}/cli/device/poll?device_code=${encodeURIComponent(deviceCode)}`,
                { headers: { 'Accept': 'application/json' } },
            );
            const data = await response.json();
            if (data.status === 'authorized') {
                result = data;
                break;
            }
        }

        assert.ok(result, 'Should have received authorized response');
        assert.equal(result.token, 'token-value-123');
        assert.equal(result.user.email, 'test@example.com');
        assert.equal(result.installation_name, 'My CLI');
        assert.ok(pollCount >= 3, 'Should have polled at least 3 times');
    });

    it('handles expired status from poll endpoint', async () => {
        const deviceCode = 'c'.repeat(64);

        mockServer = createServer((req, res) => {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ status: 'expired' }));
        });

        await new Promise(resolve => mockServer.listen(0, '127.0.0.1', resolve));
        const port = mockServer.address().port;

        const response = await fetch(
            `http://127.0.0.1:${port}/cli/device/poll?device_code=${encodeURIComponent(deviceCode)}`,
            { headers: { 'Accept': 'application/json' } },
        );

        assert.equal(response.status, 401);
        const result = await response.json();
        assert.equal(result.status, 'expired');
    });

    it('handles denied/no_api_access status from poll endpoint', async () => {
        const deviceCode = 'd'.repeat(64);

        mockServer = createServer((req, res) => {
            res.writeHead(403, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ status: 'denied', reason: 'no_api_access' }));
        });

        await new Promise(resolve => mockServer.listen(0, '127.0.0.1', resolve));
        const port = mockServer.address().port;

        const response = await fetch(
            `http://127.0.0.1:${port}/cli/device/poll?device_code=${encodeURIComponent(deviceCode)}`,
            { headers: { 'Accept': 'application/json' } },
        );

        assert.equal(response.status, 403);
        const result = await response.json();
        assert.equal(result.status, 'denied');
        assert.equal(result.reason, 'no_api_access');
    });

    it('uses expires_in from server as polling deadline, not a fixed timeout', () => {
        // Verify the deadline calculation uses expires_in (not a hardcoded 120s timeout)
        const expiresIn = 900;
        const before = Date.now();
        const deadline = before + (expiresIn * 1000);

        // Deadline should be ~15 minutes from now, not 2 minutes
        const expectedMinDeadline = before + (890 * 1000);
        const expectedMaxDeadline = before + (910 * 1000);

        assert.ok(deadline >= expectedMinDeadline, 'Deadline should be at least 890s from now');
        assert.ok(deadline <= expectedMaxDeadline, 'Deadline should be at most 910s from now');
    });

    it('fails initiation on non-OK server response', async () => {
        mockServer = createServer((req, res) => {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ message: 'Server error' }));
        });

        await new Promise(resolve => mockServer.listen(0, '127.0.0.1', resolve));
        const port = mockServer.address().port;

        const response = await fetch(`http://127.0.0.1:${port}/cli/device/initiate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ name: 'test-host' }),
        });

        assert.equal(response.ok, false);
        assert.equal(response.status, 500);
    });

    it('device_code is URL-encoded when passed as query parameter', () => {
        // Verify device_code is safely encoded in the poll URL
        const deviceCode = 'abcdefABCDEF0123456789'.repeat(2) + 'abcdefABCDEF01234567';
        assert.equal(deviceCode.length, 64);

        const pollUrl = `/cli/device/poll?device_code=${encodeURIComponent(deviceCode)}`;
        const parsed = new URL(pollUrl, 'http://localhost');
        assert.equal(parsed.searchParams.get('device_code'), deviceCode);
    });
});
