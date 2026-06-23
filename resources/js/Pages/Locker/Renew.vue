<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { encryption } from '@/encryption.js';
import LockerController from '@/actions/App/Http/Controllers/LockerController';

const enc = new encryption();

const accountId    = ref('');
const authMode     = ref('passphrase');
const keyFileCount = ref<number | null>(null);
const showClues    = ref(true);
const keyFiles     = ref<{ file: File }[]>([]);
const tier         = ref('');
const expiresAt    = ref<string | null>(null);
const initialising = ref(true);
const initError    = ref('');

const passphrase = ref('');
const years      = ref(1);
const error      = ref('');
const loading    = ref(false);

const daysRemaining = computed(() => {
    if (!expiresAt.value) return null;
    const ms = new Date(expiresAt.value).getTime() - Date.now();
    return Math.max(0, Math.ceil(ms / 86_400_000));
});

const tierLabel = computed(() => {
    if (!tier.value) return '—';
    return tier.value === 'file' ? 'File Locker' : 'Text Locker';
});

const daysLabel = computed(() => {
    if (daysRemaining.value === null) return '—';
    return daysRemaining.value;
});

onMounted(async () => {
    const prefill = sessionStorage.getItem('locker_prefill_account_renew');
    if (!prefill || !/^\d{10}$/.test(prefill)) {
        router.visit(LockerController.index.url());
        return;
    }
    sessionStorage.removeItem('locker_prefill_account_renew');
    accountId.value = prefill;

    try {
        const infoRes = await fetch(LockerController.authInfo.url(accountId.value), {
            headers: { 'Accept': 'application/json' },
        });
        if (!infoRes.ok) {
            initError.value = 'Could not load locker information. Please try again.';
            return;
        }
        const info = await infoRes.json();
        authMode.value     = info.auth_mode ?? 'passphrase';
        keyFileCount.value = info.key_file_count ?? null;
        showClues.value    = info.show_clues ?? true;
        tier.value         = info.tier ?? '';
        expiresAt.value    = info.expires_at ?? null;
    } catch {
        initError.value = "We couldn't reach the server. Please check your connection and try again.";
    } finally {
        initialising.value = false;
    }
});

const onKeyFileAdded = (e: Event): void => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    input.value = '';
    keyFiles.value.push({ file });
};

const removeKeyFile = (index: number): void => {
    keyFiles.value.splice(index, 1);
};

const canRenew = computed(() => {
    if (!showClues.value) {
        return passphrase.value.length > 0 || keyFiles.value.length > 0;
    }
    if (authMode.value === 'key_file') {
        return keyFiles.value.length >= (keyFileCount.value ?? 1);
    }
    if (authMode.value === 'combined') {
        return passphrase.value.length > 0 && keyFiles.value.length >= (keyFileCount.value ?? 1);
    }
    return passphrase.value.length > 0;
});

const computeEffectivePassphrase = async () => {
    const hasPassphrase = passphrase.value.length > 0;
    const hasFiles = keyFiles.value.length > 0;

    const getFileHashes = async () => Promise.all(
        keyFiles.value.map(async (kf) => {
            const buf = await kf.file.arrayBuffer();
            return enc.deriveLockerKeyFromFile(buf);
        })
    );

    if (!showClues.value) {
        if (!hasFiles) return passphrase.value;
        const fileHashes = await getFileHashes();
        if (!hasPassphrase) return enc.combineLockerKeyMaterials(fileHashes);
        return enc.combineLockerKeyMaterials([passphrase.value, ...fileHashes]);
    }

    if (authMode.value === 'passphrase') {
        return passphrase.value;
    }
    const fileHashes = await getFileHashes();
    if (authMode.value === 'key_file') {
        return enc.combineLockerKeyMaterials(fileHashes);
    }
    return enc.combineLockerKeyMaterials([passphrase.value, ...fileHashes]);
};

