<script setup>
import { ref, reactive, computed, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DialogModal from './DialogModal.vue';
import InputError from './InputError.vue';
import TextInput from './TextInput.vue';
import ConfirmsPasskey from './ConfirmsPasskey.vue';

const emit = defineEmits(['confirmed']);

const props = defineProps({
    title: {
        type: String,
        default: 'Confirm Authority',
    },
    content: {
        type: String,
        default: 'For your security, please confirm your authority to continue.',
    },
    button: {
        type: String,
        default: 'Confirm',
    },
    mandatory: {
        type: Boolean,
        default: false,
    },
    seconds: {
        type: Number,
        default: 0,
    }
});

const confirmingPassword = ref(false);
const showingAuthChoice = ref(false);
const passkeyFailed = ref(false);
const passkeyVerifying = ref(false);

const passkeyConfirmation = ref(null);

const userHasPasskeys = computed(() => {
    const user = usePage().props.auth?.user;
    return user?.passkeys?.length > 0;
});

const form = reactive({
    password: '',
    error: '',
    processing: false,
});

const passwordInput = ref(null);

const askForPassword = () => {
    confirmingPassword.value = true;
    setTimeout(() => passwordInput.value.focus(), 250);

}

const startConfirmingPassword = () => {
    axios.get(route('password.confirmation', props.seconds > 0 ? {seconds: props.seconds} : {})).then(response => {
        if (response.data.confirmed && !props.mandatory) {
            emit('confirmed');
        } else if (userHasPasskeys.value) {
            showingAuthChoice.value = true;
            passkeyFailed.value = false;
        } else {
            askForPassword();
        }
    });
};

const usePasskey = () => {
    passkeyFailed.value = false;
    passkeyVerifying.value = true;
    passkeyConfirmation.value.start();
};

const onPasskeyConfirmed = () => {
    showingAuthChoice.value = false;
    passkeyFailed.value = false;
    passkeyVerifying.value = false;
    emit('confirmed');
};

const onPasskeyCancelled = () => {
    passkeyFailed.value = true;
    passkeyVerifying.value = false;
};

const usePassword = () => {
    showingAuthChoice.value = false;
    passkeyFailed.value = false;
    askForPassword();
};

const closeAuthChoice = () => {
    showingAuthChoice.value = false;
    passkeyFailed.value = false;
    passkeyVerifying.value = false;
};

const confirmPassword = () => {
    form.processing = true;

    axios.post(route('password.confirm'), {
        password: form.password,
    }).then(() => {
        form.processing = false;

        closeModal();
        nextTick().then(() => emit('confirmed'));

    }).catch(error => {
        form.processing = false;
        form.error = error.response.data.errors.password[0];
        passwordInput.value.focus();
    });
};

const closeModal = () => {
    confirmingPassword.value = false;
    form.password = '';
    form.error = '';
};
</script>

<template>
    <span>
        <span @click="startConfirmingPassword">
            <slot />
        </span>

        <ConfirmsPasskey :email="$page.props.auth.user.email" ref="passkeyConfirmation" @confirmed="onPasskeyConfirmed" @cancelled="onPasskeyCancelled"/>

        <DialogModal :show="showingAuthChoice" @close="closeAuthChoice" max-width="sm">
            <template #title>
                <div class="flex flex-col items-center text-center">
                    <svg class="w-12 h-12 text-gray-400 dark:text-gray-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span class="text-xl">Passkey or security key</span>
                </div>
            </template>

            <template #content>
                <div class="text-center">
                    <p>When you are ready, authenticate using the button below.</p>

                    <div v-if="passkeyFailed" class="mt-4 flex items-center gap-2 rounded-md border border-red-300 dark:border-red-600 bg-red-50 dark:bg-red-900/30 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                        <svg class="h-5 w-5 shrink-0 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                        </svg>
                        Authentication failed.
                    </div>

                    <button
                        @click="usePasskey"
                        :disabled="passkeyVerifying"
                        class="mt-4 w-full rounded-md bg-green-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        {{ passkeyVerifying ? 'Verifying...' : (passkeyFailed ? 'Retry passkey or security key' : 'Use passkey or security key') }}
                    </button>
                </div>
            </template>

            <template #footer>
                <div class="w-full text-sm text-gray-600 dark:text-gray-400">
                    Having problems?
                    <button @click="usePassword" class="text-blue-600 dark:text-blue-400 hover:underline">
                        Use your password
                    </button>
                </div>
            </template>
        </DialogModal>

        <DialogModal :show="confirmingPassword" @close="closeModal" ref="password" max-width="sm">
            <template #title>
                <div class="flex flex-col items-center text-center">
                    <svg class="w-12 h-12 text-gray-400 dark:text-gray-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span class="text-xl">{{ title }}</span>
                </div>
            </template>

            <template #content>
                <div class="text-center">
                    <p>{{ content }}</p>

                    <div class="mt-4">
                        <TextInput
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="w-full"
                            placeholder="Password"
                            autocomplete="current-password"
                            @keyup.enter="confirmPassword"
                        />

                        <InputError :message="form.error" class="mt-2" />
                    </div>

                    <button
                        @click="confirmPassword"
                        :disabled="form.processing"
                        class="mt-4 w-full rounded-md bg-green-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        {{ form.processing ? 'Confirming...' : button }}
                    </button>

                </div>
            </template>

            <template #footer>
                <div class="w-full text-center">
                    <button @click="closeModal" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                        Cancel
                    </button>
                </div>
            </template>
        </DialogModal>
    </span>
</template>
