<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getServerUrl, setServerUrl } from '@/services/storage'
import { useAuth } from '@/composables/useAuth'
import { useUserProfile } from '@/composables/useUserProfile'
import MobileLayout from '@/layouts/MobileLayout.vue'

const { logout } = useAuth()
const { profile, fetchProfile, clearProfile } = useUserProfile()

const serverUrl = ref('')
const savedUrl = ref('')
const isSaving = ref(false)
const saved = ref(false)
const urlError = ref('')

onMounted(async () => {
    const url = await getServerUrl()
    serverUrl.value = url
    savedUrl.value = url
    fetchProfile()
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
    clearProfile()
    await logout()
}

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('')
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <h1 class="text-xs uppercase tracking-widest text-gamboge-300 mb-6">Settings</h1>

            <div class="flex flex-col gap-6">
                <!-- User profile -->
                <div v-if="profile" class="rounded-xl bg-gray-900 border border-gray-700 p-4 flex items-center gap-4">
                    <div class="shrink-0">
                        <img
                            v-if="profile.profile_photo_url"
                            :src="profile.profile_photo_url"
                            :alt="profile.name"
                            class="w-12 h-12 rounded-full object-cover border border-gray-700"
                        />
                        <div
                            v-else
                            class="w-12 h-12 rounded-full bg-gamboge-300/20 border border-gamboge-300/40 flex items-center justify-center"
                        >
                            <span class="text-gamboge-300 font-mono font-semibold text-sm">
                                {{ initials(profile.name) || '?' }}
                            </span>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-100 font-medium truncate">{{ profile.name }}</p>
                        <p class="text-xs text-gray-400 font-mono truncate">{{ profile.email }}</p>
                    </div>
                </div>

                <!-- Skeleton while loading -->
                <div v-else class="rounded-xl bg-gray-900 border border-gray-700 p-4 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gray-800 animate-pulse shrink-0" />
                    <div class="flex flex-col gap-2 flex-1">
                        <div class="h-3 bg-gray-800 rounded animate-pulse w-32" />
                        <div class="h-3 bg-gray-800 rounded animate-pulse w-48" />
                    </div>
                </div>

                <!-- Server URL -->
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

                <!-- Account -->
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

                <!-- About -->
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
