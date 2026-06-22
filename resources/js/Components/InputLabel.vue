<script setup lang="ts">
interface Props {
    value?: string;
}

defineProps<Props>();
</script>

<template>
    <label class="block font-medium text-sm text-gray-700 dark:text-gray-200">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
