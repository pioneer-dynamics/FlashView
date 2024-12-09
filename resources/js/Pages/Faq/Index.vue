<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import Faq from '../Partials/Faq.vue';

const props = defineProps({
    plans: Array,
})
</script>
<template>
    <AppLayout title="F.A.Q.">
        <Page>
            <Faq />
        </Page>
    </AppLayout>
</template>