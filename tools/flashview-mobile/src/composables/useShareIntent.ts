import { ref } from 'vue';
import { App } from '@capacitor/app';
import { getPendingShare, clearPendingShare, getPendingShareFile, clearPendingShareFile } from '@/services/storage';

export interface SharedFile {
    name: string;
    mimeType: string;
    size: number;
    data: string; // base64-encoded bytes
}

const sharedText = ref<string | null>(null);
const sharedFile = ref<SharedFile | null>(null);

function setSharedText(raw: string): void {
    // Some Android apps wrap shared text in double quotes — strip them when both are present.
    const trimmed = raw.trim();
    if (trimmed.length >= 2 && trimmed.startsWith('"') && trimmed.endsWith('"')) {
        sharedText.value = trimmed.slice(1, -1);
    } else {
        sharedText.value = trimmed;
    }
}

function setSharedFile(filename: string, mimeType: string, size: number, base64: string): void {
    sharedFile.value = { name: filename, mimeType, size, data: base64 };
}

async function loadPendingShare(): Promise<void> {
    const pending = await getPendingShare();
    if (pending) {
        setSharedText(pending);
    }

    const pendingFile = await getPendingShareFile();
    if (pendingFile) {
        try {
            const { filename, mimeType, size, data } = JSON.parse(pendingFile);
            setSharedFile(filename, mimeType, size, data);
        } catch {
            // Malformed preference — ignore
        }
    }
}

export async function initShareIntent(): Promise<void> {
    // Cold start: WebView wasn't loaded when the share intent arrived —
    // Java wrote to SharedPreferences, read it now.
    await loadPendingShare();

    // Warm start: MainActivity.onNewIntent delivers the payload directly via event.
    window.addEventListener('shareIntentReceived', (event: Event) => {
        const detail = (event as CustomEvent<{ text?: string; file?: { filename: string; mimeType: string; size: number; data: string } }>).detail;
        if (detail?.file) {
            const { filename, mimeType, size, data } = detail.file;
            setSharedFile(filename, mimeType, size, data);
        } else if (detail?.text) {
            setSharedText(detail.text);
        } else {
            loadPendingShare();
        }
    });

    App.addListener('resume', () => {
        loadPendingShare();
    });
}

export function useShareIntent() {
    function clearSharedContent(): void {
        sharedText.value = null;
        sharedFile.value = null;
        clearPendingShare();
        clearPendingShareFile();
    }

    return {
        sharedText,
        sharedFile,
        clearSharedContent,
    };
}
