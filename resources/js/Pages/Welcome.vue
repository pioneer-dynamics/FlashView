<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import Secret from '@/Components/Secret.vue';
    import { computed, ref } from 'vue';
    import Background from '../../images/bg.png';
    import Logo from '../../images/logo.png';
    import Typewriter from '@/Components/Typewriter.vue';

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
    <Head title="Welcome" />
    <div :class="bgImageClass" :style="{'background-image': `url(${Background})`}">
        <!-- <img id="background" class="absolute -left-20 top-0 w-full" :src="Background" /> -->
        <div class="relative min-h-screen flex flex-col items-center justify-center">
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3">
                    <div class="flex lg:justify-center lg:col-start-2">
                            <Link href="/">
                                <img :src="Logo" class="h-24 w-auto">
                            </Link>
                        </div>
                    <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main class="mt-6 grid-cols-1 gap-6 max-w-4xl mx-auto">
                    <!-- <div class="grid gap-6 lg:grid-cols-1 lg:gap-8 px-40"> -->
                        <Typewriter class="text-gray-200 dark:text-white mb-6" :phrases="['time-sensitive.', 'one-time use.', 'disposable.']" :speed="100">
                            <template #before>
                                Share encrypted information out of your email and chat logs with links that are
                            </template>
                        </Typewriter>   
                        <Secret :secret="secret" :decrypt-url="decryptUrl"/>
                    <!-- </div> -->
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    <!-- Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }}) -->
                </footer>
            </div>
        </div>
    </div>
</template>
