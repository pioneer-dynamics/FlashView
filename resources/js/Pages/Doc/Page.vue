<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';

interface Props {
    markdown?: string
    updated?: string
    title?: string
    showUpdatedAt?: boolean
}

defineProps<Props>();
</script>

<template>
    <AppLayout :title="title">
        <Page>
            <div class="w-full bg-white overflow-hidden sm:rounded-lg prose dark:prose-invert dark:bg-gray-900 items-center px-4 py-5 sm:p-6 bg-white dark:bg-gray-900 shadow sm:rounded-lg !max-w-none prose-a:text-gamboge-300">
                <span v-html="markdown"/>
                <span class="mt-2" v-if="showUpdatedAt">
                    Last updated: {{updated}}
                </span>
            </div>
        </Page>
    </AppLayout>
</template>
