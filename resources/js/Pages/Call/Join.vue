<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/Alert.vue';
import axios from 'axios';
import { encryption } from '@/encryption.js';
import type { CallSession } from '@/types';
import CallSessionController from '@/actions/App/Http/Controllers/CallSessionController';
import CallPageController from '@/actions/App/Http/Controllers/CallPageController';

interface Props {
    session: CallSession;
}

const props = defineProps<Props>();

const stage = ref<'form' | 'joining' | 'turn_warning' | 'error'>('form');
const password = ref('');
const errorMessage = ref('');
const isActive = ref(props.session.is_active);
const turnWarning = ref(false);

function formatDateTime(isoString: string): string {
    return new Date(isoString).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

onMounted(() => {
    if (!isActive.value) {
        const sessionOpenMs = new Date(props.session.starts_at).getTime();
        const timer = setInterval(() => {
            if (Date.now() >= sessionOpenMs) {
                isActive.value = true;
                clearInterval(timer);
            }
        }, 1000);
        onUnmounted(() => clearInterval(timer));
    }
});

async function handleJoin(): Promise<void> {
    stage.value = 'joining';
    try {
        const { data: challengeData } = await axios.get(
            CallSessionController.challenge.url(props.session.bridge_number as unknown as number)
        );

        const enc = new encryption();
        const keypair = await enc.deriveCallKeyPair(password.value, challengeData.salt);
        const ecdhKeypair = await enc.generateCallEphemeralKeypair();

        // signCallChallenge is synchronous — no await
        const signature = enc.signCallChallenge(keypair.privateKey, challengeData.challenge);

        const { data: responseData } = await axios.post(
            CallSessionController.join['/call-sessions/{callSession}/join'].url(props.session.bridge_number as unknown as number),
            { signature, public_key: ecdhKeypair.publicKeyBase64 }
        );

        const storageKey = `call_session:${props.session.bridge_number}`;
        sessionStorage.setItem(storageKey, JSON.stringify({
            participant_id:   responseData.participant_id,
            ice_servers:      responseData.ice_servers,
            ecdh_private_key: ecdhKeypair.privateKeyBase64,
            ecdh_public_key:  ecdhKeypair.publicKeyBase64,
            turn_warning:     !responseData.turn_available,
            session:          responseData.session,
        }));

        if (!responseData.turn_available) {
            turnWarning.value = true;
            stage.value = 'turn_warning';
            return;
        }

        router.visit(CallPageController.room.url(props.session.bridge_number as unknown as number));
    } catch (e: unknown) {
        stage.value = 'error';
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        const status = err.response?.status;
        const msg = err.response?.data?.message ?? '';
        if (status === 401) {
            errorMessage.value = 'Incorrect password. Please check and try again.';
        } else if (status === 404) {
            errorMessage.value = 'This call session was not found. Check the bridge number.';
        } else if (status === 422 && msg.toLowerCase().includes('full')) {
            errorMessage.value = 'This call is currently full.';
        } else if (status === 422) {
            errorMessage.value = 'This call has already ended.';
        } else {
            errorMessage.value = 'Unable to join the call. Please try again.';
        }
    }
}

function continueToRoom(): void {
    router.visit(CallPageController.room.url(props.session.bridge_number as unknown as number));
}

function retryJoin(): void {
    stage.value = 'form';
    errorMessage.value = '';
}
</script>

<template>
    <AppLayout title="Join Secure Line">
        <div class="dark min-h-screen bg-gray-950 py-16 px-4">
            <div class="max-w-md mx-auto space-y-6">

                <!-- Header -->
                <div class="text-center">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Secure Line</div>
                    <h1 class="text-2xl font-bold text-white mb-1">Join Call</h1>
                    <div class="font-mono text-gamboge-300 text-lg tracking-widest">{{ session.bridge_number }}</div>
                </div>

                <!-- Not yet started state -->
                <div v-if="!isActive" class="bg-gray-900 border border-gray-700 rounded-xl p-6 space-y-3 text-center">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Not Yet Started</div>
                    <p class="text-white text-sm">This call starts at</p>
                    <p class="text-gamboge-300 font-mono text-sm">{{ formatDateTime(session.starts_at) }}</p>
                    <p class="text-gray-400 text-xs">The form will enable automatically when the call window opens.</p>
                    <button
                        disabled
                        class="w-full mt-2 bg-gamboge-300 opacity-40 cursor-not-allowed text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm"
                    >
                        Join Call →
                    </button>
                </div>

                <!-- Join form -->
                <div v-else-if="stage === 'form' || stage === 'joining'" class="bg-gray-900 border border-gray-700 rounded-xl p-6 space-y-4">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Enter Password</div>
                    <input
                        v-model="password"
                        type="password"
                        placeholder="Call password"
                        @keydown.enter="handleJoin"
                        :disabled="stage === 'joining'"
                        data-testid="call-password-input"
                        class="w-full bg-gray-950 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none disabled:opacity-50"
                    />
                    <button
                        @click="handleJoin"
                        :disabled="!password || stage === 'joining'"
                        data-testid="join-call-button"
                        class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                        :class="{ 'animate-pulse': stage === 'joining' }"
                    >
                        <span v-if="stage === 'joining'">Joining…</span>
                        <span v-else>Join Call →</span>
                    </button>
                </div>

                <!-- TURN warning interstitial -->
                <div v-else-if="stage === 'turn_warning'" class="bg-gray-900 border border-amber-700/50 rounded-xl p-6 space-y-4">
                    <Alert type="Warning">
                        Your network may limit call quality. If you experience issues, try switching to a different network or connection.
                    </Alert>
                    <button
                        @click="continueToRoom"
                        data-testid="continue-to-call-button"
                        class="w-full bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm"
                    >
                        Continue to call →
                    </button>
                </div>

                <!-- Error state -->
                <div v-else-if="stage === 'error'" class="bg-gray-900 border border-gray-700 rounded-xl p-6 space-y-4">
                    <Alert type="Error">{{ errorMessage }}</Alert>
                    <button
                        @click="retryJoin"
                        class="w-full border border-gray-700 text-gray-300 hover:text-white hover:border-gray-500 py-2.5 rounded-lg font-mono text-sm transition-colors"
                    >
                        Try again
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
