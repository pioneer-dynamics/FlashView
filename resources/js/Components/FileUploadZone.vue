<script setup>
import FileProgressBar from '@/Components/FileProgressBar.vue';
import InputError from '@/Components/InputError.vue';
import { ref, watch, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: Object, default: null },
    fileError: { type: String, default: null },
    maxFileUploadSizeMb: { type: Number, required: true },
    allowedMimeTypes: { type: Array, default: () => [] },
    uploadState: { type: String, default: null },
    uploadProgress: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'update:fileError']);

const humanFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const onFileSelected = (event) => {
    const file = event.target.files[0];
    if (!file) { return; }

    emit('update:fileError', null);

    const maxBytes = props.maxFileUploadSizeMb * 1024 * 1024;
    if (file.size > maxBytes) {
        emit('update:fileError', `File exceeds the maximum allowed size of ${props.maxFileUploadSizeMb} MB.`);
        event.target.value = '';
        return;
    }

    if (props.allowedMimeTypes.length > 0 && !props.allowedMimeTypes.includes(file.type)) {
        emit('update:fileError', 'This file type is not supported.');
        event.target.value = '';
        return;
    }

    emit('update:modelValue', file);
};

const clearFile = () => {
    emit('update:modelValue', null);
    emit('update:fileError', null);
};

const scrambleChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
const scrambledName = ref('');
let scrambleTimer = null;

const stopScramble = (clear = false) => {
    if (scrambleTimer) {
        clearInterval(scrambleTimer);
        scrambleTimer = null;
    }
    if (clear) { scrambledName.value = ''; }
};

watch(() => props.uploadState, (state) => {
    if (state === 'encrypting' && props.modelValue?.name) {
        const name = props.modelValue.name;
        scrambleTimer = setInterval(() => {
            scrambledName.value = name
                .split('')
                .map((c) => (c === ' ' || c === '.') ? c : scrambleChars[Math.floor(Math.random() * scrambleChars.length)])
                .join('');
        }, 40);
    } else if (state === null) {
        stopScramble(true);
    } else {
        stopScramble(false);
    }
});

onUnmounted(() => stopScramble(true));
</script>

<template>
    <div>
        <div v-if="modelValue" class="mt-2 flex items-center gap-3 p-3 rounded-md bg-gray-50 dark:bg-gray-800 border border-gamboge-300/30 dark:border-gamboge-300/20">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5 text-gamboge-300 shrink-0">
                <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625Z" clip-rule="evenodd" />
            </svg>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-mono text-gamboge-300 truncate">{{ scrambledName || modelValue.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ humanFileSize(modelValue.size) }}</p>
            </div>
            <button type="button" @click="clearFile" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
        <div v-else class="mt-2">
            <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-300 hover:text-gamboge-300 dark:hover:text-gamboge-300 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 text-gamboge-300">
                    <path fill-rule="evenodd" d="M18.97 3.659a2.25 2.25 0 0 0-3.182 0l-10.94 10.94a3.75 3.75 0 1 0 5.304 5.303l7.693-7.693a.75.75 0 0 1 1.06 1.06l-7.693 7.693a5.25 5.25 0 1 1-7.424-7.424l10.939-10.94a3.75 3.75 0 1 1 5.303 5.304L9.097 18.835l-.008.008-.007.007-.002.003-.003.002A2.25 2.25 0 0 1 5.91 15.66l7.81-7.81a.75.75 0 0 1 1.061 1.06l-7.81 7.81a.75.75 0 0 0 1.054 1.068L18.97 6.84a2.25 2.25 0 0 0 0-3.182Z" clip-rule="evenodd" />
                </svg>
                Attach a file
                <input type="file" class="sr-only" :accept="allowedMimeTypes.join(',')" @change="onFileSelected" />
            </label>
            <p class="text-sm text-gray-400 dark:text-gray-400 mt-1">
                Max: {{ maxFileUploadSizeMb }} MB &middot; PDF, ZIP, images, Office docs, audio/video
            </p>
            <InputError :message="fileError" class="mt-1" />
        </div>
        <div class="mt-2" v-if="uploadState">
            <FileProgressBar :state="uploadState" :progress="uploadProgress" />
        </div>
    </div>
</template>
