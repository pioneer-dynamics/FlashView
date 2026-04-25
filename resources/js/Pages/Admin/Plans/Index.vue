<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    plans: Array,
});

const planBeingDeleted = ref(null);
const deleteForm = useForm({});

const confirmDelete = (plan) => {
    planBeingDeleted.value = plan;
};

const deletePlan = () => {
    deleteForm.delete(route('admin.plans.destroy', planBeingDeleted.value.id), {
        preserveScroll: true,
        onSuccess: () => { planBeingDeleted.value = null; },
        onError: () => { planBeingDeleted.value = null; },
    });
};

const featureCount = (plan) => plan.features ? Object.keys(plan.features).length : 0;
</script>

<template>
    <AdminLayout title="Admin — Plans">
        <template #title>Plans</template>

        <Page>
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Plan Management</h1>
                <Link :href="route('admin.plans.create')">
                    <PrimaryButton>New Plan</PrimaryButton>
                </Link>
            </div>

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg dark:shadow-neon-cyan-sm">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gamboge-300 tracking-widest">
                        <tr>
                            <th scope="col" class="px-6 py-3">Name</th>
                            <th scope="col" class="px-6 py-3">Monthly</th>
                            <th scope="col" class="px-6 py-3">Yearly</th>
                            <th scope="col" class="px-6 py-3">Stripe Product</th>
                            <th scope="col" class="px-6 py-3 text-center">Features</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="plans.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">
                                No plans yet.
                                <Link :href="route('admin.plans.create')" class="text-gamboge-300 hover:underline ml-1">Create one.</Link>
                            </td>
                        </tr>
                        <tr v-for="plan in plans" :key="plan.id"
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700">
                            <th scope="row" class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                {{ plan.name }}
                            </th>
                            <td class="px-6 py-4 font-mono">
                                ${{ Number(plan.price_per_month).toFixed(2) }}
                            </td>
                            <td class="px-6 py-4 font-mono">
                                ${{ Number(plan.price_per_year).toFixed(2) }}
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="plan.stripe_product_id" class="font-mono text-xs text-gamboge-300">
                                    {{ plan.stripe_product_id }}
                                </span>
                                <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">
                                    {{ featureCount(plan) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('admin.plans.edit', plan.id)">
                                        <SecondaryButton class="text-xs">Edit</SecondaryButton>
                                    </Link>
                                    <DangerButton class="text-xs" @click="confirmDelete(plan)">
                                        Delete
                                    </DangerButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Page>

        <ConfirmationModal :show="planBeingDeleted !== null" @close="planBeingDeleted = null">
            <template #title>Delete Plan</template>
            <template #content>
                Are you sure you want to delete <strong>{{ planBeingDeleted?.name }}</strong>?
                This action cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="planBeingDeleted = null">Cancel</SecondaryButton>
                <DangerButton class="ms-3" :class="{ 'opacity-25': deleteForm.processing }" :disabled="deleteForm.processing" @click="deletePlan">
                    Delete Plan
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
