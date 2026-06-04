<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileProgressBar from '@/Components/FileProgressBar.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { encryption, LockerDecryptionError } from '@/encryption.js';

const props = defineProps({
    account_id: String,
    renewed:    Boolean,
});

const enc = new encryption();

// Populated after successful unlock — not passed from server initially (prevents enumeration)
const isFileLocker   = ref(false);
const expiresAt      = ref(null); // ISO string or null
const authChallenge  = ref('');
const wrappedFileKey = ref('');  // base64 KEK-wrapped DEK; empty for legacy lockers

// ECDSA / upgrade state
const isLegacyLocker   = ref(false);
const upgrading        = ref(false);
const upgradeSuccess   = ref(false);
const upgradeDismissed = ref(false);
const upgradeError     = ref('');

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

// Passphrase change state
const newPassphrase            = ref('');
const passphraseChangeError    = ref('');
const passphraseChangeSuccess  = ref(false);
const passphraseChangeState    = ref(null); // null | 'encrypting' | 'uploading'
const passphraseChangeProgress = ref(0);

const newPassphraseStrength = computed(() => {
    const p = newPassphrase.value;
    if (!p) return 0;
    let score = 0;
    if (p.length >= 12) score++;
    if (p.length >= 20) score++;
    if (/[A-Z]/.test(p) || /\d/.test(p)) score++;
    if (/[^a-z0-9]/i.test(p)) score++;
    return score;
});
const newPassphraseStrengthLabel = computed(() => ['', 'Weak', 'Fair', 'Good', 'Strong'][newPassphraseStrength.value] ?? '');
const newPassphraseStrengthColor = computed(() => ['', 'text-red-400', 'text-gamboge-500', 'text-gamboge-400', 'text-gamboge-300'][newPassphraseStrength.value] ?? '');
const newPassphraseStrengthWidth = computed(() => ['w-0', 'w-1/4', 'w-2/4', 'w-3/4', 'w-full'][newPassphraseStrength.value] ?? 'w-0');
const newPassphraseStrengthBg    = computed(() => ['', 'bg-red-400', 'bg-gamboge-500', 'bg-gamboge-400', 'bg-gamboge-300'][newPassphraseStrength.value] ?? '');
const generateNewPassphrase = () => { newPassphrase.value = enc.generatePasssphrase(); };

const lockLocker = () => {
    passphrase.value          = '';
    decryptedText.value       = '';
    decryptedFileMeta.value   = null;
    downloadUrl.value         = '';
    authChallenge.value       = '';
    wrappedFileKey.value      = '';
    decryptError.value        = '';
    updateError.value         = '';
    updateSuccess.value       = false;
    newContent.value          = '';
    replacementFile.value     = null;
    passphraseChangeError.value   = '';
    passphraseChangeSuccess.value = false;
    newPassphrase.value       = '';
    deleteError.value         = '';
    showDeleteConfirm.value   = false;
    isLegacyLocker.value      = false;
    upgrading.value           = false;
    upgradeSuccess.value      = false;
    upgradeDismissed.value    = false;
    upgradeError.value        = '';
    lockState.value           = 'locked';
};

const daysRemaining = computed(() => {
    if (!expiresAt.value) return null;
    const ms = new Date(expiresAt.value).getTime() - Date.now();
    return Math.max(0, Math.ceil(ms / 86_400_000));
});

