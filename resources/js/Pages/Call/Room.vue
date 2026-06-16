<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { encryption } from '@/encryption.js';

const props = defineProps({
    session: {
        type: Object,
        required: true,
        // { bridge_number, ends_at }
    },
});

const storageKey = `call_session:${props.session.bridge_number}`;
const raw = sessionStorage.getItem(storageKey);
const sessionData = raw ? JSON.parse(raw) : null;

// Reactive state
const localStream = ref(null);
const peers = ref({});         // { [participantId]: { pc, connectionFailed } }
const isMuted = ref(false);
const mediaError = ref(null);  // null | 'denied' | 'unavailable'
const timeRemaining = ref(null);
const callEnded = ref(false);
const redirectCountdown = ref(5);
const signalCursor = ref(null);
const confirmingLeave = ref(false);
const participantCount = ref(1);
const showTurnWarning = ref(sessionData?.turn_warning === true);

// Hidden <audio> elements for remote participants — not reactive, managed directly
const remoteAudioElements = {};

// Timer handles
const participantPollTimer = ref(null);
const signalPollTimer = ref(null);
const expiryTimer = ref(null);
const redirectTimer = ref(null);

// ICE candidate queue — plain object, not reactive
const pendingCandidates = {};

// Guard against duplicate leave calls (cleanup() fires from both confirmLeave and onUnmounted)
let hasCalledLeave = false;

// Participant ECDH public key cache — populated during participant polling
const participantPublicKeys = {};

// Group AES key — in memory only, never persisted
let groupAesKey = null;

// API URLs — template literals; API routes are not in Ziggy's manifest
const PARTICIPANTS_URL = `/api/v1/calls/${props.session.bridge_number}/participants`;
const SIGNAL_STORE_URL = `/api/v1/calls/${props.session.bridge_number}/signal`;

function signalPollUrl(cursor) {
    let url = `/api/v1/calls/${props.session.bridge_number}/signal?participant_id=${sessionData.participant_id}`;
    if (cursor) {
        url += `&after=${cursor}`;
    }
    return url;
}

function formatTimeRemaining(seconds) {
    if (seconds === null) return '';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

async function sendSignal(toParticipantId, type, payload) {
    await axios.post(SIGNAL_STORE_URL, {
        from_participant_id: sessionData.participant_id,
        to_participant_id:   toParticipantId,
        type,
        payload,
    });
}

async function handleIceCandidate(fromId, payload) {
    const pc = peers.value[fromId]?.pc;
    if (!pc) return;
    if (!pc.remoteDescription) {
        pendingCandidates[fromId] = pendingCandidates[fromId] ?? [];
        pendingCandidates[fromId].push(payload.candidate);
    } else {
        await pc.addIceCandidate(new RTCIceCandidate(payload.candidate));
    }
}

async function drainPendingCandidates(fromId, pc) {
    for (const c of pendingCandidates[fromId] ?? []) {
        await pc.addIceCandidate(new RTCIceCandidate(c));
    }
    delete pendingCandidates[fromId];
}

function createPeerConnection(peerId) {
    const pc = new RTCPeerConnection({ iceServers: sessionData.ice_servers });
    localStream.value.getTracks().forEach(track => pc.addTrack(track, localStream.value));
    pc.onicecandidate = ({ candidate }) => {
        if (candidate) {
            sendSignal(peerId, 'ice-candidate', { candidate });
        }
    };
    pc.ontrack = ({ streams }) => {
        if (streams[0]) {
            // Play remote audio via a hidden <audio> element (audio-only call)
            if (!remoteAudioElements[peerId]) {
                const audio = document.createElement('audio');
                audio.autoplay = true;
                audio.style.display = 'none';
                document.body.appendChild(audio);
                remoteAudioElements[peerId] = audio;
            }
            remoteAudioElements[peerId].srcObject = streams[0];
            // Autoplay may be blocked after Inertia client-side navigation; call play() explicitly
            remoteAudioElements[peerId].play().catch(() => {});
        }
    };
    pc.onconnectionstatechange = () => {
        if (pc.connectionState === 'failed') {
            // Spread to a new object so Vue 3 detects the change
            peers.value = { ...peers.value, [peerId]: { ...peers.value[peerId], connectionFailed: true } };
        }
    };
    // Spread to a new object so Vue 3 detects the key addition
    peers.value = { ...peers.value, [peerId]: { pc, connectionFailed: false } };
    return pc;
}

async function processSignal(signal) {
    const { from_participant_id: fromId, type, payload } = signal;

    if (type === 'key-exchange') {
        // sender_public_key is included in the payload by the organiser;
        // fall back to participant cache for backwards compatibility
        const senderPublicKey = payload.sender_public_key ?? participantPublicKeys[fromId];
        if (!senderPublicKey) return;
        const enc = new encryption();
        groupAesKey = await enc.unwrapCallSessionKey(
            payload.wrapped_key,
            sessionData.ecdh_private_key,
            senderPublicKey
        );
        return;
    }

    if (type === 'offer') {
        const pc = peers.value[fromId]?.pc ?? createPeerConnection(fromId);
        await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp));
        await drainPendingCandidates(fromId, pc);
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await sendSignal(fromId, 'answer', { sdp: answer });
        return;
    }

    if (type === 'answer') {
        const pc = peers.value[fromId]?.pc;
        if (pc) {
            await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp));
            await drainPendingCandidates(fromId, pc);
        }
        return;
    }

    if (type === 'ice-candidate') {
        await handleIceCandidate(fromId, payload);
    }
}

