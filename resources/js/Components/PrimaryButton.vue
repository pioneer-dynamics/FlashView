<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    href: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <Link v-if="href" :href="href" class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-transparent border border-transparent dark:border-gamboge-300 rounded-md font-semibold text-xs text-white dark:text-gamboge-300 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-gamboge-300 dark:hover:text-gray-900 dark:hover:shadow-neon-cyan-sm focus:bg-gamboge-700 dark:focus:bg-gamboge-300 dark:focus:text-gray-900 active:bg-gamboge-900 dark:active:bg-gamboge-300 dark:active:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </Link>
    <button v-else :type="type" class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-transparent border border-transparent dark:border-gamboge-300 rounded-md font-semibold text-xs text-white dark:text-gamboge-300 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-gamboge-300 dark:hover:text-gray-900 dark:hover:shadow-neon-cyan-sm focus:bg-gamboge-700 dark:focus:bg-gamboge-300 dark:focus:text-gray-900 active:bg-gamboge-900 dark:active:bg-gamboge-300 dark:active:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
