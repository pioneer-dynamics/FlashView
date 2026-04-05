<script setup>
    import { Link } from '@inertiajs/vue3';
    import Logo from '../../images/logo.svg';
    import Typewriter from '@/Components/Typewriter.vue';
    import SecretForm from '@/Pages/Secret/SecretForm.vue';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import Page from './Page.vue';
        
    const props = defineProps({
        canLogin: {
            type: Boolean,
            default: false,
        },
        canRegister: {
            type: Boolean,
            default: false,
        },
        secret: {
            type: String,
            default: null
        },
        decryptUrl: {
            type: String,
            default: null
        },
        senderCompanyName: {
            type: String,
            default: null,
        },
        senderDomain: {
            type: String,
            default: null,
        },
        senderEmail: {
            type: String,
            default: null,
        },
    });


</script>

<template>
    <AppLayout title="Welcome">
        <Page>
        <div class="relative min-h-screen flex flex-col items-center justify-center">
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3">
                    <div class="flex lg:justify-center lg:col-start-2">
                        <Link href="/">
                            <img :src="Logo" class="h-24 w-auto">
                        </Link>
                    </div>
                </header>

                <main class="mt-6 grid-cols-1 gap-6 max-w-4xl mx-auto">
                    <!-- <div class="grid gap-6 lg:grid-cols-1 lg:gap-8 px-40"> -->
                        <Typewriter class="text-gray-600 dark:text-gray-300 mb-6" :phrases="['time-sensitive.', 'one-time use.', 'disposable.']" :speed="100">
                            <template #before>
                                Keep sensitive information out of your email and chat logs with links that are
                            </template>
                        </Typewriter>   
                        <SecretForm
                            :secret="secret"
                            :decrypt-url="decryptUrl"
                            :sender-company-name="senderCompanyName"
                            :sender-domain="senderDomain"
                            :sender-email="senderEmail"
                        />
                    <!-- </div> -->
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    <!-- Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }}) -->
                </footer>
            </div>
        </div>
        </Page>
    </AppLayout>
</template>