async function pollParticipants() {
    try {
        const { data } = await axios.get(PARTICIPANTS_URL);
        participantCount.value = data.participants.length;
        for (const p of data.participants) {
            if (p.public_key) {
                participantPublicKeys[p.id] = p.public_key;
            }

            if (p.id === sessionData.participant_id) continue;
            if (peers.value[p.id]) continue;

            // Both IDs are UUID strings from call_participants; string < produces a deterministic total order
            if (sessionData.participant_id < p.id) {
                // We are the offerer (lower UUID) — also the key organiser
                if (!groupAesKey) {
                    const enc = new encryption();
                    groupAesKey = enc.generateCallSessionAesKey(); // synchronous
                }
                const pc = createPeerConnection(p.id);
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await sendSignal(p.id, 'offer', { sdp: offer });

                if (p.public_key) {
                    const enc = new encryption();
                    const wrappedKey = await enc.wrapCallSessionKey(
                        groupAesKey,
                        p.public_key,
                        sessionData.ecdh_private_key
                    );
                    // Include sender_public_key so the receiver can unwrap immediately
                    // without waiting for a pollParticipants cycle to populate participantPublicKeys
                    await sendSignal(p.id, 'key-exchange', {
                        wrapped_key:       wrappedKey,
                        sender_public_key: sessionData.ecdh_public_key,
                    });
                }
            } else {
                // We are the answerer — create RTCPeerConnection and wait for their offer
                createPeerConnection(p.id);
            }
        }
    } catch (_) { /* swallow poll errors */ }
    participantPollTimer.value = setTimeout(pollParticipants, 3000);
}

async function pollSignals() {
    try {
        const { data } = await axios.get(signalPollUrl(signalCursor.value));
        for (const signal of data.signals) {
            await processSignal(signal);
        }
        if (data.signals.length > 0) {
            signalCursor.value = data.signals.at(-1).id;
        }
    } catch (_) { /* swallow poll errors */ }
    signalPollTimer.value = setTimeout(pollSignals, 1500);
}

function startPolling() {
    pollParticipants();
    pollSignals();
}

function startExpiryCountdown() {
    const endsAtMs = new Date(props.session.ends_at).getTime();
    expiryTimer.value = setInterval(() => {
        const remaining = Math.max(0, Math.floor((endsAtMs - Date.now()) / 1000));
        timeRemaining.value = remaining;
        if (remaining === 0) {
            callEnded.value = true;
            clearInterval(expiryTimer.value);
            redirectCountdown.value = 5;
            redirectTimer.value = setInterval(() => {
                redirectCountdown.value--;
                if (redirectCountdown.value <= 0) {
                    clearInterval(redirectTimer.value);
                    cleanup();
                    router.visit(route('calls.index'));
                }
            }, 1000);
        }
    }, 1000);
}

