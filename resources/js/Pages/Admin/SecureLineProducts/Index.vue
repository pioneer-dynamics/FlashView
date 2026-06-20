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
    products: Array,
});

const productBeingDeleted = ref(null);
const deleteForm = useForm({});

const confirmDelete = (product) => { productBeingDeleted.value = product; };

const deleteProduct = () => {
    deleteForm.delete(route('admin.secure-line-products.destroy', productBeingDeleted.value.id), {
        preserveScroll: true,
        onSuccess: () => { productBeingDeleted.value = null; },
        onError:   () => { productBeingDeleted.value = null; },
    });
};

const formatPrice = (cents) => `$${(cents / 100).toFixed(2)}`;

const formatDuration = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours === 0) {
        return `${mins} min`;
    }
    if (mins === 0) {
        return `${hours} hr`;
    }
    return `${hours} hr ${mins} min`;
};
</script>

<template>
    <AdminLayout title="Admin — Secure Line Products">
        <template #title>Secure Line Products</template>

        <Page>
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Secure Line Product Management</h1>
                <Link :href="route('admin.secure-line-products.create')" prefetch>
                    <PrimaryButton>New Product</PrimaryButton>
                </Link>
            </div>

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg dark:shadow-neon-cyan-sm">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gamboge-300 tracking-widest">
                        <tr>
                            <th scope="col" class="px-6 py-3">Name</th>
                            <th scope="col" class="px-6 py-3">Duration</th>
                            <th scope="col" class="px-6 py-3">Max Participants</th>
                            <th scope="col" class="px-6 py-3">Price</th>
                            <th scope="col" class="px-6 py-3">Stripe Price ID</th>
                            <th scope="col" class="px-6 py-3 text-center">Active</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="products.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">
                                No products yet.
                                <Link :href="route('admin.secure-line-products.create')" prefetch class="text-gamboge-300 hover:underline ml-1">Create one.</Link>
                            </td>
                        </tr>
                        <tr v-for="product in products" :key="product.id"
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700"
                            :class="{ 'opacity-50': !product.is_active }">
                            <th scope="row" class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                <div>{{ product.name }}</div>
                                <div v-if="product.is_active && !product.stripe_price_id"
                                     class="mt-1 inline-flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded px-2 py-0.5 font-mono font-normal">
                                    ⚠ Active but no Stripe price — checkout will fail
                                </div>
                            </th>
                            <td class="px-6 py-4 font-mono">{{ formatDuration(product.duration_minutes) }}</td>
                            <td class="px-6 py-4 font-mono">{{ product.max_participants }}</td>
                            <td class="px-6 py-4 font-mono">{{ formatPrice(product.amount_cents) }}</td>
                            <td class="px-6 py-4">
                                <span v-if="product.stripe_price_id" class="font-mono text-xs text-gamboge-300">
                                    {{ product.stripe_price_id }}
                                </span>
                                <span v-else class="text-red-400 text-xs">Not set</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="product.is_active" class="text-gamboge-300 text-xs font-mono">Yes</span>
                                <span v-else class="text-gray-500 text-xs font-mono">No</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('admin.secure-line-products.edit', product.id)" prefetch>
                                        <SecondaryButton class="text-xs">Edit</SecondaryButton>
                                    </Link>
                                    <DangerButton class="text-xs" @click="confirmDelete(product)">
                                        Delete
                                    </DangerButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Page>

        <ConfirmationModal :show="productBeingDeleted !== null" @close="productBeingDeleted = null">
            <template #title>Delete Secure Line Product</template>
            <template #content>
                Are you sure you want to delete
                <strong>{{ productBeingDeleted?.name }}</strong>?
                This action cannot be undone. Deactivating the product is safer if it has been purchased.
            </template>
            <template #footer>
                <SecondaryButton @click="productBeingDeleted = null">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deleteProduct"
                >
                    Delete Product
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
