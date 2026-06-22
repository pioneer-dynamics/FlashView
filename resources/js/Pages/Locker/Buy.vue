<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import type { LockerPlan } from '@/types';
import { create } from '@/actions/App/Http/Controllers/LockerController';

interface Props {
    pricing?: Record<string, Record<number, LockerPlan>>
}

const props = defineProps<Props>();

const pendingToken = ref<string | null>(null);

onMounted(() => {
    pendingToken.value = localStorage.getItem('locker_pending_token') || null;
});

const resumeCreation = (): void => {
    router.visit(create.url({ query: { token: pendingToken.value ?? '' } }));
};

const dismissPending = (): void => {
    localStorage.removeItem('locker_pending_token');
    pendingToken.value = null;
};

const tiers = [
    { key: 'text', name: 'Text Locker', description: 'Stores up to 100 KB of text or structured data — approximately 50 pages.', icon: '📄' },
    { key: 'file', name: 'File Locker', description: 'Stores files — documents, images, small archives. Can also hold text.', icon: '🗂' },
];

const fileSizeLabel = (plan: LockerPlan | null): string => {
    if (!plan || !plan.file_size_mb) return '';
    const mb = plan.file_size_mb;
    return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${mb} MB`;
};

const durations = [1, 3, 5];

const formatPrice = (cents: number): string => `$${(cents / 100).toFixed(0)}`;

const planFor = (tier: string, years: number): LockerPlan | null => props.pricing?.[tier]?.[years] ?? null;

const savingsPercent = (tier: string, years: number): number | null => {
    if (years === 1) return null;
    const base1 = planFor(tier, 1);
    const plan  = planFor(tier, years);
    if (!base1 || !plan) return null;
    const base = base1.amount_cents * years;
    const discounted = plan.amount_cents;
    return Math.round(((base - discounted) / base) * 100);
};
</script>

<template>
    <AppLayout title="eLocker Pricing">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-5xl mx-auto">

                <!-- Pending credit banner -->
                <div v-if="pendingToken" class="mb-10 bg-gamboge-300/10 border border-gamboge-300/50 rounded-xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 shadow-neon-cyan-sm">
                    <div class="flex-1">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Unused Locker Credit</div>
                        <p class="text-white text-sm">You have an unused locker credit from a previous purchase. Continue setting up your locker now.</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button @click="resumeCreation" class="bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm">
                            Continue →
                        </button>
                        <button @click="dismissPending" class="border border-gray-600 text-gray-400 hover:text-white hover:border-gray-500 py-2 px-3 rounded-lg text-sm transition-colors">
                            Dismiss
                        </button>
                    </div>
                </div>

                <!-- Hero -->
                <div class="text-center mb-14">
                    <h1 class="text-4xl font-bold text-white mb-4 tracking-tight">
                        eLocker
                        <span class="text-gamboge-300">—</span>
                        Anonymous Encrypted Storage
                    </h1>
                    <p class="text-gray-300 text-lg max-w-2xl mx-auto mb-6">
                        Like a Swiss numbered bank account, online. Your locker is accessed by a
                        <span class="text-gamboge-300 font-mono">10-digit account ID</span>
                        and a passphrase you choose — no email, no login, no identity required.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mx-auto text-sm text-gray-400">
                        <div class="bg-gray-800 border border-gray-700 rounded-lg p-4">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Zero-Knowledge</div>
                            Your passphrase never leaves your device. The server stores only ciphertext.
                        </div>
                        <div class="bg-gray-800 border border-gray-700 rounded-lg p-4">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Fully Anonymous</div>
                            No account, no email, no identity. Access by account ID and passphrase only.
                        </div>
                        <div class="bg-gray-800 border border-gray-700 rounded-lg p-4">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">No Reminders</div>
                            Lockers are anonymous by design — we cannot send expiry reminders. Save your expiry date.
                        </div>
                    </div>
                </div>

                <!-- Pricing grid -->
                <div v-for="tier in tiers" :key="tier.key" class="mb-12">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="text-2xl">{{ tier.icon }}</span>
                        <div>
                            <h2 class="text-xl font-semibold text-white">{{ tier.name }}</h2>
                            <p class="text-gray-400 text-sm">{{ tier.description }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <template v-for="years in durations" :key="years">
                            <div
                                v-if="planFor(tier.key, years)"
                                class="relative bg-gray-800 border border-gray-700 rounded-xl p-6 flex flex-col gap-4 hover:border-gamboge-300 hover:shadow-neon-cyan transition-all duration-200"
                            >
                                <div v-if="savingsPercent(tier.key, years)" class="absolute -top-3 left-1/2 -translate-x-1/2">
                                    <span class="bg-gamboge-300 text-gray-900 text-xs font-bold px-3 py-1 rounded-full font-mono uppercase tracking-wide">
                                        Save {{ savingsPercent(tier.key, years) }}%
                                    </span>
                                </div>

                                <div>
                                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">{{ years }} {{ years === 1 ? 'Year' : 'Years' }}</div>
                                    <div class="text-3xl font-bold text-white">
                                        {{ formatPrice(planFor(tier.key, years).amount_cents) }}
                                    </div>
                                    <div class="text-gray-400 text-xs mt-1">
                                        one-time payment — no subscription
                                    </div>
                                    <div v-if="tier.key === 'file' && fileSizeLabel(planFor(tier.key, years))" class="text-gray-500 text-xs mt-0.5">
                                        up to {{ fileSizeLabel(planFor(tier.key, years)) }}
                                    </div>
                                </div>

                                <Link
                                    :href="route('lockers.checkout')"
                                    method="post"
                                    :data="{ tier: tier.key, years }"
                                    as="button"
                                    class="mt-auto w-full text-center bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm transition-colors duration-150 shadow-neon-cyan-sm hover:shadow-neon-cyan"
                                >
                                    Buy {{ years }}-Year Locker
                                </Link>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-center text-gray-500 text-sm mt-4">
                    No subscription. No renewal reminders. No account required. Lockers are anonymous by design — save your expiry date.
                </p>

            </div>
        </div>
    </AppLayout>
</template>
