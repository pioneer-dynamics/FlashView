<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import FlatActionSection from '@/Components/FlatActionSection.vue';

defineProps({
    markdown: String,
    updated: String,
    title: String,
});
</script>

<template>
    <AppLayout :title="title">
        <Page>
            <FlatActionSection>
                <div class="w-full mt-6 mb-5 p-6 bg-white overflow-hidden sm:rounded-lg prose items-center">
                    <span v-html="markdown"/>
                    <span class="mt-2">
                        Last updated: {{updated}}
                    </span>
                </div>
            </FlatActionSection>
        </Page>
    </AppLayout>
</template>
