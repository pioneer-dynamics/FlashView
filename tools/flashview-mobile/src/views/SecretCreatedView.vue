<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Share } from '@capacitor/share'
import { Clipboard } from '@capacitor/clipboard'
import MobileLayout from '@/layouts/MobileLayout.vue'

const router = useRouter()

const url = ref('')
const passphrase = ref('')
const copiedUrl = ref(false)
const copiedPassphrase = ref(false)

onMounted(() => {
    const state = history.state as { url?: string; passphrase?: string }
    if (!state?.url) {
        router.replace({ name: 'create' })
        return
    }
    url.value = state.url
    passphrase.value = state.passphrase ?? ''
})

async function shareAll(): Promise<void> {
    const text = passphrase.value
        ? `Here is your secure message: ${url.value} — Passphrase: ${passphrase.value}`
        : `Here is your secure message: ${url.value}`

    await Share.share({ text })
}

async function copyUrl(): Promise<void> {
    await Clipboard.write({ string: url.value })
    copiedUrl.value = true
    setTimeout(() => { copiedUrl.value = false }, 2000)
}

async function copyPassphrase(): Promise<void> {
    await Clipboard.write({ string: passphrase.value })
    copiedPassphrase.value = true
    setTimeout(() => { copiedPassphrase.value = false }, 2000)
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <h1 class="text-xs uppercase tracking-widest text-gamboge-300 mb-4">Secret Created</h1>

            <div class="flex flex-col gap-4">
                <div class="rounded-xl bg-gray-900 border border-gray-700 p-4 flex flex-col gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-1">Link</p>
                        <p class="font-mono text-xs text-gray-300 break-all leading-relaxed">{{ url }}</p>
                    </div>

                    <div v-if="passphrase">
                        <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-1">Passphrase</p>
                        <p class="font-mono text-sm text-gray-100 break-all">{{ passphrase }}</p>
                    </div>
                </div>

                <button
                    @click="shareAll"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity shadow-neon-cyan-sm"
                >
                    Share link{{ passphrase ? ' + passphrase' : '' }}
                </button>

                <div class="flex gap-3">
                    <button
                        @click="copyUrl"
                        class="flex-1 py-2 rounded-xl bg-gray-800 border border-gray-700 text-xs text-gray-300 hover:text-gray-100 transition-colors font-mono"
                    >
                        {{ copiedUrl ? 'Copied!' : 'Copy link' }}
                    </button>

                    <button
                        v-if="passphrase"
                        @click="copyPassphrase"
                        class="flex-1 py-2 rounded-xl bg-gray-800 border border-gray-700 text-xs text-gray-300 hover:text-gray-100 transition-colors font-mono"
                    >
                        {{ copiedPassphrase ? 'Copied!' : 'Copy passphrase' }}
                    </button>
                </div>

                <p class="text-xs text-gray-600 text-center leading-relaxed">
                    This link can only be opened once — the secret is destroyed after it's read.
                </p>

                <button
                    @click="router.push({ name: 'create' })"
                    class="w-full py-2 rounded-xl border border-gray-700 text-xs text-gray-400 hover:text-gray-200 transition-colors"
                >
                    Create another secret
                </button>
            </div>
        </div>
    </MobileLayout>
</template>