const expiryLabel = computed(() => {
    if (daysRemaining.value === null) return '';
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
    lockState.value    = 'animating';

    const animationStart = Date.now();

    try {
        // Step 1: Fetch challenge (always returns one — fake for non-existent accounts)
        const challengeRes = await fetch(route('lockers.challenge', props.account_id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!challengeRes.ok) {
            const data = await challengeRes.json().catch(() => ({}));
            const elapsed = Date.now() - animationStart;
            if (elapsed < 600) await sleep(600 - elapsed);
            failCount.value++;
            triggerShake(data.error ?? 'Too many attempts. Please try again later.');
            return;
        }

        const challengeData = await challengeRes.json();
        const isEcdsa = Boolean(challengeData.challenge_id);

        let unlockBody;
        if (isEcdsa) {
            // ECDSA path: derive private key, sign the server challenge
            const { privateKey } = await enc.deriveLockerSigningKeypair(passphrase.value, props.account_id);
            const signature = await enc.signLockerChallenge(privateKey, challengeData.challenge);
            unlockBody = { challenge_id: challengeData.challenge_id, signature };
        } else {
            // Legacy HMAC path: derive verifier from passphrase
            const authKey  = await enc.deriveLockerAuthKey(passphrase.value, props.account_id);
            const verifier = await enc.computeLockerVerifier(authKey, challengeData.challenge);
            unlockBody = { verifier };
        }

        // Step 3: POST unlock — server verifies, returns payload only if correct
        const unlockRes = await fetch(route('lockers.unlock', props.account_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrf(),
            },
            body: JSON.stringify(unlockBody),
        });

        const data = await unlockRes.json();
        const elapsed = Date.now() - animationStart;
        if (elapsed < 600) await sleep(600 - elapsed);

        if (!unlockRes.ok) {
            failCount.value++;
            triggerShake(data.error ?? (unlockRes.status === 429 ? 'Too many attempts. Please try again later.' : 'Credentials do not match.'));
            return;
        }

        // Success — populate state from unlock response (server uses snake_case JSON)
        isFileLocker.value   = data.is_file_locker ?? false;
        expiresAt.value      = data.expires_at ?? null;
        authChallenge.value  = data.auth_challenge ?? '';
        wrappedFileKey.value = data.wrapped_file_key ?? '';
        isLegacyLocker.value = !isEcdsa;

        const result = await enc.decryptLockerContent(data.payload, passphrase.value);

        if (data.is_file_locker) {
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

        // Decrypt — v2 lockers use DEK from wrapped_file_key; v1 legacy use passphrase in blob
        let decryptedBuffer;
        if (wrappedFileKey.value) {
            const dek = await enc.unwrapLockerFileKey(wrappedFileKey.value, passphrase.value, props.account_id);
            decryptedBuffer = await enc.decryptLockerFileFromBuffer(encryptedBytes, { dek });
        } else {
            decryptedBuffer = await enc.decryptLockerFileFromBuffer(encryptedBytes, { passphrase: passphrase.value });
        }

        // Trigger browser download
        const name = decryptedFileMeta.value?.name ?? 'locker-file';
        const type = decryptedFileMeta.value?.type ?? 'application/octet-stream';
        const blob = new Blob([decryptedBuffer], { type });
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

const fetchSigningHeaders = async () => {
    const challengeRes = await fetch(route('lockers.challenge', props.account_id), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!challengeRes.ok) throw new Error('Could not fetch challenge.');
    const challengeData = await challengeRes.json();

    if (challengeData.challenge_id) {
        const { privateKey } = await enc.deriveLockerSigningKeypair(passphrase.value, props.account_id);
        const signature = await enc.signLockerChallenge(privateKey, challengeData.challenge);
        return {
            'X-Signing-Challenge-Id': challengeData.challenge_id,
            'X-Signature': signature,
        };
    } else {
        const updateToken = await enc.deriveLockerUpdateToken(passphrase.value, props.account_id);
        return { 'X-Update-Token': updateToken };
    }
};

const submitUpdate = async () => {
    updateError.value   = '';
    updateSuccess.value = false;
    if (!newContent.value.trim()) { updateError.value = 'Content cannot be empty.'; return; }

    updating.value = true;
    try {
        const payload  = await enc.encryptLockerContent(newContent.value, passphrase.value);
        const authHeaders = await fetchSigningHeaders();

        const res = await fetch(route('lockers.update', props.account_id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrf(),
                ...authHeaders,
            },
            body: JSON.stringify({ payload }),
        });

        const data = await res.json();
        if (!res.ok) { updateError.value = data.error ?? 'Update failed.'; return; }

        updateSuccess.value = true;
        decryptedText.value = newContent.value;
        newContent.value    = '';
    } catch (err) {
        updateError.value = err.message || 'Update failed. Please try again.';
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

        // Generate fresh DEK for the replacement file and encrypt directly to Uint8Array
        const newDek = enc.generateLockerFileKey();
        const newWrappedFileKey = await enc.wrapLockerFileKey(newDek, passphrase.value, props.account_id);
        const fileBuffer = await replacementFile.value.arrayBuffer();
        const bytes = await enc.encryptLockerFileToBuffer(fileBuffer, { dek: newDek });

        // Get presigned upload URL
        const prepRes = await fetch(route('lockers.file.prepare'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf() },
            body: JSON.stringify({}), // no credit_token needed for updates
        });

        let storagePath = null;
        if (prepRes.ok) {
            const { upload_url, upload_headers, storage_path } = await prepRes.json();
            storagePath   = storage_path;
            updateState.value = 'uploading';

            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', upload_url);
                for (const [k, v] of Object.entries(upload_headers ?? {})) xhr.setRequestHeader(k, v);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) updateProgress.value = Math.round((e.loaded / e.total) * 100);
                };
                xhr.onload  = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed.'));
                xhr.onerror = () => reject(new Error('Upload failed.'));
                xhr.send(new Blob([bytes], { type: 'application/octet-stream' }));
            });
        }

        const authHeaders = await fetchSigningHeaders();
        const body = { payload };
        if (storagePath) {
            body.storage_path = storagePath;
            body.new_wrapped_file_key = newWrappedFileKey;
        }

        const res = await fetch(route('lockers.update', props.account_id), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf(), ...authHeaders },
            body: JSON.stringify(body),
        });

        const data = await res.json();
        if (!res.ok) { updateError.value = data.error ?? 'Update failed.'; return; }

        updateSuccess.value  = true;
        wrappedFileKey.value = newWrappedFileKey;
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

