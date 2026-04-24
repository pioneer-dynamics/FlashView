import { ref, readonly } from 'vue';
import { FlashViewClient } from '@pioneer-dynamics/flashview-crypto/api';
import { getToken, getServerUrl } from '@/services/storage';

export interface UserProfile {
    name: string;
    email: string;
    profile_photo_url: string | null;
}

const profile = ref<UserProfile | null>(null);

export function useUserProfile() {
    async function fetchProfile(): Promise<void> {
        try {
            const [token, serverUrl] = await Promise.all([getToken(), getServerUrl()]);
            if (!token) {
                return;
            }
            const client = new FlashViewClient(serverUrl, token);
            const data = await client.getUser();
            profile.value = {
                name: data.name ?? '',
                email: data.email ?? '',
                profile_photo_url: data.profile_photo_url ?? null,
            };
        } catch {
            // ignore — profile display is non-critical
        }
    }

    function clearProfile(): void {
        profile.value = null;
    }

    return {
        profile: readonly(profile),
        fetchProfile,
        clearProfile,
    };
}
