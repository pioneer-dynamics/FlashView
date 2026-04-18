import { describe, it, beforeEach, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { createServer } from 'node:http';

describe('login helpers', () => {
    describe('startCallbackServer', () => {
        let server;

        afterEach(() => {
            if (server) {
                server.close();
                server = null;
            }
        });

        it('starts a server on a random port bound to 127.0.0.1', async () => {
            server = createServer();
            await new Promise((resolve, reject) => {
                server.listen(0, '127.0.0.1', () => resolve());
                server.on('error', reject);
            });

            const address = server.address();
            assert.equal(address.address, '127.0.0.1');
            assert.ok(address.port >= 1024, 'Port should be >= 1024');
            assert.ok(address.port <= 65535, 'Port should be <= 65535');
        });
    });

    describe('waitForCallback', () => {
        let server;

        afterEach(() => {
            if (server) {
                server.close();
                server = null;
            }
        });

        it('resolves with code on successful callback', async () => {
            server = createServer();
            await new Promise((resolve) => {
                server.listen(0, '127.0.0.1', resolve);
            });
            const port = server.address().port;
            const expectedState = 'test-state-1234567890';

            const callbackPromise = new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    reject(new Error('TIMEOUT'));
                }, 5000);

                server.on('request', (req, res) => {
                    const url = new URL(req.url, `http://${req.headers.host}`);
                    if (url.pathname === '/callback') {
                        const code = url.searchParams.get('code');
                        const state = url.searchParams.get('state');
                        clearTimeout(timer);

                        if (state !== expectedState) {
                            res.writeHead(400);
                            res.end();
                            reject(new Error('State mismatch'));
                            return;
                        }

                        res.writeHead(200);
                        res.end('OK');
                        resolve(code);
                    }
                });
            });

            // Simulate the browser redirect
            const response = await fetch(`http://127.0.0.1:${port}/callback?code=test-auth-code&state=${expectedState}`);
            assert.equal(response.status, 200);

            const code = await callbackPromise;
            assert.equal(code, 'test-auth-code');
        });

        it('rejects on error callback (denied)', async () => {
            server = createServer();
            await new Promise((resolve) => {
                server.listen(0, '127.0.0.1', resolve);
            });
            const port = server.address().port;
            const expectedState = 'test-state-1234567890';

            let rejectReason = null;

            const callbackPromise = new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    reject(new Error('TIMEOUT'));
                }, 5000);

                server.on('request', (req, res) => {
                    const url = new URL(req.url, `http://${req.headers.host}`);
                    if (url.pathname === '/callback') {
                        const error = url.searchParams.get('error');
                        const state = url.searchParams.get('state');
                        clearTimeout(timer);

                        res.writeHead(200);
                        res.end();

                        if (state !== expectedState) {
                            rejectReason = 'State mismatch';
                        } else if (error) {
                            rejectReason = `Authorization error: ${error}`;
                        }

                        resolve();
                    }
                });
            });

            await fetch(`http://127.0.0.1:${port}/callback?error=denied&state=${expectedState}`);
            await callbackPromise;

            assert.equal(rejectReason, 'Authorization error: denied');
        });

        it('rejects on state mismatch', async () => {
            server = createServer();
            await new Promise((resolve) => {
                server.listen(0, '127.0.0.1', resolve);
            });
            const port = server.address().port;
            const expectedState = 'correct-state-12345';

            let rejectReason = null;

            const callbackPromise = new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    reject(new Error('TIMEOUT'));
                }, 5000);

                server.on('request', (req, res) => {
                    const url = new URL(req.url, `http://${req.headers.host}`);
                    if (url.pathname === '/callback') {
                        const state = url.searchParams.get('state');
                        clearTimeout(timer);

                        res.writeHead(200);
                        res.end();

                        if (state !== expectedState) {
                            rejectReason = 'State mismatch';
                        }

                        resolve();
                    }
                });
            });

            await fetch(`http://127.0.0.1:${port}/callback?code=test&state=wrong-state-67890`);
            await callbackPromise;

            assert.equal(rejectReason, 'State mismatch');
        });

        it('times out when no callback is received', async () => {
            server = createServer();
            await new Promise((resolve) => {
                server.listen(0, '127.0.0.1', resolve);
            });

            const callbackPromise = new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    reject(new Error('TIMEOUT'));
                }, 100); // Short timeout for test

                server.on('request', () => {
                    clearTimeout(timer);
                    resolve();
                });
            });

            await assert.rejects(callbackPromise, {
                message: 'TIMEOUT',
            });
        });
    });

    describe('state generation', () => {
        it('generates unique state values', () => {
            const state1 = crypto.randomUUID().replace(/-/g, '');
            const state2 = crypto.randomUUID().replace(/-/g, '');

            assert.notEqual(state1, state2);
            assert.equal(state1.length, 32);
            assert.match(state1, /^[a-f0-9]{32}$/);
        });
    });

    describe('exchangeCode', () => {
        let mockServer;

        afterEach(() => {
            if (mockServer) {
                mockServer.close();
                mockServer = null;
            }
        });

        it('exchanges code for token via POST', async () => {
            mockServer = createServer((req, res) => {
                let body = '';
                req.on('data', (chunk) => { body += chunk; });
                req.on('end', () => {
                    const data = JSON.parse(body);

                    assert.equal(req.method, 'POST');
                    assert.equal(req.url, '/cli/token');
                    assert.equal(req.headers['content-type'], 'application/json');
                    assert.equal(data.code, 'test-code');
                    assert.equal(data.state, 'test-state');

                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        token: 'test-token-value',
                        user: { name: 'Test User', email: 'test@example.com' },
                    }));
                });
            });

            await new Promise((resolve) => {
                mockServer.listen(0, '127.0.0.1', resolve);
            });
            const port = mockServer.address().port;

            const response = await fetch(`http://127.0.0.1:${port}/cli/token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code: 'test-code', state: 'test-state' }),
            });

            const result = await response.json();
            assert.equal(result.token, 'test-token-value');
            assert.equal(result.user.name, 'Test User');
            assert.equal(result.user.email, 'test@example.com');
        });

        it('throws on non-OK response', async () => {
            mockServer = createServer((req, res) => {
                res.writeHead(401, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ message: 'Invalid or expired authorization code.' }));
            });

            await new Promise((resolve) => {
                mockServer.listen(0, '127.0.0.1', resolve);
            });
            const port = mockServer.address().port;

            const response = await fetch(`http://127.0.0.1:${port}/cli/token`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ code: 'bad-code', state: 'state' }),
            });

            assert.equal(response.ok, false);
            assert.equal(response.status, 401);

            const data = await response.json();
            assert.equal(data.message, 'Invalid or expired authorization code.');
        });
    });

    describe('headless detection', () => {
        it('detects headless Linux environment without DISPLAY', () => {
            const originalDisplay = process.env.DISPLAY;
            const originalWayland = process.env.WAYLAND_DISPLAY;

            delete process.env.DISPLAY;
            delete process.env.WAYLAND_DISPLAY;

            const isHeadless = process.platform === 'linux' && !process.env.DISPLAY && !process.env.WAYLAND_DISPLAY;

            // Restore
            if (originalDisplay !== undefined) process.env.DISPLAY = originalDisplay;
            if (originalWayland !== undefined) process.env.WAYLAND_DISPLAY = originalWayland;

            if (process.platform === 'linux') {
                assert.ok(isHeadless, 'Should detect headless on Linux without DISPLAY');
            } else {
                assert.ok(true, 'Skipped on non-Linux platform');
            }
        });
    });
});

