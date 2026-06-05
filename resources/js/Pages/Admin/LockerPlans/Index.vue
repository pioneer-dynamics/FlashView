<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    plans: Array,
});

const planBeingDeleted = ref(null);
const deleteForm = useForm({});

const confirmDelete = (plan) => { planBeingDeleted.value = plan; };

const deletePlan = () => {
    deleteForm.delete(route('admin.locker-plans.destroy', planBeingDeleted.value.id), {
        preserveScroll: true,
        onSuccess: () => { planBeingDeleted.value = null; },
        onError:   () => { planBeingDeleted.value = null; },
    });
};

const tierLabel = (tier) => tier === 'text' ? 'Text' : 'File';
const formatPrice = (cents) => `$${(cents / 100).toFixed(2)}`;
</script>

<template>
    <AdminLayout title="Admin — eLocker Plans">
        <template #title>eLocker Plans</template>

        <Page>
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">eLocker Plan Management</h1>
                <Link :href="route('admin.locker-plans.create')" prefetch>
                    <PrimaryButton>New Plan</PrimaryButton>
                </Link>
            </div>

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg dark:shadow-neon-cyan-sm">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gamboge-300 tracking-widest">
                        <tr>
                            <th scope="col" class="px-6 py-3">Tier</th>
                            <th scope="col" class="px-6 py-3">Years</th>
                            <th scope="col" class="px-6 py-3">Price</th>
                            <th scope="col" class="px-6 py-3">Stripe Price ID</th>
                            <th scope="col" class="px-6 py-3 text-center">Active</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="plans.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">
                                No plans yet.
                                <Link :href="route('admin.locker-plans.create')" prefetch class="text-gamboge-300 hover:underline ml-1">Create one.</Link>
                            </td>
                        </tr>
                        <tr v-for="plan in plans" :key="plan.id"
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700"
                            :class="{ 'opacity-50': !plan.is_active }">
                            <th scope="row" class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                {{ tierLabel(plan.tier) }}
                            </th>
                            <td class="px-6 py-4 font-mono">{{ plan.years }}yr</td>
                            <td class="px-6 py-4 font-mono">{{ formatPrice(plan.amount_cents) }}</td>
                            <td class="px-6 py-4">
                                <span v-if="plan.stripe_price_id" class="font-mono text-xs text-gamboge-300">
                                    {{ plan.stripe_price_id }}
                                </span>
                                <span v-else class="text-red-400 text-xs">Not set</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="plan.is_active" class="text-gamboge-300 text-xs font-mono">Yes</span>
                                <span v-else class="text-gray-500 text-xs font-mono">No</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('admin.locker-plans.edit', plan.id)" prefetch>
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
            <template #title>Delete Locker Plan</template>
            <template #content>
                Are you sure you want to delete the
                <strong>{{ planBeingDeleted?.years }}-year {{ planBeingDeleted?.tier }} plan</strong>?
                This will remove it from the pricing page immediately.
            </template>
            <template #footer>
                <SecondaryButton @click="planBeingDeleted = null">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deletePlan"
                >
                    Delete Plan
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
