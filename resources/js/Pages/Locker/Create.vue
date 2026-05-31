<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileProgressBar from '@/Components/FileProgressBar.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { encryption } from '@/encryption.js';

const props = defineProps({
    credit_token: String,
    tier: String,
    years: Number,
});

const enc = new encryption();

// Form fields
const accountId    = ref('');
const passphrase   = ref('');
const content      = ref('');
const selectedFile = ref(null);

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
const strengthColor = computed(() => ['', 'text-red-400', 'text-yellow-400', 'text-blue-400', 'text-gamboge-300'][passphraseStrength.value] ?? '');
const strengthWidth = computed(() => ['w-0', 'w-1/4', 'w-2/4', 'w-3/4', 'w-full'][passphraseStrength.value] ?? 'w-0');
const strengthBg    = computed(() => ['', 'bg-red-400', 'bg-yellow-400', 'bg-blue-400', 'bg-gamboge-300'][passphraseStrength.value] ?? '');

const generatePassphrase = () => { passphrase.value = enc.generatePasssphrase(); };
const onFileChange = (e) => { selectedFile.value = e.target.files[0] ?? null; };
const copyToClipboard = async (text) => { await navigator.clipboard.writeText(text); };

const downloadCredentials = () => {
    const text = [
        'eLocker Credentials — Save these securely. They cannot be recovered.',
        '',
        `Account ID: ${credentials.value.account_id}`,
        `Passphrase: ${credentials.value.passphrase}`,
        '',
        `Expires: ${new Date(credentials.value.expires_at).toLocaleDateString()}`,
        '',
        'Note: Your passphrase is the only key to decrypt and modify your locker.',
    ].join('\n');
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
    if (!passphrase.value || passphrase.value.length < 8) {
        errors.value.passphrase = 'Passphrase must be at least 8 characters.';
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
        const authKey     = await enc.deriveLockerAuthKey(passphrase.value, accountId.value);
        const updateToken = await enc.deriveLockerUpdateToken(passphrase.value, accountId.value);
        const challengeHex = enc.generateLockerChallenge();
        const verifier    = await enc.computeLockerVerifier(authKey, challengeHex);

        let payload;
        let storagePath = null;

        if (isFileTier.value) {
            // Encrypt file metadata into the payload field
            const meta = JSON.stringify({
                name: selectedFile.value.name,
                type: selectedFile.value.type,
                size: selectedFile.value.size,
            });
            payload = await enc.encryptLockerContent(meta, passphrase.value);

            // Encrypt the file contents
            const fileBuffer    = await selectedFile.value.arrayBuffer();
            const encryptedBlob = await enc.encryptLockerFile(selectedFile.value, passphrase.value);

            // Convert hex blob to binary for efficient S3 storage
            const hexStr = encryptedBlob;
            const bytes  = new Uint8Array(hexStr.length / 2);
            for (let i = 0; i < hexStr.length; i += 2) {
                bytes[i / 2] = parseInt(hexStr.slice(i, i + 2), 16);
            }

            // Get presigned upload URL
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

            const { upload_type, upload_url, upload_headers, storage_path } = await prepRes.json();
            storagePath = storage_path;

            // Upload encrypted binary via XHR for progress tracking
            step.value = 'uploading';
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(upload_type === 's3_direct' ? 'PUT' : 'POST', upload_url);
                if (upload_type === 'server') {
                    xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
                }
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
                xhr.send(new Blob([bytes], { type: 'application/octet-stream' }));
            });
        } else {
            payload = await enc.encryptLockerContent(content.value, passphrase.value);
        }

        const res = await fetch(route('lockers.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                account_id:    accountId.value,
                credit_token:  props.credit_token,
                payload,
                auth_verifier: verifier,
                update_token:  updateToken,
                tier:          props.tier,
                storage_path:  storagePath,
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

        credentials.value = {
            account_id:  data.account_id,
            passphrase:  passphrase.value,
            expires_at:  data.expires_at,
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
                        Your passphrase is the only key to decrypt, update, or delete this locker. The server has never seen it.
                    </div>

                    <div class="space-y-4 mb-6">
                        <div v-for="field in [
                            { label: 'Account ID', value: credentials.account_id },
                            { label: 'Passphrase', value: credentials.passphrase },
                        ]" :key="field.label" class="bg-gray-900 rounded-lg p-3">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">{{ field.label }}</div>
                            <div class="flex items-center gap-2">
                                <code class="text-white text-sm flex-1 break-all font-mono">{{ field.value }}</code>
                                <button
                                    @click="copyToClipboard(field.value)"
                                    class="shrink-0 text-gray-400 hover:text-gamboge-300 transition-colors text-xs border border-gray-700 hover:border-gamboge-300 rounded px-2 py-1"
                                >Copy</button>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="downloadCredentials"
                        class="w-full mb-4 border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-sm py-2.5 rounded-lg transition-colors"
                    >
                        Download as text file
                    </button>

                    <label class="flex items-center gap-2 text-gray-300 text-sm mb-4 cursor-pointer">
                        <input type="checkbox" v-model="savedConfirmed" class="rounded border-gray-600 bg-gray-700 text-gamboge-300" />
                        I have saved both credentials
                    </label>

                    <button
                        :disabled="!savedConfirmed"
                        @click="router.visit(route('lockers.show', credentials.account_id))"
                        class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm"
                    >
                        Open my locker
                    </button>
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

                        <!-- Passphrase -->
                        <div>
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

                        <!-- Content -->
                        <div>
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">
                                {{ isFileTier ? 'File' : 'Content' }}
                            </label>
                            <textarea
                                v-if="!isFileTier"
                                v-model="content"
                                rows="6"
                                class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:ring-gamboge-300 focus:outline-none resize-y"
                                :class="{ 'border-red-500': errors.content }"
                                placeholder="Enter the content to store…"
                            />
                            <input
                                v-else
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
