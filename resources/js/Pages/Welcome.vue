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
        isFileSecret: {
            type: Boolean,
            default: false,
        },
        hasMessage: {
            type: Boolean,
            default: false,
        },
        fileSize: {
            type: Number,
            default: null,
        },
        fileMimeType: {
            type: String,
            default: null,
        },
        fileDownloadUrl: {
            type: String,
            default: null,
        },
        latestPost: {
            type: Object,
            default: null,
        },
    });


</script>

<template>
    <AppLayout title="Welcome">
        <Page>
        <div class="relative min-h-screen flex flex-col items-center justify-center dark:[background:radial-gradient(ellipse_at_top,rgba(0,212,245,0.06)_0%,transparent_60%)]">
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <main class="mt-6 grid-cols-1 gap-6 max-w-4xl mx-auto">
                    <!-- <div class="grid gap-6 lg:grid-cols-1 lg:gap-8 px-40"> -->
                        <Typewriter class="font-mono text-gray-600 dark:text-gray-300 mb-6 min-h-[8rem]" :phrases="['time-sensitive.', 'one-time use.', 'disposable.']" :speed="100">
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
                            :is-file-secret="isFileSecret"
                            :has-message="hasMessage"
                            :file-size="fileSize"
                            :file-mime-type="fileMimeType"
                            :file-download-url="fileDownloadUrl"
                        />

                        <!-- Latest Blog Post -->
                        <div v-if="latestPost" class="mt-12">
                            <p class="text-xs uppercase tracking-widest font-mono text-gamboge-300 mb-4">From the Blog</p>
                            <Link
                                :href="route('blog.show', latestPost.slug)"
                                prefetch
                                class="group block border border-gray-200 dark:border-gray-700 dark:hover:border-gamboge-700 rounded-lg p-6 transition-colors duration-150 dark:hover:shadow-neon-cyan-sm"
                            >
                                <div class="flex flex-wrap items-center gap-3 mb-2">
                                    <time class="text-xs font-mono text-gamboge-300 uppercase tracking-widest">
                                        {{ latestPost.date_formatted }}
                                    </time>
                                    <span
                                        v-for="tag in latestPost.tags"
                                        :key="tag"
                                        class="text-xs font-mono px-2 py-0.5 rounded bg-gamboge-900/30 text-gamboge-300 border border-gamboge-800/40"
                                    >
                                        {{ tag }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-gamboge-300 transition-colors duration-150 mb-1">
                                    {{ latestPost.title }}
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                    {{ latestPost.excerpt }}
                                </p>
                                <span class="mt-3 inline-block text-xs font-mono text-gamboge-300 group-hover:text-gamboge-200 transition-colors duration-150">
                                    Read more →
                                </span>
                            </Link>
                        </div>
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
