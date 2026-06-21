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
    coupons: Array,
});

const couponBeingDeleted = ref(null);
const deleteForm = useForm({});

const confirmDelete = (coupon) => { couponBeingDeleted.value = coupon; };

const deleteCoupon = () => {
    deleteForm.delete(route('admin.coupons.destroy', couponBeingDeleted.value.id), {
        preserveScroll: true,
        onSuccess: () => { couponBeingDeleted.value = null; },
        onError:   () => { couponBeingDeleted.value = null; },
    });
};

const formatDiscount = (coupon) => {
    if (coupon.percent_off) {
        return `${coupon.percent_off}% off`;
    }
    if (coupon.amount_off) {
        const amount = (coupon.amount_off / 100).toFixed(2);
        const currency = (coupon.currency ?? 'aud').toUpperCase();
        return `$${amount} ${currency} off`;
    }
    return '—';
};

const formatExpiry = (redeemBy) => {
    if (!redeemBy) { return 'No expiry'; }
    return new Date(redeemBy * 1000).toLocaleDateString('en-AU');
};

const formatRedemptions = (coupon) => {
    const used = coupon.times_redeemed ?? 0;
    const limit = coupon.max_redemptions;
    return limit ? `${used} / ${limit}` : `${used} / Unlimited`;
};
</script>

<template>
    <AdminLayout title="Admin — Coupons">
        <template #title>Coupons</template>

        <Page>
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Coupon &amp; Promotion Code Management</h1>
                <Link :href="route('admin.coupons.create')" prefetch>
                    <PrimaryButton>New Coupon</PrimaryButton>
                </Link>
            </div>

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg dark:shadow-neon-cyan-sm">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gamboge-300 tracking-widest">
                        <tr>
                            <th scope="col" class="px-6 py-3">Name</th>
                            <th scope="col" class="px-6 py-3">Discount</th>
                            <th scope="col" class="px-6 py-3">Duration</th>
                            <th scope="col" class="px-6 py-3">Redemptions</th>
                            <th scope="col" class="px-6 py-3">Expiry</th>
                            <th scope="col" class="px-6 py-3 text-center">Status</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="coupons.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">
                                No coupons yet.
                                <Link :href="route('admin.coupons.create')" prefetch class="text-gamboge-300 hover:underline ml-1">Create one.</Link>
                            </td>
                        </tr>
                        <tr
                            v-for="coupon in coupons"
                            :key="coupon.id"
                            :data-testid="`coupon-row-${coupon.id}`"
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700"
                            :class="{ 'opacity-50': !coupon.valid }"
                        >
                            <th scope="row" class="px-6 py-4 font-semibold text-gray-900 dark:text-white font-mono">
                                {{ coupon.name ?? coupon.id }}
                            </th>
                            <td class="px-6 py-4 font-mono">{{ formatDiscount(coupon) }}</td>
                            <td class="px-6 py-4 capitalize">{{ coupon.duration }}</td>
                            <td class="px-6 py-4 font-mono">{{ formatRedemptions(coupon) }}</td>
                            <td class="px-6 py-4">{{ formatExpiry(coupon.redeem_by) }}</td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    v-if="coupon.valid"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    Valid
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                >
                                    Archived
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('admin.coupons.show', coupon.id)" prefetch>
                                        <SecondaryButton class="text-xs">View</SecondaryButton>
                                    </Link>
                                    <DangerButton class="text-xs" @click="confirmDelete(coupon)">
                                        Delete
                                    </DangerButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Page>

        <ConfirmationModal :show="couponBeingDeleted !== null" @close="couponBeingDeleted = null">
            <template #title>Delete Coupon</template>
            <template #content>
                Are you sure you want to delete the coupon
                <strong>{{ couponBeingDeleted?.name ?? couponBeingDeleted?.id }}</strong>?
                Deleting this coupon will permanently prevent ALL linked promotion codes from being redeemed, even active ones. This cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="couponBeingDeleted = null">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deleteCoupon"
                >
                    Delete Coupon
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
