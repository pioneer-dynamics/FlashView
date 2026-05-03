import { RTCPeerConnection } from 'werift';

const CHUNK_SIZE = 65536; // 64 KB per data channel message
const SIGNAL_POLL_MS = 500;
const STUN_SERVERS = [
    'stun:stun.l.google.com:19302',
    'stun:stun1.l.google.com:19302',
];

function humanBytes(bytes) {
    if (bytes < 1024) { return `${bytes} B`; }
    if (bytes < 1024 * 1024) { return `${(bytes / 1024).toFixed(1)} KB`; }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function renderProgress(current, total, width = 30) {
    const pct = total > 0 ? Math.min(current / total, 1) : 0;
    const filled = Math.round(pct * width);
    const bar = '█'.repeat(filled) + '░'.repeat(width - filled);
    return `  [${bar}] ${(pct * 100).toFixed(1).padStart(5)}% (${humanBytes(current)} / ${humanBytes(total)})`;
}

async function waitForIceGathered(pc) {
    while (pc.iceGatheringState !== 'complete') {
        const [state] = await pc.iceGatheringStateChange.asPromise(6000);
        if (state === 'complete') { break; }
    }
}

/**
 * Attempt to deliver an already-encrypted payload to the peer via P2P WebRTC.
 * Returns true if the full payload was delivered; false to fall back to S3.
 * Falls back only on genuine ICE failure or API error — never on a timer.
 *
 * @param {import('./api.js').FlashViewClient} client
 * @param {string} sessionId
 * @param {Uint8Array} encryptedPayload
 * @param {{ verbose?: boolean }} [options]
 * @returns {Promise<boolean>}
 */
export async function trySendP2P(client, sessionId, encryptedPayload, options = {}) {
    const { verbose = false } = options;

    return new Promise((resolve) => {
        let settled = false;
        let pc;

        const finish = (result) => {
            if (settled) { return; }
            settled = true;
            if (!result) {
                // Failure — tear down immediately.
                // On success we let dc.close() drive the SCTP shutdown; pc is
                // cleaned up when dc.onclose fires after the handshake completes.
                try { pc?.close(); } catch { /* ignore */ }
            }
            resolve(result);
        };

        (async () => {
            try {
                pc = new RTCPeerConnection({
                    iceServers: STUN_SERVERS.map(urls => ({ urls })),
                });

                // Fall back to S3 only on genuine ICE failure, not a timer.
                pc.onconnectionstatechange = () => {
                    if (pc.connectionState === 'failed') { finish(false); }
                };

                const dc = pc.createDataChannel('payload', { ordered: true });

                dc.onclose = () => {
                    if (!settled) {
                        // Premature close before we resolved — treat as failure.
                        finish(false);
                    } else {
                        // SCTP shutdown complete after successful send — clean up pc.
                        try { pc?.close(); } catch { /* ignore */ }
                    }
                };

                dc.onopen = async () => {
                    try {
                        if (verbose) {
                            process.stderr.write(`  Uploading via p2p_webrtc... [${humanBytes(encryptedPayload.length)}]\n`);
                        }

                        // 8-byte big-endian size header
                        const header = new Uint8Array(8);
                        new DataView(header.buffer).setBigUint64(0, BigInt(encryptedPayload.length), false);
                        dc.send(header);

                        let offset = 0;
                        let lastRender = 0;
                        while (offset < encryptedPayload.length) {
                            if (settled) { return; }
                            while (dc.bufferedAmount > CHUNK_SIZE * 8) {
                                await new Promise(r => setTimeout(r, 10));
                                if (settled) { return; }
                            }
                            dc.send(encryptedPayload.subarray(offset, offset + CHUNK_SIZE));
                            offset += CHUNK_SIZE;
                            if (verbose) {
                                const now = Date.now();
                                const progress = Math.min(offset, encryptedPayload.length);
                                if (now - lastRender >= 80 || progress === encryptedPayload.length) {
                                    lastRender = now;
                                    process.stderr.write(`\r${renderProgress(progress, encryptedPayload.length)}`);
                                }
                            }
                        }

                        if (verbose) { process.stderr.write('\n'); }

                        // Drain the local WebRTC send buffer. Once empty, all bytes have
                        // been handed to the OS network stack; SCTP reliability guarantees
                        // they will reach the receiver. Resolve now rather than waiting for
                        // the SCTP shutdown ACK to avoid a race where the receiver closes
                        // its side first, causing dc.onclose to fire prematurely.
                        while (dc.bufferedAmount > 0) {
                            if (settled) { return; }
                            await new Promise(r => setTimeout(r, 10));
                        }

                        finish(true); // All data in OS stack — success.
                        dc.close();   // Initiate graceful SCTP shutdown (fire-and-forget).
                    } catch {
                        if (verbose) { process.stderr.write('\n'); }
                        finish(false);
                    }
                };

                await pc.setLocalDescription(await pc.createOffer());
                await waitForIceGathered(pc);

                const { type, sdp } = pc.localDescription;
                await client.sendSignal(sessionId, 'sender', 'offer', { type, sdp });

                // Poll for the receiver's answer — no timeout; ICE failure triggers finish(false)
                let lastId = 0;
                while (!settled) {
                    const { signals } = await client.pollSignals(sessionId, 'receiver', lastId);
                    for (const sig of signals) {
                        lastId = Math.max(lastId, sig.id);
                        if (sig.type === 'answer') {
                            await pc.setRemoteDescription({ type: 'answer', sdp: sig.payload.sdp });
                        } else if (sig.type === 'ice-candidate' && sig.payload?.candidate) {
                            await pc.addIceCandidate(sig.payload).catch(() => {});
                        }
                    }
                    if (!settled) {
                        await new Promise(r => setTimeout(r, SIGNAL_POLL_MS));
                    }
                }
            } catch {
                finish(false);
            }
        })();
    });
}

/**
 * Attempt to receive an encrypted payload from the peer via P2P WebRTC.
 * Returns the received Uint8Array if successful, null to fall back to S3.
 * Falls back only on genuine ICE failure or API error — never on a timer.
 *
 * @param {import('./api.js').FlashViewClient} client
 * @param {string} sessionId
 * @param {{ verbose?: boolean }} [options]
 * @returns {Promise<Uint8Array|null>}
 */
export async function tryReceiveP2P(client, sessionId, options = {}) {
    const { verbose = false } = options;

    // Poll for the sender's offer — no deadline; falls back only on API error
    // (e.g. session expired), meaning the sender must have used the S3 relay.
    let offerSignal = null;
    let lastId = 0;
    while (!offerSignal) {
        let result;
        try {
            result = await client.pollSignals(sessionId, 'sender', lastId);
        } catch {
            return null; // Session gone or network error — fall back to S3
        }
        for (const sig of result.signals) {
            lastId = Math.max(lastId, sig.id);
            if (sig.type === 'offer') { offerSignal = sig; break; }
        }
        if (!offerSignal) {
            await new Promise(r => setTimeout(r, SIGNAL_POLL_MS));
        }
    }

    return new Promise((resolve) => {
        let settled = false;
        let pc;

        const finish = (result) => {
            if (settled) { return; }
            settled = true;
            if (result === null) {
                // Failure — tear down immediately.
                // On success we deliberately leave pc open so the sender can
                // complete its SCTP shutdown; pc is closed by channel.onclose.
                try { pc?.close(); } catch { /* ignore */ }
            }
            resolve(result);
        };

        (async () => {
            try {
                pc = new RTCPeerConnection({
                    iceServers: STUN_SERVERS.map(urls => ({ urls })),
                });

                // Fall back to S3 only on genuine ICE failure, not a timer.
                pc.onconnectionstatechange = () => {
                    if (pc.connectionState === 'failed') { finish(null); }
                };

                const chunks = [];
                let expectedSize = null;
                let receivedBytes = 0;
                let lastRender = 0;

                pc.ondatachannel = ({ channel }) => {
                    channel.onmessage = ({ data }) => {
                        const buf = data instanceof Uint8Array
                            ? data
                            : ArrayBuffer.isView(data)
                                ? new Uint8Array(data.buffer, data.byteOffset, data.byteLength)
                                : new Uint8Array(data);

                        if (expectedSize === null) {
                            expectedSize = Number(
                                new DataView(buf.buffer, buf.byteOffset, 8).getBigUint64(0, false),
                            );
                            if (verbose) {
                                process.stderr.write(
                                    `  Downloading via p2p_webrtc... [${humanBytes(expectedSize)}]\n`,
                                );
                            }
                            return;
                        }

                        chunks.push(buf);
                        receivedBytes += buf.length;

                        if (verbose) {
                            const now = Date.now();
                            if (now - lastRender >= 80 || receivedBytes >= expectedSize) {
                                lastRender = now;
                                process.stderr.write(`\r${renderProgress(receivedBytes, expectedSize)}`);
                            }
                        }

                        if (receivedBytes >= expectedSize) {
                            if (verbose) { process.stderr.write('\n'); }
                            const out = new Uint8Array(expectedSize);
                            let off = 0;
                            for (const c of chunks) { out.set(c, off); off += c.length; }
                            // Resolve with data but keep pc open — the sender will initiate
                            // SCTP shutdown; channel.onclose handles final pc cleanup.
                            finish(out);
                        }
                    };

                    channel.onclose = () => {
                        if (settled) {
                            // Sender completed SCTP shutdown after successful transfer — clean up.
                            try { pc?.close(); } catch { /* ignore */ }
                        } else {
                            // Premature close before all data arrived.
                            finish(null);
                        }
                    };
                };

                await pc.setRemoteDescription({
                    type: offerSignal.payload.type,
                    sdp: offerSignal.payload.sdp,
                });
                await pc.setLocalDescription(await pc.createAnswer());
                await waitForIceGathered(pc);

                const { type, sdp } = pc.localDescription;
                await client.sendSignal(sessionId, 'receiver', 'answer', { type, sdp });
            } catch {
                finish(null);
            }
        })();
    });
}
