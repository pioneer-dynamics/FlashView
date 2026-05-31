<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { encryption } from '@/encryption.js';
import { LockerDecryptionError } from '@pioneer-dynamics/flashview-crypto';

const props = defineProps({
    account_id:     String,
    is_file_locker: Boolean,
    expires_at:     String,
    renewed:        Boolean,
});

const enc = new encryption();

// Unlock state
const passphrase         = ref('');
const lockState          = ref('locked'); // 'locked' | 'animating' | 'unlocked' | 'shaking'
const decryptError       = ref('');
const failCount          = ref(0);
const decryptedText      = ref('');
const decryptedFileBlob  = ref(null);
const decryptedFileName  = ref('');

// Update state
const updateToken    = ref('');
const newContent     = ref('');
const updateError    = ref('');
const updateSuccess  = ref(false);
const updating       = ref(false);

// Delete state
const deleteToken    = ref('');
const deleteError    = ref('');
const deleting       = ref(false);
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
            triggerShake('Failed to fetch locker. Please try again.');
            return;
        }

        const data = await res.json();
        const result = await enc.decryptLockerContent(data.payload, passphrase.value);

        const elapsed = Date.now() - animationStart;
        if (elapsed < 600) await sleep(600 - elapsed);

        if (result.type === 'text') {
            decryptedText.value = new TextDecoder().decode(result.data);
        } else {
            // File: result.data is metadata; actual file is at storage_path
            try {
                const meta = JSON.parse(new TextDecoder().decode(result.data));
                decryptedFileName.value = meta.name ?? 'download';
            } catch {
                decryptedFileName.value = 'download';
            }
            decryptedFileBlob.value = data.storage_path;
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

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

const triggerShake = (msg) => {
    decryptError.value = msg;
    lockState.value = 'shaking';
    setTimeout(() => { lockState.value = 'locked'; }, 450);
};

const getXsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const submitUpdate = async () => {
    updateError.value = '';
    updateSuccess.value = false;
    if (!updateToken.value) { updateError.value = 'Update token is required.'; return; }
    if (!newContent.value.trim()) { updateError.value = 'Content cannot be empty.'; return; }
    if (!passphrase.value) { updateError.value = 'Enter your passphrase to re-encrypt.'; return; }

    updating.value = true;
    try {
        const payload = await enc.encryptLockerContent(newContent.value, passphrase.value);

        const res = await fetch(route('lockers.update', props.account_id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Update-Token': updateToken.value,
                'X-XSRF-TOKEN': getXsrf(),
            },
            body: JSON.stringify({ payload }),
        });

        const data = await res.json();
        if (!res.ok) { updateError.value = data.error ?? 'Update failed.'; return; }

        updateSuccess.value = true;
        decryptedText.value = newContent.value;
        newContent.value = '';
    } catch {
        updateError.value = 'Update failed. Please try again.';
    } finally {
        updating.value = false;
    }
};

