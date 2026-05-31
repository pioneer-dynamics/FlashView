<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const accountId = ref('');
const destination = ref('open'); // 'open' | 'renew'

const go = () => {
    if (!/^\d{10}$/.test(accountId.value)) return;
    const target = destination.value === 'renew'
        ? route('lockers.renew.challenge', accountId.value)
        : route('lockers.show', accountId.value);
    router.visit(target);
};
</script>

<template>
    <AppLayout title="eLocker">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-md mx-auto space-y-10">

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
                        class="inline-block bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2.5 px-8 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                    >
                        Buy an eLocker
                    </Link>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
