<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StoreMaskedEmailForm from '@/Pages/Settings/Partials/StoreMaskedEmailForm.vue';
import Page from '../Page.vue';

defineProps({
    storeMaskedRecipientEmail: Boolean,
});
</script>

<template>
    <AppLayout title="Settings">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Settings
            </h2>
        </template>

        <Page>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <StoreMaskedEmailForm :store-masked-recipient-email="storeMaskedRecipientEmail" />
            </div>
        </Page>
    </AppLayout>
</template>