const submit = async () => {
    error.value = '';
    loading.value = true;
    try {
        const challengeRes = await fetch(LockerController.renewChallenge.url(accountId.value), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!challengeRes.ok) {
            error.value = challengeRes.status === 429
                ? 'Too many attempts. Please wait a few minutes before trying again.'
                : 'Could not fetch challenge. Please try again.';
            return;
        }

        const challengeData = await challengeRes.json();
        const isEcdsa = Boolean(challengeData.challenge_id);
        const ep = await computeEffectivePassphrase();
        const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

        let renewBody;
        if (isEcdsa) {
            const { privateKey } = await enc.deriveLockerSigningKeypair(ep, accountId.value);
            const signature = await enc.signLockerChallenge(privateKey, challengeData.challenge);
            renewBody = { challenge_id: challengeData.challenge_id, signature, years: years.value, tier: tier.value || 'text' };
        } else {
            const authKey = await enc.deriveLockerAuthKey(ep, accountId.value);
            const verifier = await enc.computeLockerVerifier(authKey, challengeData.challenge);
            renewBody = { verifier, years: years.value, tier: tier.value || 'text' };
        }

        const renewRes = await fetch(LockerController.renewPurchase.url(accountId.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
            body: JSON.stringify(renewBody),
        });

        const data = await renewRes.json();

        if (!renewRes.ok) {
            error.value = renewRes.status === 429
                ? 'Too many attempts. Please wait a few minutes before trying again.'
                : (data.error ?? 'Renewal failed. Please try again.');
            return;
        }

        window.location.href = data.checkout_url;

    } catch {
        error.value = 'An unexpected error occurred. Please try again.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AppLayout title="Renew eLocker">
        <div class="dark min-h-screen bg-gray-900 py-16 px-4">
            <div class="max-w-md mx-auto">

                <!-- Initialising skeleton -->
                <div v-if="initialising" class="bg-gray-800 border border-gray-700 rounded-xl p-8 space-y-6">
                    <div class="space-y-2">
                        <div class="h-3 w-24 bg-gray-700 rounded animate-shimmer" />
                        <div class="h-6 w-40 bg-gray-700 rounded animate-shimmer" />
                    </div>
                    <div class="bg-gray-900 rounded-lg p-4 space-y-3">
                        <div class="h-4 w-full bg-gray-700 rounded animate-shimmer" />
                        <div class="h-4 w-3/4 bg-gray-700 rounded animate-shimmer" />
                    </div>
                    <div class="text-gray-500 text-xs text-center font-mono animate-pulse">Loading locker details…</div>
                </div>

                <!-- Init error -->
                <div v-else-if="initError" class="bg-gray-800 border border-gray-700 rounded-xl p-8 space-y-4">
                    <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Renew eLocker</div>
                    <p class="text-red-400 text-sm">{{ initError }}</p>
                    <Link
                        :href="LockerController.index.url()"
                        class="block text-center text-gamboge-300 hover:text-gamboge-200 text-sm font-mono underline"
                    >Back to eLocker home</Link>
                </div>

                <!-- Main renew form -->
                <div v-else class="bg-gray-800 border border-gray-700 rounded-xl p-8 space-y-6">

                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Renew eLocker</div>
                            <h1 class="ph-no-capture text-xl font-bold text-white font-mono">{{ accountId }}</h1>
                        </div>
                        <Link
                            :href="LockerController.index.url()"
                            class="text-gray-500 hover:text-gamboge-300 text-xs font-mono transition-colors"
                        >Wrong locker? Start over</Link>
                    </div>

                    <!-- Current tier & expiry -->
                    <div class="bg-gray-900 rounded-lg p-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tier</span>
                            <span class="text-white font-mono capitalize">{{ tierLabel }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Days remaining</span>
                            <span
                                :class="showClues && daysRemaining !== null && daysRemaining <= 30 ? 'text-red-400' : 'text-white'"
                                class="font-mono"
                            >{{ daysLabel }}</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Duration -->
                        <div>
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-2">Renewal Duration</label>
                            <div class="flex gap-2">
                                <button
                                    v-for="y in [1, 3, 5]"
                                    :key="y"
                                    @click="years = y"
                                    :class="years === y ? 'bg-gamboge-300 text-gray-900 shadow-neon-cyan-sm' : 'border border-gray-700 text-gray-300 hover:border-gamboge-300/50'"
                                    class="flex-1 font-mono text-sm py-2 rounded-lg transition-all"
                                >
                                    {{ y }}yr
                                </button>
                            </div>
                        </div>

                        <!-- Passphrase input — shown when mode needs a passphrase, or when no-clues (generic) -->
                        <div v-if="showClues ? authMode !== 'key_file' : true">
                            <label class="block text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">Passphrase</label>
                            <input
                                v-model="passphrase"
                                type="password"
                                placeholder="Your locker passphrase"
                                @keydown.enter="canRenew && !loading && submit()"
                                class="w-full bg-gray-900 border border-gray-700 text-white font-mono rounded-lg px-3 py-2.5 text-sm focus:border-gamboge-300 focus:outline-none"
                                data-testid="passphrase-input"
                            />
                            <p class="text-gray-500 text-xs mt-1">Used to compute your renewal authorisation. Never sent to the server.</p>
                        </div>

                        <!-- Key file section — shown when mode needs key files, or when no-clues (generic) -->
                        <div v-if="showClues ? authMode !== 'passphrase' : true" class="space-y-2">
                            <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest">Key Files</div>
                            <p v-if="showClues" class="text-gray-500 text-xs">
                                Load your key files in the same order as when you created this locker.
                                Refer to your saved credential file for the required order.
                            </p>

                            <div v-if="keyFiles.length > 0" class="space-y-1">
                                <div
                                    v-for="(kf, i) in keyFiles"
                                    :key="i"
                                    class="flex items-center gap-3 bg-gray-900 rounded-lg px-3 py-2"
                                >
                                    <span class="text-gamboge-300/60 text-xs w-4 shrink-0">{{ i + 1 }}.</span>
                                    <span class="text-white text-sm flex-1 truncate">{{ kf.file.name }}</span>
                                    <button
                                        type="button"
                                        @click="removeKeyFile(i)"
                                        class="shrink-0 text-gray-500 hover:text-red-400 font-mono text-xs transition-colors"
                                        title="Remove"
                                    >✕</button>
                                </div>
                            </div>

                            <div v-if="showClues && keyFileCount" class="text-xs text-gray-500 font-mono">
                                {{ keyFiles.length }} / {{ keyFileCount }} loaded
                            </div>

                            <label
                                v-if="showClues ? keyFiles.length < (keyFileCount ?? 999) : true"
                                class="flex items-center gap-2 cursor-pointer border border-dashed border-gray-600 hover:border-gamboge-300/50 hover:shadow-neon-cyan-sm rounded-lg px-3 py-2 transition-colors text-gray-400 text-sm"
                                data-testid="key-file-input-label"
                            >
                                <span class="font-mono text-xs">+ Add key file</span>
                                <input type="file" class="sr-only" @change="onKeyFileAdded" data-testid="key-file-input" />
                            </label>

                            <p v-if="showClues && authMode === 'combined'" class="text-gamboge-300/70 text-xs">
                                Both your passphrase and all {{ keyFileCount }} key file(s) are required to renew.
                            </p>
                        </div>

                        <p v-if="error" class="text-red-400 text-sm">{{ error }}</p>

                        <button
                            @click="submit"
                            :disabled="!canRenew || loading"
                            class="w-full bg-gamboge-300 hover:bg-gamboge-400 disabled:opacity-60 text-gray-900 font-semibold py-2.5 rounded-lg font-mono text-sm transition-colors shadow-neon-cyan-sm hover:shadow-neon-cyan"
                            data-testid="renew-submit-button"
                        >
                            {{ loading ? 'Verifying…' : `Renew for ${years} ${years === 1 ? 'year' : 'years'} →` }}
                        </button>

                        <p class="text-gray-500 text-xs text-center">
                            You'll be redirected to Stripe to complete payment. No subscription is created.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
