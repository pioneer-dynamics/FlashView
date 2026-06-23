<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { encryption } from '../encryption';
import FileProgressBar from '@/Components/FileProgressBar.vue';

interface Props {
    decryptUrl: string;
    password?: string | null;
    fileMimeType?: string | null;
    fileSize?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    password: null,
    fileMimeType: null,
    fileSize: null,
});

const emit = defineEmits<{
    success: [];
    failure: [reason: string];
    'state-change': [state: 'encrypting' | 'uploading' | 'decrypting' | 'downloading' | null];
}>();

const decryptForm = useForm({});
const fileDecryptState = ref<'encrypting' | 'uploading' | 'decrypting' | 'downloading' | null>(null);
const fileDecryptProgress = ref(0);

watch(fileDecryptState, (next) => emit('state-change', next));

const humanFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const executeDownload = async (flash: Record<string, string> | null | undefined): Promise<void> => {
    if (!flash?.file_download_url) {
        fileDecryptState.value = null;
        emit('failure', 'unavailable');
        return;
    }

    try {
        const response = await fetch(flash.file_download_url);
        if (!response.ok) {
            throw new Error('download_failed');
        }

        fileDecryptProgress.value = 0;
        const contentLength = response.headers.get('Content-Length');
        const totalBytes = contentLength ? parseInt(contentLength) : 0;
        let receivedBytes = 0;
        const chunks: Uint8Array[] = [];
        const reader = response.body!.getReader();

        while (true) {
            const { done, value } = await reader.read();
            if (done) { break; }
            chunks.push(value);
            receivedBytes += value.length;
            if (totalBytes > 0) {
                fileDecryptProgress.value = Math.round((receivedBytes / totalBytes) * 100);
            }
        }

        const encryptedBytes = new Uint8Array(receivedBytes);
        let offset = 0;
        for (const chunk of chunks) {
            encryptedBytes.set(chunk, offset);
            offset += chunk.length;
        }

        fileDecryptState.value = 'decrypting';

        // Confirm only after the full binary is in memory so S3 deletion is safe.
        if (flash.file_confirm_url) {
            router.post(flash.file_confirm_url, {}, { preserveState: true, preserveScroll: true });
        }

        const e = new encryption();
        const decryptedBytes = await e.decryptFile(encryptedBytes, props.password);
        const originalFilename = await e.decryptMessage(flash.file_original_name, props.password);

        const blob = new Blob([decryptedBytes as BlobPart]);
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
        emit('failure', 'wrong-password');
    }
};

const triggerDecrypt = async (): Promise<void> => {
    fileDecryptState.value = 'downloading';

    let retrieveFailed = false;
    await new Promise<void>((resolve, reject) => {
        decryptForm.get(props.decryptUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('retrieve_failed')),
        });
    }).catch(() => {
        fileDecryptState.value = null;
        emit('failure', 'unavailable');
        retrieveFailed = true;
    });

    if (retrieveFailed) { return; }

    const flash = usePage<PageProps>().props.jetstream.flash?.secret;
    await executeDownload(flash as Record<string, string> | null | undefined);
};

const startDownload = async (flash: Record<string, string> | null | undefined): Promise<void> => {
    fileDecryptState.value = 'downloading';
    await executeDownload(flash);
};

defineExpose({ triggerDecrypt, startDownload });
</script>

<template>
    <div class="space-y-3">
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
            <FileProgressBar v-if="fileDecryptState" :state="fileDecryptState" :progress="fileDecryptState === 'downloading' ? fileDecryptProgress : 0" />
        </div>
    </div>
</template>
