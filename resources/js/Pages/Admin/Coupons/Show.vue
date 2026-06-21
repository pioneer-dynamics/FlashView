<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    coupon:     Object,
    promoCodes: Array,
});

const showDeleteModal = ref(false);
const deleteForm = useForm({});

const deleteCoupon = () => {
    deleteForm.delete(route('admin.coupons.destroy', props.coupon.id), {
        onSuccess: () => { showDeleteModal.value = false; },
        onError:   () => { showDeleteModal.value = false; },
    });
};

const togglePromoCode = (code) => {
    router.patch(
        route('admin.coupons.promo-codes.toggle', [props.coupon.id, code.id]),
        { active: !code.active },
        { preserveScroll: true }
    );
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

const formatCreated = (timestamp) => {
    if (!timestamp) { return '—'; }
    return new Date(timestamp * 1000).toLocaleDateString('en-AU');
};

const formatMinAmount = (code) => {
    const amount = code.restrictions?.minimum_amount;
    if (!amount) { return '—'; }
    const currency = (code.restrictions?.minimum_amount_currency ?? 'aud').toUpperCase();
    return `$${(amount / 100).toFixed(2)} ${currency}`;
};

const formatRedemptions = (code) => {
    const used = code.times_redeemed ?? 0;
    const limit = code.max_redemptions;
    return limit ? `${used} / ${limit}` : `${used} / Unlimited`;
};

const couponRedemptions = () => {
    const used = props.coupon.times_redeemed ?? 0;
    const limit = props.coupon.max_redemptions;
    return limit ? `${used} / ${limit}` : `${used} / Unlimited`;
};

const durationLabel = (coupon) => {
    if (coupon.duration === 'repeating') {
        return `Repeating — ${coupon.duration_in_months} month(s)`;
    }
    return coupon.duration ? coupon.duration.charAt(0).toUpperCase() + coupon.duration.slice(1) : '—';
};
</script>

<template>
    <AdminLayout title="Admin — Coupon">
        <template #title>Coupon Details</template>

        <Page>
            <div class="mb-6 flex items-center justify-between">
                <Link :href="route('admin.coupons.index')" prefetch class="text-sm text-gamboge-300 hover:text-gamboge-200">
                    ← Back to Coupons
                </Link>
                <DangerButton @click="showDeleteModal = true" data-testid="delete-coupon-btn">
                    Delete Coupon
                </DangerButton>
            </div>

            <!-- Coupon summary card -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mb-8 shadow-sm dark:shadow-neon-cyan-sm">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white font-mono">
                            {{ coupon.name ?? coupon.id }}
                        </h2>
                        <div class="text-xs text-gray-400 font-mono mt-0.5">{{ coupon.id }}</div>
                    </div>
                    <span
                        v-if="coupon.valid"
                        class="inline-flex items-center px-2.5 py-1 rounded text-xs font-mono font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                    >
                        Valid
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center px-2.5 py-1 rounded text-xs font-mono font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                    >
                        Archived
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Discount</div>
                        <div class="text-sm text-gray-900 dark:text-gray-200 font-mono">{{ formatDiscount(coupon) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Duration</div>
                        <div class="text-sm text-gray-900 dark:text-gray-200">{{ durationLabel(coupon) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Redemptions</div>
                        <div class="text-sm text-gray-900 dark:text-gray-200 font-mono">{{ couponRedemptions() }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Expiry</div>
                        <div class="text-sm text-gray-900 dark:text-gray-200">{{ formatExpiry(coupon.redeem_by) }}</div>
                    </div>
                    <div v-if="coupon.applies_to">
                        <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-1">Applies To</div>
                        <div class="text-sm text-gray-900 dark:text-gray-200 font-mono break-all">
                            {{ coupon.applies_to.products?.join(', ') ?? '—' }}
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Customers can enter promotion codes at the eLocker, Secure Line, or subscription checkout page.
                </p>
            </div>

            <!-- Promotion Codes table -->
            <div class="mb-4">
                <h3 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Promotion Codes</h3>
            </div>

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg dark:shadow-neon-cyan-sm">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gamboge-300 tracking-widest">
                        <tr>
                            <th scope="col" class="px-6 py-3">Code</th>
                            <th scope="col" class="px-6 py-3 text-center">Active</th>
                            <th scope="col" class="px-6 py-3">Redemptions</th>
                            <th scope="col" class="px-6 py-3">Per-User Limit</th>
                            <th scope="col" class="px-6 py-3">Min. Amount</th>
                            <th scope="col" class="px-6 py-3">Created</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="promoCodes.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">
                                No promotion codes found.
                            </td>
                        </tr>
                        <tr
                            v-for="code in promoCodes"
                            :key="code.id"
                            :data-testid="`promo-code-row-${code.id}`"
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700"
                            :class="{ 'opacity-50': !code.active }"
                        >
                            <th scope="row" class="px-6 py-4 font-mono font-semibold text-gray-900 dark:text-white">
                                {{ code.code }}
                            </th>
                            <td class="px-6 py-4 text-center">
                                <span v-if="code.active" class="text-gamboge-300 text-xs font-mono">Yes</span>
                                <span v-else class="text-gray-500 text-xs font-mono">No</span>
                            </td>
                            <td class="px-6 py-4 font-mono">{{ formatRedemptions(code) }}</td>
                            <td class="px-6 py-4 font-mono">{{ code.max_redemptions ?? '—' }}</td>
                            <td class="px-6 py-4 font-mono">{{ formatMinAmount(code) }}</td>
                            <td class="px-6 py-4">{{ formatCreated(code.created) }}</td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    type="button"
                                    @click="togglePromoCode(code)"
                                    class="text-xs font-mono px-3 py-1 rounded border transition-all"
                                    :class="code.active
                                        ? 'border-red-400 text-red-400 hover:bg-red-400/10'
                                        : 'border-gamboge-300 text-gamboge-300 hover:bg-gamboge-300/10'"
                                    :data-testid="`toggle-promo-code-${code.id}`"
                                >
                                    {{ code.active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Page>

        <!-- Delete confirmation modal -->
        <ConfirmationModal :show="showDeleteModal" @close="showDeleteModal = false">
            <template #title>Delete Coupon</template>
            <template #content>
                Are you sure you want to delete the coupon
                <strong>{{ coupon.name ?? coupon.id }}</strong>?
                Deleting this coupon will permanently prevent ALL linked promotion codes from being redeemed, even active ones. This cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deleteCoupon"
                    data-testid="confirm-delete-coupon"
                >
                    Delete Coupon
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
