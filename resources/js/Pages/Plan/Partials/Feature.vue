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
    <li class="flex items-center relative" v-if="feature.type == 'feature'">
        <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 absolute top-1 -left-2" aria-hidden="true"
            xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path
                d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
        </svg>
        <span class="text-base font-normal leading-tight text-gray-500 dark:text-gray-400 ms-3">
            {{ feature.label }}
        </span>
    </li>
    <li class="flex line-through decoration-gray-500 relative" v-else-if="feature.type == 'missing'">
        <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500  absolute top-1 -left-2" aria-hidden="true"
            xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path
                d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
        </svg>
        <span class="flex flex-wrap">
            <span class="text-base font-normal leading-tight text-gray-500 ms-3">{{ feature.label }}</span>
        </span>
    </li>
    <li class="flex decoration-gray-500 relative" v-else-if="feature.type == 'limit'">
        <svg xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4  absolute top-1 -left-2">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
        </svg>
        <span class="flex flex-wrap">
            <span class="text-base font-normal leading-tight text-gray-500 ms-3">{{ feature.label }}</span>
        </span>
    </li>
</template>