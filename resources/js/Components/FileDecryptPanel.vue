<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { encryption } from '../../encryption';
import FileProgressBar from '@/Components/FileProgressBar.vue';
import Alert from '@/Components/Alert.vue';

const props = defineProps({
    decryptUrl: { type: String, required: true },
    password: { type: String, default: null },
    fileMimeType: { type: String, default: null },
    fileSize: { type: Number, default: null },
});

const emit = defineEmits(['success']);

const decryptForm = useForm({});
const fileDecryptState = ref(null);
const fileDecryptError = ref(null);

const humanFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const triggerDecrypt = async () => {
    fileDecryptError.value = null;
    fileDecryptState.value = 'downloading';

    await new Promise((resolve, reject) => {
        decryptForm.get(props.decryptUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('retrieve_failed')),
        });
    }).catch(() => {
        fileDecryptState.value = null;
        fileDecryptError.value = 'Could not retrieve the file. It may have already been downloaded or has expired.';
        return;
    });

    const flash = usePage().props.jetstream.flash?.secret;
    if (!flash?.file_download_url) {
        fileDecryptState.value = null;
        fileDecryptError.value = 'Could not retrieve the file. It may have already been downloaded or has expired.';
        return;
    }

    try {
        const response = await fetch(flash.file_download_url);
        if (!response.ok) {
            throw new Error('download_failed');
        }

        fileDecryptState.value = 'decrypting';

        const arrayBuffer = await response.arrayBuffer();
        const encryptedBytes = new Uint8Array(arrayBuffer);

        const e = new encryption();
        const decryptedBytes = await e.decryptFile(encryptedBytes, props.password);
        const originalFilename = await e.decryptMessage(flash.file_original_name, props.password);

        const blob = new Blob([decryptedBytes]);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = originalFilename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        emit('success');
        fileDecryptState.value = null;
    } catch {
        fileDecryptState.value = null;
        fileDecryptError.value = 'The password is incorrect — the file has been permanently deleted. Please ask the sender to share it again.';
    }
};

defineExpose({ triggerDecrypt });
</script>

<template>
    <div class="space-y-3">
        <Alert v-if="fileDecryptError" hide-title type="Error">{{ fileDecryptError }}</Alert>
        <div class="mt-1 p-4 rounded-md bg-gray-50 dark:bg-gray-800 border border-gamboge-300/30 dark:border-gamboge-300/20 space-y-2">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8 text-gamboge-300 shrink-0">
                    <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625ZM7.5 15a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 7.5 15Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H8.25Z" clip-rule="evenodd" />
                    <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                </svg>
                <div>
                    <p class="font-mono text-xs tracking-widest text-gamboge-300 uppercase">Encrypted File</p>
                    <p v-if="fileMimeType" class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">{{ fileMimeType }}</p>
                    <p v-if="fileSize" class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ humanFileSize(fileSize) }}</p>
                </div>
            </div>
            <FileProgressBar v-if="fileDecryptState" :state="fileDecryptState" />
        </div>
    </div>
</template>
