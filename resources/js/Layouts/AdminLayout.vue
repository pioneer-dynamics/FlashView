<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: String,
});
</script>

<template>
    <AppLayout :title="title">
        <template #header>
            <div class="flex items-center gap-3">
                <span class="text-xs uppercase tracking-widest text-gamboge-300 font-mono border border-gamboge-300/40 rounded px-2 py-0.5 dark:shadow-neon-cyan-sm">
                    Admin
                </span>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    <slot name="title">{{ title }}</slot>
                </h2>
            </div>
        </template>

        <slot />
    </AppLayout>
</template>
