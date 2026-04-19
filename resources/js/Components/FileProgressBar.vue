<script setup>
import { computed } from 'vue';

const props = defineProps({
    state: {
        type: String,
        required: true,
        validator: (v) => ['encrypting', 'uploading', 'decrypting', 'downloading'].includes(v),
    },
    progress: {
        type: Number,
        default: 0,
    },
});

const label = computed(() => {
    switch (props.state) {
        case 'encrypting': return 'Encrypting file...';
        case 'uploading': return `Uploading...${props.progress > 0 ? ' ' + props.progress + '%' : ''}`;
        case 'decrypting': return 'Decrypting file...';
        case 'downloading': return `Downloading...${props.progress > 0 ? ' ' + props.progress + '%' : ''}`;
    }
});

const isDeterminate = computed(() => props.progress > 0);
</script>

<template>
    <div class="w-full space-y-1">
        <div class="flex justify-between items-center">
            <span class="font-mono text-xs tracking-widest uppercase text-gamboge-300">
                {{ label }}
            </span>
        </div>
        <div class="relative h-1.5 w-full rounded-sm bg-gray-100 dark:bg-gray-800 border border-gamboge-300/30 dark:border-gamboge-300/20 overflow-hidden">
            <div v-if="isDeterminate"
                 class="absolute inset-y-0 left-0 bg-gamboge-300 rounded-sm transition-all duration-300 ease-out shadow-neon-cyan"
                 :style="{ width: progress + '%' }">
                <div class="absolute inset-0 shadow-neon-cyan animate-pulse opacity-60" />
            </div>
            <div v-else class="absolute inset-y-0 left-0 w-full bg-gray-200 dark:bg-gray-700">
                <div class="absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-gamboge-300 to-transparent animate-shimmer" />
            </div>
        </div>
    </div>
</template>
