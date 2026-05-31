<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileProgressBar from '@/Components/FileProgressBar.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { encryption, LockerDecryptionError } from '@/encryption.js';

const props = defineProps({
    account_id:     String,
    is_file_locker: Boolean,
    expires_at:     String,
    renewed:        Boolean,
});

const enc = new encryption();

// Unlock state
const passphrase        = ref('');
const lockState         = ref('locked'); // 'locked' | 'animating' | 'unlocked' | 'shaking'
const decryptError      = ref('');
const failCount         = ref(0);
const decryptedText     = ref('');
const decryptedFileMeta = ref(null); // { name, type, size }
const downloadUrl       = ref('');

// File download state
const fileState    = ref(null); // null | 'downloading'
const fileProgress = ref(0);
const downloadError = ref('');

// Update state
const newContent      = ref('');
const replacementFile = ref(null);
const updateError     = ref('');
const updateSuccess   = ref(false);
const updating        = ref(false);
const updateProgress  = ref(0);
const updateState     = ref(null); // null | 'encrypting' | 'uploading'

const onReplacementFileChange = (e) => { replacementFile.value = e.target.files[0] ?? null; };

// Delete state
const deleteError       = ref('');
const deleting          = ref(false);
const showDeleteConfirm = ref(false);

const daysRemaining = computed(() => {
    const ms = new Date(props.expires_at).getTime() - Date.now();
    return Math.max(0, Math.ceil(ms / 86_400_000));
});

const expiryLabel = computed(() => {
    if (daysRemaining.value === 0) return 'Expires today';
    if (daysRemaining.value === 1) return 'Expires tomorrow';
    return `${daysRemaining.value} days remaining`;
});

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

const triggerShake = (msg) => {
    decryptError.value = msg;
    lockState.value = 'shaking';
    setTimeout(() => { lockState.value = 'locked'; }, 450);
};

const getXsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const unlock = async () => {
    if (!passphrase.value || lockState.value === 'animating' || lockState.value === 'shaking') return;
    decryptError.value = '';
    lockState.value = 'animating';

    const animationStart = Date.now();

    try {
        const res = await fetch(route('lockers.payload', props.account_id), {
            headers: { 'Accept': 'application/json' },
        });

        if (!res.ok) {
            const elapsed = Date.now() - animationStart;
            if (elapsed < 600) await sleep(600 - elapsed);
            failCount.value++;
            const msg = res.status === 429
                ? 'Too many attempts. Please wait 5 minutes.'
                : 'Failed to fetch locker. Please try again.';
            triggerShake(msg);
            return;
        }

        const data   = await res.json();
        const result = await enc.decryptLockerContent(data.payload, passphrase.value);

        const elapsed = Date.now() - animationStart;
        if (elapsed < 600) await sleep(600 - elapsed);

        // Use the server prop, not the blob type byte.
        // File locker metadata is encrypted as text (JSON), so result.type is always 'text'.
        if (props.is_file_locker) {
            try {
                decryptedFileMeta.value = JSON.parse(new TextDecoder().decode(result.data));
            } catch {
                decryptedFileMeta.value = { name: 'download', type: 'application/octet-stream', size: 0 };
            }
            downloadUrl.value = data.download_url ?? '';
        } else {
            decryptedText.value = new TextDecoder().decode(result.data);
        }

        failCount.value = 0;
        lockState.value = 'unlocked';

    } catch (err) {
        const elapsed = Date.now() - animationStart;
        if (elapsed < 600) await sleep(600 - elapsed);
        failCount.value++;
        triggerShake(err instanceof LockerDecryptionError ? 'Incorrect passphrase.' : 'Decryption failed. Please try again.');
    }
};

