<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="placeholder-gray-400 dark:placeholder-gray-300 border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-200 focus:border-gray-500 dark:focus:border-gray-600 focus:ring-gray-500 dark:focus:ring-gray-600 rounded-md shadow-sm"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
