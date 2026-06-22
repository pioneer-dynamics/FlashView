<script setup lang="ts">
import { onMounted, ref } from 'vue';

interface Props {
    modelValue?: string | number | null;
}

defineProps<Props>();

defineEmits<{ 'update:modelValue': [value: string] }>();

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        ref="input"
        class="placeholder-gray-400 dark:placeholder-gray-400 border-gray-300 dark:border-gamboge-300/40 dark:bg-gray-800 dark:text-gray-200 focus:border-gamboge-500 dark:focus:border-gamboge-500 focus:ring-gamboge-500 dark:focus:ring-gamboge-500 rounded-md shadow-sm"
        :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
</template>
