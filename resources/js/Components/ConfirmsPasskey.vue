<script setup lang="ts">
    import { browserSupportsWebAuthn, startAuthentication } from "@simplewebauthn/browser";
    import type { AuthenticationResponseJSON } from "@simplewebauthn/types";
    import DialogModal from './DialogModal.vue';
    import { ref } from 'vue';
    import { useForm, usePage } from '@inertiajs/vue3';
    import type { PageProps } from '@/types';

    const emit = defineEmits<{
        confirmed: [];
        cancelled: [failed?: boolean];
    }>();

    const confirmingPasskey = ref(false);

    const authorityConfirmed = ref<boolean | null>(null);

    interface Props {
        remember?: boolean;
        title?: string;
        content?: string;
        email?: string;
        mode?: 'login' | 'verify';
    }

    const props = withDefaults(defineProps<Props>(), {
        remember: false,
        title: 'Confirm Passkey',
        content: 'For your security, please confirm your passkey to continue.',
        email: '',
        mode: 'verify',
    });

    const passkeyForm = useForm({
        passkey: '' as string | AuthenticationResponseJSON,
        email: props.email,
        remember: false,
    });

    const operationCancelled = (failed = false): void => {
        emit('cancelled', failed);
        confirmingPasskey.value = false;
    }

    const askForPasskey = (): void => {
        startAuthentication(JSON.parse(JSON.stringify(usePage<PageProps>().props.jetstream.flash.options)))
            .then((res) =>{
                passkeyForm.passkey = res;
                passkeyForm.transform(data => ({
                        ...data,
                        remember: props.remember ? 'on' : '',
                    })).post(route(props.mode == 'login' ? 'passkeys.login' : 'passkeys.verify'), {
                            preserveScroll: true,
                            preserveState: true,
                            onSuccess: () => {
                                if(props.mode == 'login' || usePage<PageProps>().props.jetstream.flash.verified) {
                                    authorityConfirmed.value = true;
                                    operationSuccess();
                                }
                            },
                            onError: (e) => {
                                console.error(e);
                                authorityConfirmed.value = false;
                                operationCancelled();
                            }
                });
            })
            .catch((e) => {
                console.log(e);
                authorityConfirmed.value = false;
                operationCancelled();
            })
    }

    defineExpose({
        passkeyForm: passkeyForm,
        start: (email: string | null = null): void => {
            if(!browserSupportsWebAuthn()) {
                emit('cancelled');
            }
            if(email)
                passkeyForm.email = email;
                passkeyForm.post(route('passkeys.authentication-options'), {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if(!usePage<PageProps>().props.jetstream.flash.options) {
                        emit('cancelled');
                    }
                    else
                    {
                        authorityConfirmed.value = null;
                        confirmingPasskey.value = true;
                        askForPasskey();
                    }
                },
                onError: () => {
                    operationCancelled(true);
                }
            });
        },
    });


    const operationSuccess = (): void => {
        confirmingPasskey.value = false;
        emit('confirmed');
    }

</script>
<template>
   
</template>