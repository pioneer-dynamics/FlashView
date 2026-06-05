<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileProgressBar from '@/Components/FileProgressBar.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { encryption } from '@/encryption.js';

const props = defineProps({
    credit_token: String,
    tier: String,
    years: Number,
});

onMounted(() => {
    if (!props.credit_token) {
        const saved = localStorage.getItem('locker_pending_token');
        if (saved) {
            router.visit(route('lockers.create') + '?token=' + encodeURIComponent(saved));
        }
    }
});

const enc = new encryption();

// Form fields
const accountId    = ref('');
const passphrase   = ref('');
const content      = ref('');
const selectedFile = ref(null);

// Auth mode
const authMode                = ref('passphrase'); // 'passphrase' | 'key_file' | 'combined'
const keyFiles                = ref([]); // [{ file: File }]
const keyFileRiskAcknowledged = ref(false);
const showClues               = ref(true); // when false, unlock page reveals no credential-type hints

// State
const step          = ref('form'); // 'form' | 'encrypting' | 'uploading' | 'credentials'
const uploadProgress = ref(0);
const errors        = ref({});
const credentials   = ref(null);
const savedConfirmed = ref(false);

const isFileTier = computed(() => props.tier === 'file');

const passphraseStrength = computed(() => {
    const p = passphrase.value;
    if (!p) return 0;
    let score = 0;
    if (p.length >= 12) score++;
    if (p.length >= 20) score++;
    if (/[A-Z]/.test(p) || /\d/.test(p)) score++;
    if (/[^a-z0-9]/i.test(p)) score++;
    return score;
});

const strengthLabel = computed(() => ['', 'Weak', 'Fair', 'Good', 'Strong'][passphraseStrength.value] ?? '');
const strengthColor = computed(() => ['', 'text-red-400', 'text-gamboge-500', 'text-gamboge-400', 'text-gamboge-300'][passphraseStrength.value] ?? '');
const strengthWidth = computed(() => ['w-0', 'w-1/4', 'w-2/4', 'w-3/4', 'w-full'][passphraseStrength.value] ?? 'w-0');
const strengthBg    = computed(() => ['', 'bg-red-400', 'bg-gamboge-500', 'bg-gamboge-400', 'bg-gamboge-300'][passphraseStrength.value] ?? '');

const generatePassphrase = () => { passphrase.value = enc.generatePasssphrase(); };
const onFileChange = (e) => { selectedFile.value = e.target.files[0] ?? null; };
const copyToClipboard = async (text) => { await navigator.clipboard.writeText(text); };

const onKeyFileAdded = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    e.target.value = '';
    keyFiles.value.push({ file });
};

const removeKeyFile = (index) => {
    keyFiles.value.splice(index, 1);
};

const setAuthMode = (mode) => {
    authMode.value = mode;
    keyFiles.value = [];
    keyFileRiskAcknowledged.value = false;
    if (mode === 'passphrase') showClues.value = true;
};

const computeEffectivePassphrase = async () => {
    if (authMode.value === 'passphrase') {
        return passphrase.value;
    }
    // Re-read buffers from File references at submit time — avoids holding large ArrayBuffers in memory
    const fileHashes = await Promise.all(
        keyFiles.value.map(async (kf) => {
            const buf = await kf.file.arrayBuffer();
            return enc.deriveLockerKeyFromFile(buf);
        })
    );
    if (authMode.value === 'key_file') {
        return enc.combineLockerKeyMaterials(fileHashes);
    }
    return enc.combineLockerKeyMaterials([passphrase.value, ...fileHashes]);
};

