<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/Alert.vue';
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
if (!raw) {
    router.visit(route('calls.join', props.session.bridge_number));
}
const sessionData = raw ? JSON.parse(raw) : null;

// Reactive state
const localStream = ref(null);
const peers = ref({});         // { [participantId]: { pc, stream, connectionFailed } }
const isMuted = ref(false);
const isCameraOff = ref(false);
const mediaError = ref(null);  // null | 'denied' | 'unavailable'
const timeRemaining = ref(null);
const callEnded = ref(false);
const redirectCountdown = ref(5);
const signalCursor = ref(null);
const confirmingLeave = ref(false);
const participantCount = ref(1);
const showTurnWarning = ref(sessionData?.turn_warning === true);

// Timer handles
const participantPollTimer = ref(null);
const signalPollTimer = ref(null);
const expiryTimer = ref(null);
const redirectTimer = ref(null);

// ICE candidate queue — plain object, not reactive
const pendingCandidates = {};

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
        peers.value[peerId] = { ...peers.value[peerId], stream: streams[0] };
    };
    pc.onconnectionstatechange = () => {
        if (pc.connectionState === 'failed') {
            peers.value[peerId] = { ...peers.value[peerId], connectionFailed: true };
        }
    };
    peers.value[peerId] = { pc, stream: null, connectionFailed: false };
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

function cleanup() {
    clearTimeout(participantPollTimer.value);
    clearTimeout(signalPollTimer.value);
    clearInterval(expiryTimer.value);
    clearInterval(redirectTimer.value);
    Object.values(peers.value).forEach(({ pc }) => pc.close());
    localStream.value?.getTracks().forEach(t => t.stop());
    sessionStorage.removeItem(storageKey);
}

function toggleMute() {
    isMuted.value = !isMuted.value;
    localStream.value?.getAudioTracks().forEach(t => { t.enabled = !isMuted.value; });
}

function toggleCamera() {
    isCameraOff.value = !isCameraOff.value;
    localStream.value?.getVideoTracks().forEach(t => { t.enabled = !isCameraOff.value; });
}

function requestLeave()  { confirmingLeave.value = true; }
function cancelLeave()   { confirmingLeave.value = false; }
function confirmLeave()  { cleanup(); router.visit(route('calls.index')); }

