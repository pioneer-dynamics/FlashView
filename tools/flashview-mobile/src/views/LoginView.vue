<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '@/composables/useAuth'

const { login } = useAuth()

const isLoading = ref(false)
const error = ref('')

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
                    class="w-full py-3 px-4 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity disabled:opacity-50"
                >
                    {{ isLoading ? 'Opening browser...' : 'Sign in with FlashView' }}
                </button>
            </div>
        </div>
    </div>
</template>
