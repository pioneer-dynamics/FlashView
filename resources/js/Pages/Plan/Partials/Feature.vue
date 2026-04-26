<script setup>
    defineProps({
        feature: Object,
        type: {
            type: String,
            default: 'feature'
        }
    })
</script>
<template>
    <!-- Included feature — bright text, cyan checkmark -->
    <li class="flex items-center relative" v-if="feature.type == 'feature' || feature.type == 'limit'">
        <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 absolute top-1 -left-2" aria-hidden="true"
            xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path
                d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
        </svg>
        <span class="text-base font-normal leading-tight text-gray-700 dark:text-gray-200 ms-3">
            {{ feature.label }}
        </span>
    </li>
    <!-- Missing feature — muted text, X icon -->
    <li class="flex line-through decoration-gray-500 relative" v-else-if="feature.type == 'missing'">
        <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500 absolute top-1 -left-2" aria-hidden="true"
            xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path
                d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 11.793a1 1 0 1 1-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 0 1-1.414-1.414L8.586 10 6.293 7.707a1 1 0 0 1 1.414-1.414L10 8.586l2.293-2.293a1 1 0 0 1 1.414 1.414L11.414 10l2.293 2.293Z" />
        </svg>
        <span class="flex flex-wrap">
            <span class="text-base font-normal leading-tight text-gray-500 dark:text-gray-500 ms-3">{{ feature.label }}</span>
        </span>
    </li>

</template>