const downloadCredentials = () => {
    const lines = [
        'eLocker Credentials — Save these securely. They cannot be recovered.',
        '',
        `Account ID: ${credentials.value.account_id}`,
    ];

    if (authMode.value !== 'key_file') {
        lines.push(`Passphrase: ${credentials.value.passphrase}`);
    }

    if (authMode.value !== 'passphrase') {
        lines.push('');
        lines.push('Key Files (load in this exact order when unlocking):');
        credentials.value.keyFileNames.forEach((name, i) => {
            lines.push(`  ${i + 1}. ${name}`);
        });
        lines.push('');
        lines.push('IMPORTANT: Key files must be loaded in this exact order. There is no recovery if you lose your key files.');
        if (!showClues.value) {
            lines.push('NOTE: The unlock page is configured to show no hints about required credentials.');
            lines.push('Share access instructions with authorised users through a separate secure channel.');
        }
    }

    lines.push('', `Expires: ${new Date(credentials.value.expires_at).toLocaleDateString()}`);

    if (authMode.value === 'passphrase') {
        lines.push('', 'Note: Your passphrase is the only key to decrypt and modify your locker.');
    }

    const text = lines.join('\n');
    const blob = new Blob([text], { type: 'text/plain' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `elocker-${credentials.value.account_id}.txt`;
    a.click();
    URL.revokeObjectURL(url);
};

const xsrfToken = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const submit = async () => {
    errors.value = {};

    if (!/^\d{10}$/.test(accountId.value)) {
        errors.value.account_id = 'Account ID must be exactly 10 digits.';
        return;
    }
    if (authMode.value !== 'key_file' && (!passphrase.value || passphrase.value.length < 8)) {
        errors.value.passphrase = 'Passphrase must be at least 8 characters.';
        return;
    }
    if (authMode.value !== 'passphrase' && keyFiles.value.length === 0) {
        errors.value.key_files = 'Please add at least one key file.';
        return;
    }
    if (authMode.value !== 'passphrase' && !keyFileRiskAcknowledged.value) {
        errors.value.key_file_risk = 'You must acknowledge the key file risk before continuing.';
        return;
    }
    if (!isFileTier.value && !content.value.trim()) {
        errors.value.content = 'Please enter some content.';
        return;
    }
    if (isFileTier.value && !selectedFile.value) {
        errors.value.file = 'Please select a file.';
        return;
    }

    step.value = 'encrypting';
    uploadProgress.value = 0;

    try {
        const effectivePassphrase = await computeEffectivePassphrase();

        const { privateKey: _unusedKey, publicKeyJwkBase64 } = await enc.deriveLockerSigningKeypair(effectivePassphrase, accountId.value);

        let payload;
        let storagePath = null;
        let wrappedFileKey = null;

        if (isFileTier.value) {
            const meta = JSON.stringify({
                name: selectedFile.value.name,
                type: selectedFile.value.type,
                size: selectedFile.value.size,
            });
            payload = await enc.encryptLockerContent(meta, effectivePassphrase);

            const dek = enc.generateLockerFileKey();
            wrappedFileKey = await enc.wrapLockerFileKey(dek, effectivePassphrase, accountId.value);
            const fileBuffer = await selectedFile.value.arrayBuffer();
            const encryptedBytes = await enc.encryptLockerFileToBuffer(fileBuffer, { dek });

            const prepRes = await fetch(route('lockers.file.prepare'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ credit_token: props.credit_token }),
            });

            if (!prepRes.ok) throw new Error('Could not prepare file upload.');

            const { upload_url, upload_headers, storage_path } = await prepRes.json();
            storagePath = storage_path;

            step.value = 'uploading';
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', upload_url);
                for (const [key, val] of Object.entries(upload_headers ?? {})) {
                    xhr.setRequestHeader(key, val);
                }
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        uploadProgress.value = Math.round((e.loaded / e.total) * 100);
                    }
                };
                xhr.onload  = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed.'));
                xhr.onerror = () => reject(new Error('Upload failed.'));
                xhr.send(new Blob([encryptedBytes], { type: 'application/octet-stream' }));
            });
        } else {
            payload = await enc.encryptLockerContent(content.value, effectivePassphrase);
        }

        const res = await fetch(route('lockers.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                account_id:       accountId.value,
                credit_token:     props.credit_token,
                payload,
                public_key:       publicKeyJwkBase64,
                tier:             props.tier,
                storage_path:     storagePath,
                wrapped_file_key: wrappedFileKey,
                auth_mode:        authMode.value,
                key_file_count:   authMode.value !== 'passphrase' ? keyFiles.value.length : null,
                show_clues:       showClues.value,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            step.value = 'form';
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
                );
            } else {
                errors.value.general = data.message ?? 'An error occurred.';
            }
            return;
        }

        localStorage.removeItem('locker_pending_token');
        credentials.value = {
            account_id:   data.account_id,
            passphrase:   passphrase.value,
            expires_at:   data.expires_at,
            keyFileNames: keyFiles.value.map(kf => kf.file.name),
            authMode:     authMode.value,
        };
        step.value = 'credentials';

    } catch (err) {
        step.value = 'form';
        errors.value.general = err.message || 'Encryption failed. Please try again.';
    }
};
</script>

