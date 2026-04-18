<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCardWithFeatures from '@/Components/AuthenticationCardWithFeatures.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    email: String,
    signedUrl: String,
});

const form = useForm({
    email: props.email,
    name: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    // Build the POST URL with the signed query parameters preserved.
    // Laravel's 'signed' middleware validates the signature from query params, not POST body.
    const signed = new URL(props.signedUrl);
    const postUrl = route('register.complete.store')
        + '?email=' + encodeURIComponent(signed.searchParams.get('email'))
        + '&expires=' + signed.searchParams.get('expires')
        + '&signature=' + signed.searchParams.get('signature');

    form.post(postUrl, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Complete Registration" />

    <AuthenticationCardWithFeatures>
        <template #heading>
            Almost there
        </template>

        <template #subtitle>
            Complete your account to unlock additional features
        </template>

        <template #features>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Longer expiry options for your secrets</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Higher message size limits</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Dashboard to track your shared secrets</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Webhook notifications</span>
            </li>
        </template>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    :model-value="email"
                    type="email"
                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800"
                    disabled
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Email verified. This cannot be changed.
                </p>
            </div>

            <div class="mt-4">
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2">
                            I agree to the <a target="_blank" :href="route('terms.show')" class="underline text-sm text-gamboge-300 dark:text-gamboge-200 hover:text-gamboge-200 dark:hover:text-gamboge-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gamboge-500 dark:focus:ring-offset-gray-900">Terms of Service</a> and <a target="_blank" :href="route('policy.show')" class="underline text-sm text-gamboge-300 dark:text-gamboge-200 hover:text-gamboge-200 dark:hover:text-gamboge-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gamboge-500 dark:focus:ring-offset-gray-900">Privacy Policy</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCardWithFeatures>
</template>
