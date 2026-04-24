<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { encryptMessage, encryptBuffer } from '@pioneer-dynamics/flashview-crypto'
import { useAuth } from '@/composables/useAuth'
import { useServerConfig } from '@/composables/useServerConfig'
import { useShareIntent } from '@/composables/useShareIntent'
import MobileLayout from '@/layouts/MobileLayout.vue'
import ExpiryPicker from '@/components/ExpiryPicker.vue'

const router = useRouter()
const { getClient, reAuthenticate } = useAuth()
const { config, fetchConfig } = useServerConfig()
const { sharedText, sharedFile, clearSharedContent } = useShareIntent()

const message = ref(sharedText.value ?? '')
const expiresIn = ref(1440)
const passphrase = ref('')
const useCustomPassphrase = ref(false)
// Passphrases are shown by default — they are memorable phrases, not passwords.
const showPassphrase = ref(true)
const recipientEmail = ref('')
const includeSenderIdentity = ref(false)
const isSubmitting = ref(false)
const uploadStage = ref<'idle' | 'encrypting' | 'uploading' | 'saving'>('idle')
const error = ref('')

interface AttachedFile {
    name: string
    size: number
    type: string
}
const selectedFile = ref<AttachedFile | null>(null)
const fileBytes = ref<Uint8Array | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

// Pre-fill from share intent
watch(sharedText, (text) => {
    if (text) message.value = text
})

watch(sharedFile, (file) => {
    if (file) {
        selectedFile.value = { name: file.name, size: file.size, type: file.mimeType }
        const binary = atob(file.data)
        const bytes = new Uint8Array(binary.length)
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i)
        }
        fileBytes.value = bytes
    }
}, { immediate: true })

watch(() => config.value.senderIdentity, (identity) => {
    if (identity?.include_by_default) {
        includeSenderIdentity.value = true
    }
}, { immediate: true })

onMounted(() => {
    fetchConfig()
})

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => {
        fileBytes.value = new Uint8Array(reader.result as ArrayBuffer)
        selectedFile.value = { name: file.name, size: file.size, type: file.type || 'application/octet-stream' }
    }
    reader.readAsArrayBuffer(file)
    // Reset so the same file can be re-selected if removed and re-added
    input.value = ''
}

function clearFile(): void {
    selectedFile.value = null
    fileBytes.value = null
}

function resetForm(): void {
    message.value = ''
    passphrase.value = ''
    useCustomPassphrase.value = false
    recipientEmail.value = ''
    error.value = ''
    uploadStage.value = 'idle'
    selectedFile.value = null
    fileBytes.value = null
}

