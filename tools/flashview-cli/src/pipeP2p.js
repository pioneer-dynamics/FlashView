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

async function waitForIceGathered(pc) {
    while (pc.iceGatheringState !== 'complete') {
        const [state] = await pc.iceGatheringStateChange.asPromise(6000);
        if (state === 'complete') { break; }
    }
}

/**
 * Attempt to deliver an already-encrypted payload to the peer via P2P WebRTC.
 * Returns true if the full payload was delivered; false to fall back to S3.
 *
 * @param {import('./api.js').FlashViewClient} client
 * @param {string} sessionId
 * @param {Uint8Array} encryptedPayload
 * @param {{ verbose?: boolean, timeoutMs?: number }} [options]
 * @returns {Promise<boolean>}
 */
export async function trySendP2P(client, sessionId, encryptedPayload, options = {}) {
    const { verbose = false, timeoutMs = 10_000 } = options;

    return new Promise((resolve) => {
        let settled = false;
        let pc;

        const finish = (result) => {
            if (settled) { return; }
            settled = true;
            clearTimeout(timer);
            try { pc?.close(); } catch { /* ignore */ }
            resolve(result);
        };

        const timer = setTimeout(() => finish(false), timeoutMs);

        (async () => {
            try {
                pc = new RTCPeerConnection({
                    iceServers: STUN_SERVERS.map(urls => ({ urls })),
                });

                const dc = pc.createDataChannel('payload', { ordered: true });

                dc.onopen = async () => {
                    // P2P connection established — cancel the negotiation timeout so large
                    // payloads are not abandoned mid-transfer.
                    clearTimeout(timer);
                    try {
                        if (verbose) {
                            process.stderr.write(`  Uploading via p2p_webrtc... [${humanBytes(encryptedPayload.length)}]\n`);
                        }

                        // 8-byte big-endian size header
                        const header = new Uint8Array(8);
                        new DataView(header.buffer).setBigUint64(0, BigInt(encryptedPayload.length), false);
                        dc.send(header);

                        let offset = 0;
                        while (offset < encryptedPayload.length) {
                            if (settled) { return; }
                            while (dc.bufferedAmount > CHUNK_SIZE * 8) {
                                await new Promise(r => setTimeout(r, 10));
                                if (settled) { return; }
                            }
                            dc.send(encryptedPayload.subarray(offset, offset + CHUNK_SIZE));
                            offset += CHUNK_SIZE;
                        }

                        dc.close();
                        finish(true);
                    } catch {
                        finish(false);
                    }
                };

                dc.onclose = () => { if (!settled) { finish(false); } };

                await pc.setLocalDescription(await pc.createOffer());
                await waitForIceGathered(pc);

                const { type, sdp } = pc.localDescription;
                await client.sendSignal(sessionId, 'sender', 'offer', { type, sdp });

                // Poll for the receiver's answer
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
 *
 * @param {import('./api.js').FlashViewClient} client
 * @param {string} sessionId
 * @param {{ verbose?: boolean, timeoutMs?: number }} [options]
 * @returns {Promise<Uint8Array|null>}
 */
export async function tryReceiveP2P(client, sessionId, options = {}) {
    const { verbose = false, timeoutMs = 10_000 } = options;

    const deadline = Date.now() + timeoutMs;

    // Poll for the sender's offer — sender may not have posted it yet
    let offerSignal = null;
    let lastId = 0;
    while (!offerSignal && Date.now() < deadline) {
        const { signals } = await client.pollSignals(sessionId, 'sender', lastId);
        for (const sig of signals) {
            lastId = Math.max(lastId, sig.id);
            if (sig.type === 'offer') { offerSignal = sig; break; }
        }
        if (!offerSignal) {
            await new Promise(r => setTimeout(r, SIGNAL_POLL_MS));
        }
    }

    if (!offerSignal) { return null; }

    const remaining = deadline - Date.now();
    if (remaining <= 0) { return null; }

    return new Promise((resolve) => {
        let settled = false;
        let pc;

        const finish = (result) => {
            if (settled) { return; }
            settled = true;
            clearTimeout(timer);
            try { pc?.close(); } catch { /* ignore */ }
            resolve(result);
        };

        const timer = setTimeout(() => finish(null), remaining);

        (async () => {
            try {
                pc = new RTCPeerConnection({
                    iceServers: STUN_SERVERS.map(urls => ({ urls })),
                });

                const chunks = [];
                let expectedSize = null;
                let receivedBytes = 0;

                pc.ondatachannel = ({ channel }) => {
                    // P2P connection established — cancel the negotiation timeout.
                    clearTimeout(timer);
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

                        if (receivedBytes >= expectedSize) {
                            const out = new Uint8Array(expectedSize);
                            let off = 0;
                            for (const c of chunks) { out.set(c, off); off += c.length; }
                            finish(out);
                        }
                    };

                    channel.onclose = () => {
                        if (!settled && receivedBytes < (expectedSize ?? Infinity)) {
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