const changePassphrase = async () => {
    passphraseChangeError.value   = '';
    passphraseChangeSuccess.value = false;

    if (newPassphrase.value.length < 8) {
        passphraseChangeError.value = 'New passphrase must be at least 8 characters.';
        return;
    }
    if (newPassphrase.value === passphrase.value) {
        passphraseChangeError.value = 'New passphrase must be different from the current one.';
        return;
    }

    passphraseChangeState.value = 'encrypting';
    passphraseChangeProgress.value = 0;

    try {
        const authHeaders = await fetchSigningHeaders();

        let newPayload;
        const putBody = {};

        // ECDSA lockers: register new public key derived from new passphrase
        if (!isLegacyLocker.value) {
            const { publicKeyJwkBase64: newPublicKey } = await enc.deriveLockerSigningKeypair(newPassphrase.value, props.account_id);
            putBody.new_public_key = newPublicKey;
        } else {
            // Legacy lockers: update verifier + update token
            const newAuthKey     = await enc.deriveLockerAuthKey(newPassphrase.value, props.account_id);
            const newVerifier    = await enc.computeLockerVerifier(newAuthKey, authChallenge.value);
            const newUpdateToken = await enc.deriveLockerUpdateToken(newPassphrase.value, props.account_id);
            putBody.new_auth_verifier = newVerifier;
            putBody.new_update_token  = newUpdateToken;
        }

        if (isFileLocker.value && wrappedFileKey.value) {
            // New locker (v2): re-wrap DEK with new passphrase — no file download required
            const meta = JSON.stringify(decryptedFileMeta.value);
            newPayload = await enc.encryptLockerContent(meta, newPassphrase.value);
            const dek = await enc.unwrapLockerFileKey(wrappedFileKey.value, passphrase.value, props.account_id);
            const newWrappedKey = await enc.wrapLockerFileKey(dek, newPassphrase.value, props.account_id);
            putBody.new_wrapped_file_key = newWrappedKey;
            putBody.payload = newPayload;

            const res = await fetch(route('lockers.update', props.account_id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf(), ...authHeaders },
                body: JSON.stringify(putBody),
            });

            const data = await res.json();
            if (!res.ok) { passphraseChangeError.value = data.error ?? 'Failed to change passphrase.'; return; }

            passphrase.value     = newPassphrase.value;
            wrappedFileKey.value = newWrappedKey;
            newPassphrase.value           = '';
            passphraseChangeSuccess.value = true;

        } else if (isFileLocker.value) {
            // Legacy locker (v1): download, decrypt, re-encrypt with new passphrase
            const meta = JSON.stringify(decryptedFileMeta.value);
            newPayload = await enc.encryptLockerContent(meta, newPassphrase.value);

            const encryptedBytes = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', downloadUrl.value);
                xhr.responseType = 'arraybuffer';
                xhr.onprogress = (e) => {
                    if (e.lengthComputable) passphraseChangeProgress.value = Math.round((e.loaded / e.total) * 50);
                };
                xhr.onload  = () => (xhr.status >= 200 && xhr.status < 300) ? resolve(new Uint8Array(xhr.response)) : reject(new Error('Could not download file for re-encryption.'));
                xhr.onerror = () => reject(new Error('Could not download file for re-encryption.'));
                xhr.send();
            });

            const fileData = await enc.decryptLockerFileFromBuffer(encryptedBytes, { passphrase: passphrase.value });
            const reEncryptedBytes = await enc.encryptLockerFileToBuffer(fileData, { passphrase: newPassphrase.value });

            const prepRes = await fetch(route('lockers.file.prepare'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf() },
                body: JSON.stringify({}),
            });
            if (!prepRes.ok) throw new Error('Could not prepare file upload.');
            const { upload_url, upload_headers, storage_path: newStoragePath } = await prepRes.json();

            passphraseChangeState.value = 'uploading';
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', upload_url);
                for (const [k, v] of Object.entries(upload_headers ?? {})) xhr.setRequestHeader(k, v);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) passphraseChangeProgress.value = 50 + Math.round((e.loaded / e.total) * 50);
                };
                xhr.onload  = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed.'));
                xhr.onerror = () => reject(new Error('Upload failed.'));
                xhr.send(new Blob([reEncryptedBytes], { type: 'application/octet-stream' }));
            });

            putBody.payload = newPayload;
            putBody.storage_path = newStoragePath;

            const res = await fetch(route('lockers.update', props.account_id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf(), ...authHeaders },
                body: JSON.stringify(putBody),
            });

            const data = await res.json();
            if (!res.ok) { passphraseChangeError.value = data.error ?? 'Failed to change passphrase.'; return; }

            passphrase.value = newPassphrase.value;
            downloadUrl.value = '';
            newPassphrase.value           = '';
            passphraseChangeSuccess.value = true;

        } else {
            newPayload = await enc.encryptLockerContent(decryptedText.value, newPassphrase.value);
            putBody.payload = newPayload;

            const res = await fetch(route('lockers.update', props.account_id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf(), ...authHeaders },
                body: JSON.stringify(putBody),
            });

            const data = await res.json();
            if (!res.ok) { passphraseChangeError.value = data.error ?? 'Failed to change passphrase.'; return; }

            passphrase.value = newPassphrase.value;
            newPassphrase.value           = '';
            passphraseChangeSuccess.value = true;
        }

    } catch (err) {
        passphraseChangeError.value = err.message || 'Failed to change passphrase. Please try again.';
    } finally {
        passphraseChangeState.value = null;
    }
};

