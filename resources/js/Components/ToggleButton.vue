<script setup>
    const props = defineProps({
        modelValue: String,
        options: Array,
    })

    defineEmits(['update:modelValue']);
</script>
<template>
    <div class="flex flex-row mb-4">
        <div class="flex flex-row gap-0 rounded-md bg-white">
            <button 
                v-for="option in options" 
                @click="() => $emit('update:modelValue', option.value)" 
                :class="{ 'bg-gamboge-300 dark:bg-gamboge-800': option.value == modelValue }"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-gamboge-800 dark:text-gamboge-200 uppercase tracking-widest focus:outline-none disabled:opacity-50 transition ease-in-out duration-150 justify-center p-2 "
                >
                    {{ option.label }}
            </button>
        </div>
    </div>
</template>