import { ref, readonly } from 'vue';
import { FlashViewClient } from '@pioneer-dynamics/flashview-crypto/api';
import { getToken, getServerUrl } from '@/services/storage';

interface ExpiryOption {
    value: number;
    label: string;
}

export interface SenderIdentity {
    type: string;
    company_name: string | null;
    email: string | null;
    include_by_default: boolean;
}

interface ServerConfig {
    maxMessageLength: number;
    expiryOptions: ExpiryOption[];
    senderIdentity: SenderIdentity | null;
}

const DEFAULT_CONFIG: ServerConfig = {
    maxMessageLength: 10000,
    expiryOptions: [
        { value: 5, label: '5 minutes' },
        { value: 30, label: '30 minutes' },
        { value: 60, label: '1 hour' },
        { value: 240, label: '4 hours' },
        { value: 720, label: '12 hours' },
        { value: 1440, label: '1 day' },
        { value: 4320, label: '3 days' },
        { value: 10080, label: '7 days' },
    ],
    senderIdentity: null,
};

const config = ref<ServerConfig>(DEFAULT_CONFIG);
let fetchPromise: Promise<void> | null = null;

export function useServerConfig() {
    async function fetchConfig(): Promise<void> {
        if (fetchPromise) {
            return fetchPromise;
        }

        fetchPromise = (async () => {
            try {
                const [token, serverUrl] = await Promise.all([getToken(), getServerUrl()]);
                const raw = await FlashViewClient.fetchConfig(serverUrl, token);

                config.value = {
                    maxMessageLength: raw.max_message_length ?? DEFAULT_CONFIG.maxMessageLength,
                    expiryOptions: raw.expiry_options ?? DEFAULT_CONFIG.expiryOptions,
                    senderIdentity: raw.sender_identity ?? null,
                };
            } catch {
                // Fall back to defaults on error — do not crash the app
            }
        })();

        return fetchPromise;
    }

    return {
        config: readonly(config),
        fetchConfig,
    };
}
