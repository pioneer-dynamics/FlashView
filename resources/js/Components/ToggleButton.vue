<script setup>
    const props = defineProps({
        modelValue: String,
        options: Array,
    })

    defineEmits(['update:modelValue']);
</script>
<template>
    <div class="flex flex-row mb-4">
        <div class="flex flex-row gap-0 rounded-md bg-gray-200 dark:bg-gray-700">
            <button
                v-for="option in options"
                :key="option.value"
                @click="() => $emit('update:modelValue', option.value)"
                :class="option.value == modelValue
                    ? 'bg-gamboge-500 text-white dark:bg-gamboge-600 dark:text-white'
                    : 'text-gray-700 dark:text-gray-300'"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest focus:outline-none disabled:opacity-50 transition ease-in-out duration-150 justify-center p-2"
                >
                    {{ option.label }}
            </button>
        </div>
    </div>
</template>