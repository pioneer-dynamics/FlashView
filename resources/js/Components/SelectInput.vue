<script setup lang="ts">
import { onMounted, ref } from 'vue';

interface SelectOption {
    value: string | number;
    label: string;
}

interface Props {
    modelValue?: string | number;
    options?: SelectOption[];
}

defineProps<Props>();

defineEmits<{ 'update:modelValue': [value: string | number] }>();

const input = ref<HTMLSelectElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <select
        ref="input"
        class="border-gray-300 dark:border-gamboge-300/40 dark:bg-gray-800 dark:text-gray-200 focus:border-gamboge-500 dark:focus:border-gamboge-500 focus:ring-gamboge-500 dark:focus:ring-gamboge-500 rounded-md shadow-sm"
        :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
</template>
