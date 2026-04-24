<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { encryptMessage } from '@pioneer-dynamics/flashview-crypto'
import { useAuth } from '@/composables/useAuth'
import { useServerConfig } from '@/composables/useServerConfig'
import { useShareIntent } from '@/composables/useShareIntent'
import MobileLayout from '@/layouts/MobileLayout.vue'
import ExpiryPicker from '@/components/ExpiryPicker.vue'

const router = useRouter()
const { getClient, reAuthenticate } = useAuth()
const { config, fetchConfig } = useServerConfig()
const { sharedText, clearSharedContent } = useShareIntent()

const message = ref(sharedText.value ?? '')
const expiresIn = ref(1440)
const passphrase = ref('')
const useCustomPassphrase = ref(false)
// Passphrases are shown by default — they are memorable phrases, not passwords.
const showPassphrase = ref(true)
const recipientEmail = ref('')
const includeSenderIdentity = ref(false)
const isSubmitting = ref(false)
const error = ref('')

// Update the message field if a share arrives while this view is already mounted.
watch(sharedText, (text) => {
    if (text) {
        message.value = text
    }
})

// Auto-enable verified sender badge when the user has it configured with include_by_default.
watch(() => config.value.senderIdentity, (identity) => {
    if (identity?.include_by_default) {
        includeSenderIdentity.value = true
    }
}, { immediate: true })

onMounted(() => {
    fetchConfig()
})

function resetForm(): void {
    message.value = ''
    passphrase.value = ''
    useCustomPassphrase.value = false
    recipientEmail.value = ''
    error.value = ''
    // Keep includeSenderIdentity at its current value (follows user preference).
}

async function handleCreate(): Promise<void> {
    if (!message.value.trim()) {
        return
    }

    if (message.value.length > config.value.maxMessageLength) {
        error.value = `Message too long. Maximum ${config.value.maxMessageLength} characters.`
        return
    }

    if (useCustomPassphrase.value && passphrase.value.length < 8) {
        error.value = 'Passphrase must be at least 8 characters.'
        return
    }

    isSubmitting.value = true
    error.value = ''

    try {
        const result = await encryptMessage(
            message.value,
            useCustomPassphrase.value ? passphrase.value : null,
        )

        const client = await getClient()
        const response = await client.createSecret(
            result.secret,
            expiresIn.value,
            recipientEmail.value.trim() || null,
            config.value.senderIdentity ? includeSenderIdentity.value : false,
        )

        clearSharedContent()
        resetForm()

        // Pass sensitive data via router state (in-memory), not query params.
        router.push({
            name: 'secret-created',
            state: { url: response.data.url, passphrase: result.passphrase },
        })
    } catch (err: unknown) {
        const apiErr = err as { status?: number; retryAfter?: number | null; message?: string }

        if (apiErr.status === 429) {
            const wait = apiErr.retryAfter || 60
            error.value = `Please wait ${wait} seconds before creating another secret.`
        } else if (apiErr.status === 401) {
            await reAuthenticate()
        } else if (apiErr instanceof TypeError || String(apiErr.message).toLowerCase().includes('network')) {
            error.value = 'Your secret was NOT sent. Your text is still only on this device. Check your connection and try again.'
        } else {
            error.value = apiErr.message || 'Something went wrong. Please try again.'
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <h1 class="text-xs uppercase tracking-widest text-gamboge-300 mb-4">New Secret</h1>

            <div class="flex flex-col gap-4">
                <!-- Message -->
                <div>
                    <textarea
                        v-model="message"
                        placeholder="Enter your secret message…"
                        rows="6"
                        class="w-full rounded-xl bg-gray-900 border border-gray-700 px-3 py-3 text-sm text-gray-100 placeholder-gray-500 focus:border-gamboge-300 focus:outline-none resize-none transition-colors"
                    />
                    <p class="mt-1 text-xs text-gray-500 text-right font-mono">
                        {{ message.length }} / {{ config.maxMessageLength }}
                    </p>
                </div>

                <!-- Expiry -->
                <ExpiryPicker v-model="expiresIn" :options="config.expiryOptions" />

                <!-- Recipient email -->
                <div>
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-1">Recipient email (optional)</p>
                    <input
                        v-model="recipientEmail"
                        type="email"
                        placeholder="notify@example.com"
                        autocorrect="off"
                        autocapitalize="none"
                        class="w-full rounded-xl bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-gamboge-300 focus:outline-none transition-colors"
                    />
                </div>

                <!-- Custom passphrase -->
                <div>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            v-model="useCustomPassphrase"
                            class="rounded border-gray-600 bg-gray-800 text-gamboge-300 focus:ring-gamboge-300"
                        />
                        Use custom passphrase
                    </label>

                    <div v-if="useCustomPassphrase" class="mt-2 flex gap-2">
                        <input
                            v-model="passphrase"
                            :type="showPassphrase ? 'text' : 'password'"
                            placeholder="Min 8 characters"
                            autocorrect="off"
                            autocapitalize="none"
                            class="flex-1 rounded-xl bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:border-gamboge-300 focus:outline-none transition-colors font-mono"
                        />
                        <button
                            type="button"
                            @click="showPassphrase = !showPassphrase"
                            class="px-3 rounded-xl bg-gray-800 border border-gray-700 text-xs text-gray-400 hover:text-gray-200 transition-colors"
                        >
                            {{ showPassphrase ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                </div>

                <!-- Verified sender badge -->
                <div v-if="config.senderIdentity">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            v-model="includeSenderIdentity"
                            class="rounded border-gray-600 bg-gray-800 text-gamboge-300 focus:ring-gamboge-300"
                        />
                        Include my verified sender identity
                        <span class="text-gray-500 text-xs font-mono">
                            ({{ config.senderIdentity.company_name ?? config.senderIdentity.email }})
                        </span>
                    </label>
                </div>

                <p v-if="error" class="text-sm text-red-400 rounded-xl bg-red-950/30 border border-red-800/50 px-3 py-2">
                    {{ error }}
                </p>

                <button
                    @click="handleCreate"
                    :disabled="isSubmitting || !message.trim()"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity disabled:opacity-40 shadow-neon-cyan-sm"
                >
                    {{ isSubmitting ? 'Encrypting & sending…' : 'Create Secret' }}
                </button>
            </div>
        </div>
    </MobileLayout>
</template>