const confirmDelete = async () => {
    deleteError.value = '';
    if (!deleteToken.value) { deleteError.value = 'Update token is required to delete.'; return; }

    deleting.value = true;
    try {
        const res = await fetch(route('lockers.destroy', props.account_id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Update-Token': deleteToken.value,
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
        <div class="min-h-screen bg-gray-900 py-16 px-4">
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

                    <!-- Locked state: SVG lock + passphrase input -->
                    <div v-if="lockState !== 'unlocked'" class="flex flex-col items-center gap-6">

                        <!-- Animated SVG lock -->
                        <div class="relative w-20 h-20 flex items-center justify-center" data-testid="lock-icon">
                            <svg viewBox="0 0 64 80" class="w-20 h-20" fill="none" xmlns="http://www.w3.org/2000/svg" :class="{ 'animate-shake': lockState === 'shaking' }">
                                <!-- Shackle (top arc) — animated independently -->
                                <path
                                    d="M16 32 V20 A16 16 0 0 1 48 20 V32"
                                    stroke="currentColor"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    fill="none"
                                    class="text-gamboge-300 origin-bottom"
                                    :class="{
                                        'animate-shackle-rise':  lockState === 'animating',
                                        'animate-shackle-swing': lockState === 'animating',
                                    }"
                                    style="transform-origin: 32px 32px"
                                />
                                <!-- Lock body -->
                                <rect
                                    x="8" y="30" width="48" height="38" rx="6"
                                    fill="currentColor"
                                    class="text-gamboge-300"
                                    :class="{ 'animate-glow-burst': lockState === 'animating' }"
                                />
                                <!-- Keyhole -->
                                <circle cx="32" cy="48" r="5" fill="#0d1b2a" />
                                <rect x="29" y="48" width="6" height="8" rx="1" fill="#0d1b2a" />
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

                    <!-- Unlocked: content panel -->
                    <Transition name="fade">
                        <div v-if="lockState === 'unlocked'" class="space-y-4" data-testid="decrypted-content">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Decrypted Content</div>

                            <div v-if="!is_file_locker" class="bg-gray-900 rounded-lg p-4">
                                <pre class="text-white text-sm whitespace-pre-wrap break-words font-mono">{{ decryptedText }}</pre>
                            </div>

                            <div v-else class="bg-gray-900 rounded-lg p-4 flex items-center gap-3">
                                <span class="text-2xl">🗂</span>
                                <div class="flex-1">
                                    <div class="text-white text-sm font-mono">{{ decryptedFileName }}</div>
                                    <div class="text-gray-400 text-xs">File locker — download via secure link</div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Update panel -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Update Content</h2>

                    <div class="bg-yellow-900/10 border border-yellow-600/20 rounded-lg p-3 text-yellow-300/80 text-xs">
                        If you have lost your Update Token, your locker is permanently read-only.
                        This cannot be reversed — our server has never seen your token and cannot recover it.
                    </div>

                    <input
                        v-model="updateToken"
                        type="text"
                        placeholder="Update token"
                        class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-xs focus:border-gamboge-300 focus:outline-none"
                        data-testid="update-token-input"
                    />

                    <textarea
                        v-if="!is_file_locker && lockState === 'unlocked'"
                        v-model="newContent"
                        rows="4"
                        placeholder="New content (passphrase must be entered above)"
                        class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-xs focus:border-gamboge-300 focus:outline-none resize-y"
                    />

                    <p v-if="updateError" class="text-red-400 text-xs">{{ updateError }}</p>
                    <p v-if="updateSuccess" class="text-gamboge-300 text-xs">Content updated successfully.</p>

                    <button
                        v-if="lockState === 'unlocked'"
                        @click="submitUpdate"
                        :disabled="updating"
                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-xs py-2 rounded-lg transition-colors disabled:opacity-50"
                        data-testid="update-button"
                    >
                        {{ updating ? 'Updating…' : 'Update' }}
                    </button>
                </div>

                <!-- Delete panel -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 class="text-red-400 font-mono text-xs uppercase tracking-widest">Delete Locker</h2>

                    <div v-if="!showDeleteConfirm">
                        <button
                            @click="showDeleteConfirm = true"
                            class="text-red-400 hover:text-red-300 font-mono text-xs border border-red-500/30 hover:border-red-500 px-4 py-2 rounded-lg transition-colors"
                        >
                            Delete this locker permanently
                        </button>
                    </div>

                    <div v-else class="space-y-3">
                        <p class="text-red-300 text-xs">This action is permanent and cannot be undone.</p>
                        <input
                            v-model="deleteToken"
                            type="text"
                            placeholder="Update token required to delete"
                            class="w-full bg-gray-900 border border-red-500/40 text-white font-mono rounded-lg px-3 py-2.5 text-xs focus:border-red-400 focus:outline-none"
                            data-testid="delete-token-input"
                        />
                        <p v-if="deleteError" class="text-red-400 text-xs">{{ deleteError }}</p>
                        <div class="flex gap-2">
                            <button
                                @click="showDeleteConfirm = false"
                                class="flex-1 border border-gray-600 text-gray-400 font-mono text-xs py-2 rounded-lg"
                            >Cancel</button>
                            <button
                                @click="confirmDelete"
                                :disabled="deleting"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-mono text-xs py-2 rounded-lg transition-colors disabled:opacity-50"
                                data-testid="confirm-delete-button"
                            >
                                {{ deleting ? 'Deleting…' : 'Confirm Delete' }}
                            </button>
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
