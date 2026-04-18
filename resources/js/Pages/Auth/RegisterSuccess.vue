<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticationCardWithFeatures from '@/Components/AuthenticationCardWithFeatures.vue';
</script>

<template>
    <Head title="Check Your Email" />

    <AuthenticationCardWithFeatures>
        <template #heading>
            You're almost there
        </template>

        <template #subtitle>
            Here's what you'll get with your FlashView account
        </template>

        <template #features>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">End-to-end encrypted secret sharing</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Extended expiry options and higher limits</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Personal dashboard to track your secrets</span>
            </li>
            <li class="flex items-start gap-3">
                <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200 mt-1" aria-hidden="true" xmlns="https://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="text-sm text-gray-300">Webhook notifications for secret access</span>
            </li>
        </template>

        <div class="text-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Check your email
            </h2>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                We've sent a verification link to your email address.
                Please check your inbox and click the link to continue your registration.
            </p>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Didn't receive it? Check your spam or junk folder, or
                <Link :href="route('register')" class="underline hover:text-gray-700 dark:hover:text-gray-300">go back and try again</Link>.
            </p>

            <div class="mt-6">
                <Link
                    :href="route('login')"
                    class="underline text-sm text-gamboge-300 dark:text-gamboge-200 hover:text-gamboge-200 dark:hover:text-gamboge-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gamboge-500 dark:focus:ring-offset-gray-900"
                >
                    Back to Login
                </Link>
            </div>
        </div>
    </AuthenticationCardWithFeatures>
</template>
