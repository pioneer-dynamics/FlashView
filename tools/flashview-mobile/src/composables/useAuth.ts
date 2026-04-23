import { ref, readonly } from 'vue';
import { useRouter } from 'vue-router';
import { getToken, getServerUrl } from '@/services/storage';
import { loginWithBrowser, logout as authLogout } from '@/services/auth';
import { FlashViewClient } from '@pioneer-dynamics/flashview-crypto/api';

const isAuthenticated = ref(false);
const isInitialised = ref(false);

export async function initAuth(): Promise<void> {
    const token = await getToken();
    isAuthenticated.value = !!token;
    isInitialised.value = true;
}

export function useAuth() {
    const router = useRouter();

    async function getClient(): Promise<FlashViewClient> {
        const [token, serverUrl] = await Promise.all([getToken(), getServerUrl()]);
        if (!token) {
            throw new Error('Not authenticated');
        }
        return new FlashViewClient(serverUrl, token);
    }

    async function login(): Promise<void> {
        await loginWithBrowser();
        isAuthenticated.value = true;
    }

    async function logout(): Promise<void> {
        await authLogout();
        isAuthenticated.value = false;
        await router.replace({ name: 'login' });
    }

    async function reAuthenticate(): Promise<void> {
        await authLogout();
        isAuthenticated.value = false;
        await router.replace({ name: 'login' });
    }

    return {
        isAuthenticated: readonly(isAuthenticated),
        isInitialised: readonly(isInitialised),
        getClient,
        login,
        logout,
        reAuthenticate,
    };
}