<template>
    <AppLayout title="Create eLocker">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-xl mx-auto">

                <!-- Credentials panel -->
                <div v-if="step === 'credentials'" class="bg-gray-800 border border-gamboge-300 rounded-xl p-8 shadow-neon-cyan">
                    <h1 class="text-2xl font-bold text-white mb-2">Locker created!</h1>
                    <div class="bg-red-900/20 border border-red-500/40 rounded-lg p-4 mb-6 text-red-300 text-sm">
                        <p class="font-semibold text-red-200 mb-1">Save these credentials now — they cannot be recovered.</p>
                        <span v-if="credentials.authMode === 'passphrase'">Your passphrase is the only key to decrypt, update, or delete this locker. The server has never seen it.</span>
                        <span v-else>Your key file(s) and passphrase are the only way to access this locker. The server has never seen them.</span>
                    </div>

                    <div class="space-y-4 mb-6 ph-no-capture">
                        <div class="bg-gray-900 rounded-lg p-3">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Account ID</div>
                            <div class="flex items-center gap-2">
                                <code class="text-white text-sm flex-1 break-all font-mono">{{ credentials.account_id }}</code>
                                <button @click="copyToClipboard(credentials.account_id)" class="shrink-0 text-gray-400 hover:text-gamboge-300 transition-colors text-xs border border-gray-700 hover:border-gamboge-300 rounded px-2 py-1">Copy</button>
                            </div>
                        </div>

                        <div v-if="credentials.authMode !== 'key_file'" class="bg-gray-900 rounded-lg p-3">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Passphrase</div>
                            <div class="flex items-center gap-2">
                                <code class="text-white text-sm flex-1 break-all font-mono">{{ credentials.passphrase }}</code>
                                <button @click="copyToClipboard(credentials.passphrase)" class="shrink-0 text-gray-400 hover:text-gamboge-300 transition-colors text-xs border border-gray-700 hover:border-gamboge-300 rounded px-2 py-1">Copy</button>
                            </div>
                        </div>

                        <div v-if="credentials.authMode !== 'passphrase'" class="bg-gray-900 rounded-lg p-3">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Key Files (load in this order)</div>
                            <div class="space-y-1 mt-2">
                                <div v-for="(name, i) in credentials.keyFileNames" :key="i" class="flex items-center gap-2">
                                    <span class="text-gamboge-300/60 text-xs w-4 shrink-0">{{ i + 1 }}.</span>
                                    <span class="text-white text-sm truncate">{{ name }}</span>
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs mt-2">Key files must be loaded in this exact order when unlocking.</p>
                        </div>
                    </div>

                    <button
                        @click="downloadCredentials"
                        class="w-full mb-4 border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-sm py-2.5 rounded-lg transition-colors"
                    >
                        Download as text file
                    </button>

                    <label class="flex items-center gap-2 text-gray-300 text-sm mb-4 cursor-pointer">
                        <input type="checkbox" v-model="savedConfirmed" class="rounded border-gray-600 bg-gray-700 text-gamboge-300" data-testid="saved-confirmed-checkbox" />
                        <span v-if="credentials.authMode === 'passphrase'">I have saved both credentials</span>
                        <span v-else>I have saved my credentials</span>
                    </label>

                    <button
                        :disabled="!savedConfirmed"
                        @click="router.visit(route('lockers.open'))"
                        class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm"
                    >
                        Open my locker
                    </button>
                    <p class="text-gray-400 text-xs text-center mt-2">
                        Copy your account number above — you will need to enter it on the next screen.
                    </p>
                </div>

                <!-- Create form -->
                <div v-else class="bg-gray-800 border border-gray-700 rounded-xl p-8">
                    <h1 class="text-2xl font-bold text-white mb-1">Create your eLocker</h1>
                    <p class="text-gray-400 text-sm mb-6">
                        {{ tier === 'file' ? 'File' : 'Text' }} locker — {{ years }}-year access
                    </p>

                    <div v-if="errors.general" class="bg-red-900/20 border border-red-500/40 rounded-lg p-3 text-red-300 text-sm mb-4">
                        {{ errors.general }}
                    </div>

                    <!-- Upload progress -->
                    <div v-if="step === 'encrypting' || step === 'uploading'" class="mb-6">
                        <FileProgressBar
                            :state="step === 'uploading' ? 'uploading' : 'encrypting'"
                            :progress="uploadProgress"
                        />
                    </div>

                    <div class="space-y-5" :class="{ 'opacity-50 pointer-events-none': step !== 'form' }">
                        <!-- Account ID -->
                        <div>
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Account ID</label>
                            <input
                                v-model="accountId"
                                type="text"
                                inputmode="numeric"
                                maxlength="10"
                                placeholder="Choose a 10-digit number"
                                class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:ring-gamboge-300 focus:outline-none"
                                :class="{ 'border-red-500': errors.account_id }"
                            />
                            <p v-if="errors.account_id" class="text-red-400 text-xs mt-1">{{ errors.account_id }}</p>
                            <p v-else class="text-gray-500 text-xs mt-1">Your memorable number — like a bank account ID.</p>
                        </div>

                        <!-- Authentication Mode -->
                        <div>
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Authentication Mode</label>
                            <div class="flex gap-2">
                                <button
                                    v-for="mode in [
                                        { value: 'passphrase', label: 'Passphrase' },
                                        { value: 'key_file',  label: 'Key File(s)' },
                                        { value: 'combined',  label: 'Both' },
                                    ]"
                                    :key="mode.value"
                                    type="button"
                                    @click="setAuthMode(mode.value)"
                                    class="flex-1 py-2 rounded-lg font-mono text-xs transition-colors border"
                                    :class="authMode === mode.value
                                        ? 'bg-gamboge-300/20 border-gamboge-300 text-gamboge-300 shadow-neon-cyan-sm'
                                        : 'border-gray-600 text-gray-400 hover:border-gray-400'"
                                >
                                    {{ mode.label }}
                                </button>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">
                                <span v-if="authMode === 'passphrase'">Passphrase only — current behaviour.</span>
                                <span v-else-if="authMode === 'key_file'">One or more key files required to unlock.</span>
                                <span v-else>Both passphrase and all key files required.</span>
                            </p>
                            <div v-if="authMode !== 'passphrase'" class="mt-2 bg-amber-900/20 border border-amber-500/40 rounded-lg p-3 text-amber-400 text-xs">
                                &#x26A0; Key file credential rotation is not yet supported. To change your key files in the future, you will need to recreate the locker.
                            </div>
                        </div>

                        <!-- Passphrase — hidden for key_file mode -->
                        <div v-if="authMode !== 'key_file'">
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Passphrase</label>
                            <div class="flex gap-2">
                                <input
                                    v-model="passphrase"
                                    type="text"
                                    class="flex-1 bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:ring-gamboge-300 focus:outline-none"
                                    :class="{ 'border-red-500': errors.passphrase }"
                                    placeholder="Enter or generate a passphrase"
                                />
                                <button
                                    @click="generatePassphrase"
                                    class="shrink-0 border border-gamboge-300/50 text-gamboge-300 hover:border-gamboge-300 text-xs font-mono px-3 rounded-lg transition-colors"
                                >Generate</button>
                            </div>
                            <div v-if="passphrase" class="mt-2">
                                <div class="h-1 bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-300" :class="[strengthWidth, strengthBg]" />
                                </div>
                                <p class="text-xs mt-1" :class="strengthColor">{{ strengthLabel }}</p>
                            </div>
                            <p v-if="errors.passphrase" class="text-red-400 text-xs mt-1">{{ errors.passphrase }}</p>
                            <p v-else class="text-gray-500 text-xs mt-1">This also controls who can update or delete the locker — keep it safe.</p>
                        </div>

                        <!-- Key File section — shown for key_file and combined modes -->
                        <div v-if="authMode !== 'passphrase'">
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Key Files</label>
                            <p class="text-gray-500 text-xs mb-3">
                                Use a unique file only you possess — a personal photo, private document, or generated key file.
                                Avoid widely-shared files (stock images, public downloads). The file must never change — even a single byte difference will lock you out.
                            </p>

                            <div v-if="keyFiles.length > 0" class="mb-3 space-y-2">
                                <div
                                    v-for="(kf, i) in keyFiles"
                                    :key="i"
                                    class="flex items-center gap-3 bg-gray-900 rounded-lg px-3 py-2"
                                >
                                    <span class="text-gamboge-300/60 font-mono text-xs w-4 shrink-0">{{ i + 1 }}.</span>
                                    <span class="text-white text-sm flex-1 truncate">{{ kf.file.name }}</span>
                                    <button
                                        type="button"
                                        @click="removeKeyFile(i)"
                                        class="shrink-0 text-gray-500 hover:text-red-400 font-mono text-xs transition-colors"
                                        title="Remove"
                                    >✕</button>
                                </div>
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer border border-dashed border-gray-600 hover:border-gamboge-300/50 hover:shadow-neon-cyan-sm rounded-lg px-3 py-2.5 transition-colors text-gray-400 text-sm">
                                <span class="font-mono text-xs">+ Add key file</span>
                                <input type="file" class="sr-only" @change="onKeyFileAdded" data-testid="key-file-input" />
                            </label>
                            <p v-if="errors.key_files" class="text-red-400 text-xs mt-1">{{ errors.key_files }}</p>
                            <p v-else class="text-gray-500 text-xs mt-1">{{ keyFiles.length }} key file(s) added. Files are processed locally — no content is uploaded.</p>

                            <!-- Show clues toggle -->
                            <label class="flex items-start gap-2 text-sm cursor-pointer mt-3">
                                <input
                                    type="checkbox"
                                    v-model="showClues"
                                    class="mt-0.5 rounded border-gray-600 bg-gray-700 text-gamboge-300 shrink-0"
                                    data-testid="show-clues-checkbox"
                                />
                                <span class="text-gray-400 text-xs">
                                    Show unlock hints (displays required credential type and count on the unlock page)
                                </span>
                            </label>
                            <p v-if="!showClues" class="text-gray-500 text-xs mt-1 ml-5">
                                The unlock page will show a generic interface with no hints about what credentials are needed.
                            </p>

                            <!-- Recovery acknowledgement (required) -->
                            <label class="flex items-start gap-2 text-sm cursor-pointer mt-3 p-3 bg-red-900/10 border border-red-500/30 rounded-lg">
                                <input
                                    type="checkbox"
                                    v-model="keyFileRiskAcknowledged"
                                    class="mt-0.5 rounded border-gray-600 bg-gray-700 text-gamboge-300 shrink-0"
                                    data-testid="key-file-risk-checkbox"
                                />
                                <span class="text-red-300 text-xs">
                                    I understand that if I lose my key file(s), my locker cannot be unlocked by anyone, including FlashView support. There is no recovery option.
                                </span>
                            </label>
                            <p v-if="errors.key_file_risk" class="text-red-400 text-xs mt-1">{{ errors.key_file_risk }}</p>
                        </div>

                        <!-- Content -->
                        <div>
                            <label for="locker-content-input" class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">
                                {{ isFileTier ? 'File' : 'Content' }}
                            </label>
                            <textarea
                                v-if="!isFileTier"
                                id="locker-content-input"
                                v-model="content"
                                rows="6"
                                class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:ring-gamboge-300 focus:outline-none resize-y"
                                :class="{ 'border-red-500': errors.content }"
                                placeholder="Enter the content to store…"
                            />
                            <input
                                v-else
                                id="locker-content-input"
                                type="file"
                                @change="onFileChange"
                                class="w-full bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none file:mr-3 file:text-gamboge-300 file:bg-gray-800 file:border-0 file:rounded file:text-xs file:font-mono file:cursor-pointer"
                                :class="{ 'border-red-500': errors.file }"
                            />
                            <p v-if="errors.content || errors.file" class="text-red-400 text-xs mt-1">{{ errors.content || errors.file }}</p>
                        </div>

                        <button
                            @click="submit"
                            :disabled="step !== 'form'"
                            class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-60 disabled:cursor-not-allowed text-gray-900 font-semibold py-3 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                            data-testid="create-submit-button"
                        >
                            <span v-if="step === 'encrypting'">Encrypting…</span>
                            <span v-else-if="step === 'uploading'">Uploading…</span>
                            <span v-else>Encrypt &amp; Create</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
