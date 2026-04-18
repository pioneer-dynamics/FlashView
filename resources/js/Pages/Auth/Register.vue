<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCardWithFeatures from '@/Components/AuthenticationCardWithFeatures.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('register.store'));
};
</script>

<template>
    <Head title="Register" />

    <AuthenticationCardWithFeatures>
        <template #heading>
            Secrets that disappear after one view
        </template>

        <template #subtitle>
            Keep sensitive information out of your email and chat logs
        </template>

        <template #features>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">End-to-end encrypted — secrets are encrypted in your browser</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Auto-expiring links — set custom expiry times</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">One-time access — links are destroyed after viewing</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Personal dashboard to manage your shared secrets</span>
            </li>
        </template>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link :href="route('login')" class="underline text-sm text-gamboge-300 dark:text-gamboge-200 hover:text-gamboge-200 dark:hover:text-gamboge-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gamboge-500 dark:focus:ring-offset-gray-900">
                    Already registered?
                </Link>

                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Continue
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCardWithFeatures>
</template>
