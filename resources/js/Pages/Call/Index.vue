<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const bridgeNumber = ref('');

function joinLine() {
    const trimmed = bridgeNumber.value.trim();
    if (!trimmed) return;
    router.visit(route('calls.join', trimmed));
}
</script>

<template>
    <AppLayout title="Secure Line">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-3xl mx-auto space-y-10">

                <!-- Hero -->
                <div class="text-center">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Secure Line</div>
                    <h1 class="text-3xl font-bold text-white mb-3">Encrypted, Ephemeral Audio Calls</h1>
                    <p class="text-gray-400 text-sm max-w-lg mx-auto">
                        Time-limited, end-to-end encrypted calls. No account needed to join — just a bridge number and password.
                    </p>
                </div>

                <!-- Two-column action cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Join a Line -->
                    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 space-y-4 shadow-neon-cyan-sm">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Join a Line</div>
                        <p class="text-gray-400 text-sm">Enter the bridge number you received to join a secure call.</p>
                        <input
                            v-model="bridgeNumber"
                            type="text"
                            placeholder="Bridge number"
                            @keydown.enter="joinLine"
                            data-testid="bridge-number-input"
                            class="w-full bg-gray-800 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none"
                        />
                        <button
                            @click="joinLine"
                            :disabled="!bridgeNumber.trim()"
                            data-testid="join-line-button"
                            class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                        >
                            Join Line →
                        </button>
                    </div>

                    <!-- Buy a Line -->
                    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 space-y-4 shadow-neon-cyan-sm">
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Buy a Line</div>
                        <p class="text-gray-400 text-sm">
                            Host your own encrypted call window. Time-limited, zero-knowledge, no account needed for participants.
                        </p>
                        <Link
                            :href="route('calls.buy')"
                            class="block w-full text-center bg-gamboge-300 hover:bg-gamboge-400 text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                        >
                            Buy a Line →
                        </Link>
                    </div>

                </div>

            </div>
        </div>
    </AppLayout>
</template>