onMounted(async () => {
    if (!sessionData) return;

    try {
        localStream.value = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
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

            <!-- Media permission error screen -->
            <div v-if="mediaError" class="flex items-center justify-center min-h-screen px-4">
                <div class="max-w-md w-full bg-gray-900 border border-red-700/50 rounded-xl p-8 space-y-4 text-center">
                    <div class="text-red-400 font-mono text-xs uppercase tracking-widest mb-2">Permission Required</div>
                    <h2 class="text-white text-xl font-bold">Camera &amp; Microphone Access Needed</h2>
                    <p v-if="mediaError === 'unavailable'" class="text-gray-400 text-sm">
                        No camera or microphone was found on this device. Please connect a webcam and microphone and reload.
                    </p>
                    <p v-else class="text-gray-400 text-sm">
                        Camera and microphone access was denied. To join the call, allow access in your browser settings and reload this page.
                    </p>
                    <p class="text-gray-500 text-xs">
                        In Chrome: click the lock icon in the address bar → Site settings → Allow Camera and Microphone.<br>
                        In Firefox: click the camera icon in the address bar → Remove block.
                    </p>
                    <button
                        @click="router.visit(route('calls.join', session.bridge_number))"
                        class="w-full border border-gray-700 text-gray-300 hover:text-white py-2.5 rounded-lg font-mono text-sm transition-colors"
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

                <!-- Video grid -->
                <div class="flex-1 p-4">
                    <div class="grid gap-4 h-full" :class="Object.keys(peers).length === 0 ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2'">

                        <!-- Local video tile -->
                        <div class="relative bg-gray-900 rounded-xl overflow-hidden aspect-video">
                            <video
                                v-if="localStream"
                                :srcObject="localStream"
                                autoplay
                                muted
                                playsinline
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="flex items-center justify-center h-full text-gray-600">
                                <span class="font-mono text-sm">Loading camera…</span>
                            </div>
                            <div class="absolute bottom-2 left-2 text-xs font-mono text-gamboge-300 bg-gray-950/70 px-2 py-0.5 rounded">You</div>
                            <div v-if="isMuted" class="absolute top-2 right-2 text-red-400 bg-gray-950/70 rounded-full p-1">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 19L5 5m5.5 4.5A4 4 0 0 0 12 12v3m-4 2.5c.8.8 2 1.5 4 1.5s3.2-.7 4-1.5m.5-8.5a4 4 0 0 0-8 0v4"/></svg>
                            </div>
                        </div>

                        <!-- Remote video tiles -->
                        <template v-for="(peer, peerId) in peers" :key="peerId">
                            <div class="relative bg-gray-900 rounded-xl overflow-hidden aspect-video">
                                <video
                                    v-if="peer.stream"
                                    :srcObject="peer.stream"
                                    autoplay
                                    playsinline
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="flex items-center justify-center h-full text-gray-600">
                                    <span class="font-mono text-sm animate-pulse">Connecting…</span>
                                </div>
                                <!-- Connection failed overlay -->
                                <div v-if="peer.connectionFailed" class="absolute inset-0 bg-gray-950/80 flex items-center justify-center p-4">
                                    <p class="text-red-400 text-xs text-center font-mono">
                                        Connection lost. This may recover automatically — if not, ask the participant to rejoin.
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Empty state — no peers yet -->
                        <div v-if="Object.keys(peers).length === 0" class="bg-gray-900/50 border border-gray-700/50 rounded-xl aspect-video flex items-center justify-center">
                            <p class="text-gray-500 font-mono text-sm animate-pulse">Waiting for others to join…</p>
                        </div>

                    </div>
                </div>

                <!-- Controls bar -->
                <div class="bg-gray-900 border-t border-gray-800 px-4 py-4">
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

                        <!-- Centre — mic / camera -->
                        <div class="flex gap-3">
                            <button
                                @click="toggleMute"
                                :title="isMuted ? 'Unmute' : 'Mute'"
                                :class="isMuted ? 'bg-red-700 hover:bg-red-600 text-white' : 'bg-gray-700 hover:bg-gray-600 text-gray-200'"
                                class="p-3 rounded-full transition-colors"
                            >
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path v-if="!isMuted" stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19 19L5 5m5.5 4.5A4 4 0 0 0 12 12v3m-4 2.5c.8.8 2 1.5 4 1.5s3.2-.7 4-1.5m.5-8.5a4 4 0 0 0-8 0v4"/>
                                </svg>
                            </button>
                            <button
                                @click="toggleCamera"
                                :title="isCameraOff ? 'Turn camera on' : 'Turn camera off'"
                                :class="isCameraOff ? 'bg-red-700 hover:bg-red-600 text-white' : 'bg-gray-700 hover:bg-gray-600 text-gray-200'"
                                class="p-3 rounded-full transition-colors"
                            >
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path v-if="!isCameraOff" stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5 4.72-4.72m0 0a.75.75 0 0 0-1.28.53v11.38a.75.75 0 0 0 1.28.53l4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Right — leave -->
                        <div class="text-right">
                            <div v-if="confirmingLeave" class="flex gap-2 items-center">
                                <span class="text-xs text-gray-400 font-mono">Leave call?</span>
                                <button @click="cancelLeave" class="text-xs text-gray-400 hover:text-white font-mono border border-gray-700 px-2 py-1 rounded transition-colors">Stay</button>
                                <button @click="confirmLeave" class="text-xs text-red-400 hover:text-red-300 font-mono border border-red-700 px-2 py-1 rounded transition-colors">Leave call</button>
                            </div>
                            <button
                                v-else
                                @click="requestLeave"
                                class="bg-red-800 hover:bg-red-700 text-white font-mono text-sm px-4 py-2 rounded-lg transition-colors"
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
