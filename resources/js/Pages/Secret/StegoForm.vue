<script setup>
    import { ref } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';
    import axios from 'axios';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import ToggleButton from '@/Components/ToggleButton.vue';
    import { encryption } from '../../encryption';
    import { embedText, extractText } from '../../steganography';

    const props = defineProps({
        canUseStego: {
            type: Boolean,
            default: false,
        },
    });

    const page = usePage();

    const mode = ref('embed');

    const modeOptions = [
        { value: 'embed', label: 'Embed a secret' },
        { value: 'extract', label: 'Extract from image' },
    ];

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
    const includeIdentity = ref(false);

    // Extract state
    const extractStegoFile = ref(null);
    const extractStegoFileName = ref('');
    const extractPassword = ref('');
    const extractProcessing = ref(false);
    const extractIdentityProcessing = ref(false);
    const extractError = ref('');
    const extractPasswordError = ref('');
    const extractedMessage = ref('');
    const extractedCiphertext = ref('');
    const verifiedIdentity = ref(null);

    const coverFileInput = ref(null);
    const stegoFileInput = ref(null);

    // Cancellation counter for previewStegoIdentity — incremented on new file select or mode
    // switch to discard results from any in-flight request.
    let previewId = 0;

    const switchMode = (newMode) => {
        previewId++; // invalidate any in-flight previewStegoIdentity
        mode.value = newMode;
        embedError.value = '';
        embedPasswordError.value = '';
        extractError.value = '';
        extractPasswordError.value = '';
        extractedMessage.value = '';
        extractedCiphertext.value = '';
        embedSuccess.value = false;
        verifiedIdentity.value = null;
        extractIdentityProcessing.value = false;
    };

    const onCoverFileChange = (event) => {
        const file = event.target.files[0];
        if (file) {
            embedCoverFile.value = file;
            embedCoverFileName.value = file.name;
            embedError.value = '';
        }
    };

    const previewStegoIdentity = async (file) => {
        previewId++;
        const myId = previewId;

        verifiedIdentity.value = null;
        extractedCiphertext.value = '';
        extractIdentityProcessing.value = true;

        try {
            const rawText = await extractText(file);
            if (previewId !== myId) { return; }

            let ciphertext = rawText;

            try {
                const parsed = JSON.parse(rawText);
                if (parsed && typeof parsed.ciphertext === 'string') {
                    ciphertext = parsed.ciphertext;

                    if (parsed.verified_identity && parsed.signature) {
                        const verifyResponse = await axios.post(route('stego.verify'), {
                            ciphertext: parsed.ciphertext,
                            verified_identity: parsed.verified_identity,
                            signature: parsed.signature,
                        });
                        if (previewId !== myId) { return; }
                        if (verifyResponse.data.verified) {
                            verifiedIdentity.value = parsed.verified_identity;
                        }
                    }
                }
            } catch (err) {
                if (err.response?.status === 429) {
                    // Rate limit hit — badge simply won't show; extraction still works
                } // else: not JSON — legacy plain-ciphertext image
            }

            if (previewId !== myId) { return; }
            extractedCiphertext.value = ciphertext;
        } catch {
            // Not a stego image or no hidden message — user may have picked the wrong file;
            // don't show an error here as they haven't attempted extraction yet.
        } finally {
            if (previewId === myId) {
                extractIdentityProcessing.value = false;
            }
        }
    };

    const onStegoFileChange = (event) => {
        const file = event.target.files[0];
        if (file) {
            extractStegoFile.value = file;
            extractStegoFileName.value = file.name;
            extractError.value = '';
            extractedMessage.value = '';
            previewStegoIdentity(file);
        }
    };

    const embedAndDownload = async () => {
        embedError.value = '';
        embedPasswordError.value = '';

        if (!embedMessage.value.trim()) {
            embedError.value = 'Please enter a message to hide.';
            return;
        }

        if (!embedCoverFile.value) {
            embedError.value = 'Please upload a cover image (PNG).';
            return;
        }

        embedProcessing.value = true;

        try {
            const e = new encryption();
            const result = await e.encryptMessage(embedMessage.value, embedPassword.value || null);

            let textToEmbed = result.secret;

            if (includeIdentity.value && page.props.auth.senderIdentity) {
                const signResponse = await axios.post(route('stego.sign'), {
                    ciphertext: result.secret,
                });
                textToEmbed = JSON.stringify({
                    ciphertext: result.secret,
                    verified_identity: signResponse.data.verified_identity,
                    signature: signResponse.data.signature,
                });
            }

            const stegoPngBlob = await embedText(embedCoverFile.value, textToEmbed);

            const url = URL.createObjectURL(stegoPngBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'flashview-secret.png';
            a.click();
            URL.revokeObjectURL(url);

            embedPassphrase.value = result.passphrase ?? embedPassword.value;
            embedStegoUrl.value = route('stego.index');
            embedSuccess.value = true;
        } catch (err) {
            if (err.response?.status === 403) {
                embedError.value = 'Your verified sender identity is no longer active. Uncheck "Include identity" to embed without it.';
            } else if (err.message?.toLowerCase().includes('passphrase')) {
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
        includeIdentity.value = false;
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
            // Use the ciphertext cached by previewStegoIdentity on image upload.
            // Fall back to re-extracting if the cache is empty (e.g. identity check is still in flight).
            const ciphertext = extractedCiphertext.value || await extractText(extractStegoFile.value);

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
    <!-- Guest / plan gate -->
    <template v-if="!canUseStego">
        <FlatFormSection>
            <template #form>
                <div class="col-span-6">
                    <Alert type="Info" hide-title>
                        <strong>Login required.</strong>
                        Steganography mode is available to logged-in users only.
                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                            <Link :href="route('login')" class="underline">Log in</Link>
                            <span>or</span>
                            <Link :href="route('register')" class="underline">create a free account</Link>
                            <span>to hide secrets inside images.</span>
                        </div>
                    </Alert>
                </div>
            </template>
        </FlatFormSection>
    </template>

    <!-- Authorised content -->
    <template v-else>
        <ToggleButton
            :model-value="mode"
            :options="modeOptions"
            @update:model-value="switchMode"
            class="mb-4"
        />

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
                        <CodeBlock class="mt-1">To read this message: visit {{ embedStegoUrl }}, click "Extract from image", upload the PNG I sent you, and enter the password above.</CodeBlock>
                    </div>
                </template>

                <!-- Embed form -->
                <template v-else>
                    <div class="col-span-12">
                        <Alert type="Info" hide-title class="mb-4">
                            Your message will be encrypted end-to-end, then hidden inside a PNG image using steganography. The image looks completely normal to anyone without the password.
                            Unlike a normal secret link, this image can be forwarded and read multiple times — there is no server-enforced one-time read or expiry.
                        </Alert>
                        <span>
                            <TextAreaInput
                                v-model="embedMessage"
                                rows="7"
                                class="mt-1 p-3 block w-full"
                                placeholder="Your secret message..."
                                :autofocus="true"
                            />
                        </span>
                        <div class="flex flex-wrap mt-2 relative text-sm gap-2">
                            <div class="flex flex-wrap">
                                <svg xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4">
                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                                </svg>
                                <div class="ml-1">End-to-end encrypted</div>
                            </div>
                        </div>
                        <InputError :message="embedError" class="mt-2" />
                    </div>

                    <div class="col-span-12">
                        <TextInput
                            id="embed-password"
                            v-model="embedPassword"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Enter a password, or leave blank to auto-generate one."
                        />
                        <InputError :message="embedPasswordError" class="mt-2" />
                    </div>

                    <div class="col-span-12">
                        <InputLabel value="Cover image (PNG)" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-2">
                            Upload a PNG image to hide your message in. The larger the image, the more text it can carry.
                        </p>
                        <input
                            ref="coverFileInput"
                            type="file"
                            accept="image/png"
                            class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gamboge-800 file:text-white dark:file:bg-gamboge-200 dark:file:text-gamboge-800 hover:file:bg-gamboge-700"
                            @change="onCoverFileChange"
                        />
                    </div>

                    <!-- Verified sender identity opt-in (only for users with a verified identity) -->
                    <div v-if="page.props.auth.senderIdentity" class="col-span-12">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" v-model="includeIdentity" class="rounded border-gray-300 dark:border-gray-600 text-gamboge-600 focus:ring-gamboge-500" />
                            Include my verified sender identity
                            <span class="text-gray-500 dark:text-gray-400">
                                ({{ page.props.auth.senderIdentity.company_name ?? page.props.auth.senderIdentity.email }})
                            </span>
                        </label>
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
                    <input
                        ref="stegoFileInput"
                        type="file"
                        accept="image/png"
                        class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gamboge-800 file:text-white dark:file:bg-gamboge-200 dark:file:text-gamboge-800 hover:file:bg-gamboge-700"
                        @change="onStegoFileChange"
                    />
                    <InputError :message="extractError" class="mt-2" />
                </div>

                <div class="col-span-6">
                    <TextInput
                        id="extract-password"
                        v-model="extractPassword"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Enter the password to decrypt the hidden message."
                    />
                    <InputError :message="extractPasswordError" class="mt-2" />
                </div>

                <!-- Verified Sender badge — shown as soon as the image is loaded, before decryption -->
                <div v-if="verifiedIdentity" class="col-span-6">
                    <Alert type="Success" hide-title>
                        <div class="flex items-start gap-2">
                            <div>
                                <p class="font-semibold">&#10003; Verified Sender</p>
                                <p v-if="verifiedIdentity.company_name" class="mt-1">
                                    This image was created by <strong>{{ verifiedIdentity.company_name }}</strong>
                                    (verified domain: {{ verifiedIdentity.domain }})
                                </p>
                                <p v-else-if="verifiedIdentity.email" class="mt-1">
                                    This image was created by <strong>{{ verifiedIdentity.email }}</strong>
                                </p>
                            </div>
                        </div>
                    </Alert>
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
                    :class="{ 'opacity-25': extractProcessing || extractIdentityProcessing || !extractStegoFile || !extractPassword }"
                    :disabled="extractProcessing || extractIdentityProcessing || !extractStegoFile || !extractPassword"
                >
                    {{ extractProcessing ? 'Extracting…' : 'Extract & Decrypt' }}
                </PrimaryButton>
            </template>
        </FlatFormSection>
    </template>
</template>
