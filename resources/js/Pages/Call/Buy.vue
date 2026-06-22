<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { create, checkout } from '@/actions/App/Http/Controllers/SecureLineCheckoutController';
import type { SecureLineProduct } from '@/types';

interface Props {
    products: SecureLineProduct[];
}

const props = defineProps<Props>();

const pendingToken = ref<string | null>(null);

onMounted(() => {
    pendingToken.value = localStorage.getItem('secure_line_pending_token') || null;
});

const resumeSetup = (): void => {
    router.visit(create.url({ query: { token: pendingToken.value! } }));
};

const dismissPending = (): void => {
    localStorage.removeItem('secure_line_pending_token');
    pendingToken.value = null;
};

const formatPrice = (cents: number): string => `$${(cents / 100).toFixed(0)}`;
const formatDuration = (minutes: number): string => minutes >= 60 ? `${minutes / 60} hour${minutes / 60 !== 1 ? 's' : ''}` : `${minutes} minutes`;
</script>

<template>
    <AppLayout title="Buy a Secure Line">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-3xl mx-auto space-y-10">

                <!-- Pending token recovery banner -->
                <div v-if="pendingToken" class="bg-gamboge-300/10 border border-gamboge-300/50 rounded-xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 shadow-neon-cyan-sm">
                    <div class="flex-1">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Unused Secure Line Credit</div>
                        <p class="text-white text-sm">You have an unused Secure Line setup from a previous purchase. Continue where you left off.</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button @click="resumeSetup" class="bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm">
                            Continue →
                        </button>
                        <button @click="dismissPending" class="border border-gray-600 text-gray-400 hover:text-white hover:border-gray-500 py-2 px-3 rounded-lg text-sm transition-colors">
                            Dismiss
                        </button>
                    </div>
                </div>

                <!-- Hero -->
                <div class="text-center">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Secure Line</div>
                    <h1 class="text-3xl font-bold text-white mb-3">Buy a Secure Line</h1>
                    <p class="text-gray-400 text-sm max-w-lg mx-auto">
                        One-off payment. No account. No subscription. A time-limited, end-to-end encrypted call window you share with your participant.
                    </p>
                </div>

                <!-- How it works -->
                <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-4">How it works</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex gap-3">
                            <div class="shrink-0 w-6 h-6 rounded-full bg-gamboge-300/20 text-gamboge-300 font-mono text-xs flex items-center justify-center">1</div>
                            <p class="text-gray-300 text-sm">Pay once — no account, no subscription.</p>
                        </div>
                        <div class="flex gap-3">
                            <div class="shrink-0 w-6 h-6 rounded-full bg-gamboge-300/20 text-gamboge-300 font-mono text-xs flex items-center justify-center">2</div>
                            <p class="text-gray-300 text-sm">Receive a bridge number and a call password.</p>
                        </div>
                        <div class="flex gap-3">
                            <div class="shrink-0 w-6 h-6 rounded-full bg-gamboge-300/20 text-gamboge-300 font-mono text-xs flex items-center justify-center">3</div>
                            <p class="text-gray-300 text-sm">Share both with your participant — they join at <span class="font-mono text-gamboge-300/80">/calls</span>.</p>
                        </div>
                    </div>
                    <div class="mt-4 bg-yellow-900/20 border border-yellow-600/30 rounded-lg px-4 py-3 text-yellow-300/90 text-xs">
                        Your call window starts the moment you generate your credentials — share them promptly.
                    </div>
                </div>

                <!-- Product grid -->
                <div v-if="products.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="bg-gray-900 border border-gray-700 rounded-xl p-6 flex flex-col gap-4 hover:border-gamboge-300 hover:shadow-neon-cyan transition-all duration-200"
                    >
                        <div>
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">{{ product.name }}</div>
                            <div class="text-3xl font-bold text-white" data-testid="product-price">{{ formatPrice(product.amount_cents) }}</div>
                            <div class="text-gray-400 text-xs mt-1">one-time payment</div>
                        </div>
                        <ul class="space-y-1 text-sm text-gray-300">
                            <li>
                                <span class="text-gamboge-300 font-mono mr-1">✓</span>
                                {{ formatDuration(product.duration_minutes) }} call window
                            </li>
                            <li>
                                <span class="text-gamboge-300 font-mono mr-1">✓</span>
                                Up to {{ product.max_participants }} participants
                            </li>
                            <li>
                                <span class="text-gamboge-300 font-mono mr-1">✓</span>
                                End-to-end encrypted
                            </li>
                        </ul>
                        <Link
                            :href="checkout.url()"
                            method="post"
                            :data="{ product_id: product.id }"
                            as="button"
                            class="mt-auto w-full text-center bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm transition-colors duration-150 shadow-neon-cyan-sm hover:shadow-neon-cyan"
                            data-testid="purchase-button"
                        >
                            Purchase — {{ formatPrice(product.amount_cents) }}
                        </Link>
                    </div>
                </div>

                <div v-else class="text-center text-gray-500 text-sm">
                    No Secure Line products are currently available. Please check back soon.
                </div>

            </div>
        </div>
    </AppLayout>
</template>