function leaveCall() {
    if (!sessionData?.participant_id || hasCalledLeave) return;
    hasCalledLeave = true;
    fetch(`/api/v1/calls/${props.session.bridge_number}/leave`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ participant_id: sessionData.participant_id }),
        keepalive: true,
    }).catch(() => {});
}

function cleanup() {
    leaveCall();
    clearTimeout(participantPollTimer.value);
    clearTimeout(signalPollTimer.value);
    clearInterval(expiryTimer.value);
    clearInterval(redirectTimer.value);
    Object.values(peers.value).forEach(({ pc }) => pc.close());
    localStream.value?.getTracks().forEach(t => t.stop());
    Object.values(remoteAudioElements).forEach(a => {
        a.srcObject = null;
        a.remove();
    });
    sessionStorage.removeItem(storageKey);
}

function toggleMute() {
    isMuted.value = !isMuted.value;
    localStream.value?.getAudioTracks().forEach(t => { t.enabled = !isMuted.value; });
}

function requestLeave()  { confirmingLeave.value = true; }
function cancelLeave()   { confirmingLeave.value = false; }
function confirmLeave()  { cleanup(); router.visit(route('calls.index')); }

onMounted(async () => {
    if (!sessionData) {
        router.visit(route('calls.join', props.session.bridge_number));
        return;
    }

    try {
        localStream.value = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
        mediaError.value = e.name === 'NotFoundError' ? 'unavailable' : 'denied';
        return;
    }

    startPolling();
    startExpiryCountdown();
});

onUnmounted(cleanup);
</script>

