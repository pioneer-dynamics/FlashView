<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    session_id: String,
});

const timedOut = ref(false);
let pollInterval = null;
let elapsed = 0;

const poll = async () => {
    if (!props.session_id) return;
    try {
        const res = await fetch(route('lockers.credit-status') + '?session=' + encodeURIComponent(props.session_id));
        const data = await res.json();
        if (data.token) {
            clearInterval(pollInterval);
            router.visit(route('lockers.create') + '?token=' + encodeURIComponent(data.token));
        }
    } catch {
        // network error — keep polling
    }
};

onMounted(() => {
    pollInterval = setInterval(() => {
        elapsed += 2;
        if (elapsed >= 60) {
            clearInterval(pollInterval);
            timedOut.value = true;
            return;
        }
        poll();
    }, 2000);
    poll();
});

onUnmounted(() => clearInterval(pollInterval));
</script>

<template>
    <AppLayout title="Awaiting Payment — eLocker">
        <div class="min-h-screen bg-gray-900 flex items-center justify-center px-4 py-16">
            <div class="max-w-lg w-full">

                <div class="bg-gray-800 border border-gray-700 rounded-xl p-8">
                    <h1 class="text-2xl font-bold text-white mb-2">Processing your payment…</h1>
                    <p class="text-gray-400 text-sm mb-6">Your locker credit will appear here automatically once Stripe confirms payment.</p>

                    <!-- Session ID recovery reference -->
                    <div class="bg-gray-900 border border-gamboge-300/30 rounded-lg p-4 mb-6">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Payment Reference</div>
                        <div class="font-mono text-xs text-white break-all">{{ session_id }}</div>
                        <p class="text-gray-400 text-xs mt-2">
                            Save or bookmark this page before leaving — you'll need this reference if something goes wrong.
                        </p>
                    </div>

                    <!-- Shimmer while polling -->
                    <div v-if="!timedOut" class="space-y-3">
                        <div class="relative overflow-hidden h-3 bg-gray-700 rounded-full">
                            <div class="absolute inset-0 bg-gamboge-300/30 rounded-full">
                                <div class="absolute inset-y-0 w-1/3 bg-gamboge-300/60 rounded-full animate-shimmer" />
                            </div>
                        </div>
                        <p class="text-gray-500 text-sm text-center">Waiting for Stripe confirmation…</p>
                    </div>

                    <!-- Timeout state -->
                    <div v-else class="space-y-4">
                        <div class="bg-yellow-900/20 border border-yellow-600/30 rounded-lg p-4 text-yellow-300 text-sm">
                            <p class="font-semibold mb-2">Payment is taking longer than expected.</p>
                            <ul class="list-disc list-inside space-y-1 text-yellow-300/80">
                                <li>Wait a few minutes, then return to this page via browser back/history and it may appear.</li>
                                <li>Quote your payment reference above when contacting support — no account is needed.</li>
                            </ul>
                        </div>
                        <button
                            @click="timedOut = false; elapsed = 0; pollInterval = setInterval(() => { elapsed += 2; if (elapsed >= 60) { clearInterval(pollInterval); timedOut = true; return; } poll(); }, 2000); poll();"
                            class="w-full text-center border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-sm py-2.5 rounded-lg transition-colors"
                        >
                            Try again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
