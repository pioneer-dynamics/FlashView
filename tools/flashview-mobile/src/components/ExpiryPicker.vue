<script setup lang="ts">
import { ref, computed } from 'vue'

interface ExpiryOption {
    value: number
    label: string
}

const props = defineProps<{
    modelValue: number
    options: readonly ExpiryOption[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: number]
}>()

const expanded = ref(false)

const selectedLabel = computed(() => {
    return props.options.find((o) => o.value === props.modelValue)?.label ?? '1 day'
})

function select(value: number): void {
    emit('update:modelValue', value)
    expanded.value = false
}
</script>

<template>
    <div>
        <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-1">Expires in</p>

        <button
            type="button"
            @click="expanded = !expanded"
            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-sm text-gray-100 hover:border-gamboge-300 transition-colors"
        >
            <span class="font-mono">{{ selectedLabel }}</span>
            <span class="text-gray-400 text-xs">{{ expanded ? '▲' : '▼' }}</span>
        </button>

        <div v-if="expanded" class="mt-1 rounded-lg bg-gray-800 border border-gray-700 overflow-hidden">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                @click="select(option.value)"
                class="w-full text-left px-3 py-2 text-sm transition-colors hover:bg-gray-700"
                :class="option.value === modelValue ? 'text-gamboge-300 font-medium' : 'text-gray-300'"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
