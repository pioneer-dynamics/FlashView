<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import Page from '@/Pages/Page.vue';
    import StegoForm from '@/Pages/Secret/StegoForm.vue';

    const props = defineProps({
        canUseStego: {
            type: Boolean,
            default: false,
        },
    });
</script>

<template>
    <Head title="Steganography Mode" />

    <AppLayout title="Steganography Mode">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Hide a secret inside an image
            </h2>
        </template>

        <Page>
            <StegoForm :can-use-stego="props.canUseStego" />

            <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                <Link :href="route('dashboard')" class="underline hover:text-gray-700 dark:hover:text-gray-200">
                    Back to regular secret links
                </Link>
            </div>
        </Page>
    </AppLayout>
</template>
