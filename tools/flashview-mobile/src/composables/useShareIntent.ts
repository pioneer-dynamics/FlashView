import { ref } from 'vue';
import { App } from '@capacitor/app';
import { getPendingShare, setPendingShare, clearPendingShare } from '@/services/storage';

const sharedText = ref<string | null>(null);

export async function initShareIntent(): Promise<void> {
    // Restore any text cached before a login redirect
    const pending = await getPendingShare();
    if (pending) {
        sharedText.value = pending;
    }

    // Android: handle incoming share intent on app launch/resume
    App.addListener('appUrlOpen', async (event) => {
        // Deep-link URL from Android intent — data is passed via query param
        const url = new URL(event.url);
        const text = url.searchParams.get('text');
        if (text) {
            sharedText.value = text;
            await setPendingShare(text);
        }
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
