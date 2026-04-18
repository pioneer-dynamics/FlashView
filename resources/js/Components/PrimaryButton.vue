<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent dark:border-gamboge-500/30 rounded-md font-semibold text-xs text-white dark:text-gamboge-900 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-gamboge-300 dark:hover:text-gray-900 dark:hover:shadow-neon-cyan-sm focus:bg-gamboge-700 dark:focus:bg-gamboge-300 active:bg-gamboge-900 dark:active:bg-gamboge-400 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
