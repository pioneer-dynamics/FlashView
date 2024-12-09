<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import { DateTime } from 'luxon';
import { useForm } from '@inertiajs/vue3';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import { ref } from 'vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

defineProps({
    secrets: Array
})

const form = useForm({})

const messageIdBeingDeleted = ref(null)

const burn = () => {
    form.delete(route('secrets.destroy', messageIdBeingDeleted.value.hash_id), {
        preserveScroll: true,
        onFinish: () => messageIdBeingDeleted.value = null
    });
}

</script>
<template>
    <AppLayout title="My Secrets">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                My Secrets
            </h2>
        </template>

        <Page>
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">
                                Message ID
                            </th>
                            <th scope="col" class="px-6 py-3 text-center">
                                Created At
                            </th>
                            <th scope="col" class="px-6 py-3 text-center">
                                Expires At
                            </th>
                            <th scope="col" class="px-6 py-3 text-center">
                                Retrieved / Burned At
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="secret in secrets.data" class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700">
                            <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                {{ secret.hash_id }}
                            </th>
                            <td class="px-6 py-4 text-center">
                                {{ DateTime.fromISO(secret.created_at).toLocaleString(DateTime.DATETIME_MED) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                {{ DateTime.fromISO(secret.expires_at).toLocaleString(DateTime.DATETIME_MED) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="secret.retrieved_at">{{ DateTime.fromISO(secret.retrieved_at).toLocaleString(DateTime.DATETIME_MED) }}</span>
                                <button v-if="!secret.retrieved_at" @click.prevent="() => messageIdBeingDeleted = secret" class="inline-flex items-center font-medium text-red-600 dark:text-red-500 hover:underline cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-red-800">Burn</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Page>
    </AppLayout>
    <ConfirmationModal :show="messageIdBeingDeleted != null" @close="messageIdBeingDeleted = null">
        <template #title>
            Delete Message - {{ messageIdBeingDeleted.hash_id }}
        </template>

        <template #content>
            Are you sure you would like to burn this Message? Once burned, no one will be able to retrieve the message.
        </template>

        <template #footer>
            <SecondaryButton @click="messageIdBeingDeleted = null">
                Cancel
            </SecondaryButton>

            <DangerButton
                class="ms-3"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="burn"
            >
                Delete
            </DangerButton>
        </template>
    </ConfirmationModal>
</template>