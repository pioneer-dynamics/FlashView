<script setup>
    import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
    import {encryption} from '../../encryption';
    import { computed, ref } from 'vue';
    import Checkbox from '@/Components/Checkbox.vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import SelectInput from '@/Components/SelectInput.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import FileProgressBar from '@/Components/FileProgressBar.vue';
    import { router } from '@inertiajs/vue3'
    import InputLabel from '@/Components/InputLabel.vue';

    const stage = ref('generating');

    const props = defineProps({
        secret: {
            type: String,
            default: null,
        },
        decryptUrl: {
            type: String,
            default: null,
        },
        senderCompanyName: {
            type: String,
            default: null,
        },
        senderDomain: {
            type: String,
            default: null,
        },
        senderEmail: {
            type: String,
            default: null,
        },
        isFileSecret: {
            type: Boolean,
            default: false,
        },
        fileSize: {
            type: Number,
            default: null,
        },
        fileMimeType: {
            type: String,
            default: null,
        },
        fileDownloadUrl: {
            type: String,
            default: null,
        },
    })

    const passwordInput = ref(null);

    const decryptForm = useForm({})

    const other = useForm({
        password: null,
    });

    const decryptionSuccess = ref(false)

    const messageClass = computed(() => {
        if(props.secret) {
            if(!usePage().props.jetstream.flash?.secret?.message || decryptionSuccess.value === false) {
                return 'mt-1 block w-full blur-sm';
            }
            else {
                return 'mt-1 block w-full';
            }
        }
        else {
            return 'mt-1 block w-full';
        }
    })

    const getErrorMessage = (error) => {
        switch(error.code) {
            case 429: return "That's too many messages in a short time. Please wait try again in a minute.";
            default: return error.message;
        }
    }

    const numberWithCommas = (number) => {
        return number.toString().replace(/\B(?<!\.\d*)(?=(\d{3})+(?!\d))/g, ",");
    }

    const userType = computed(() => {
        if(usePage().props?.auth?.user?.id) {
            return usePage().props?.auth?.user?.subscription ? 'subscribed' : 'user';
        }
        return 'guest';
    })

    const expiryOptions = computed(() => {
        let max_expiry = 0;
        switch(userType.value) {
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
    })

    const form = useForm({
        message: props.secret ? 'This isn't the actual message—it's just a placeholder. To view the message, please click the button below.' : '',
        email: '',
        expires_in: expiryOptions.value[expiryOptions.value.length-1].value,
        include_sender_identity: usePage().props.auth.senderIdentity?.include_by_default ?? false,
    });

    const maxLength = computed(() => {
        switch(userType.value) {
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
    const uploadState = ref(null); // null | 'encrypting' | 'uploading'

    const humanFileSize = (bytes) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    const onFileSelected = (event) => {
        const file = event.target.files[0];
        if (!file) return;

        fileError.value = null;

        const maxBytes = maxFileUploadSizeMb.value * 1024 * 1024;
        if (file.size > maxBytes) {
            fileError.value = `File exceeds the maximum allowed size of ${maxFileUploadSizeMb.value} MB.`;
            event.target.value = '';
            return;
        }

        if (allowedMimeTypes.value.length > 0 && !allowedMimeTypes.value.includes(file.type)) {
            fileError.value = 'This file type is not supported.';
            event.target.value = '';
            return;
        }

        selectedFile.value = file;
    };

    const clearFile = () => {
        selectedFile.value = null;
        fileError.value = null;
    };

    const encryptFileData = async () => {
        if (!selectedFile.value) return;

        const e = new encryption();
        const passphrase = other.password || null;

        uploadState.value = 'encrypting';

        try {
            const { encryptedBuffer, passphrase: resolvedPassphrase } = await e.encryptFile(selectedFile.value, passphrase);
            const { secret: encryptedFilename } = await e.encryptMessage(selectedFile.value.name, resolvedPassphrase);

            if (!other.password) {
                other.password = resolvedPassphrase;
            }

            uploadState.value = 'uploading';

            const formData = new FormData();
            formData.append('file', new Blob([encryptedBuffer], { type: 'application/octet-stream' }), 'encrypted.bin');
            formData.append('file_original_name', encryptedFilename);
            formData.append('file_size', String(selectedFile.value.size));
            formData.append('file_mime_type', selectedFile.value.type);
            formData.append('expires_in', String(form.expires_in));
            if (form.email) formData.append('email', form.email);
            if (form.include_sender_identity) formData.append('include_sender_identity', '1');

            router.post(route('secret.store'), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    uploadState.value = null;
                    if (usePage().props.jetstream.flash?.error) {
                        fileError.value = getErrorMessage(usePage().props.jetstream.flash.error);
                        return;
                    }
                    stage.value = 'generated';
                },
                onError: () => {
                    uploadState.value = null;
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

        e.encryptMessage(form.message, other.password).then((data) => {
            form.transform((formdata) => ({
                ...formdata,
                message: data.secret
            }))
                .post(route('secret.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        if(usePage().props.jetstream.flash?.error)
                        {
                            form.setError('message', getErrorMessage(usePage().props.jetstream.flash.error));
                            return;
                        }
                        if(data.passphrase != other.password)
                        {
                            other.password = data.passphrase;
                        }

                        stage.value = 'generated';
                    },
                });

        }).
        catch((e) => {
            other.setError('password', e.message);
        });
    }

    const passwordPlaceholder = computed(() => {
        if(props.secret == null)
            return 'Enter a passsword, or leave blank to auto generate a password for you.';
        else
            return 'Enter your password to decrypt the message.'
    })

    const showPrivacyOptions = ref(false)

    const letsDoAnotherOne = () => {
        form.email = '';
        form.message = '';
        form.expires_in = expiryOptions.value[expiryOptions.value.length-1].value ;
        other.password = null;
        selectedFile.value = null;
        fileError.value = null;
        uploadState.value = null;
        stage.value = 'generating';
        router.reload();
    }

    const decryptData = () => {
        if (props.isFileSecret) {
            decryptFileData();
            return;
        }

        const e = new encryption();
        decryptForm.get(props.decryptUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if(!usePage().props.jetstream.flash?.error && usePage().props.jetstream.flash?.error?.code != 404)
                {
                    const secretMessage = usePage().props.jetstream.flash.secret.message;

                    const passphrase = other.password;

                    e.decryptMessage(secretMessage, passphrase)
                        .then((data) => {
                            form.message = data;
                            decryptionSuccess.value = true;
                        })
                        .catch((error) => {
                            form.setError('message', error);
                        });
                }
            },
            onError: () => {
                form.setError('message', 'Could not get your message. Either the password was wrong, or the message is already expired, or the message was already retrieved. You have no more attempts.');
            }
        })
    }

    const fileDecryptError = ref(null);
    const fileDecryptState = ref(null); // null | 'downloading' | 'decrypting'

    const decryptFileData = async () => {
        fileDecryptError.value = null;
        fileDecryptState.value = 'downloading';

        let flashData;
        await new Promise((resolve, reject) => {
            decryptForm.get(props.decryptUrl, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => resolve(),
                onError: () => reject(new Error('retrieve_failed')),
            });
        }).catch(() => {
            fileDecryptState.value = null;
            fileDecryptError.value = 'Could not retrieve the file. It may have already been downloaded or has expired.';
            return;
        });

        const flash = usePage().props.jetstream.flash?.secret;
        if (!flash?.file_download_url) {
            fileDecryptState.value = null;
            fileDecryptError.value = 'Could not retrieve the file. It may have already been downloaded or has expired.';
            return;
        }

        flashData = flash;

        try {
            const response = await fetch(flashData.file_download_url);
            if (!response.ok) {
                throw new Error('download_failed');
            }

            fileDecryptState.value = 'decrypting';

            const arrayBuffer = await response.arrayBuffer();
            const encryptedBytes = new Uint8Array(arrayBuffer);

            const e = new encryption();
            const decryptedBytes = await e.decryptFile(encryptedBytes, other.password);
            const originalFilename = await e.decryptMessage(flashData.file_original_name, other.password);

            const blob = new Blob([decryptedBytes]);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = originalFilename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            decryptionSuccess.value = true;
            fileDecryptState.value = null;
        } catch {
            fileDecryptState.value = null;
            fileDecryptError.value = 'The password is incorrect — the file has been permanently deleted. Please ask the sender to share it again.';
        }
    };
</script>
<template>
    <FlatFormSection>
        <template #title>
            FlashView
        </template>

        <template #form>
            <!-- Verified Sender badge (on reveal page when secret has sender identity) -->
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
                        <p class="font-semibold" v-if="props.isFileSecret">
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
                <Alert hide-title v-if="$page.props.jetstream.flash.error?.code == 404" type="Error">
                    This message has expired or has already been retrieved.
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

            <!-- File decrypt error -->
            <div class="col-span-12" v-if="fileDecryptError">
                <Alert hide-title type="Error">{{ fileDecryptError }}</Alert>
            </div>

            <div class="col-span-12">
                <span v-if="stage=='generated'">
                    <InputLabel value="Retrieval Link"/>
                    <CodeBlock v-if="stage=='generated'" :value="$page.props.jetstream.flash?.secret?.url" class="break-words mt-1"/>
                </span>
                <span v-else>
                    <!-- File secret recipient: show file info card instead of textarea -->
                    <div v-if="props.isFileSecret && props.secret != null" class="mt-1 p-4 rounded-md bg-gray-50 dark:bg-gray-800 border border-gamboge-300/30 dark:border-gamboge-300/20 space-y-2">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8 text-gamboge-300 shrink-0">
                                <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625ZM7.5 15a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 7.5 15Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H8.25Z" clip-rule="evenodd" />
                                <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                            </svg>
                            <div>
                                <p class="font-mono text-xs tracking-widest text-gamboge-300 uppercase">Encrypted File</p>
                                <p v-if="props.fileMimeType" class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">{{ props.fileMimeType }}</p>
                                <p v-if="props.fileSize" class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ humanFileSize(props.fileSize) }}</p>
                            </div>
                        </div>
                        <FileProgressBar v-if="fileDecryptState" :state="fileDecryptState" />
                    </div>
                    <div v-else>
                        <CodeBlock v-if="decryptionSuccess && props.secret != null" :value="form.message" class="mt-1" />
                        <TextAreaInput v-else :autofocus="props.secret == null" id="message" rows="7" v-model="form.message" type="text" class="font-mono" :class="messageClass" placeholder="Your secret message..." :max-length="$page.props.jetstream.flash?.secret?.message ? 0 : maxLength"/>
                    </div>
                    <div class="flex flex-wrap mt-2 relative text-sm gap-2" v-if="!props.isFileSecret || props.secret == null">
                        <div class="flex flex-wrap">
                            <svg xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-1">End-to-end encrypted</div>
                        </div>
                        <span v-if="!props.secret" class="flex flex-wrap gap-1">
                            <div v-if="!$page.props.auth.user || !$page.props.auth.user.subscription">|</div>
                            <div v-if="!$page.props.auth.user">
                                Is {{ maxLength }} characters too short, or need a longer expiry? - <Link class="underline text-gamboge-200" :href="route('login')">login</Link> or <Link class="underline text-gamboge-200" :href="route('register')">create a free account!</Link> to increase the limit.
                            </div>
                            <div v-else-if="!$page.props.auth.user.subscription" class="flex flex-wrap gap-1">
                                Is {{ maxLength }} characters still too short, or need a longer expiry? - <a as="a" class="underline text-gamboge-200" :href="route('plans.index')">subscribe to a paid plan</a> to increase the limits.
                            </div>
                        </span>
                    </div>
                    <div class="flex flex-wrap mt-2 gap-1" v-if="!props.isFileSecret">
                        <InputError :message="form.errors.message" />
                        <div v-if="form.errors.message?.length && $page.props.jetstream.flash.error?.code == 429" class="text-sm text-red-600 dark:text-red-400">
                            Or <Link class="underline text-gamboge-200" :href="route('login')">login</Link> or <Link class="underline text-gamboge-200" :href="route('register')">create a free account!</Link> to send more.
                        </div>
                    </div>
                </span>
            </div>

            <!-- File attachment zone (creator, text mode when no file selected) -->
            <div class="col-span-12" v-if="props.secret == null && stage == 'generating' && !$page.props.jetstream.flash?.secret?.url">
                <div v-if="$page.props.auth.user">
                    <div v-if="selectedFile" class="mt-2 flex items-center gap-3 p-3 rounded-md bg-gray-50 dark:bg-gray-800 border border-gamboge-300/30 dark:border-gamboge-300/20">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5 text-gamboge-300 shrink-0">
                            <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625Z" clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-mono text-gamboge-300 truncate">{{ selectedFile.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ humanFileSize(selectedFile.size) }}</p>
                        </div>
                        <button type="button" @click="clearFile" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>
                    <div v-else class="mt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-300 hover:text-gamboge-300 dark:hover:text-gamboge-300 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 text-gamboge-300">
                                <path fill-rule="evenodd" d="M18.97 3.659a2.25 2.25 0 0 0-3.182 0l-10.94 10.94a3.75 3.75 0 1 0 5.304 5.303l7.693-7.693a.75.75 0 0 1 1.06 1.06l-7.693 7.693a5.25 5.25 0 1 1-7.424-7.424l10.939-10.94a3.75 3.75 0 1 1 5.303 5.304L9.097 18.835l-.008.008-.007.007-.002.003-.003.002A2.25 2.25 0 0 1 5.91 15.66l7.81-7.81a.75.75 0 0 1 1.061 1.06l-7.81 7.81a.75.75 0 0 0 1.054 1.068L18.97 6.84a2.25 2.25 0 0 0 0-3.182Z" clip-rule="evenodd" />
                            </svg>
                            Attach a file
                            <input type="file" class="sr-only" :accept="allowedMimeTypes.join(',')" @change="onFileSelected" />
                        </label>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            Max: {{ maxFileUploadSizeMb }} MB &middot; PDF, ZIP, images, Office docs, audio/video
                        </p>
                        <InputError :message="fileError" class="mt-1" />
                    </div>
                </div>
                <div v-else class="mt-2">
                    <p class="text-sm text-gray-400 dark:text-gray-500">
                        Want to attach a file?
                        <Link class="underline text-gamboge-200" :href="route('login')">Log in</Link>
                        or
                        <Link class="underline text-gamboge-200" :href="route('register')">create a free account</Link>
                        to share encrypted files up to 10 MB.
                    </p>
                </div>
            </div>

            <!-- Upload progress bar -->
            <div class="col-span-12" v-if="uploadState">
                <FileProgressBar :state="uploadState" />
            </div>

            <div class="col-span-12">
                <div class="flex flex-wrap sm:flex-nowrap gap-2 sm:space-y-0 sm:space-y-0">
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
            <span v-else>
                <PrimaryButton
                    @click.prevent="decryptData"
                    v-if="!$page.props.jetstream.flash?.secret?.message && !decryptionSuccess"
                    :class="{ 'opacity-25': decryptForm.processing || (other.password?.length == 0 || other.password == null) || !!fileDecryptState }"
                    :disabled="decryptForm.processing || (other.password?.length == 0 || other.password == null) || !!fileDecryptState">
                    {{ props.isFileSecret ? 'Unlock & Download' : 'Retrieve Message' }}
                </PrimaryButton>
                <Link :href="route('welcome')" v-else class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-transparent border border-transparent dark:border-gamboge-300 rounded-md font-semibold text-xs text-white dark:text-gamboge-300 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-gamboge-300 dark:hover:text-gray-900 dark:hover:shadow-neon-cyan-sm focus:bg-gamboge-700 dark:focus:bg-gamboge-300 dark:focus:text-gray-900 active:bg-gamboge-900 dark:active:bg-gamboge-300 dark:active:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150">
                    Send a new secret link
                </Link>
            </span>
        </template>
    </FlatFormSection>
</template>
