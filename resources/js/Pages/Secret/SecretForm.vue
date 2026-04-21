<script setup>
    import { Link, useForm, usePage } from '@inertiajs/vue3';
    import { encryption } from '../../encryption';
    import { computed, reactive, ref } from 'vue';
    import Checkbox from '@/Components/Checkbox.vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import SelectInput from '@/Components/SelectInput.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import DestroyedSecretState from '@/Components/DestroyedSecretState.vue';
    import FileDecryptPanel from '@/Components/FileDecryptPanel.vue';
    import FileUploadZone from '@/Components/FileUploadZone.vue';
    import { router } from '@inertiajs/vue3';
    import InputLabel from '@/Components/InputLabel.vue';

    const stage = ref('generating');

    const props = defineProps({
        secret: { type: String, default: null },
        decryptUrl: { type: String, default: null },
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
    const passwordInput = ref(null);

    const decryptForm = useForm({});
    const other = useForm({ password: null });
    const decryptionSuccess = ref(false);
    const decryptionFailed = ref(false);
    const decryptionFailureReason = ref('wrong-password');

    const placeholderMessage = 'This isn\'t the actual message—it\'s just a placeholder. To view the message, please click the button below.';

    const handleDecryptionFailure = (reason = 'wrong-password') => {
        decryptionSuccess.value = false;
        form.message = props.secret ? placeholderMessage : '';
        decryptionFailureReason.value = reason;
        decryptionFailed.value = true;
    };

    const combinedFlow = reactive({
        anyFailed: false,
        messageResolved: false,
        fileResolved: false,
        decryptedMessage: null,
    });

    const combinedMaybeReveal = () => {
        if (combinedFlow.anyFailed) { return; }
        if (!combinedFlow.messageResolved || !combinedFlow.fileResolved) { return; }
        form.message = combinedFlow.decryptedMessage;
        decryptionSuccess.value = true;
    };

    const onCombinedPanelSuccess = () => {
        if (combinedFlow.anyFailed) { return; }
        combinedFlow.fileResolved = true;
        combinedMaybeReveal();
    };

    const onCombinedPanelFailure = (reason = 'wrong-password') => {
        combinedFlow.anyFailed = true;
        handleDecryptionFailure(reason);
    };

    const messageClass = computed(() => {
        if (props.secret) {
            if (!usePage().props.jetstream.flash?.secret?.message || decryptionSuccess.value === false) {
                return 'mt-1 block w-full blur-sm';
            }
            return 'mt-1 block w-full';
        }
        return 'mt-1 block w-full';
    });

    const getErrorMessage = (error) => {
        switch (error.code) {
            case 429: return "That's too many messages in a short time. Please wait try again in a minute.";
            default: return error.message;
        }
    };

    const userType = computed(() => {
        if (usePage().props?.auth?.user?.id) {
            return usePage().props?.auth?.user?.subscription ? 'subscribed' : 'user';
        }
        return 'guest';
    });

    const expiryOptions = computed(() => {
        let max_expiry = 0;
        switch (userType.value) {
            case 'subscribed':
                max_expiry = usePage().props.auth.user.plan.settings.expiry.expiry_minutes;
                break;
            case 'user':
                max_expiry = usePage().props.config.secrets.expiry_limits.user;
                break;
            case 'guest':
                max_expiry = usePage().props.config.secrets.expiry_limits.guest;
                break;
        }
        return usePage().props.config.secrets.expiry_options.filter((option) => option.value <= max_expiry);
    });

    const form = useForm({
        message: props.secret ? placeholderMessage : '',
        email: '',
        expires_in: expiryOptions.value[expiryOptions.value.length - 1].value,
        include_sender_identity: usePage().props.auth.senderIdentity?.include_by_default ?? false,
    });

    const maxLength = computed(() => {
        switch (userType.value) {
            case 'subscribed': return usePage().props.auth.user.plan.settings.messages.message_length;
            case 'user': return usePage().props.config.secrets.message_length.user;
            case 'guest': return usePage().props.config.secrets.message_length.guest;
        }
    });

    const maxFileUploadSizeMb = computed(() => {
        switch (userType.value) {
            case 'subscribed': return usePage().props.auth.user.plan.settings.file_upload?.max_file_size_mb ?? 10;
            case 'user': return usePage().props.config.secrets.file_upload?.max_file_size_mb?.user ?? 10;
            case 'guest': return 0;
        }
    });

    const allowedMimeTypes = computed(() => {
        return usePage().props.config.secrets.file_upload?.allowed_mime_types ?? [];
    });

    const selectedFile = ref(null);
    const fileError = ref(null);
    const uploadState = ref(null);
    const uploadProgress = ref(0);

    const encryptFileData = async () => {
        if (!selectedFile.value) { return; }

        const e = new encryption();
        const passphrase = other.password || null;

        uploadState.value = 'encrypting';

        try {
            const { encryptedBuffer, passphrase: resolvedPassphrase } = await e.encryptFile(selectedFile.value, passphrase);
            const { secret: encryptedFilename } = await e.encryptMessage(selectedFile.value.name, resolvedPassphrase);

            if (!other.password) {
                other.password = resolvedPassphrase;
            }

            // Step 1: Ask server for a presigned S3 PUT URL (or server fallback URL).
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const prepareRes = await fetch(route('secret.file.prepare'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });

            if (!prepareRes.ok) { throw new Error('Could not prepare upload.'); }

            const { upload_type, upload_url, upload_headers, token } = await prepareRes.json();

            uploadState.value = 'uploading';
            uploadProgress.value = 0;

            // Step 2: Upload encrypted bytes directly to S3 (or server fallback) via XHR for progress.
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(upload_type === 's3_direct' ? 'PUT' : 'POST', upload_url);

                if (upload_type === 'server') {
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                }
                for (const [key, value] of Object.entries(upload_headers ?? {})) {
                    xhr.setRequestHeader(key, value);
                }

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        uploadProgress.value = Math.round((event.loaded / event.total) * 100);
                    }
                };
                xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed.'));
                xhr.onerror = () => reject(new Error('Upload failed.'));
                xhr.send(new Blob([encryptedBuffer], { type: 'application/octet-stream' }));
            });

            // Step 3: Create the secret record with the token referencing the uploaded file.
            const formData = new FormData();
            formData.append('file_token', token);
            formData.append('file_original_name', encryptedFilename);
            formData.append('file_size', String(selectedFile.value.size));
            formData.append('file_mime_type', selectedFile.value.type);
            formData.append('expires_in', String(form.expires_in));
            if (form.message) {
                const { secret: encryptedMsg } = await e.encryptMessage(form.message, resolvedPassphrase);
                formData.append('message', encryptedMsg);
            }
            if (form.email) { formData.append('email', form.email); }
            if (form.include_sender_identity) { formData.append('include_sender_identity', '1'); }

            router.post(route('secret.store'), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    uploadState.value = null;
                    uploadProgress.value = 0;
                    if (usePage().props.jetstream.flash?.error) {
                        fileError.value = getErrorMessage(usePage().props.jetstream.flash.error);
                        return;
                    }
                    stage.value = 'generated';
                },
                onError: () => {
                    uploadState.value = null;
                    uploadProgress.value = 0;
                },
            });
        } catch (err) {
            uploadState.value = null;
            fileError.value = err.message || 'Encryption failed.';
        }
    };

    const encryptData = () => {
        if (selectedFile.value) {
            encryptFileData();
            return;
        }

        const e = new encryption();

        e.encryptMessage(form.message, other.password)
            .then((data) => {
                form.transform((formdata) => ({ ...formdata, message: data.secret }))
                    .post(route('secret.store'), {
                        preserveScroll: true,
                        onSuccess: () => {
                            if (usePage().props.jetstream.flash?.error) {
                                form.setError('message', getErrorMessage(usePage().props.jetstream.flash.error));
                                return;
                            }
                            if (data.passphrase != other.password) {
                                other.password = data.passphrase;
                            }
                            stage.value = 'generated';
                        },
                    });
            })
            .catch((e) => {
                other.setError('password', e.message);
            });
    };

    const passwordPlaceholder = computed(() => {
        if (props.secret == null) {
            return 'Enter a passsword, or leave blank to auto generate a password for you.';
        }
        return 'Enter your password to decrypt the message.';
    });

    const letsDoAnotherOne = () => {
        form.email = '';
        form.message = '';
        form.expires_in = expiryOptions.value[expiryOptions.value.length - 1].value;
        other.password = null;
        selectedFile.value = null;
        fileError.value = null;
        uploadState.value = null;
        stage.value = 'generating';
        router.reload();
    };

    const decryptData = () => {
        decryptionFailed.value = false;

        if (props.isFileSecret && !props.hasMessage) {
            fileDecryptPanelRef.value.triggerDecrypt();
            return;
        }

        if (props.isFileSecret && props.hasMessage) {
            combinedFlow.anyFailed = false;
            combinedFlow.messageResolved = false;
            combinedFlow.fileResolved = false;
            combinedFlow.decryptedMessage = null;

            const e = new encryption();
            decryptForm.get(props.decryptUrl, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    const flash = usePage().props.jetstream.flash?.secret;
                    if (!flash) { onCombinedPanelFailure('unavailable'); return; }

                    if (flash.message) {
                        e.decryptMessage(flash.message, other.password)
                            .then((data) => {
                                if (combinedFlow.anyFailed) { return; }
                                combinedFlow.decryptedMessage = data;
                                combinedFlow.messageResolved = true;
                                combinedMaybeReveal();
                            })
                            .catch(() => onCombinedPanelFailure('wrong-password'));
                    } else {
                        combinedFlow.messageResolved = true;
                    }

                    if (flash.file_download_url) {
                        fileDecryptPanelRef.value.startDownload(flash);
                    } else {
                        combinedFlow.fileResolved = true;
                        combinedMaybeReveal();
                    }
                },
                onError: () => onCombinedPanelFailure('unavailable'),
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
                        form.message = data;
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
            <template v-if="props.secret != null && decryptionFailed">
                <div class="col-span-12">
                    <DestroyedSecretState :reason="decryptionFailureReason" />
                </div>
            </template>
            <template v-else>
            <div class="col-span-12" v-if="props.secret != null && (senderCompanyName || senderEmail)">
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
                <Alert v-if="props.secret != null" type="Warning" hide-title>
                    <div class="space-y-2">
                        <p class="font-semibold" v-if="props.isFileSecret && props.hasMessage">
                            This message and file will be permanently deleted after one retrieval attempt &mdash; even if you enter the wrong password.
                        </p>
                        <p class="font-semibold" v-else-if="props.isFileSecret">
                            This file will be permanently deleted after one download attempt &mdash; even if you enter the wrong password.
                        </p>
                        <p class="font-semibold" v-else>
                            This message will self-destruct after one retrieval attempt &mdash; even if you enter the wrong password.
                        </p>
                        <p>
                            Please double-check your password before submitting &mdash; this cannot be undone.
                        </p>
                        <p class="text-xs opacity-75">
                            Unretrieved {{ props.isFileSecret ? 'files' : 'messages' }} are also deleted after expiration.
                        </p>
                    </div>
                </Alert>
            </div>

            <div class="col-span-12" v-if="stage=='generated'">
                <Alert hide-title type="Success">
                    <span v-if="$page.props.jetstream.flash?.secret?.is_file">
                        Please share the link and password separately to the recipient. The file can be downloaded only once and only with both the link and the password.
                    </span>
                    <span v-else>
                        Please share the link and password separately to the recipient. The message can be retrieved only once and only with both the link and the password. If you wish to prematurely delete the message, you may visit the link and enter any random password and click retrieve.
                    </span>
                </Alert>
            </div>

            <div class="col-span-12" v-if="props.secret == null && stage == 'generated' && $page.props.auth.senderIdentity && form.include_sender_identity">
                <Alert hide-title type="Info">
                    <span v-if="$page.props.auth.senderIdentity.type === 'domain'">
                        Your Verified Sender badge (<strong>{{ $page.props.auth.senderIdentity.company_name }}</strong>) is included in this link.
                    </span>
                    <span v-else>
                        Your Verified Sender badge (<strong>{{ $page.props.auth.senderIdentity.email }}</strong>) is included in this link.
                    </span>
                </Alert>
            </div>

            <div class="col-span-12">
                <span v-if="stage=='generated'">
                    <InputLabel value="Retrieval Link"/>
                    <CodeBlock :value="$page.props.jetstream.flash?.secret?.url" class="break-words mt-1"/>
                </span>
                <span v-else>
                    <div v-if="props.isFileSecret && props.secret != null">
                        <FileDecryptPanel
                            ref="fileDecryptPanelRef"
                            :decrypt-url="props.decryptUrl"
                            :password="other.password"
                            :file-mime-type="props.fileMimeType"
                            :file-size="props.fileSize"
                            @success="props.hasMessage ? onCombinedPanelSuccess() : (decryptionSuccess = true)"
                            @failure="props.hasMessage ? onCombinedPanelFailure($event) : handleDecryptionFailure($event)"
                        />
                        <div v-if="props.hasMessage && decryptionSuccess" class="mt-3">
                            <p class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Note from sender</p>
                            <CodeBlock :value="form.message" class="mt-1" />
                        </div>
                    </div>
                    <div v-else>
                        <CodeBlock v-if="decryptionSuccess && props.secret != null" :value="form.message" class="mt-1" />
                        <TextAreaInput v-else :autofocus="props.secret == null" id="message" rows="7" v-model="form.message" type="text" class="font-mono" :class="messageClass" placeholder="Your secret message..." :max-length="$page.props.jetstream.flash?.secret?.message ? 0 : maxLength"/>
                    </div>
                    <div class="flex flex-wrap mt-2 relative text-sm gap-2" v-if="!props.isFileSecret || props.secret == null">
                        <div class="flex flex-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 text-gamboge-300">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-1">End-to-end encrypted</div>
                        </div>
                        <span v-if="!props.secret" class="flex flex-wrap gap-1">
                            <div v-if="!$page.props.auth.user || !$page.props.auth.user.subscription">|</div>
                            <div v-if="!$page.props.auth.user">
                                Is {{ maxLength }} characters too short, or need a longer expiry? - <Link class="underline text-gamboge-300" :href="route('login')">login</Link> or <Link class="underline text-gamboge-300" :href="route('register')">create a free account!</Link> to increase the limit.
                            </div>
                            <div v-else-if="!$page.props.auth.user.subscription" class="flex flex-wrap gap-1">
                                Is {{ maxLength }} characters still too short, or need a longer expiry? - <a as="a" class="underline text-gamboge-300" :href="route('plans.index')">subscribe to a paid plan</a> to increase the limits.
                            </div>
                        </span>
                    </div>
                    <div class="flex flex-wrap mt-2 gap-1" v-if="!props.isFileSecret">
                        <InputError :message="form.errors.message" />
                        <div v-if="form.errors.message?.length && $page.props.jetstream.flash.error?.code == 429" class="text-sm text-red-600 dark:text-red-400">
                            Or <Link class="underline text-gamboge-300" :href="route('login')">login</Link> or <Link class="underline text-gamboge-300" :href="route('register')">create a free account!</Link> to send more.
                        </div>
                    </div>
                </span>
            </div>

            <div class="col-span-12" v-if="props.secret == null && stage == 'generating' && !$page.props.jetstream.flash?.secret?.url">
                <FileUploadZone
                    v-if="$page.props.auth.user"
                    v-model="selectedFile"
                    v-model:fileError="fileError"
                    :max-file-upload-size-mb="maxFileUploadSizeMb"
                    :allowed-mime-types="allowedMimeTypes"
                    :upload-state="uploadState"
                    :upload-progress="uploadProgress"
                />
                <div v-else class="-mt-4">
                    <p class="text-sm">
                        Want to attach a file?
                        <Link class="underline text-gamboge-300" :href="route('login')">Log in</Link>
                        or
                        <Link class="underline text-gamboge-300" :href="route('register')">create a free account</Link>
                        to share encrypted files up to 10 MB.
                    </p>
                </div>
            </div>

            <div class="col-span-12">
                <div class="flex flex-wrap sm:flex-nowrap gap-2 sm:space-y-0">
                    <div class="w-full" v-if="$page.props.jetstream.flash?.secret?.message == undefined && !decryptionSuccess">
                        <span v-if="stage=='generated'">
                            <InputLabel value="Password"/>
                            <CodeBlock :value="other.password" class="mt-1"/>
                        </span>
                        <span v-else>
                            <TextInput id="password" :autofocus="props.secret != null" ref="passwordInput" v-model="other.password" type="text" class="font-mono mt-1 block w-full" :placeholder="passwordPlaceholder" />
                            <InputError :message="other.errors.password" class="mt-2" />
                        </span>
                    </div>
                    <div v-if="!$page.props.jetstream.flash?.secret?.url && props.secret == null">
                        <SelectInput id="expires_in" v-model="form.expires_in" class="mt-1 sm:w-full" :options="expiryOptions" />
                        <InputError :message="other.errors.expires_in" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="col-span-12" v-if="$page.props.auth.user">
                <span v-if="stage=='generated'">
                    <span v-if="form.email">
                        <InputLabel value="Recipient's Email"/>
                        <CodeBlock :value="form.email" class="mt-1"/>
                    </span>
                </span>
                <span v-else-if="!$page.props.jetstream.flash?.secret?.url && props.secret == null">
                    <TextInput v-model="form.email" placeholder="Recipient's email adddress (optional)" class="mt-1 block w-full" type="email"/>
                    <InputError :message="form.errors.email" class="mt-2" />
                </span>
            </div>

            <div v-if="!$page.props.jetstream.flash?.secret?.url && props.secret == null && $page.props.auth.senderIdentity" class="col-span-12">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <Checkbox v-model:checked="form.include_sender_identity"/>
                    Include my verified sender identity
                    <span class="text-gray-500 dark:text-gray-400">
                        ({{ $page.props.auth.senderIdentity.company_name ?? $page.props.auth.senderIdentity.email }})
                    </span>
                </label>
            </div>
            </template>
        </template>

        <template #actions>
            <span v-if="props.secret == null">
                <div class="flex items-center gap-4">
                    <PrimaryButton @click.prevent="letsDoAnotherOne" v-if="$page.props.jetstream.flash?.secret?.url" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Let's do another one
                    </PrimaryButton>
                    <PrimaryButton @click.prevent="encryptData" v-else :class="{ 'opacity-25': form.processing || !!uploadState }" :disabled="form.processing || !!uploadState">
                        Generate link
                    </PrimaryButton>
                </div>
            </span>
            <span v-else-if="!decryptionFailed">
                <PrimaryButton
                    @click.prevent="decryptData"
                    v-if="!$page.props.jetstream.flash?.secret?.message && !decryptionSuccess"
                    :class="{ 'opacity-25': decryptForm.processing || (other.password?.length == 0 || other.password == null) }"
                    :disabled="decryptForm.processing || (other.password?.length == 0 || other.password == null)">
                    {{ props.isFileSecret ? 'Unlock & Download' : 'Retrieve Message' }}
                </PrimaryButton>
                <PrimaryButton v-else :href="route('welcome')">
                    Send a new secret link
                </PrimaryButton>
            </span>
        </template>
    </FlatFormSection>
</template>
