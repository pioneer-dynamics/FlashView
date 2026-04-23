<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getServerUrl, setServerUrl } from '@/services/storage'
import { useAuth } from '@/composables/useAuth'
import MobileLayout from '@/layouts/MobileLayout.vue'

const { logout } = useAuth()

const serverUrl = ref('')
const savedUrl = ref('')
const isSaving = ref(false)
const saved = ref(false)
const urlError = ref('')

onMounted(async () => {
    const url = await getServerUrl()
    serverUrl.value = url
    savedUrl.value = url
})

async function saveServerUrl(): Promise<void> {
    urlError.value = ''

    try {
        new URL(serverUrl.value)
    } catch {
        urlError.value = 'Please enter a valid URL (e.g. https://flashview.link)'
        return
    }

    isSaving.value = true

    try {
        const trimmed = serverUrl.value.replace(/\/$/, '')
        await setServerUrl(trimmed)
        savedUrl.value = trimmed
        serverUrl.value = trimmed
        saved.value = true
        setTimeout(() => { saved.value = false }, 2000)
    } finally {
        isSaving.value = false
    }
}

async function handleLogout(): Promise<void> {
    await logout()
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <h1 class="text-xs uppercase tracking-widest text-gamboge-300 mb-6">Settings</h1>

            <div class="flex flex-col gap-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Server</p>
                    <div class="rounded-xl bg-gray-900 border border-gray-700 p-4 flex flex-col gap-3">
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Self-hosted FlashView instance? Enter your server URL below.
                        </p>

                        <input
                            v-model="serverUrl"
                            type="url"
                            placeholder="https://flashview.link"
                            class="w-full rounded-xl bg-gray-800 border border-gray-700 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-gamboge-300 focus:outline-none transition-colors font-mono"
                            autocorrect="off"
                            autocapitalize="none"
                            spellcheck="false"
                        />

                        <p v-if="urlError" class="text-xs text-red-400">{{ urlError }}</p>

                        <button
                            @click="saveServerUrl"
                            :disabled="isSaving || serverUrl === savedUrl"
                            class="w-full py-2 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-xs transition-opacity disabled:opacity-40 shadow-neon-cyan-sm"
                        >
                            {{ saved ? 'Saved!' : isSaving ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Account</p>
                    <div class="rounded-xl bg-gray-900 border border-gray-700 overflow-hidden">
                        <button
                            @click="handleLogout"
                            class="w-full text-left px-4 py-3 text-sm text-red-400 hover:bg-gray-800 transition-colors"
                        >
                            Sign out
                        </button>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">About</p>
                    <div class="rounded-xl bg-gray-900 border border-gray-700 p-4">
                        <p class="text-xs text-gray-500 leading-relaxed">
                            <span class="text-gray-300 font-medium">FlashView</span> — end-to-end encrypted secret sharing.
                            Your secrets are encrypted on this device before being sent. The server never sees your plaintext.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </MobileLayout>
</template>
