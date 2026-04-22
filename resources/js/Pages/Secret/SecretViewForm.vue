<script setup>
    import { useForm, usePage } from '@inertiajs/vue3';
    import { encryption } from '../../encryption';
    import { computed, ref } from 'vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import InputError from '@/Components/InputError.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import DestroyedSecretState from '@/Components/DestroyedSecretState.vue';
    import FileDecryptPanel from '@/Components/FileDecryptPanel.vue';

    const props = defineProps({
        secret: { type: String, required: true },
        decryptUrl: { type: String, required: true },
        senderCompanyName: { type: String, default: null },
        senderDomain: { type: String, default: null },
        senderEmail: { type: String, default: null },
        isFileSecret: { type: Boolean, default: false },
        hasMessage: { type: Boolean, default: false },
        fileSize: { type: Number, default: null },
        fileMimeType: { type: String, default: null },
        fileDownloadUrl: { type: String, default: null },
    });

    const fileDecryptPanelRef = ref(null);

    const other = useForm({ password: null });
    const decryptForm = useForm({});

    const decryptedMessage = ref('');
    const decryptionSuccess = ref(false);
    const decryptionFailed = ref(false);
    const decryptionFailureReason = ref('wrong-password');

    const fileDecryptState = ref(null);
    const isDecryptBusy = computed(() => decryptForm.processing || !!fileDecryptState.value);

    const messageClass = computed(() =>
        !decryptionSuccess.value ? 'mt-1 block w-full blur-sm' : 'mt-1 block w-full'
    );

    const handleDecryptionFailure = (reason = 'wrong-password') => {
        decryptionSuccess.value = false;
        decryptedMessage.value = '';
        fileDecryptState.value = null;
        decryptionFailureReason.value = reason;
        decryptionFailed.value = true;
    };

    const decryptData = () => {
        decryptionFailed.value = false;

        if (props.isFileSecret && !props.hasMessage) {
            fileDecryptPanelRef.value.triggerDecrypt();
            return;
        }

        if (props.isFileSecret && props.hasMessage) {
            const e = new encryption();
            decryptForm.get(props.decryptUrl, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    const flash = usePage().props.jetstream.flash?.secret;
                    if (!flash) { handleDecryptionFailure('unavailable'); return; }

                    if (flash.message) {
                        e.decryptMessage(flash.message, other.password)
                            .then((data) => {
                                if (decryptionFailed.value) { return; }
                                decryptedMessage.value = data;
                                decryptionSuccess.value = true;
                            })
                            .catch(() => handleDecryptionFailure('wrong-password'));
                    }

                    if (flash.file_download_url) {
                        fileDecryptPanelRef.value.startDownload(flash);
                    }
                },
                onError: () => handleDecryptionFailure('unavailable'),
            });
            return;
        }

        const e = new encryption();
        decryptForm.get(props.decryptUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                const flash = usePage().props.jetstream.flash;
                if (flash?.error?.code === 404 || !flash?.secret?.message) {
                    handleDecryptionFailure('unavailable');
                    return;
                }
                e.decryptMessage(flash.secret.message, other.password)
                    .then((data) => {
                        decryptedMessage.value = data;
                        decryptionSuccess.value = true;
                    })
                    .catch(() => handleDecryptionFailure('wrong-password'));
            },
            onError: () => handleDecryptionFailure('unavailable'),
        });
    };
</script>

<template>
    <FlatFormSection>
        <template #title>
            FlashView
        </template>

        <template #form>
            <template v-if="decryptionFailed">
                <div class="col-span-12">
                    <DestroyedSecretState :reason="decryptionFailureReason" />
                </div>
            </template>
            <template v-else>
                <div class="col-span-12" v-if="senderCompanyName || senderEmail">
                    <Alert type="Success" hide-title>
                        <div class="flex items-start gap-2">
                            <div>
                                <p class="font-semibold">&#10003; Verified Sender</p>
                                <p v-if="senderCompanyName" class="mt-1">
                                    This secret was sent by <strong>{{ senderCompanyName }}</strong> (verified domain: {{ senderDomain }})
                                </p>
                                <p v-else-if="senderEmail" class="mt-1">
                                    This secret was sent by <strong>{{ senderEmail }}</strong>
                                </p>
                            </div>
                        </div>
                    </Alert>
                </div>

                <div class="col-span-12">
                    <Alert type="Warning" hide-title>
                        <div class="space-y-2">
                            <p class="font-semibold" v-if="isFileSecret && hasMessage">
                                This message and file will be permanently deleted after one retrieval attempt &mdash; even if you enter the wrong password.
                            </p>
                            <p class="font-semibold" v-else-if="isFileSecret">
                                This file will be permanently deleted after one download attempt &mdash; even if you enter the wrong password.
                            </p>
                            <p class="font-semibold" v-else>
                                This message will self-destruct after one retrieval attempt &mdash; even if you enter the wrong password.
                            </p>
                            <p>
                                Please double-check your password before submitting &mdash; this cannot be undone.
                            </p>
                            <p class="text-xs opacity-75">
                                Unretrieved {{ isFileSecret ? 'files' : 'messages' }} are also deleted after expiration.
                            </p>
                        </div>
                    </Alert>
                </div>

                <div class="col-span-12">
                    <div v-if="isFileSecret">
                        <FileDecryptPanel
                            ref="fileDecryptPanelRef"
                            :decrypt-url="decryptUrl"
                            :password="other.password"
                            :file-mime-type="fileMimeType"
                            :file-size="fileSize"
                            @success="decryptionSuccess = true"
                            @state-change="(next) => fileDecryptState = next"
                            @failure="handleDecryptionFailure"
                        />
                        <div v-if="hasMessage && decryptionSuccess && decryptedMessage?.length > 0" class="mt-3">
                            <p class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Note from sender</p>
                            <CodeBlock :value="decryptedMessage" class="mt-1" />
                        </div>
                    </div>
                    <div v-else>
                        <CodeBlock v-if="decryptionSuccess && decryptedMessage?.length > 0" :value="decryptedMessage" class="mt-1" />
                    </div>
                    <div class="flex flex-wrap mt-2 relative text-sm gap-2">
                        <div class="flex flex-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 text-gamboge-300">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-1">End-to-end encrypted</div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12">
                    <div class="flex flex-wrap sm:flex-nowrap gap-2 sm:space-y-0">
                        <div class="w-full" v-if="!$page.props.jetstream.flash?.secret?.message && !decryptionSuccess">
                            <TextInput id="password" autofocus ref="passwordInput" :model-value="other.password" @update:model-value="other.password = $event" type="text" class="font-mono mt-1 block w-full dark:shadow-neon-cyan-sm" placeholder="Enter your password to decrypt the message." :disabled="isDecryptBusy" />
                            <InputError :message="other.errors.password" class="mt-2" />
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <template #actions>
            <span v-if="!decryptionFailed">
                <PrimaryButton
                    @click.prevent="decryptData"
                    v-if="!$page.props.jetstream.flash?.secret?.message && !decryptionSuccess"
                    :class="{ 'opacity-25': isDecryptBusy || (other.password?.length == 0 || other.password == null) }"
                    :disabled="isDecryptBusy || (other.password?.length == 0 || other.password == null)">
                    {{ isFileSecret ? 'Download and decrypt' : 'Retrieve Message' }}
                </PrimaryButton>
                <PrimaryButton v-else :href="isDecryptBusy ? null : route('welcome')" :class="{ 'opacity-25': isDecryptBusy }" :disabled="isDecryptBusy">
                    Send a new secret link
                </PrimaryButton>
            </span>
        </template>
    </FlatFormSection>
</template>
