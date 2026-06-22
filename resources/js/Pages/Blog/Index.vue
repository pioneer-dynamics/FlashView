<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '@/Pages/Page.vue';
import type { BlogPost } from '@/types';
import { show } from '@/actions/App/Http/Controllers/BlogController';

interface Props {
    posts?: BlogPost[]
}

defineProps<Props>();
</script>

<template>
    <AppLayout title="Blog">
        <Page>
            <div class="max-w-4xl mx-auto px-4 py-10">
                <div class="mb-10">
                    <p class="text-xs uppercase tracking-widest font-mono text-gamboge-300 mb-2">Latest</p>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Blog</h1>
                </div>

                <div v-if="posts.length === 0" class="text-gray-500 dark:text-gray-400 font-mono">
                    No posts yet.
                </div>

                <div v-else class="space-y-8">
                    <article
                        v-for="post in posts"
                        :key="post.slug"
                        class="group border border-gray-200 dark:border-gray-700 dark:hover:border-gamboge-700 rounded-lg p-6 transition-colors duration-150 dark:shadow-neon-cyan-sm hover:dark:shadow-neon-cyan"
                    >
                        <div class="flex flex-wrap items-center gap-3 mb-3">
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

                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-gamboge-300 transition-colors duration-150">
                            <Link :href="show.url(post.slug)" prefetch>
                                {{ post.title }}
                            </Link>
                        </h2>

                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                            {{ post.excerpt }}
                        </p>

                        <Link
                            :href="show.url(post.slug)"
                            prefetch
                            class="text-sm font-mono text-gamboge-300 hover:text-gamboge-200 transition-colors duration-150"
                        >
                            Read more →
                        </Link>
                    </article>
                </div>
            </div>
        </Page>
    </AppLayout>
</template>
