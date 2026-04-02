<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
</script>

<template>
    <Head title="Check Your Email" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="text-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Check your email
            </h2>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                We've sent a verification link to your email address.
                Please check your inbox and click the link to continue your registration.
            </p>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-500">
                Didn't receive it? Check your spam or junk folder, or
                <Link :href="route('register')" class="underline hover:text-gray-700 dark:hover:text-gray-300">go back and try again</Link>.
            </p>

            <div class="mt-6">
                <Link
                    :href="route('login')"
                    class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                >
                    Back to Login
                </Link>
            </div>
        </div>
    </AuthenticationCard>
</template>
