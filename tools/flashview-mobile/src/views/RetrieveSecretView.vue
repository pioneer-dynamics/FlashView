<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { decryptMessage } from '@pioneer-dynamics/flashview-crypto'
import { Clipboard } from '@capacitor/clipboard'
import { useAuth } from '@/composables/useAuth'
import MobileLayout from '@/layouts/MobileLayout.vue'

const route = useRoute()
const router = useRouter()
const { getClient, reAuthenticate } = useAuth()

const hashId = route.params.id as string

const passphrase = ref('')
const showPassphrase = ref(false)
const isRetrieving = ref(false)
const decryptedMessage = ref('')
const error = ref('')
const copied = ref(false)

async function handleRetrieve(): Promise<void> {
    isRetrieving.value = true
    error.value = ''
    decryptedMessage.value = ''

    try {
        const client = await getClient()
        const response = await client.retrieveSecret(hashId)
        const encrypted = response.data?.message ?? response.message

        if (!encrypted) {
            error.value = 'This secret has already been read or no longer exists.'
            return
        }

        const result = await decryptMessage(encrypted, passphrase.value)
        decryptedMessage.value = result
    } catch (err: unknown) {
        const apiErr = err as { status?: number; message?: string; name?: string }

        if (apiErr.status === 401) {
            await reAuthenticate()
        } else if (apiErr.status === 404 || apiErr.status === 410) {
            error.value = 'This secret has already been read or no longer exists.'
        } else if (apiErr.name === 'OperationError' || apiErr.name === 'InvalidAccessError') {
            error.value = 'Wrong passphrase — could not decrypt the message.'
        } else if (err instanceof TypeError || String(apiErr.message).toLowerCase().includes('network')) {
            error.value = 'Network error. Check your connection and try again.'
        } else {
            error.value = (apiErr.message as string) || 'Something went wrong.'
        }
    } finally {
        isRetrieving.value = false
    }
}

async function copyMessage(): Promise<void> {
    await Clipboard.write({ string: decryptedMessage.value })
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <div class="flex items-center gap-3 mb-4">
                <button
                    @click="router.back()"
                    class="text-gray-400 hover:text-gray-200 transition-colors text-xs uppercase tracking-widest"
                >
                    ← Back
                </button>
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300">Retrieve Secret</h1>
            </div>

            <div v-if="!decryptedMessage" class="flex flex-col gap-4">
                <p class="text-xs text-gray-500 leading-relaxed">
                    Retrieving a secret is permanent — once read, it cannot be accessed again.
                </p>

                <div>
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-1">Passphrase</p>
                    <div class="flex gap-2">
                        <input
                            v-model="passphrase"
                            :type="showPassphrase ? 'text' : 'password'"
                            placeholder="Leave empty if none was set"
                            class="flex-1 rounded-xl bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-gamboge-300 focus:outline-none transition-colors font-mono"
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

                <p v-if="error" class="text-sm text-red-400 rounded-xl bg-red-950/30 border border-red-800/50 px-3 py-2">
                    {{ error }}
                </p>

                <button
                    @click="handleRetrieve"
                    :disabled="isRetrieving"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity disabled:opacity-40"
                >
                    {{ isRetrieving ? 'Decrypting…' : 'Retrieve & Decrypt' }}
                </button>
            </div>

            <div v-else class="flex flex-col gap-4">
                <div class="rounded-xl bg-gray-900 border border-gray-700 p-4">
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Message</p>
                    <p class="text-sm text-gray-100 whitespace-pre-wrap leading-relaxed">{{ decryptedMessage }}</p>
                </div>

                <button
                    @click="copyMessage"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity"
                >
                    {{ copied ? 'Copied!' : 'Copy message' }}
                </button>

                <p class="text-xs text-gray-600 text-center">
                    This secret has been destroyed and cannot be accessed again.
                </p>

                <button
                    @click="router.push({ name: 'secrets' })"
                    class="w-full py-2 rounded-xl border border-gray-700 text-xs text-gray-400 hover:text-gray-200 transition-colors"
                >
                    Back to secrets
                </button>
            </div>
        </div>
    </MobileLayout>
</template>
