<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '@/Pages/Page.vue';
import type { BlogPost } from '@/types';
import BlogController from '@/actions/App/Http/Controllers/BlogController';

interface Props {
    post: BlogPost
}

defineProps<Props>();
</script>

<template>
    <AppLayout :title="post.title">
        <Page>
            <div class="max-w-3xl mx-auto px-4 py-10">
                <!-- Back link -->
                <Link
                    :href="BlogController.index.url()"
                    prefetch
                    class="inline-flex items-center gap-1 text-xs font-mono text-gamboge-300 hover:text-gamboge-200 uppercase tracking-widest mb-8 transition-colors duration-150"
                >
                    ← Blog
                </Link>

                <!-- Post header -->
                <header class="mb-10">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <time class="text-xs font-mono text-gamboge-300 uppercase tracking-widest">
                            {{ post.date_formatted }}
                        </time>
                        <span
                            v-for="tag in post.tags"
                            :key="tag"
                            class="text-xs font-mono px-2 py-0.5 rounded bg-gamboge-900/30 text-gamboge-300 border border-gamboge-800/40"
                        >
                            {{ tag }}
                        </span>
                    </div>

                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white leading-tight mb-3">
                        {{ post.title }}
                    </h1>

                    <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                        By {{ post.author }}
                    </p>
                </header>

                <!-- Post body -->
                <div
                    class="prose dark:prose-invert prose-a:text-gamboge-300 prose-code:font-mono prose-pre:bg-gray-900 prose-pre:border prose-pre:border-gray-700 dark:prose-pre:border-gamboge-800/40 max-w-none"
                    v-html="post.body"
                />

                <!-- Footer -->
                <div class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <Link
                        :href="BlogController.index.url()"
                        prefetch
                        class="text-sm font-mono text-gamboge-300 hover:text-gamboge-200 transition-colors duration-150"
                    >
                        ← Back to Blog
                    </Link>
                </div>
            </div>
        </Page>
    </AppLayout>
</template>