// Download the encrypted file from S3, decrypt it, and save it
const downloadFile = async () => {
    if (fileState.value) return;
    downloadError.value = '';

    if (!downloadUrl.value) {
        downloadError.value = 'No download URL available — this file may not have been uploaded correctly.';
        return;
    }

    fileState.value    = 'downloading';
    fileProgress.value = 0;

    try {
        // Download binary blob with XHR progress
        const encryptedBytes = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', downloadUrl.value);
            xhr.responseType = 'arraybuffer';
            xhr.onprogress = (e) => {
                if (e.lengthComputable) fileProgress.value = Math.round((e.loaded / e.total) * 100);
            };
            xhr.onload  = () => (xhr.status >= 200 && xhr.status < 300) ? resolve(new Uint8Array(xhr.response)) : reject(new Error('Download failed.'));
            xhr.onerror = () => reject(new Error('Download failed.'));
            xhr.send();
        });

        // Convert binary back to hex blob for decryptFromBlob
        const hexBlob = Array.from(encryptedBytes).map(b => b.toString(16).padStart(2, '0')).join('');
        const result  = await enc.decryptLockerContent(hexBlob, passphrase.value);

        // Trigger browser download
        const name = decryptedFileMeta.value?.name ?? 'locker-file';
        const type = decryptedFileMeta.value?.type ?? 'application/octet-stream';
        const blob = new Blob([result.data], { type });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = name; a.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        downloadError.value = err.message || 'Download failed. Please try again.';
    } finally {
        fileState.value = null;
    }
};

const submitUpdate = async () => {
    updateError.value   = '';
    updateSuccess.value = false;
    if (!newContent.value.trim()) { updateError.value = 'Content cannot be empty.'; return; }

    updating.value = true;
    try {
        const payload     = await enc.encryptLockerContent(newContent.value, passphrase.value);
        const updateToken = await enc.deriveLockerUpdateToken(passphrase.value, props.account_id);

        const res = await fetch(route('lockers.update', props.account_id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Update-Token': updateToken,
                'X-XSRF-TOKEN': getXsrf(),
            },
            body: JSON.stringify({ payload }),
        });

        const data = await res.json();
        if (!res.ok) { updateError.value = data.error ?? 'Update failed.'; return; }

        updateSuccess.value = true;
        decryptedText.value = newContent.value;
        newContent.value    = '';
    } catch {
        updateError.value = 'Update failed. Please try again.';
    } finally {
        updating.value = false;
    }
};

const submitFileUpdate = async () => {
    updateError.value   = '';
    updateSuccess.value = false;
    if (!replacementFile.value) { updateError.value = 'Please select a file.'; return; }

    updating.value       = true;
    updateState.value    = 'encrypting';
    updateProgress.value = 0;

    try {
        const meta    = JSON.stringify({ name: replacementFile.value.name, type: replacementFile.value.type, size: replacementFile.value.size });
        const payload = await enc.encryptLockerContent(meta, passphrase.value);

        const encryptedBlob = await enc.encryptLockerFile(replacementFile.value, passphrase.value);
        const bytes = new Uint8Array(encryptedBlob.length / 2);
        for (let i = 0; i < encryptedBlob.length; i += 2) {
            bytes[i / 2] = parseInt(encryptedBlob.slice(i, i + 2), 16);
        }

        // Get presigned upload URL
        const prepRes = await fetch(route('lockers.file.prepare'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf() },
            body: JSON.stringify({}), // no credit_token needed for updates
        });

        let storagePath = null;
        if (prepRes.ok) {
            const { upload_type, upload_url, upload_headers, storage_path } = await prepRes.json();
            storagePath   = storage_path;
            updateState.value = 'uploading';

            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(upload_type === 's3_direct' ? 'PUT' : 'POST', upload_url);
                if (upload_type === 'server') xhr.setRequestHeader('X-XSRF-TOKEN', getXsrf());
                for (const [k, v] of Object.entries(upload_headers ?? {})) xhr.setRequestHeader(k, v);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) updateProgress.value = Math.round((e.loaded / e.total) * 100);
                };
                xhr.onload  = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed.'));
                xhr.onerror = () => reject(new Error('Upload failed.'));
                xhr.send(new Blob([bytes], { type: 'application/octet-stream' }));
            });
        }

        const updateToken = await enc.deriveLockerUpdateToken(passphrase.value, props.account_id);
        const body = { payload };
        if (storagePath) body.storage_path = storagePath;

        const res = await fetch(route('lockers.update', props.account_id), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Update-Token': updateToken, 'X-XSRF-TOKEN': getXsrf() },
            body: JSON.stringify(body),
        });

        const data = await res.json();
        if (!res.ok) { updateError.value = data.error ?? 'Update failed.'; return; }

        updateSuccess.value  = true;
        decryptedFileMeta.value = JSON.parse(meta);
        replacementFile.value  = null;
        downloadUrl.value = '';  // URL is now stale; re-unlock to get fresh URL
    } catch (err) {
        updateError.value = err.message || 'Update failed. Please try again.';
    } finally {
        updating.value    = false;
        updateState.value = null;
    }
};