<template>
    <AppLayout title="Secure Line — Call">
        <div class="dark min-h-screen bg-gray-950">

            <!-- Microphone permission error screen -->
            <div v-if="mediaError" class="flex items-center justify-center min-h-screen px-4">
                <div class="max-w-md w-full bg-gray-900 border border-red-700/50 rounded-xl p-8 space-y-4 text-center">
                    <div class="text-red-400 font-mono text-xs uppercase tracking-widest mb-2">Permission Required</div>
                    <h2 class="text-white text-xl font-bold">Microphone Access Needed</h2>
                    <p v-if="mediaError === 'unavailable'" class="text-gray-400 text-sm">
                        No microphone was found on this device. Please connect a microphone and reload.
                    </p>
                    <p v-else class="text-gray-400 text-sm">
                        Microphone access was denied. To join the call, allow access in your browser settings and reload this page.
                    </p>
                    <p class="text-gray-500 text-xs">
                        In Chrome: click the lock icon in the address bar → Site settings → Allow Microphone.<br>
                        In Firefox: click the microphone icon in the address bar → Remove block.
                    </p>
                    <button
                        @click="router.visit(route('calls.join', session.bridge_number))"
                        class="w-full border border-gray-700 text-gray-300 hover:text-white py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm"
                    >
                        ← Back to join page
                    </button>
                </div>
            </div>

            <!-- Call ended overlay -->
            <div v-else-if="callEnded" class="fixed inset-0 bg-gray-950/95 flex items-center justify-center z-50">
                <div class="text-center space-y-4">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Call Ended</div>
                    <h2 class="text-white text-2xl font-bold">This call has ended</h2>
                    <p class="text-gray-400 text-sm">Returning to home in {{ redirectCountdown }}…</p>
                </div>
            </div>

            <!-- Main call UI -->
            <div v-else-if="sessionData" class="flex flex-col min-h-screen">

                <!-- TURN warning banner -->
                <div v-if="showTurnWarning" class="bg-amber-900/30 border-b border-amber-700/40 px-4 py-2 flex items-center justify-between gap-4">
                    <span class="text-amber-300 text-sm">Your network may limit call quality. If you experience issues, try switching to a different connection.</span>
                    <button @click="showTurnWarning = false" class="text-amber-400 hover:text-amber-200 text-xs font-mono shrink-0">Dismiss</button>
                </div>

                <!-- Audio call area — pb-24 clears the fixed controls bar -->
                <div class="flex-1 flex flex-col items-center justify-center gap-10 pb-24 px-8">

                    <!-- Waiting state — shown until a second participant joins -->
                    <div v-if="participantCount <= 1" class="text-center space-y-4">
                        <div class="w-24 h-24 rounded-full bg-gray-900 border-2 border-gray-700 flex items-center justify-center mx-auto">
                            <svg class="w-10 h-10 text-gray-600 animate-pulse" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/>
                            </svg>
                        </div>
                        <p class="text-gray-500 font-mono text-sm">Waiting for others to join…</p>
                    </div>

                    <!-- Active call — participant circles, one per person -->
                    <div v-else class="flex flex-wrap gap-8 justify-center">
                        <!-- You -->
                        <div class="flex flex-col items-center gap-2">
                            <div
                                class="w-20 h-20 rounded-full bg-gray-800 border-2 flex items-center justify-center transition-colors"
                                :class="isMuted ? 'border-gray-700' : 'border-gamboge-300 shadow-neon-cyan-sm'"
                            >
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-mono text-gamboge-300">You{{ isMuted ? ' (muted)' : '' }}</span>
                        </div>

                        <!-- Remote participants — one circle per additional participant -->
                        <div
                            v-for="i in (participantCount - 1)"
                            :key="i"
                            class="flex flex-col items-center gap-2"
                        >
                            <div class="w-20 h-20 rounded-full bg-gray-800 border-2 border-gray-600 flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-mono text-gray-400">Participant {{ i }}</span>
                        </div>
                    </div>

                    <!-- Bridge number display -->
                    <div class="text-center">
                        <p class="text-xs uppercase tracking-widest text-gray-600 font-mono mb-1">Bridge</p>
                        <p class="font-mono text-gamboge-300 text-lg tracking-widest">{{ session.bridge_number }}</p>
                    </div>

                </div>

                <!-- Controls bar — fixed to viewport bottom, above any page footer -->
                <div class="fixed bottom-0 left-0 right-0 z-10 bg-gray-900 border-t border-gray-800 px-4 py-4">
                    <div class="max-w-lg mx-auto flex items-center justify-between gap-4">

                        <!-- Left — participant count + time -->
                        <div class="text-xs font-mono text-gray-400 space-y-0.5">
                            <div>{{ participantCount }} participant{{ participantCount !== 1 ? 's' : '' }}</div>
                            <div
                                v-if="timeRemaining !== null"
                                :class="timeRemaining < 60 ? 'text-red-400 animate-pulse' : 'text-gray-500'"
                            >
                                {{ formatTimeRemaining(timeRemaining) }} remaining
                            </div>
                        </div>

                        <!-- Centre — mute only (audio-only call, no camera button) -->
                        <div class="flex gap-3">
                            <button
                                @click="toggleMute"
                                :title="isMuted ? 'Unmute' : 'Mute'"
                                :class="isMuted ? 'bg-red-700 hover:bg-red-600 text-white' : 'bg-gray-700 hover:bg-gray-600 text-gray-200'"
                                class="p-3 rounded-full transition-colors shadow-neon-cyan-sm"
                            >
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path v-if="!isMuted" stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19 19L5 5m5.5 4.5A4 4 0 0 0 12 12v3m-4 2.5c.8.8 2 1.5 4 1.5s3.2-.7 4-1.5m.5-8.5a4 4 0 0 0-8 0v4"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Right — leave -->
                        <div class="text-right">
                            <div v-if="confirmingLeave" class="flex gap-2 items-center">
                                <span class="text-xs text-gray-400 font-mono">Leave call?</span>
                                <button @click="cancelLeave" class="text-xs text-gray-400 hover:text-white font-mono border border-gray-700 px-2 py-1 rounded transition-colors shadow-neon-cyan-sm">Stay</button>
                                <button @click="confirmLeave" class="text-xs text-red-400 hover:text-red-300 font-mono border border-red-700 px-2 py-1 rounded transition-colors shadow-neon-cyan-sm">Leave call</button>
                            </div>
                            <button
                                v-else
                                @click="requestLeave"
                                class="bg-red-800 hover:bg-red-700 text-white font-mono text-sm px-4 py-2 rounded-lg transition-colors shadow-neon-cyan-sm"
                            >
                                Leave
                            </button>
                        </div>

                    </div>
                </div>

            </div>

        </div>
    </AppLayout>
</template>
