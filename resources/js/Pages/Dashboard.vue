<script setup lang="ts">
    import AppLayout from '@/Layouts/AppLayout.vue';
    import Page from './Page.vue';
    import Secret from '@/Pages/Secret/SecretForm.vue';

    interface Props {
        secret?: string | null
        decryptUrl?: string | null
    }

    const props = defineProps<Props>()
</script>

<template>
    <AppLayout title="Generate a Secret">
        <template #header>
            <h2 class="font-mono font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Generate a Secret
            </h2>
        </template>

        <Page>
            <Secret :secret="props.secret" :decrypt-url="props.decryptUrl"/>
        </Page>
    </AppLayout>
</template>
