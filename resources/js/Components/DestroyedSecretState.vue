<script setup>
import { computed } from 'vue';
import Alert from '@/Components/Alert.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    reason: {
        type: String,
        default: 'wrong-password',
        validator: (value) => ['wrong-password', 'unavailable'].includes(value),
    },
});

const label = computed(() => props.reason === 'wrong-password'
    ? 'Secret destroyed'
    : 'Secret unavailable');

const headline = computed(() => props.reason === 'wrong-password'
    ? 'Wrong password — this secret has been permanently destroyed.'
    : 'This secret is no longer available.');

const body = computed(() => props.reason === 'wrong-password'
    ? 'Secrets self-destruct after one retrieval attempt — even on a wrong password. If you still need this information, ask the person who sent you this link to create a new one.'
    : 'This link is no longer valid. It may have expired, or it has already been opened. If you still need this information, ask the person who sent you this link to create a new one.');
</script>

<template>
    <div class="space-y-4">
        <Alert type="Error" hide-title>
            <div class="space-y-2">
                <p class="font-mono text-xs uppercase tracking-widest text-gamboge-300">
                    {{ label }}
                </p>
                <p class="font-semibold">
                    {{ headline }}
                </p>
                <p>
                    {{ body }}
                </p>
            </div>
        </Alert>

        <div>
            <PrimaryButton class="shadow-neon-cyan-sm" :href="route('welcome')">
                Return to FlashView
            </PrimaryButton>
        </div>
    </div>
</template>
