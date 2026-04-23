import { ref } from 'vue';
import { App } from '@capacitor/app';
import { getPendingShare, clearPendingShare } from '@/services/storage';

const sharedText = ref<string | null>(null);

function setSharedText(raw: string): void {
    // Some Android apps or CharSequence types wrap the shared text in double quotes.
    // Strip them only when both the opening and closing quote are present.
    const trimmed = raw.trim();
    if (trimmed.length >= 2 && trimmed.startsWith('"') && trimmed.endsWith('"')) {
        sharedText.value = trimmed.slice(1, -1);
    } else {
        sharedText.value = trimmed;
    }
}

async function loadPendingShare(): Promise<void> {
    const pending = await getPendingShare();
    if (pending) {
        setSharedText(pending);
    }
}

export async function initShareIntent(): Promise<void> {
    // Cold start: WebView wasn't loaded when the share intent arrived,
    // so the text was written to SharedPreferences — read it now.
    await loadPendingShare();

    // Warm start: MainActivity.onNewIntent passes the text directly in the event
    // payload, bypassing SharedPreferences entirely.
    window.addEventListener('shareIntentReceived', (event: Event) => {
        const text = (event as CustomEvent<{ text?: string }>).detail?.text;
        if (text) {
            setSharedText(text);
        } else {
            // Fallback if payload is absent (e.g. older APK build).
            loadPendingShare();
        }
    });

    // Covers the case where the app was backgrounded after a share intent arrived
    // but before the event was processed.
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
