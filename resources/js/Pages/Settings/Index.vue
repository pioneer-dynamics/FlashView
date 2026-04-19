<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SenderIdentityForm from '@/Pages/Settings/Partials/SenderIdentityForm.vue';
import StoreMaskedEmailForm from '@/Pages/Settings/Partials/StoreMaskedEmailForm.vue';
import Page from '../Page.vue';
import SectionBorder from '@/Components/SectionBorder.vue';

defineProps({
    storeMaskedRecipientEmail: Boolean,
    senderIdentity: {
        type: Object,
        default: null,
    },
    planSupportsSenderIdentity: {
        type: Boolean,
        default: false,
    },
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
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-10">
                <StoreMaskedEmailForm :store-masked-recipient-email="storeMaskedRecipientEmail" />

                <span v-if="planSupportsSenderIdentity">
                    <SectionBorder/>
                    <SenderIdentityForm
                        :sender-identity="senderIdentity"
                    />
                </span>

                <div v-else class="text-sm text-gray-500 dark:text-gray-400 py-2">
                    Prime subscribers can add a Verified Sender badge to their secret links.
                    <Link :href="route('plans.index')" class="underline text-sm text-gamboge-300 dark:text-gamboge-200 hover:text-gamboge-200 dark:hover:text-gamboge-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gamboge-500 dark:focus:ring-offset-gray-900">Upgrade to Prime →</Link>
                </div>
            </div>
        </Page>
    </AppLayout>
</template>
