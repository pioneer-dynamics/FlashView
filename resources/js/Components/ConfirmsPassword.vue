<script setup lang="ts">
import { ref, reactive, nextTick } from 'vue';
import DialogModal from './DialogModal.vue';
import InputError from './InputError.vue';
import PrimaryButton from './PrimaryButton.vue';
import SecondaryButton from './SecondaryButton.vue';
import TextInput from './TextInput.vue';

const emit = defineEmits<{
    confirmed: [];
}>();

interface Props {
    title?: string;
    content?: string;
    button?: string;
}

withDefaults(defineProps<Props>(), {
    title: 'Confirm Password',
    content: 'For your security, please confirm your password to continue.',
    button: 'Confirm',
});

const confirmingPassword = ref(false);

const form = reactive({
    password: '',
    error: '',
    processing: false,
});

const passwordInput = ref<InstanceType<typeof TextInput> | null>(null);

const startConfirmingPassword = (): void => {
    axios.get(route('password.confirmation')).then((response: { data: { confirmed: boolean } }) => {
        if (response.data.confirmed) {
            emit('confirmed');
        } else {
            confirmingPassword.value = true;

            setTimeout(() => passwordInput.value?.focus(), 250);
        }
    });
};

const confirmPassword = (): void => {
    form.processing = true;

    axios.post(route('password.confirm'), {
        password: form.password,
    }).then(() => {
        form.processing = false;

        closeModal();
        nextTick().then(() => emit('confirmed'));

    }).catch((error: { response: { data: { errors: { password: string[] } } } }) => {
        form.processing = false;
        form.error = error.response.data.errors.password[0];
        passwordInput.value?.focus();
    });
};

const closeModal = (): void => {
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

        <DialogModal :show="confirmingPassword" @close="closeModal">
            <template #title>
                {{ title }}
            </template>

            <template #content>
                {{ content }}

                <div class="mt-4">
                    <TextInput
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Password"
                        autocomplete="current-password"
                        @keyup.enter="confirmPassword"
                    />

                    <InputError :message="form.error" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ms-3"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="confirmPassword"
                >
                    {{ button }}
                </PrimaryButton>
            </template>
        </DialogModal>
    </span>
</template>
