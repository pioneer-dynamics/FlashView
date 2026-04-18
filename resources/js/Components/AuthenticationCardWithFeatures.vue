<script setup>
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 px-4 py-8">
        <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-2 gap-0 overflow-hidden sm:rounded-lg shadow-md">
            <!-- Branding Panel -->
            <div class="bg-gray-800 dark:bg-gray-950 px-8 py-10 md:py-16 flex flex-col justify-center">
                <div class="mb-8">
                    <AuthenticationCardLogo />
                </div>

                <h1 class="text-2xl md:text-3xl font-bold text-white leading-tight">
                    <slot name="heading" />
                </h1>

                <p class="mt-3 text-sm md:text-base text-gray-400 hidden md:block">
                    <slot name="subtitle" />
                </p>

                <ul class="mt-6 md:mt-8 space-y-4">
                    <slot name="features" />
                </ul>
            </div>

            <!-- Form Panel -->
            <div class="flex items-center justify-center bg-white dark:bg-gray-800 dark:border-l dark:border-gamboge-800/30 px-6 py-8 md:py-16">
                <div class="w-full max-w-sm">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