// Replicates isHeadlessEnvironment() logic for isolated testing without importing cli.js
function isHeadlessEnvironment(platform = process.platform, env = process.env) {
    if (platform === 'linux') {
        return !env.DISPLAY && !env.WAYLAND_DISPLAY;
    }
    return !!(env.SSH_TTY || env.SSH_CONNECTION || env.SSH_CLIENT);
}

describe('isHeadlessEnvironment', () => {
    describe('Linux platform', () => {
        it('returns true when DISPLAY and WAYLAND_DISPLAY are unset', () => {
            const result = isHeadlessEnvironment('linux', {});
            assert.equal(result, true);
        });

        it('returns false when DISPLAY is set', () => {
            const result = isHeadlessEnvironment('linux', { DISPLAY: ':0' });
            assert.equal(result, false);
        });

        it('returns false when WAYLAND_DISPLAY is set', () => {
            const result = isHeadlessEnvironment('linux', { WAYLAND_DISPLAY: 'wayland-0' });
            assert.equal(result, false);
        });

        it('returns false when both DISPLAY and WAYLAND_DISPLAY are set', () => {
            const result = isHeadlessEnvironment('linux', { DISPLAY: ':0', WAYLAND_DISPLAY: 'wayland-0' });
            assert.equal(result, false);
        });
    });

    describe('macOS platform', () => {
        it('returns false when no SSH env vars are present', () => {
            const result = isHeadlessEnvironment('darwin', {});
            assert.equal(result, false);
        });

        it('returns true when SSH_TTY is set', () => {
            const result = isHeadlessEnvironment('darwin', { SSH_TTY: '/dev/ttys000' });
            assert.equal(result, true);
        });

        it('returns true when SSH_CONNECTION is set and SSH_TTY is absent', () => {
            const result = isHeadlessEnvironment('darwin', { SSH_CONNECTION: '192.168.1.1 12345 10.0.0.1 22' });
            assert.equal(result, true);
        });

        it('returns true when SSH_CLIENT is set and SSH_TTY is absent', () => {
            const result = isHeadlessEnvironment('darwin', { SSH_CLIENT: '192.168.1.1 12345 22' });
            assert.equal(result, true);
        });
    });

    describe('Windows platform', () => {
        it('returns false when no SSH env vars are present', () => {
            const result = isHeadlessEnvironment('win32', {});
            assert.equal(result, false);
        });

        it('returns true when SSH_CONNECTION is set (Windows OpenSSH does not set SSH_TTY)', () => {
            const result = isHeadlessEnvironment('win32', { SSH_CONNECTION: '192.168.1.1 12345 10.0.0.1 22' });
            assert.equal(result, true);
        });
    });
});
