import { Preferences } from '@capacitor/preferences';

const TOKEN_KEY = 'flashview_token';
const SERVER_URL_KEY = 'flashview_server_url';
const PENDING_SHARE_KEY = 'flashview_pending_share';
const DEFAULT_SERVER_URL = 'https://flashview.link';

export async function getToken(): Promise<string | null> {
    const { value } = await Preferences.get({ key: TOKEN_KEY });
    return value;
}

export async function setToken(token: string): Promise<void> {
    await Preferences.set({ key: TOKEN_KEY, value: token });
}

export async function clearToken(): Promise<void> {
    await Preferences.remove({ key: TOKEN_KEY });
}

export async function getServerUrl(): Promise<string> {
    const { value } = await Preferences.get({ key: SERVER_URL_KEY });
    return value || DEFAULT_SERVER_URL;
}

export async function setServerUrl(url: string): Promise<void> {
    await Preferences.set({ key: SERVER_URL_KEY, value: url });
}

export async function getPendingShare(): Promise<string | null> {
    const { value } = await Preferences.get({ key: PENDING_SHARE_KEY });
    return value;
}

export async function setPendingShare(text: string): Promise<void> {
    await Preferences.set({ key: PENDING_SHARE_KEY, value: text });
}

export async function clearPendingShare(): Promise<void> {
    await Preferences.remove({ key: PENDING_SHARE_KEY });
}
