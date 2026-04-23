<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { getServerUrl, setServerUrl } from '@/services/storage'

const { login } = useAuth()

const isLoading = ref(false)
const error = ref('')
const serverUrl = ref('')
const editingServer = ref(false)
const serverInput = ref('')
const serverError = ref('')

onMounted(async () => {
    serverUrl.value = await getServerUrl()
})

function startEditServer(): void {
    serverInput.value = serverUrl.value
    serverError.value = ''
    editingServer.value = true
}

function cancelEditServer(): void {
    editingServer.value = false
    serverError.value = ''
}

async function saveServer(): Promise<void> {
    serverError.value = ''
    const trimmed = serverInput.value.trim().replace(/\/$/, '')
    try {
        new URL(trimmed)
    } catch {
        serverError.value = 'Please enter a valid URL (e.g. https://flashview.link)'
        return
    }
    await setServerUrl(trimmed)
    serverUrl.value = trimmed
    editingServer.value = false
}

async function handleLogin(): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
        await login()
    } catch (err) {
        if (err instanceof Error && err.message === 'API_ACCESS_REQUIRED') {
            error.value = 'API access is required to use the FlashView app. Please upgrade your plan.'
        } else {
            error.value = err instanceof Error ? err.message : 'Login failed. Please try again.'
        }
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-950 flex flex-col items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-gamboge-300 font-mono tracking-tight">FlashView</h1>
                <p class="mt-2 text-sm text-gray-400">Secure secret sharing</p>
            </div>

            <!-- Server URL indicator -->
            <div class="mb-6">
                <div v-if="!editingServer" class="flex items-center justify-between bg-gray-900 rounded-xl px-4 py-3 border border-gray-800">
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-0.5">Server</p>
                        <p class="text-xs font-mono text-gray-300 truncate">{{ serverUrl }}</p>
                    </div>
                    <button
                        type="button"
                        @click="startEditServer"
                        class="ml-3 text-xs text-gamboge-300 hover:text-white transition-colors shrink-0"
                    >
                        Change
                    </button>
                </div>

                <div v-else class="bg-gray-900 rounded-xl px-4 py-3 border border-gamboge-300">
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Server URL</p>
                    <input
                        v-model="serverInput"
                        type="url"
                        placeholder="https://flashview.link"
                        class="w-full bg-gray-800 rounded-lg px-3 py-2 text-sm font-mono text-gray-100 border border-gray-700 focus:outline-none focus:border-gamboge-300"
                        @keyup.enter="saveServer"
                        @keyup.escape="cancelEditServer"
                    />
                    <p v-if="serverError" class="mt-1 text-xs text-red-400">{{ serverError }}</p>
                    <div class="flex gap-2 mt-3">
                        <button
                            type="button"
                            @click="saveServer"
                            class="flex-1 py-2 rounded-lg bg-gamboge-300 text-gray-950 text-xs font-semibold shadow-neon-cyan-sm"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            @click="cancelEditServer"
                            class="flex-1 py-2 rounded-lg bg-gray-800 text-gray-300 text-xs font-semibold border border-gray-700"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                <p class="text-sm text-gray-300 mb-6 text-center leading-relaxed">
                    Sign in to start sharing secrets securely from your phone.
                </p>

                <div class="bg-gray-800 rounded-xl p-4 mb-6 border border-gray-700">
                    <p class="text-xs text-gray-400 leading-relaxed">
                        <span class="text-gamboge-300 font-medium">Secure sign-in:</span>
                        You will be redirected to your browser to authenticate.
                        This ensures FlashView never handles your password directly.
                    </p>
                </div>

                <p v-if="error" class="text-sm text-red-400 mb-4 text-center">{{ error }}</p>

                <button
                    @click="handleLogin"
                    :disabled="isLoading"
                    class="w-full py-3 px-4 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity disabled:opacity-50 shadow-neon-cyan-sm"
                >
                    {{ isLoading ? 'Opening browser...' : 'Sign in with FlashView' }}
                </button>
            </div>
        </div>
    </div>
</template>
