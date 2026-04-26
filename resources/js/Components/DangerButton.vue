<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2 bg-transparent border border-red-400/60 rounded-md font-semibold text-xs text-red-400 uppercase tracking-widest hover:bg-red-400/10 hover:border-red-400 dark:hover:shadow-[0_0_8px_rgba(248,113,113,0.3)] focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
