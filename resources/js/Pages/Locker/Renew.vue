<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { encryption } from '@/encryption.js';

const props = defineProps({
    account_id: String,
    tier:       String,
    expires_at: String,
});

const enc = new encryption();

const passphrase  = ref('');
const years       = ref(1);
const error       = ref('');
const loading     = ref(false);

const daysRemaining = computed(() => {
    const ms = new Date(props.expires_at).getTime() - Date.now();
    return Math.max(0, Math.ceil(ms / 86_400_000));
});

const submit = async () => {
    error.value = '';
    if (!passphrase.value) { error.value = 'Passphrase is required.'; return; }

    loading.value = true;
    try {
        const challengeRes = await fetch(route('lockers.renew.challenge', props.account_id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!challengeRes.ok) {
            error.value = 'Could not fetch challenge. Please try again.';
            return;
        }

        const { challenge } = await challengeRes.json();

        const authKey = await enc.deriveLockerAuthKey(passphrase.value, props.account_id);
        const verifier = await enc.computeLockerVerifier(authKey, challenge);

        const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

        const renewRes = await fetch(route('lockers.renew.purchase', props.account_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
            body: JSON.stringify({
                verifier,
                years: years.value,
                tier:  props.tier,
            }),
        });

        const data = await renewRes.json();

        if (!renewRes.ok) {
            error.value = data.error ?? 'Renewal failed. Please try again.';
            return;
        }

        window.location.href = data.checkout_url;

    } catch {
        error.value = 'An unexpected error occurred. Please try again.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AppLayout title="Renew eLocker">
        <div class="min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-md mx-auto">
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 space-y-6">

                    <div>
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Renew eLocker</div>
                        <h1 class="text-xl font-bold text-white font-mono">{{ account_id }}</h1>
                    </div>

                    <!-- Current tier & expiry -->
                    <div class="bg-gray-900 rounded-lg p-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tier</span>
                            <span class="text-white font-mono capitalize">{{ tier }} Locker</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Days remaining</span>
                            <span :class="daysRemaining <= 30 ? 'text-red-400' : 'text-white'" class="font-mono">{{ daysRemaining }}</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Duration -->
                        <div>
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Renewal Duration</label>
                            <div class="flex gap-2">
                                <button
                                    v-for="y in [1, 3, 5]"
                                    :key="y"
                                    @click="years = y"
                                    :class="years === y ? 'bg-gamboge-300 text-gray-900 shadow-neon-cyan-sm' : 'border border-gray-700 text-gray-300 hover:border-gamboge-300/50'"
                                    class="flex-1 font-mono text-sm py-2 rounded-lg transition-all"
                                >
                                    {{ y }}yr
                                </button>
                            </div>
                        </div>

                        <!-- Passphrase -->
                        <div>
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Passphrase</label>
                            <input
                                v-model="passphrase"
                                type="password"
                                placeholder="Your locker passphrase"
                                @keydown.enter="submit"
                                class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none"
                            />
                            <p class="text-gray-500 text-xs mt-1">Used to compute your renewal authorisation. Never sent to the server.</p>
                        </div>

                        <p v-if="error" class="text-red-400 text-sm">{{ error }}</p>

                        <button
                            @click="submit"
                            :disabled="loading"
                            class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-60 text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                        >
                            {{ loading ? 'Verifying…' : `Renew for ${years} ${years === 1 ? 'year' : 'years'} →` }}
                        </button>

                        <p class="text-gray-500 text-xs text-center">
                            You'll be redirected to Stripe to complete payment. No subscription is created.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
