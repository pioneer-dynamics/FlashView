<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { usePoll } from '@inertiajs/vue3';
import { ref, onBeforeUnmount } from 'vue';

interface Props {
    sessionId?: string | null;
}

defineProps<Props>();

const TIMEOUT_MS = 30_000;
const timedOut = ref(false);

const { stop } = usePoll(2000);

const timeoutHandle = setTimeout(() => {
    stop();
    timedOut.value = true;
}, TIMEOUT_MS);

onBeforeUnmount(() => {
    clearTimeout(timeoutHandle);
});
</script>

<template>
    <AppLayout title="Setting Up Your Plan">
        <Page>
            <div class="flex flex-col items-center justify-center py-16 px-4 gap-8">
                <!-- Loading state -->
                <template v-if="!timedOut">
                    <div class="text-center space-y-2">
                        <p class="font-mono text-xs uppercase tracking-widest text-gamboge-300">
                            Activating Your Plan
                        </p>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            Your payment is being processed by Stripe. Please wait while we activate your plan&hellip;
                        </p>
                    </div>

                    <div class="w-full max-w-sm">
                        <div class="relative h-1.5 w-full rounded-sm bg-gray-100 dark:bg-gray-800 border border-gamboge-300/30 dark:border-gamboge-300/20 overflow-hidden">
                            <div class="absolute inset-y-0 left-0 w-full bg-gray-200 dark:bg-gray-700">
                                <div class="absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-gamboge-300 to-transparent animate-shimmer" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Timeout state -->
                <template v-else>
                    <div class="text-center space-y-3 max-w-sm">
                        <p class="font-mono text-xs uppercase tracking-widest text-gamboge-300">
                            Taking Longer Than Expected
                        </p>
                        <p class="text-gray-600 dark:text-gray-300 text-sm">
                            Your payment is being processed and your plan will activate shortly. You can safely go to the dashboard &mdash; your account will update automatically. If your plan has not updated after a few minutes, please contact support.
                        </p>
                        <PrimaryButton :href="route('dashboard')" class="w-full justify-center">
                            Go to Dashboard
                        </PrimaryButton>
                    </div>
                </template>
            </div>
        </Page>
    </AppLayout>
</template>