async function handleCreate(): Promise<void> {
    const hasFile = !!(selectedFile.value && fileBytes.value)

    if (!hasFile && !message.value.trim()) {
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
        const client = await getClient()

        if (hasFile) {
            uploadStage.value = 'encrypting'
            const { encrypted, passphrase: resolvedPassphrase } = await encryptBuffer(
                fileBytes.value!,
                useCustomPassphrase.value ? passphrase.value : null,
            )

            const { secret: encryptedFilename } = await encryptMessage(
                selectedFile.value!.name,
                resolvedPassphrase,
            )

            let encryptedNote: string | null = null
            if (message.value.trim()) {
                const { secret } = await encryptMessage(message.value, resolvedPassphrase)
                encryptedNote = secret
            }

            uploadStage.value = 'uploading'
            const prepare = await client.prepareFileUpload()

            // Use Blob so Capacitor's native HTTP bridge sends raw binary — passing
            // ArrayBuffer directly can cause double-encoding through the JS bridge.
            const uploadResponse = await fetch(prepare.upload_url, {
                method: prepare.upload_type === 's3_direct' ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/octet-stream',
                    ...prepare.upload_headers,
                },
                // Copy into a fresh ArrayBuffer so TypeScript is satisfied and
                // Capacitor's bridge treats the body as raw binary (not JSON-encoded).
                body: (() => {
                    const buf = new ArrayBuffer(encrypted.byteLength)
                    new Uint8Array(buf).set(encrypted)
                    return new Blob([buf])
                })(),
            })

            if (!uploadResponse.ok) {
                throw new TypeError(`File upload failed (HTTP ${uploadResponse.status})`)
            }

            uploadStage.value = 'saving'
            const response = await client.createSecretWithFileToken(
                prepare.token,
                encryptedFilename,
                fileBytes.value!.length,
                selectedFile.value!.type,
                expiresIn.value,
                recipientEmail.value.trim() || null,
                config.value.senderIdentity ? includeSenderIdentity.value : false,
                encryptedNote,
            )

            clearSharedContent()
            resetForm()

            router.push({
                name: 'secret-created',
                state: { url: response.data.url, passphrase: resolvedPassphrase },
            })
        } else {
            const result = await encryptMessage(
                message.value,
                useCustomPassphrase.value ? passphrase.value : null,
            )

            const response = await client.createSecret(
                result.secret,
                expiresIn.value,
                recipientEmail.value.trim() || null,
                config.value.senderIdentity ? includeSenderIdentity.value : false,
            )

            clearSharedContent()
            resetForm()

            router.push({
                name: 'secret-created',
                state: { url: response.data.url, passphrase: result.passphrase },
            })
        }
    } catch (err: unknown) {
        const apiErr = err as { status?: number; retryAfter?: number | null; message?: string }

        if (apiErr.status === 429) {
            const wait = apiErr.retryAfter || 60
            error.value = `Please wait ${wait} seconds before creating another secret.`
        } else if (apiErr.status === 401) {
            await reAuthenticate()
        } else if (err instanceof TypeError || String(apiErr.message).toLowerCase().includes('network') || String(apiErr.message).toLowerCase().includes('failed')) {
            error.value = 'Your secret was NOT sent. Your content is still only on this device. Check your connection and try again.'
        } else {
            error.value = apiErr.message || 'Something went wrong. Please try again.'
        }
    } finally {
        isSubmitting.value = false
        uploadStage.value = 'idle'
    }
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <h1 class="text-xs uppercase tracking-widest text-gamboge-300 mb-4">New Secret</h1>

            <div class="flex flex-col gap-4">
                <!-- Attached file -->
                <div v-if="selectedFile" class="rounded-xl bg-gray-900 border border-gamboge-800 p-3 flex items-center justify-between gap-3">
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <p class="text-xs text-gamboge-300 uppercase tracking-widest mb-1">Attached file</p>
                        <p class="text-sm text-gray-100 font-mono truncate">{{ selectedFile.name }}</p>
                        <p class="text-xs text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                    </div>
                    <button
                        type="button"
                        @click="clearFile"
                        class="shrink-0 text-xs text-red-400 hover:text-red-300 transition-colors px-2"
                    >
                        Remove
                    </button>
                </div>

                <!-- File picker (shown when no file attached) -->
                <div v-else>
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="hidden"
                        @change="onFileSelected"
                    />
                    <button
                        type="button"
                        @click="fileInputRef?.click()"
                        class="w-full py-2 rounded-xl border border-dashed border-gray-600 text-xs text-gray-400 hover:border-gamboge-300 hover:text-gamboge-300 transition-colors"
                    >
                        Attach a file
                    </button>
                </div>

                <!-- Message / note -->
                <div>
                    <p v-if="selectedFile" class="text-xs uppercase tracking-widest text-gamboge-300 mb-1">Note (optional)</p>
                    <textarea
                        v-model="message"
                        :placeholder="selectedFile ? 'Add an optional note…' : 'Enter your secret message…'"
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

                <!-- Upload progress (file secrets only):
                     Phase 1 (encrypting/uploading) — indeterminate shimmer overlay
                     Phase 2 (saving) — determinate full-width gamboge fill -->
                <div v-if="uploadStage !== 'idle'" class="flex flex-col gap-1.5">
                    <div class="h-1.5 w-full rounded-full bg-gray-800 overflow-hidden relative">
                        <template v-if="uploadStage === 'saving'">
                            <div class="absolute inset-y-0 left-0 w-full bg-gamboge-300 rounded-full transition-all duration-300" />
                        </template>
                        <template v-else>
                            <div class="absolute inset-y-0 left-0 right-0 bg-gamboge-300/20" />
                            <div class="absolute inset-y-0 w-1/3 bg-gamboge-300 rounded-full animate-shimmer" />
                        </template>
                    </div>
                    <p class="text-xs text-gray-400 font-mono text-center">
                        <span v-if="uploadStage === 'encrypting'">Encrypting file…</span>
                        <span v-else-if="uploadStage === 'uploading'">Uploading…</span>
                        <span v-else-if="uploadStage === 'saving'">Creating secret…</span>
                    </p>
                </div>

                <button
                    @click="handleCreate"
                    :disabled="isSubmitting || (!message.trim() && !selectedFile)"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity disabled:opacity-40 shadow-neon-cyan-sm"
                >
                    {{ isSubmitting ? (selectedFile ? 'Sending…' : 'Encrypting & sending…') : 'Create Secret' }}
                </button>
            </div>
        </div>
    </MobileLayout>
</template>
