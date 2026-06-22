<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { generatePassphrase, deriveCallKeyPair, generateCallKeySalt } from '@pioneer-dynamics/flashview-crypto';
import type { SecureLineProduct } from '@/types';

interface Props {
    credit_token?: string;
    product?: SecureLineProduct;
}

const props = defineProps<Props>();

const step = ref<'creating' | 'done' | 'error'>('creating');
const errorMessage = ref<string | null>(null);
const bridgeNumber = ref<string | null>(null);
const callPassword = ref<string | null>(null);
const endsAt = ref<string | null>(null);
const savedConfirmed = ref(false);

const xsrfToken = (): string => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const formattedExpiry = computed((): string => {
    if (!endsAt.value) return '';
    return new Date(endsAt.value).toLocaleString();
});

const minutesRemaining = computed((): string => {
    if (!endsAt.value) return '';
    const diff = Math.round((new Date(endsAt.value).getTime() - Date.now()) / 60000);
    return diff > 0 ? `${diff} minutes from now` : 'soon';
});

const copyToClipboard = async (text: string | null): Promise<void> => {
    if (text) {
        await navigator.clipboard.writeText(text);
    }
};

const downloadCredentials = (): void => {
    const callsUrl = route('calls.index');
    const lines = [
        'Secure Line Credentials',
        '=======================',
        `Bridge Number: ${bridgeNumber.value}`,
        `Call Password: ${callPassword.value}`,
        `Session Closes: ${formattedExpiry.value}`,
        '',
        'Your participant visits ' + callsUrl + ', enters the bridge number under',
        '"Join a Line", and uses the call password when prompted.',
    ];
    const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `secure-line-${bridgeNumber.value}.txt`;
    a.click();
    URL.revokeObjectURL(url);
};

onMounted(async () => {
    try {
        const password = generatePassphrase();
        const keySalt = generateCallKeySalt();
        const { publicKeyBase64 } = await deriveCallKeyPair(password, keySalt);

        const res = await fetch(route('calls.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                credit_token: props.credit_token,
                public_key: publicKeyBase64,
                key_salt: keySalt,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            errorMessage.value = data.error ?? 'An error occurred while setting up your Secure Line.';
            step.value = 'error';
            return;
        }

        callPassword.value = password;
        bridgeNumber.value = data.bridge_number;
        endsAt.value = data.ends_at;
        localStorage.removeItem('secure_line_pending_token');
        step.value = 'done';
    } catch {
        errorMessage.value = 'An unexpected error occurred. Please try again.';
        step.value = 'error';
    }
});
</script>

<template>
    <AppLayout title="Your Secure Line">
        <div class="dark min-h-screen bg-gray-900 flex items-center justify-center py-16 px-4">
            <div class="max-w-xl w-full">

                <!-- Creating / shimmer state -->
                <div v-if="step === 'creating'" class="bg-gray-900 border border-gray-700 rounded-xl p-8 space-y-4">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Secure Line</div>
                    <h1 class="text-2xl font-bold text-white">Setting up your Secure Line…</h1>
                    <p class="text-gray-400 text-sm">Generating encryption keys and creating your session. This only takes a moment.</p>
                    <div class="relative h-1.5 w-full rounded-sm bg-gray-700 border border-gamboge-300/20 overflow-hidden mt-4">
                        <div class="absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-gamboge-300 to-transparent animate-shimmer" />
                    </div>
                    <!-- Skeleton placeholders -->
                    <div class="space-y-3 mt-4 animate-pulse">
                        <div class="h-4 bg-gray-800 rounded w-3/4" />
                        <div class="h-4 bg-gray-800 rounded w-1/2" />
                        <div class="h-10 bg-gray-800 rounded" />
                    </div>
                </div>

                <!-- Error state -->
                <div v-else-if="step === 'error'" class="bg-gray-900 border border-red-500/40 rounded-xl p-8">
                    <div class="text-red-400 font-mono text-xs uppercase tracking-widest mb-2">Error</div>
                    <h1 class="text-2xl font-bold text-white mb-3">Setup failed</h1>
                    <p class="text-red-300 text-sm mb-6">{{ errorMessage }}</p>
                    <button
                        @click="router.visit(route('calls.buy'))"
                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-sm py-2.5 rounded-lg transition-colors"
                    >
                        ← Back to Buy
                    </button>
                </div>

                <!-- Credentials panel -->
                <div v-else class="bg-gray-900 border border-gamboge-300 rounded-xl p-8 shadow-neon-cyan space-y-6">
                    <div>
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Secure Line — Ready</div>
                        <h1 class="text-2xl font-bold text-white">Your Secure Line is set up</h1>
                    </div>

                    <div class="bg-red-900/20 border border-red-500/40 rounded-lg p-4 text-red-300 text-sm">
                        <p class="font-semibold text-red-200 mb-1">Save these credentials now — they cannot be recovered.</p>
                        Share both the bridge number and password with your participant before the session closes.
                    </div>

                    <!-- Bridge Number -->
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Bridge Number</div>
                        <div class="flex items-center gap-3">
                            <code class="text-white text-lg flex-1 font-mono" data-testid="bridge-number">{{ bridgeNumber }}</code>
                            <button
                                @click="copyToClipboard(bridgeNumber)"
                                class="shrink-0 text-gray-400 hover:text-gamboge-300 transition-colors text-xs border border-gray-700 hover:border-gamboge-300 rounded px-2 py-1 font-mono"
                                data-testid="copy-bridge-number"
                            >Copy</button>
                        </div>
                    </div>

                    <!-- Call Password -->
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Call Password</div>
                        <div class="flex items-center gap-3">
                            <code class="text-white text-sm flex-1 break-all font-mono" data-testid="call-password">{{ callPassword }}</code>
                            <button
                                @click="copyToClipboard(callPassword)"
                                class="shrink-0 text-gray-400 hover:text-gamboge-300 transition-colors text-xs border border-gray-700 hover:border-gamboge-300 rounded px-2 py-1 font-mono"
                                data-testid="copy-call-password"
                            >Copy</button>
                        </div>
                    </div>

                    <!-- Session expiry -->
                    <div class="bg-yellow-900/20 border border-yellow-600/40 rounded-lg p-4">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Session Closes</div>
                        <p class="text-yellow-200 text-sm font-semibold" data-testid="session-expiry">{{ formattedExpiry }}</p>
                        <p class="text-yellow-300/70 text-xs mt-0.5">{{ minutesRemaining }}</p>
                    </div>

                    <!-- Participant instructions -->
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Participant Instructions</div>
                        <p class="text-gray-300 text-sm">
                            Your participant visits
                            <a :href="route('calls.index')" class="font-mono text-gamboge-300 hover:underline">{{ route('calls.index') }}</a>,
                            enters the bridge number under <span class="font-mono text-white">Join a Line</span>,
                            and enters the call password when prompted.
                        </p>
                    </div>

                    <!-- Download button -->
                    <button
                        @click="downloadCredentials"
                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-sm py-2.5 rounded-lg transition-colors"
                        data-testid="download-credentials"
                    >
                        Download credentials as text file
                    </button>

                    <!-- Save confirmation -->
                    <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="savedConfirmed"
                            class="rounded border-gray-600 bg-gray-700 text-gamboge-300"
                            data-testid="saved-confirmed-checkbox"
                        />
                        I have saved the bridge number and call password
                    </label>

                    <!-- Done button -->
                    <button
                        :disabled="!savedConfirmed"
                        @click="router.visit(route('calls.index'))"
                        class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm"
                        data-testid="done-button"
                    >
                        Done — Go to Calls
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
