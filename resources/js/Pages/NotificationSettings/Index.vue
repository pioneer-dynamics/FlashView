<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import NotificationPreferencesForm from '@/Pages/Profile/Partials/NotificationPreferencesForm.vue';
import WebhookSettingsForm from '@/Pages/Profile/Partials/WebhookSettingsForm.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import Page from '../Page.vue';
</script>

<template>
    <AppLayout title="Notification Settings">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Notification Settings
            </h2>
        </template>

        <Page>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <NotificationPreferencesForm />

                <SectionBorder />

                <WebhookSettingsForm class="mt-10 sm:mt-0" />
            </div>
        </Page>
    </AppLayout>
</template>