const confirmDelete = async () => {
    deleteError.value = '';
    deleting.value    = true;
    try {
        const updateToken = await enc.deriveLockerUpdateToken(passphrase.value, props.account_id);

        const res = await fetch(route('lockers.destroy', props.account_id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Update-Token': updateToken,
                'X-XSRF-TOKEN': getXsrf(),
            },
        });

        const data = await res.json();
        if (!res.ok) { deleteError.value = data.error ?? 'Delete failed.'; return; }

        router.visit(route('welcome'));
    } catch {
        deleteError.value = 'Delete failed. Please try again.';
    } finally {
        deleting.value = false;
    }
};
</script>

<template>
    <AppLayout :title="`eLocker ${account_id}`">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-xl mx-auto space-y-6">

                <!-- Renewal banner -->
                <div v-if="renewed" class="bg-gamboge-300/10 border border-gamboge-300/40 rounded-xl p-4 text-gamboge-300 text-sm text-center">
                    Payment received — your expiry date will update within a few minutes.
                </div>

                <!-- Locker header -->
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-0.5">eLocker</div>
                        <div class="text-white font-mono text-xl tracking-widest">{{ account_id }}</div>
                    </div>
                    <div class="text-right">
                        <div :class="daysRemaining <= 30 ? 'text-red-400' : 'text-gray-400'" class="text-xs font-mono">{{ expiryLabel }}</div>
                        <a :href="route('lockers.renew.challenge', account_id)" class="text-gamboge-300 hover:text-gamboge-200 text-xs font-mono underline">Renew</a>
                    </div>
                </div>

                <!-- Unlock panel -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-8">

                    <!-- Locked state -->
                    <div v-if="lockState !== 'unlocked'" class="flex flex-col items-center gap-6">
                        <div class="relative w-20 h-20 flex items-center justify-center" data-testid="lock-icon">
                            <svg viewBox="0 0 64 80" class="w-20 h-20" fill="none" xmlns="http://www.w3.org/2000/svg" :class="{ 'animate-shake': lockState === 'shaking' }">
                                <path
                                    d="M16 32 V20 A16 16 0 0 1 48 20 V32"
                                    stroke="currentColor" stroke-width="5" stroke-linecap="round" fill="none"
                                    class="text-gamboge-300 origin-bottom"
                                    :class="{
                                        'animate-shackle-rise':  lockState === 'animating',
                                        'animate-shackle-swing': lockState === 'animating',
                                    }"
                                    style="transform-origin: 32px 32px"
                                />
                                <rect x="8" y="30" width="48" height="38" rx="6" fill="currentColor"
                                    class="text-gamboge-300"
                                    :class="{ 'animate-glow-burst': lockState === 'animating' }"
                                />
                                <circle cx="32" cy="48" r="5" class="fill-gray-900" />
                                <rect x="29" y="48" width="6" height="8" rx="1" class="fill-gray-900" />
                            </svg>
                        </div>

                        <div class="w-full space-y-3">
                            <input
                                v-model="passphrase"
                                type="password"
                                placeholder="Enter passphrase to unlock"
                                @keydown.enter="unlock"
                                class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:ring-gamboge-300 focus:outline-none"
                                data-testid="passphrase-input"
                            />
                            <p v-if="decryptError" class="text-red-400 text-sm text-center" data-testid="decrypt-error">{{ decryptError }}</p>
                            <p v-if="failCount >= 2 && decryptError" class="text-gray-500 text-xs text-center">
                                Repeatedly seeing this error? If your passphrase is lost, the content of this locker cannot be recovered.
                                Passphrase reset is not possible by design.
                            </p>
                            <button
                                @click="unlock"
                                :disabled="lockState === 'animating' || lockState === 'shaking'"
                                class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-60 text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                                data-testid="unlock-button"
                            >
                                <span v-if="lockState === 'animating'">Unlocking…</span>
                                <span v-else>Unlock</span>
                            </button>
                        </div>
                    </div>

                    <!-- Unlocked: content -->
                    <Transition name="fade">
                        <div v-if="lockState === 'unlocked'" class="space-y-4" data-testid="decrypted-content">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Decrypted Content</div>

                            <!-- Text locker -->
                            <div v-if="!is_file_locker" class="bg-gray-900 rounded-lg p-4">
                                <pre class="text-white text-sm whitespace-pre-wrap break-words font-mono">{{ decryptedText }}</pre>
                            </div>

                            <!-- File locker -->
                            <div v-else class="space-y-3">
                                <div class="bg-gray-900 rounded-lg p-4 flex items-center gap-3">
                                    <span class="text-2xl">🗂</span>
                                    <div class="flex-1">
                                        <div class="text-white text-sm font-mono">{{ decryptedFileMeta?.name ?? 'File' }}</div>
                                        <div class="text-gray-400 text-xs">{{ decryptedFileMeta?.type }}</div>
                                    </div>
                                </div>
                                <FileProgressBar v-if="fileState" :state="fileState" :progress="fileProgress" />
                                <template v-else>
                                    <p v-if="downloadError" class="text-red-400 text-xs">{{ downloadError }}</p>
                                    <button
                                        @click="downloadFile"
                                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-sm py-2 rounded-lg transition-colors"
                                    >
                                        Decrypt &amp; Download
                                    </button>
                                </template>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Update panel — only visible after unlock -->
                <div v-if="lockState === 'unlocked'" class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Update Content</h2>

                    <!-- Text locker update -->
                    <template v-if="!is_file_locker">
                        <textarea
                            v-model="newContent"
                            rows="4"
                            placeholder="New content…"
                            class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-xs focus:border-gamboge-300 focus:outline-none resize-y"
                        />
                    </template>

                    <!-- File locker update -->
                    <template v-else>
                        <input
                            type="file"
                            @change="onReplacementFileChange"
                            class="w-full bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-2.5 text-xs file:mr-3 file:text-gamboge-300 file:bg-gray-800 file:border-0 file:rounded file:text-xs file:font-mono file:cursor-pointer"
                        />
                        <FileProgressBar v-if="updateState" :state="updateState" :progress="updateProgress" />
                        <p v-if="updateSuccess" class="text-gamboge-300 text-xs">
                            File updated. Re-unlock to download the new version.
                        </p>
                    </template>

                    <p v-if="updateError" class="text-red-400 text-xs">{{ updateError }}</p>
                    <p v-if="!is_file_locker && updateSuccess" class="text-gamboge-300 text-xs">Content updated.</p>

                    <button
                        v-if="!updateState"
                        @click="is_file_locker ? submitFileUpdate() : submitUpdate()"
                        :disabled="updating"
                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-xs py-2 rounded-lg transition-colors disabled:opacity-50"
                        data-testid="update-button"
                    >{{ updating ? 'Updating…' : (is_file_locker ? 'Replace File' : 'Update') }}</button>
                </div>

                <!-- Update hint when locked -->
                <div v-if="lockState !== 'unlocked'" class="bg-gray-800 border border-gray-700 rounded-xl p-4 text-center">
                    <p class="text-gray-500 text-xs">Unlock your locker with your passphrase to update or delete it.</p>
                </div>

                <!-- Delete panel — only when unlocked -->
                <div v-if="lockState === 'unlocked'" class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 class="text-red-400 font-mono text-xs uppercase tracking-widest">Delete Locker</h2>

                    <div v-if="!showDeleteConfirm">
                        <button
                            @click="showDeleteConfirm = true"
                            class="text-red-400 hover:text-red-300 font-mono text-xs border border-red-500/30 hover:border-red-500 px-4 py-2 rounded-lg transition-colors"
                        >Delete this locker permanently</button>
                    </div>

                    <div v-else class="space-y-3">
                        <div class="bg-red-900/20 border border-red-500/50 rounded-lg p-3 space-y-1">
                            <p class="text-red-200 text-xs font-semibold">This will permanently delete your locker and forfeit your remaining paid time.</p>
                            <p class="text-red-300/80 text-xs">Your locker expires on {{ new Date(expires_at).toLocaleDateString() }}. Deleting now means that time is lost — there is no refund or credit. To store content again you would need to purchase a new locker.</p>
                        </div>
                        <p v-if="deleteError" class="text-red-400 text-xs">{{ deleteError }}</p>
                        <div class="flex gap-2">
                            <button @click="showDeleteConfirm = false" class="flex-1 border border-gray-600 text-gray-400 font-mono text-xs py-2 rounded-lg">Cancel</button>
                            <button
                                @click="confirmDelete"
                                :disabled="deleting"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-mono text-xs py-2 rounded-lg transition-colors disabled:opacity-50"
                                data-testid="confirm-delete-button"
                            >{{ deleting ? 'Deleting…' : 'Confirm Delete' }}</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active { transition: opacity 0.4s ease; }
.fade-enter-from { opacity: 0; }
</style>