const confirmDelete = async () => {
    deleteError.value = '';
    deleting.value    = true;
    try {
        const authHeaders = await fetchSigningHeaders();

        const res = await fetch(route('lockers.destroy', props.account_id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrf(),
                ...authHeaders,
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

const upgradeAuth = async () => {
    upgrading.value     = true;
    upgradeError.value  = '';
    try {
        const authKey  = await enc.deriveLockerAuthKey(passphrase.value, props.account_id);
        const verifier = await enc.computeLockerVerifier(authKey, authChallenge.value);
        const { publicKeyJwkBase64 } = await enc.deriveLockerSigningKeypair(passphrase.value, props.account_id);

        const res = await fetch(route('lockers.upgrade-auth', props.account_id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': getXsrf() },
            body: JSON.stringify({ verifier, public_key: publicKeyJwkBase64 }),
        });
        const data = await res.json();
        if (res.status === 429) { upgradeError.value = data.error ?? 'Too many failed attempts. Try again in 1 hour. Your locker is unchanged.'; return; }
        if (!res.ok) { upgradeError.value = data.error ?? 'Upgrade failed.'; return; }

        isLegacyLocker.value = false;
        upgradeSuccess.value = true;
        setTimeout(() => { upgradeSuccess.value = false; }, 4000);
    } catch {
        upgradeError.value = 'Upgrade failed. Please try again.';
    } finally {
        upgrading.value = false;
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

                <!-- Upgrade success banner -->
                <div
                    v-if="upgradeSuccess"
                    data-testid="upgrade-success"
                    class="bg-gamboge-300/10 border border-gamboge-300/40 rounded-xl p-4 text-gamboge-300 text-sm text-center"
                >
                    Your locker has been upgraded to stronger security.
                </div>

                <!-- Upgrade banner — visible after legacy unlock, dismissable -->
                <div
                    v-if="lockState === 'unlocked' && isLegacyLocker && !upgradeSuccess && !upgradeDismissed"
                    data-testid="upgrade-banner"
                    class="bg-gamboge-300/10 border border-gamboge-300/40 rounded-xl p-4 flex items-start justify-between gap-4"
                >
                    <div class="text-sm text-gamboge-300">
                        <p class="font-semibold mb-1">Security upgrade available</p>
                        <p class="text-gamboge-300/70 text-xs">This locker uses an older security scheme. Upgrade it for stronger protection — takes a moment and requires no passphrase change.</p>
                        <p v-if="upgradeError" class="text-red-400 text-xs mt-1">{{ upgradeError }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            @click="upgradeAuth"
                            :disabled="upgrading"
                            data-testid="upgrade-button"
                            class="border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-xs px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50"
                        >{{ upgrading ? 'Upgrading…' : 'Upgrade' }}</button>
                        <button
                            @click="upgradeDismissed = true"
                            :disabled="upgrading"
                            data-testid="upgrade-dismiss-button"
                            class="text-gamboge-300/50 hover:text-gamboge-300 font-mono text-xs px-2 py-1.5 transition-colors disabled:opacity-50"
                            title="Dismiss"
                        >✕</button>
                    </div>
                </div>

                <!-- Locker header -->
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-0.5">eLocker</div>
                        <div class="ph-no-capture text-white font-mono text-xl tracking-widest">{{ account_id }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div v-if="expiresAt" class="text-right">
                            <div :class="daysRemaining <= 30 ? 'text-red-400' : 'text-gray-400'" class="text-xs font-mono">{{ expiryLabel }}</div>
                            <a :href="route('lockers.renew.challenge', account_id)" class="text-gamboge-300 hover:text-gamboge-200 text-xs font-mono underline">Renew</a>
                        </div>
                        <button
                            v-if="lockState === 'unlocked'"
                            @click="lockLocker"
                            data-testid="lock-button"
                            class="flex items-center gap-1.5 border border-gray-600 hover:border-gray-400 text-gray-400 hover:text-white font-mono text-xs px-3 py-1.5 rounded-lg transition-colors"
                            title="Lock locker"
                        >
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            Lock
                        </button>
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
                            <div v-if="!isFileLocker" class="bg-gray-900 rounded-lg p-4">
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
                    <template v-if="!isFileLocker">
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
                    <p v-if="!isFileLocker && updateSuccess" class="text-gamboge-300 text-xs">Content updated.</p>

                    <button
                        v-if="!updateState"
                        @click="isFileLocker ? submitFileUpdate() : submitUpdate()"
                        :disabled="updating"
                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-xs py-2 rounded-lg transition-colors disabled:opacity-50"
                        data-testid="update-button"
                    >{{ updating ? 'Updating…' : (isFileLocker ? 'Replace File' : 'Update') }}</button>
                </div>

                <!-- Update hint when locked -->
                <div v-if="lockState !== 'unlocked'" class="bg-gray-800 border border-gray-700 rounded-xl p-4 text-center">
                    <p class="text-gray-500 text-xs">Unlock your locker with your passphrase to update or delete it.</p>
                </div>

                <!-- Change Passphrase panel — only when unlocked -->
                <div v-if="lockState === 'unlocked'" class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Change Passphrase</h2>
                    <p class="text-gray-400 text-xs">Your content will be re-encrypted with the new passphrase. This cannot be undone.</p>

                    <div>
                        <div class="flex gap-2">
                            <input
                                v-model="newPassphrase"
                                type="text"
                                placeholder="Enter or generate a passphrase"
                                class="flex-1 bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none"
                            />
                            <button
                                @click="generateNewPassphrase"
                                class="shrink-0 border border-gamboge-300/50 text-gamboge-300 hover:border-gamboge-300 text-xs font-mono px-3 rounded-lg transition-colors"
                            >Generate</button>
                        </div>
                        <div v-if="newPassphrase" class="mt-2">
                            <div class="h-1 bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300" :class="[newPassphraseStrengthWidth, newPassphraseStrengthBg]" />
                            </div>
                            <p class="text-xs mt-1" :class="newPassphraseStrengthColor">{{ newPassphraseStrengthLabel }}</p>
                        </div>
                    </div>

                    <FileProgressBar v-if="passphraseChangeState" :state="passphraseChangeState" :progress="passphraseChangeProgress" />
                    <p v-if="passphraseChangeError" class="text-red-400 text-xs">{{ passphraseChangeError }}</p>
                    <p v-if="passphraseChangeSuccess" class="text-gamboge-300 text-xs">Passphrase changed. Use your new passphrase next time you unlock.</p>

                    <button
                        v-if="!passphraseChangeState"
                        @click="changePassphrase"
                        class="w-full border border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10 font-mono text-xs py-2 rounded-lg transition-colors"
                    >Change Passphrase</button>
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
                            <p class="text-red-300/80 text-xs">Your locker expires on {{ new Date(expiresAt).toLocaleDateString() }}. Deleting now means that time is lost — there is no refund or credit. To store content again you would need to purchase a new locker.</p>
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
