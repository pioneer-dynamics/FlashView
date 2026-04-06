<script setup>
    import { ref } from 'vue';
    import { Head, Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import Page from '@/Pages/Page.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import TextInput from '@/Components/TextInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import SecondaryButton from '@/Components/SecondaryButton.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import InputError from '@/Components/InputError.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import { encryption } from '../../encryption';
    import { embedText, extractText, getImageCapacityBytes } from '../../steganography';
    import defaultCoverUrl from '../../../images/stego-default.png';

    const mode = ref('embed');

    // Embed state
    const embedMessage = ref('');
    const embedPassword = ref('');
    const embedCoverFile = ref(null);
    const embedCoverFileName = ref('');
    const embedProcessing = ref(false);
    const embedError = ref('');
    const embedPasswordError = ref('');
    const embedSuccess = ref(false);
    const embedPassphrase = ref('');
    const embedStegoUrl = ref('');

    // Extract state
    const extractStegoFile = ref(null);
    const extractStegoFileName = ref('');
    const extractPassword = ref('');
    const extractProcessing = ref(false);
    const extractError = ref('');
    const extractPasswordError = ref('');
    const extractedMessage = ref('');

    const coverFileInput = ref(null);
    const stegoFileInput = ref(null);

    const switchMode = (newMode) => {
        mode.value = newMode;
        embedError.value = '';
        embedPasswordError.value = '';
        extractError.value = '';
        extractPasswordError.value = '';
        extractedMessage.value = '';
        embedSuccess.value = false;
    };

    const onCoverFileChange = (event) => {
        const file = event.target.files[0];
        if (file) {
            embedCoverFile.value = file;
            embedCoverFileName.value = file.name;
        }
    };

    const onStegoFileChange = (event) => {
        const file = event.target.files[0];
        if (file) {
            extractStegoFile.value = file;
            extractStegoFileName.value = file.name;
        }
    };

    const loadDefaultCover = async () => {
        try {
            const response = await fetch(defaultCoverUrl);
            const blob = await response.blob();
            return new File([blob], 'cover.png', { type: 'image/png' });
        } catch {
            throw new Error('Failed to load the default cover image. Please upload your own PNG image.');
        }
    };

    const embedAndDownload = async () => {
        embedError.value = '';
        embedPasswordError.value = '';

        if (!embedMessage.value.trim()) {
            embedError.value = 'Please enter a message to hide.';
            return;
        }

        embedProcessing.value = true;

        try {
            const e = new encryption();

            let password = embedPassword.value || null;
            const result = await e.encryptMessage(embedMessage.value, password);
            const ciphertext = result.secret;
            const passphrase = result.passphrase;

            const coverFile = embedCoverFile.value ?? await loadDefaultCover();
            const stegoPngBlob = await embedText(coverFile, ciphertext);

            const url = URL.createObjectURL(stegoPngBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'flashview-secret.png';
            a.click();
            URL.revokeObjectURL(url);

            embedPassphrase.value = passphrase ?? embedPassword.value;
            embedStegoUrl.value = route('stego.index');
            embedSuccess.value = true;
        } catch (err) {
            if (err.message?.toLowerCase().includes('passphrase')) {
                embedPasswordError.value = err.message;
            } else {
                embedError.value = err.message ?? 'Something went wrong. Please try again.';
            }
        } finally {
            embedProcessing.value = false;
        }
    };

    const hideAnother = () => {
        embedMessage.value = '';
        embedPassword.value = '';
        embedCoverFile.value = null;
        embedCoverFileName.value = '';
        embedSuccess.value = false;
        embedPassphrase.value = '';
        embedStegoUrl.value = '';
        embedError.value = '';
        if (coverFileInput.value) {
            coverFileInput.value.value = '';
        }
    };

    const extractAndDecrypt = async () => {
        extractError.value = '';
        extractPasswordError.value = '';
        extractedMessage.value = '';

        if (!extractStegoFile.value) {
            extractError.value = 'Please select the PNG image to extract from.';
            return;
        }

        if (!extractPassword.value) {
            extractPasswordError.value = 'Please enter the password.';
            return;
        }

        extractProcessing.value = true;

        try {
            const ciphertext = await extractText(extractStegoFile.value);
            const e = new encryption();
            const message = await e.decryptMessage(ciphertext, extractPassword.value);
            extractedMessage.value = message;
        } catch (err) {
            if (err.message?.includes('No hidden message')) {
                extractError.value = 'No hidden message found in this image. Make sure you uploaded the correct PNG and have not re-saved it as JPEG.';
            } else {
                extractError.value = 'No hidden message found in this image, or the password is incorrect.';
            }
        } finally {
            extractProcessing.value = false;
        }
    };
</script>

<template>
    <Head title="Steganography Mode" />

    <AppLayout title="Steganography Mode">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Hide a secret inside an image
            </h2>
        </template>

        <Page>
            <div class="max-w-2xl mx-auto">

                <!-- Mode tabs -->
                <div class="flex gap-2 mb-4">
                    <button
                        type="button"
                        @click.prevent="switchMode('embed')"
                        :class="mode === 'embed'
                            ? 'bg-gamboge-800 dark:bg-gamboge-200 text-white dark:text-gamboge-800'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-semibold transition"
                    >
                        Embed a secret
                    </button>
                    <button
                        type="button"
                        @click.prevent="switchMode('extract')"
                        :class="mode === 'extract'
                            ? 'bg-gamboge-800 dark:bg-gamboge-200 text-white dark:text-gamboge-800'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-semibold transition"
                    >
                        Extract from image
                    </button>
                </div>

                <!-- Embed mode -->
                <FlatFormSection v-if="mode === 'embed'">
                    <template #form>

                        <!-- Success screen -->
                        <template v-if="embedSuccess">
                            <div class="col-span-6">
                                <Alert type="Success" hide-title>
                                    Your secret has been embedded and downloaded as <strong>flashview-secret.png</strong>.
                                </Alert>
                            </div>

                            <div class="col-span-6">
                                <Alert type="Warning" hide-title>
                                    <strong>Send the image and this password through separate channels.</strong>
                                    If you send both together (e.g. in the same email), the hidden layer provides no protection.
                                </Alert>
                            </div>

                            <div class="col-span-6">
                                <InputLabel value="Password — share this separately" />
                                <CodeBlock :value="embedPassphrase" class="mt-1" />
                            </div>

                            <div class="col-span-6">
                                <InputLabel value="Recipient instructions — copy and send separately" />
                                <CodeBlock class="mt-1">
                                    To read this message: visit {{ embedStegoUrl }}, click "Extract from image", upload the PNG I sent you, and enter the password above.
                                </CodeBlock>
                            </div>
                        </template>

                        <!-- Embed form -->
                        <template v-else>
                            <div class="col-span-6">
                                <Alert type="Info" hide-title>
                                    Your message will be encrypted end-to-end, then hidden inside a PNG image using steganography. The image looks completely normal to anyone without the password.
                                    <br><br>
                                    <strong>Note:</strong> Unlike a normal secret link, this image can be forwarded and read multiple times — there is no server-enforced one-time read or expiry.
                                </Alert>
                            </div>

                            <div class="col-span-6">
                                <InputLabel for="embed-message" value="Secret message" />
                                <TextAreaInput
                                    id="embed-message"
                                    v-model="embedMessage"
                                    rows="6"
                                    class="mt-1 block w-full"
                                    placeholder="Your secret message..."
                                    :autofocus="true"
                                />
                                <InputError :message="embedError" class="mt-2" />
                            </div>

                            <div class="col-span-6">
                                <InputLabel for="embed-password" value="Password" />
                                <TextInput
                                    id="embed-password"
                                    v-model="embedPassword"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Enter a password, or leave blank to auto-generate one."
                                />
                                <InputError :message="embedPasswordError" class="mt-2" />
                            </div>

                            <div class="col-span-6">
                                <InputLabel value="Cover image (optional)" />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-2">
                                    Upload any image (PNG, JPEG, WebP, etc.), or leave blank to use the default. The larger the image, the more text it can carry. The output will always be a PNG.
                                </p>
                                <input
                                    ref="coverFileInput"
                                    type="file"
                                    accept="image/*"
                                    class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gamboge-800 file:text-white dark:file:bg-gamboge-200 dark:file:text-gamboge-800 hover:file:bg-gamboge-700"
                                    @change="onCoverFileChange"
                                />
                                <p v-if="embedCoverFileName" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Selected: {{ embedCoverFileName }}
                                </p>
                            </div>
                        </template>

                    </template>

                    <template #actions>
                        <PrimaryButton
                            v-if="embedSuccess"
                            type="button"
                            @click.prevent="hideAnother"
                        >
                            Hide another secret
                        </PrimaryButton>
                        <PrimaryButton
                            v-else
                            type="button"
                            @click.prevent="embedAndDownload"
                            :class="{ 'opacity-25': embedProcessing }"
                            :disabled="embedProcessing"
                        >
                            {{ embedProcessing ? 'Processing…' : 'Embed & Download' }}
                        </PrimaryButton>
                    </template>
                </FlatFormSection>

                <!-- Extract mode -->
                <FlatFormSection v-else>
                    <template #form>

                        <div class="col-span-6">
                            <Alert type="Info" hide-title>
                                Upload the PNG image you received. Only PNG images are supported — if the image was re-saved as JPEG, the hidden message will be lost.
                            </Alert>
                        </div>

                        <div class="col-span-6">
                            <InputLabel value="Stego image (PNG only)" />
                            <input
                                ref="stegoFileInput"
                                type="file"
                                accept="image/png"
                                class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gamboge-800 file:text-white dark:file:bg-gamboge-200 dark:file:text-gamboge-800 hover:file:bg-gamboge-700"
                                @change="onStegoFileChange"
                            />
                            <p v-if="extractStegoFileName" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Selected: {{ extractStegoFileName }}
                            </p>
                            <InputError :message="extractError" class="mt-2" />
                        </div>

                        <div class="col-span-6">
                            <InputLabel for="extract-password" value="Password" />
                            <TextInput
                                id="extract-password"
                                v-model="extractPassword"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Enter the password to decrypt the hidden message."
                            />
                            <InputError :message="extractPasswordError" class="mt-2" />
                        </div>

                        <div v-if="extractedMessage" class="col-span-6">
                            <Alert type="Success" hide-title>
                                Message extracted and decrypted successfully.
                            </Alert>
                            <InputLabel value="Decrypted message" class="mt-3" />
                            <CodeBlock :value="extractedMessage" class="mt-1" />
                        </div>

                    </template>

                    <template #actions>
                        <PrimaryButton
                            type="button"
                            @click.prevent="extractAndDecrypt"
                            :class="{ 'opacity-25': extractProcessing || !extractStegoFile || !extractPassword }"
                            :disabled="extractProcessing || !extractStegoFile || !extractPassword"
                        >
                            {{ extractProcessing ? 'Extracting…' : 'Extract & Decrypt' }}
                        </PrimaryButton>
                    </template>
                </FlatFormSection>

                <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                    <Link :href="route('welcome')" class="underline hover:text-gray-700 dark:hover:text-gray-200">
                        Back to regular secret links
                    </Link>
                </div>

            </div>
        </Page>
    </AppLayout>
</template>
