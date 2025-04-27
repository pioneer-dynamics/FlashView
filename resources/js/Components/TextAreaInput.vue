<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: String,
    maxLength: {
        type: Number,
        default: 0
    },
    rows: {
        type: Number,
        default: 3
    },
    placeholder: {
        type: String,
        default: ''
    },
    autofocus: {
        type: Boolean,
        default: false
    }
});

defineEmits(['update:modelValue']);

const input = ref(null);
const base = ref(null);

onMounted(() => {
    if (props.autofocus) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });

const inputClass = computed(() => {
    
})

</script>

<template>
        <span @click="() => input.focus()" ref="base" :class="{'relative ring-0 focus-within:ring-1 border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-200 focus-within:border-gray-500 dark:focus-within:border-gray-600 focus-within:ring-gray-500 dark:focus-within:ring-gray-600 rounded-md shadow-sm': maxLength == 0 || input?.value?.length <= maxLength, 'text-red-500 relative border-red-300 dark:border-red-700 dark:bg-red-100 dark:text-red-200 focus:border-red-500 dark:focus:border-red-600 focus:ring-red-500 dark:focus:ring-red-600 rounded-md shadow-sm bg-red-50': maxLength > 0 && input?.value?.length > maxLength}">
            <textarea
                ref="input"
                class="w-full border-0 focus:ring-0 -mt-2 -ml-2 -mr-2 mb-2 dark:bg-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-300"
                :class="{'bg-red-50 dark:bg-red-100 text-red-500 dark:text-red-600': maxLength > 0 && input?.value?.length > maxLength}"
                :value="modelValue"
                :rows="rows"
                :placeholder="placeholder"
                @input="$emit('update:modelValue', $event.target.value)"
            ></textarea>
            <div v-if="maxLength > 0" class="absolute bottom-2 right-6 text-sm" :class="{ 'text-red-500': input?.value?.length > maxLength }">
                <div class="flex flex-wrap">
                    <svg xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute size-4 ml-1 top-1">
                        <path fill-rule="evenodd" d="M4.848 2.771A49.144 49.144 0 0 1 12 2.25c2.43 0 4.817.178 7.152.52 1.978.292 3.348 2.024 3.348 3.97v6.02c0 1.946-1.37 3.678-3.348 3.97a48.901 48.901 0 0 1-3.476.383.39.39 0 0 0-.297.17l-2.755 4.133a.75.75 0 0 1-1.248 0l-2.755-4.133a.39.39 0 0 0-.297-.17 48.9 48.9 0 0 1-3.476-.384c-1.978-.29-3.348-2.024-3.348-3.97V6.741c0-1.946 1.37-3.68 3.348-3.97ZM6.75 8.25a.75.75 0 0 1 .75-.75h9a.75.75 0 0 1 0 1.5h-9a.75.75 0 0 1-.75-.75Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H7.5Z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-6">{{ maxLength - input?.value?.length }}</span>
                </div>
            </div>
        </span>
</template>
