<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const accountId = ref('');
const destination = ref<'open' | 'renew'>('open');
const pendingToken = ref<string | null>(null);

onMounted(() => {
    pendingToken.value = localStorage.getItem('locker_pending_token') || null;
});

const resumeCreation = (): void => {
    router.visit(route('lockers.create') + '?token=' + encodeURIComponent(pendingToken.value ?? ''));
};

const dismissPending = (): void => {
    localStorage.removeItem('locker_pending_token');
    pendingToken.value = null;
};

const go = (): void => {
    if (!/^\d{10}$/.test(accountId.value)) return;
    if (destination.value === 'renew') {
        sessionStorage.setItem('locker_prefill_account_renew', accountId.value);
        router.visit(route('lockers.renew'));
    } else {
        sessionStorage.setItem('locker_prefill_account', accountId.value);
        router.visit(route('lockers.open'));
    }
};
</script>

<template>
    <AppLayout title="eLocker">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-md mx-auto space-y-10">

                <!-- Pending credit banner -->
                <div v-if="pendingToken" class="bg-gamboge-300/10 border border-gamboge-300/50 rounded-xl p-5 shadow-neon-cyan-sm">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Unused Locker Credit</div>
                    <p class="text-white text-sm mb-3">You have an unused locker credit from a previous purchase.</p>
                    <div class="flex gap-2">
                        <button @click="resumeCreation" class="bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm">
                            Continue setting up →
                        </button>
                        <button @click="dismissPending" class="border border-gray-600 text-gray-400 hover:text-white hover:border-gray-500 py-2 px-3 rounded-lg text-sm transition-colors">
                            Dismiss
                        </button>
                    </div>
                </div>

                <!-- Header -->
                <div class="text-center">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">eLocker</div>
                    <h1 class="text-3xl font-bold text-white mb-3">Anonymous Encrypted Storage</h1>
                    <p class="text-gray-400 text-sm">
                        Access your locker with your 10-digit account ID and passphrase — no login required.
                    </p>
                </div>

                <!-- Access form -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Access your locker</div>

                    <input
                        v-model="accountId"
                        type="text"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="Enter your 10-digit account ID"
                        @keydown.enter="go"
                        class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none"
                    />

                    <div class="flex gap-2">
                        <button
                            v-for="opt in [{ value: 'open', label: 'Unlock' }, { value: 'renew', label: 'Renew' }]"
                            :key="opt.value"
                            @click="destination = opt.value"
                            :class="destination === opt.value
                                ? 'bg-gamboge-300 text-gray-900 shadow-neon-cyan-sm'
                                : 'border border-gray-700 text-gray-400 hover:border-gamboge-300/50'"
                            class="flex-1 font-mono text-sm py-2 rounded-lg transition-all"
                        >
                            {{ opt.label }}
                        </button>
                    </div>

                    <button
                        @click="go"
                        :disabled="!/^\d{10}$/.test(accountId)"
                        class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                    >
                        {{ destination === 'renew' ? 'Go to Renew →' : 'Open Locker →' }}
                    </button>
                </div>

                <!-- Buy CTA -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 text-center space-y-3">
                    <div class="text-white font-semibold">Don't have a locker yet?</div>
                    <p class="text-gray-400 text-sm">Anonymous, zero-knowledge storage. No account required. Text from $20/yr.</p>
                    <Link
                        :href="route('lockers.buy')"
                        prefetch
                        class="inline-block bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2.5 px-8 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                    >
                        Buy an eLocker
                    </Link>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
