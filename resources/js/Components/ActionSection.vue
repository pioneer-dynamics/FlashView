<script setup>
import SectionTitle from './SectionTitle.vue';
</script>

<template>
    <div class="md:grid md:grid-cols-3 mt-2">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="px-4 py-5 sm:p-6 bg-white dark:bg-gray-800 shadow sm:rounded-r-lg">
                <slot name="content" />
            </div>
        </div>
    </div>
</template>
