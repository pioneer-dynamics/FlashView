<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
    session_id?: string;
}

const props = defineProps<Props>();

const timedOut = ref(false);
let pollInterval: ReturnType<typeof setInterval> | null = null;
let elapsed = 0;

const poll = async () => {
    if (!props.session_id) return;
    try {
        const res = await fetch(route('lockers.credit-status') + '?session=' + encodeURIComponent(props.session_id));
        const data = await res.json();
        if (data.token) {
            if (pollInterval !== null) clearInterval(pollInterval);
            localStorage.setItem('locker_pending_token', data.token);
            router.visit(route('lockers.create') + '?token=' + encodeURIComponent(data.token));
        }
    } catch {
        // network error — keep polling
    }
};

const startPolling = () => {
    elapsed = 0;
    timedOut.value = false;
    pollInterval = setInterval(() => {
        elapsed += 2;
        if (elapsed >= 60) {
            if (pollInterval !== null) clearInterval(pollInterval);
            timedOut.value = true;
            return;
        }
        poll();
    }, 2000);
    poll();
};

onMounted(startPolling);
onUnmounted(() => { if (pollInterval !== null) clearInterval(pollInterval); });
</script>

<template>
    <AppLayout title="Awaiting Payment — eLocker">
        <div class="dark min-h-screen bg-gray-900 flex items-center justify-center px-4 py-16">
            <div class="max-w-lg w-full">

                <div class="bg-gray-800 border border-gray-700 rounded-xl p-8">
                    <h1 class="text-2xl font-bold text-white mb-2">Processing your payment…</h1>
                    <p class="text-gray-400 text-sm mb-6">Your locker credit will appear here automatically once Stripe confirms payment.</p>

                    <!-- Session ID recovery reference -->
                    <div class="bg-gray-900 border border-gamboge-300/30 rounded-lg p-4 mb-6">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Payment Reference</div>
                        <div class="font-mono text-xs text-white break-all">{{ session_id }}</div>
                        <p class="text-gray-400 text-xs mt-2">
                            Bookmark this page — if anything goes wrong, returning here will resume automatically.
                        </p>
                    </div>

                    <!-- Shimmer while polling -->
                    <div v-if="!timedOut" class="space-y-3">
                        <div class="relative h-1.5 w-full rounded-sm bg-gray-700 border border-gamboge-300/20 overflow-hidden">
                            <div class="absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-gamboge-300 to-transparent animate-shimmer" />
                        </div>
                        <p class="text-gray-500 text-sm text-center">Waiting for Stripe confirmation…</p>
                    </div>

                    <!-- Timeout state -->
                    <div v-else class="space-y-4">
                        <div class="bg-yellow-900/20 border border-yellow-600/30 rounded-lg p-4 text-yellow-300 text-sm">
                            <p class="font-semibold mb-2">Payment is taking longer than expected.</p>
                            <ul class="list-disc list-inside space-y-1 text-yellow-300/80">
                                <li>If you've completed payment, wait a moment and click <strong>Try again</strong> below.</li>
                                <li>Quote your payment reference above when contacting support — no account is needed.</li>
                            </ul>
                        </div>
                        <button
                            @click="startPolling"
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
