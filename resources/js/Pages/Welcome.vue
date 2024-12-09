<script setup>
    import { Head, Link, router } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import Background from '../../images/bg.png';
    import Logo from '../../images/logo.png';
    import Typewriter from '@/Components/Typewriter.vue';
    import Faq from './Partials/Faq.vue';
    import SecretForm from '@/Pages/Secret/SecretForm.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownLink from '@/Components/DropdownLink.vue';
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
        }
    });

    const bgImageClass = computed(() => "bg-gray-50 text-black/50 dark:bg-black dark:text-white/50 bg-cover ");

</script>

<template>
    <AppLayout title="Welcome">
        <Page>
        <!-- <img id="background" class="absolute -left-20 top-0 w-full" :src="Background" /> -->
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
                        <Typewriter class="text-gray-200 dark:text-white mb-6" :phrases="['time-sensitive.', 'one-time use.', 'disposable.']" :speed="100">
                            <template #before>
                                Keep sensitive information out of your email and chat logs with links that are
                            </template>
                        </Typewriter>   
                        <SecretForm :secret="secret" :decrypt-url="decryptUrl"/>
                        <Faq/>
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
