<script setup>
    import { ref } from 'vue';

    const props = defineProps({
        value: String
    })

    const text = ref(null)
    const copied = ref(false)

    const copyText = (data) => {
        navigator.clipboard.writeText(data);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000)
    }
</script>
<template>

<div class="w-full">
    <div class="relative">
        <label for="npm-install-copy-button" class="sr-only">Copyable content</label>
        <code ref="text" class="col-span-6 bg-gray-50 border border-gray-300 text-gray-700 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-gray-200 dark:focus:ring-blue-500 dark:focus:border-blue-500" disabled readonly>
            <slot>{{ props.value }}</slot>
        </code>
        <button type="button" @click.prevent="copyText(text.innerText)" class="group absolute end-2 bottom-1 translate-y-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg p-1.5 mb-1 inline-flex items-center justify-center transition-colors">
            <span v-if="!copied">
                <svg class="w-4 h-4" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                    <path d="M16 1h-3.278A1.992 1.992 0 0 0 11 0H7a1.993 1.993 0 0 0-1.722 1H2a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-3 14H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 1 1 0 2Zm0-5H5a1 1 0 0 1 0-2h2V2h4v2h2a1 1 0 1 1 0 2Z"/>
                </svg>
            </span>
            <span class="inline-flex items-center" v-if="copied">
                <svg class="w-4 h-4 text-green-600 dark:text-green-400" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 12">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5.917 5.724 10.5 15 1.5"/>
                </svg>
            </span>
            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs font-medium text-white bg-gray-900 dark:bg-gray-600 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap">
                {{ copied ? 'Copied!' : 'Copy to clipboard' }}
            </span>
        </button>
    </div>
</div>
</template>
