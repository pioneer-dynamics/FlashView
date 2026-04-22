<script setup>
    import { Link, useForm, usePage, router } from '@inertiajs/vue3';
    import { encryption } from '../../encryption';
    import { computed, ref, watch } from 'vue';
    import Checkbox from '@/Components/Checkbox.vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import SelectInput from '@/Components/SelectInput.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import FileUploadZone from '@/Components/FileUploadZone.vue';
    import InputLabel from '@/Components/InputLabel.vue';

    const stage = ref('generating');

    const other = useForm({ password: null });

    // Clears stale password validation error when the field is cleared —
    // an empty password triggers auto-generation which bypasses the min-length check.
    watch(() => other.password, (value) => {
        if (!value && other.errors.password) {
            other.clearErrors('password');
        }
    });

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
        message: '',
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

    const isEncryptBusy = computed(() => !!uploadState.value || form.processing);

    // Kept as a named computed (not inlined) so PIO75Test can assert its presence.
    const passwordInputDisabled = computed(() => isEncryptBusy.value);

    const getErrorMessage = (error) => {
        switch (error.code) {
            case 429: return "That's too many messages in a short time. Please wait try again in a minute.";
            default: return error.message;
        }
    };

    const encryptFileData = async () => {
        if (!selectedFile.value) { return; }

        const e = new encryption();
        const passphrase = other.password || null;

        try {
            e.validatePassphrase(passphrase);
        } catch (err) {
            other.setError('password', err.message);
            return;
        }

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
</script>

<template>
    <FlatFormSection>
        <template #title>
            FlashView
        </template>

        <template #form>
            <div class="col-span-12" v-if="stage == 'generated'">
                <Alert hide-title type="Success">
                    <span v-if="$page.props.jetstream.flash?.secret?.is_file">
                        Please share the link and password separately to the recipient. The file can be downloaded only once and only with both the link and the password.
                    </span>
                    <span v-else>
                        Please share the link and password separately to the recipient. The message can be retrieved only once and only with both the link and the password. If you wish to prematurely delete the message, you may visit the link and enter any random password and click retrieve.
                    </span>
                </Alert>
            </div>

            <div class="col-span-12" v-if="stage == 'generated' && $page.props.auth.senderIdentity && form.include_sender_identity">
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
                <span v-if="stage == 'generated'">
                    <InputLabel value="Retrieval Link"/>
                    <CodeBlock :value="$page.props.jetstream.flash?.secret?.url" class="break-words mt-1"/>
                </span>
                <span v-else>
                    <TextAreaInput autofocus id="message" rows="7" v-model="form.message" type="text" class="font-mono mt-1 block w-full" placeholder="Your secret message..." :max-length="maxLength" :disabled="isEncryptBusy"/>
                    <div class="flex flex-wrap mt-2 relative text-sm gap-2">
                        <div class="flex flex-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 text-gamboge-300">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-1">End-to-end encrypted</div>
                        </div>
                        <span class="flex flex-wrap gap-1">
                            <div v-if="!$page.props.auth.user || !$page.props.auth.user.subscription">|</div>
                            <div v-if="!$page.props.auth.user">
                                Is {{ maxLength }} characters too short, or need a longer expiry? - <Link class="underline text-gamboge-300" :href="route('login')">login</Link> or <Link class="underline text-gamboge-300" :href="route('register')">create a free account!</Link> to increase the limit.
                            </div>
                            <div v-else-if="!$page.props.auth.user.subscription" class="flex flex-wrap gap-1">
                                Is {{ maxLength }} characters still too short, or need a longer expiry? - <a as="a" class="underline text-gamboge-300" :href="route('plans.index')">subscribe to a paid plan</a> to increase the limits.
                            </div>
                        </span>
                    </div>
                    <div class="flex flex-wrap mt-2 gap-1">
                        <InputError :message="form.errors.message" />
                        <div v-if="form.errors.message?.length && $page.props.jetstream.flash.error?.code == 429" class="text-sm text-red-600 dark:text-red-400">
                            Or <Link class="underline text-gamboge-300" :href="route('login')">login</Link> or <Link class="underline text-gamboge-300" :href="route('register')">create a free account!</Link> to send more.
                        </div>
                    </div>
                </span>
            </div>

            <div class="col-span-12" v-if="stage == 'generating' && !$page.props.jetstream.flash?.secret?.url">
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
                    <p class="text-sm text-gray-600 dark:text-gray-300">
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
                    <div class="w-full">
                        <span v-if="stage == 'generated' && !isEncryptBusy">
                            <InputLabel value="Password"/>
                            <CodeBlock :value="other.password" class="mt-1"/>
                        </span>
                        <span v-else>
                            <TextInput id="password" ref="passwordInput" :model-value="isEncryptBusy ? '' : other.password" @update:model-value="other.password = $event" type="text" class="font-mono mt-1 block w-full dark:shadow-neon-cyan-sm" placeholder="Enter a password, or leave blank to auto generate a password for you." :disabled="passwordInputDisabled" />
                            <InputError :message="other.errors.password" class="mt-2" />
                        </span>
                    </div>
                    <div v-if="!$page.props.jetstream.flash?.secret?.url">
                        <SelectInput id="expires_in" v-model="form.expires_in" class="mt-1 sm:w-full dark:shadow-neon-cyan-sm" :options="expiryOptions" :disabled="isEncryptBusy" />
                        <InputError :message="other.errors.expires_in" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="col-span-12" v-if="$page.props.auth.user">
                <span v-if="stage == 'generated'">
                    <span v-if="form.email">
                        <InputLabel value="Recipient's Email"/>
                        <CodeBlock :value="form.email" class="mt-1"/>
                    </span>
                </span>
                <span v-else-if="!$page.props.jetstream.flash?.secret?.url">
                    <TextInput v-model="form.email" placeholder="Recipient's email address (optional)" class="mt-1 block w-full dark:shadow-neon-cyan-sm" type="email" :disabled="isEncryptBusy"/>
                    <InputError :message="form.errors.email" class="mt-2" />
                </span>
            </div>

            <div v-if="!$page.props.jetstream.flash?.secret?.url && $page.props.auth.senderIdentity" class="col-span-12">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <Checkbox v-model:checked="form.include_sender_identity" :disabled="isEncryptBusy"/>
                    Include my verified sender identity
                    <span class="text-gray-500 dark:text-gray-400">
                        ({{ $page.props.auth.senderIdentity.company_name ?? $page.props.auth.senderIdentity.email }})
                    </span>
                </label>
            </div>
        </template>

        <template #actions>
            <div class="flex items-center gap-4">
                <PrimaryButton @click.prevent="letsDoAnotherOne" v-if="$page.props.jetstream.flash?.secret?.url" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Let's do another one
                </PrimaryButton>
                <PrimaryButton @click.prevent="encryptData" v-else :class="{ 'opacity-25': isEncryptBusy }" :disabled="isEncryptBusy">
                    Generate link
                </PrimaryButton>
            </div>
        </template>
    </FlatFormSection>
</template>
