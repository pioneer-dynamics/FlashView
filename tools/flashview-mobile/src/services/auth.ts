import { Browser } from '@capacitor/browser';
import { App } from '@capacitor/app';
import { getToken, setToken, clearToken, getServerUrl } from './storage';

// Custom scheme deep-link — triggers appUrlOpen in Capacitor on both platforms.
// Must be registered in AndroidManifest.xml and iOS Info.plist.
const REDIRECT_URI = 'com.pioneerdynamics.flashview://auth/callback';

export async function loginWithBrowser(): Promise<void> {
    const serverUrl = await getServerUrl();
    const state = generateState();
    const redirectUri = REDIRECT_URI;

    const authorizeUrl = `${serverUrl}/cli/authorize?` + new URLSearchParams({
        redirect_uri: redirectUri,
        state,
        name: 'FlashView Mobile',
        client_type: 'mobile',
    }).toString();

    return new Promise((resolve, reject) => {
        const listener = App.addListener('appUrlOpen', async (event) => {
            listener.then((l) => l.remove());
            await Browser.close();

            try {
                const url = new URL(event.url);
                const error = url.searchParams.get('error');

                if (error === 'no_api_access') {
                    reject(new Error('API_ACCESS_REQUIRED'));
                    return;
                }

                if (error) {
                    reject(new Error('Authorization denied.'));
                    return;
                }

                const returnedState = url.searchParams.get('state');
                if (returnedState !== state) {
                    reject(new Error('State mismatch. Please try again.'));
                    return;
                }

                const code = url.searchParams.get('code');
                if (!code) {
                    reject(new Error('No authorization code received.'));
                    return;
                }

                const token = await exchangeCode(serverUrl, code, state);
                await setToken(token);
                resolve();
            } catch (err) {
                reject(err);
            }
        });

        Browser.open({ url: authorizeUrl }).catch(reject);
    });
}

async function exchangeCode(serverUrl: string, code: string, state: string): Promise<string> {
    const response = await fetch(`${serverUrl}/cli/token`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ code, state }),
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error((data as { message?: string }).message || `Token exchange failed (HTTP ${response.status})`);
    }

    const data = await response.json() as { token: string };
    return data.token;
}

export async function logout(): Promise<void> {
    await clearToken();
}

export async function hasValidToken(): Promise<boolean> {
    const token = await getToken();
    return !!token;
}

function generateState(): string {
    const array = new Uint8Array(32);
    globalThis.crypto.getRandomValues(array);
    return Array.from(array, (b) => b.toString(16).padStart(2, '0')).join('');
}
