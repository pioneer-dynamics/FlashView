import { ref } from 'vue';
import { App } from '@capacitor/app';
import { getPendingShare, clearPendingShare } from '@/services/storage';

const sharedText = ref<string | null>(null);

async function loadPendingShare(): Promise<void> {
    const pending = await getPendingShare();
    if (pending) {
        sharedText.value = pending;
    }
}

export async function initShareIntent(): Promise<void> {
    await loadPendingShare();

    // Fired by MainActivity.onNewIntent when the app is already open.
    window.addEventListener('shareIntentReceived', () => {
        loadPendingShare();
    });

    // Covers the case where the share intent arrives while the app is backgrounded.
    App.addListener('resume', () => {
        loadPendingShare();
    });
}

export function useShareIntent() {
    function clearSharedContent(): void {
        sharedText.value = null;
        clearPendingShare();
    }

    return {
        sharedText,
        clearSharedContent,
    };
}
