<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { decryptMessage, decryptBuffer } from '@pioneer-dynamics/flashview-crypto'
import { Clipboard } from '@capacitor/clipboard'
import { Filesystem, Directory } from '@capacitor/filesystem'
import { CapacitorHttp } from '@capacitor/core'
import { Share } from '@capacitor/share'
import { useAuth } from '@/composables/useAuth'
import { getToken, getServerUrl } from '@/services/storage'
import { base64ToUint8Array, uint8ArrayToBase64 } from '@/utils/binary'
import MobileLayout from '@/layouts/MobileLayout.vue'

const route = useRoute()
const router = useRouter()
const { getClient, reAuthenticate } = useAuth()

const hashId = route.params.id as string

const passphrase = ref('')
const showPassphrase = ref(false)
const isRetrieving = ref(false)
const downloadStage = ref<'idle' | 'fetching' | 'downloading' | 'decrypting' | 'saving'>('idle')

const decryptedMessage = ref('')
const decryptedFilename = ref('')
const decryptedNote = ref('')
const decryptedFileUri = ref('')
const isFileSecret = ref(false)

const error = ref('')
const copied = ref(false)

async function handleRetrieve(): Promise<void> {
    isRetrieving.value = true
    error.value = ''
    decryptedMessage.value = ''
    decryptedFilename.value = ''
    decryptedNote.value = ''
    decryptedFileUri.value = ''
    isFileSecret.value = false

    try {
        const client = await getClient()
        const response = await client.retrieveSecret(hashId)
        const data = response.data ?? response
        const type = data?.type as string | undefined

        if (type === 'file' || type === 'combined') {
            isFileSecret.value = true
            await handleFileRetrieve(client, data)
        } else {
            await handleTextRetrieve(data)
        }
    } catch (err: unknown) {
        const apiErr = err as { status?: number; message?: string; name?: string }
        if (apiErr.status === 401) {
            await reAuthenticate()
        } else if (apiErr.status === 404 || apiErr.status === 410) {
            error.value = 'This secret has already been read or no longer exists.'
        } else if (apiErr.name === 'OperationError' || apiErr.name === 'InvalidAccessError') {
            error.value = 'Wrong passphrase — could not decrypt the secret.'
        } else if (err instanceof TypeError || String(apiErr.message).toLowerCase().includes('network')) {
            error.value = 'Network error. Check your connection and try again.'
        } else {
            error.value = (apiErr.message as string) || 'Something went wrong.'
        }
    } finally {
        isRetrieving.value = false
        downloadStage.value = 'idle'
    }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function handleTextRetrieve(data: any): Promise<void> {
    const encrypted = data?.message as string | null ?? null
    if (!encrypted) {
        error.value = 'This secret has already been read or no longer exists.'
        return
    }
    decryptedMessage.value = await decryptMessage(encrypted, passphrase.value)
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function handleFileRetrieve(client: any, data: any): Promise<void> {
    downloadStage.value = 'fetching'
    const [token, serverUrl] = await Promise.all([getToken(), getServerUrl()])

    // Get the presigned S3 URL without following the redirect so we can download
    // without forwarding the Authorization header (AWS rejects dual auth).
    const redirectResponse = await CapacitorHttp.request({
        url: `${serverUrl}/api/v1/secrets/${hashId}/file`,
        method: 'GET',
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        disableRedirects: true,
    })

    const s3Url = (redirectResponse.headers['Location'] ?? redirectResponse.headers['location']) as string | undefined
    if (!s3Url) {
        throw new Error('Could not retrieve file download URL.')
    }

    // Download encrypted bytes from S3 directly (presigned URL, no auth header).
    downloadStage.value = 'downloading'
    const tempPath = `flashview_enc_${hashId}.bin`
    await Filesystem.downloadFile({ url: s3Url, path: tempPath, directory: Directory.Cache })

    const fileResult = await Filesystem.readFile({ path: tempPath, directory: Directory.Cache })
    const encryptedBytes = base64ToUint8Array(fileResult.data as string)

    // Decrypt file and filename.
    downloadStage.value = 'decrypting'
    const [decryptedBytes, filename] = await Promise.all([
        decryptBuffer(encryptedBytes, passphrase.value),
        decryptMessage(data.filename as string, passphrase.value),
    ])
    decryptedFilename.value = filename

    // Decrypt optional note (combined type).
    if (data.type === 'combined' && data.message) {
        decryptedNote.value = await decryptMessage(data.message as string, passphrase.value)
    }

    // Write decrypted file to cache so Share can access it.
    downloadStage.value = 'saving'
    const outputPath = `flashview_dec_${filename}`
    await Filesystem.writeFile({
        path: outputPath,
        data: uint8ArrayToBase64(decryptedBytes),
        directory: Directory.Cache,
    })
    const { uri } = await Filesystem.getUri({ path: outputPath, directory: Directory.Cache })
    decryptedFileUri.value = uri

    // Cleanup temp download; keep output until user shares.
    await Filesystem.deleteFile({ path: tempPath, directory: Directory.Cache }).catch(() => {})

    // Best-effort server confirmation so it can schedule S3 cleanup.
    client.confirmFileDownloaded(hashId).catch(() => {})
}

async function shareFile(): Promise<void> {
    if (!decryptedFileUri.value) { return }
    await Share.share({ url: decryptedFileUri.value, title: decryptedFilename.value })
}

async function copyMessage(): Promise<void> {
    await Clipboard.write({ string: decryptedMessage.value })
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}

function retrieveButtonLabel(): string {
    if (!isRetrieving.value) { return 'Retrieve & Decrypt' }
    switch (downloadStage.value) {
        case 'fetching': return 'Fetching…'
        case 'downloading': return 'Downloading…'
        case 'decrypting': return 'Decrypting…'
        case 'saving': return 'Saving…'
        default: return 'Decrypting…'
    }
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

            <!-- Input form — shown until decryption succeeds -->
            <div v-if="!decryptedMessage && !decryptedFileUri" class="flex flex-col gap-4">
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

                <!-- Download progress bar (file secrets only) -->
                <div v-if="downloadStage !== 'idle'" class="flex flex-col gap-1.5">
                    <div class="h-1.5 w-full rounded-full bg-gray-800 overflow-hidden relative">
                        <template v-if="downloadStage === 'saving'">
                            <div class="absolute inset-y-0 left-0 w-full bg-gamboge-300 rounded-full transition-all duration-300" />
                        </template>
                        <template v-else>
                            <div class="absolute inset-y-0 left-0 right-0 bg-gamboge-300/20" />
                            <div class="absolute inset-y-0 w-1/3 bg-gamboge-300 rounded-full animate-shimmer" />
                        </template>
                    </div>
                    <p class="text-xs text-gray-400 font-mono text-center">
                        <span v-if="downloadStage === 'fetching'">Preparing download…</span>
                        <span v-else-if="downloadStage === 'downloading'">Downloading…</span>
                        <span v-else-if="downloadStage === 'decrypting'">Decrypting…</span>
                        <span v-else-if="downloadStage === 'saving'">Saving…</span>
                    </p>
                </div>

                <p v-if="error" class="text-sm text-red-400 rounded-xl bg-red-950/30 border border-red-800/50 px-3 py-2">
                    {{ error }}
                </p>

                <button
                    @click="handleRetrieve"
                    :disabled="isRetrieving"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity disabled:opacity-40 shadow-neon-cyan-sm"
                >
                    {{ retrieveButtonLabel() }}
                </button>
            </div>

            <!-- Text secret result -->
            <div v-else-if="decryptedMessage" class="flex flex-col gap-4">
                <div class="rounded-xl bg-gray-900 border border-gray-700 p-4">
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Message</p>
                    <p class="font-mono text-sm text-gray-100 whitespace-pre-wrap leading-relaxed">{{ decryptedMessage }}</p>
                </div>

                <button
                    @click="copyMessage"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity shadow-neon-cyan-sm"
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

            <!-- File secret result -->
            <div v-else-if="decryptedFileUri" class="flex flex-col gap-4">
                <div class="rounded-xl bg-gray-900 border border-gamboge-300/20 p-4">
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Decrypted file</p>
                    <p class="font-mono text-sm text-gray-100 truncate">{{ decryptedFilename }}</p>
                </div>

                <div v-if="decryptedNote" class="rounded-xl bg-gray-900 border border-gray-700 p-4">
                    <p class="text-xs uppercase tracking-widest text-gamboge-300 mb-2">Note</p>
                    <p class="font-mono text-sm text-gray-100 whitespace-pre-wrap leading-relaxed">{{ decryptedNote }}</p>
                </div>

                <button
                    @click="shareFile"
                    class="w-full py-3 rounded-xl bg-gamboge-300 text-gray-950 font-semibold text-sm transition-opacity shadow-neon-cyan-sm"
                >
                    Save / Share File
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
