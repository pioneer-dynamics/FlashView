<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    name:                     '',
    discount_type:            'percent',
    discount_value:           '',
    currency:                 'AUD',
    duration:                 'once',
    duration_in_months:       '',
    applies_to:               '',
    promo_code:               '',
    max_redemptions:          '',
    max_redemptions_per_user: '',
    minimum_amount:           '',
    expires_at:               '',
});

const discountValueLabel = computed(() =>
    form.discount_type === 'percent' ? '%' : '$ (AUD)'
);

const showCurrencyField = computed(() => form.discount_type === 'amount');
const showDurationMonths = computed(() => form.duration === 'repeating');
const showMinAmountCurrencyHint = computed(() =>
    form.discount_type === 'percent' && form.minimum_amount !== ''
);

const submit = (): void => {
    form.post(route('admin.coupons.store'));
};
</script>

<template>
    <AdminLayout title="Admin — New Coupon">
        <template #title>New Coupon</template>

        <Page>
            <div class="mb-6">
                <Link :href="route('admin.coupons.index')" prefetch class="text-sm text-gamboge-300 hover:text-gamboge-200">
                    ← Back to Coupons
                </Link>
            </div>

            <div class="max-w-lg">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <TextInput
                            v-model="form.name"
                            type="text"
                            class="w-full"
                            placeholder="Summer Sale"
                            data-testid="coupon-name"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <!-- Discount Type -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Discount Type</label>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="form.discount_type = 'percent'"
                                :class="form.discount_type === 'percent'
                                    ? 'border-gamboge-300 bg-gamboge-300/10 text-gamboge-300'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gamboge-300/50'"
                                class="flex-1 py-2 text-xs font-mono rounded-lg border transition-all"
                                data-testid="discount-type-percent"
                            >
                                Percent Off
                            </button>
                            <button
                                type="button"
                                @click="form.discount_type = 'amount'"
                                :class="form.discount_type === 'amount'
                                    ? 'border-gamboge-300 bg-gamboge-300/10 text-gamboge-300'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gamboge-300/50'"
                                class="flex-1 py-2 text-xs font-mono rounded-lg border transition-all"
                                data-testid="discount-type-amount"
                            >
                                Fixed Amount Off
                            </button>
                        </div>
                        <InputError :message="form.errors.discount_type" class="mt-1" />
                    </div>

                    <!-- Discount Value -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Discount Value ({{ discountValueLabel }})
                        </label>
                        <TextInput
                            v-model="form.discount_value"
                            type="number"
                            step="0.01"
                            min="1"
                            :max="form.discount_type === 'percent' ? 100 : undefined"
                            class="w-full font-mono"
                            placeholder="20"
                            data-testid="discount-value"
                        />
                        <InputError :message="form.errors.discount_value" class="mt-1" />
                    </div>

                    <!-- Currency (only when fixed amount) -->
                    <div v-if="showCurrencyField">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Currency (3-letter code, e.g. AUD)
                        </label>
                        <TextInput
                            v-model="form.currency"
                            type="text"
                            class="w-28 font-mono uppercase"
                            maxlength="3"
                            placeholder="AUD"
                            data-testid="currency"
                        />
                        <InputError :message="form.errors.currency" class="mt-1" />
                    </div>

                    <!-- Duration -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Duration</label>
                        <select
                            v-model="form.duration"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:ring-gamboge-300 focus:border-gamboge-300 text-sm"
                            data-testid="duration"
                        >
                            <option value="once">Once (recommended for one-time purchases)</option>
                            <option value="forever">Forever</option>
                            <option value="repeating">Repeating</option>
                        </select>
                        <InputError :message="form.errors.duration" class="mt-1" />
                    </div>

                    <!-- Duration in months (only when repeating) -->
                    <div v-if="showDurationMonths">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Duration in Months
                        </label>
                        <TextInput
                            v-model.number="form.duration_in_months"
                            type="number"
                            min="1"
                            max="36"
                            class="w-28 font-mono"
                            placeholder="3"
                            data-testid="duration-in-months"
                        />
                        <InputError :message="form.errors.duration_in_months" class="mt-1" />
                    </div>

                    <!-- Applies To -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Applies To</label>
                        <select
                            v-model="form.applies_to"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:ring-gamboge-300 focus:border-gamboge-300 text-sm"
                            data-testid="applies-to"
                        >
                            <option value="">All Products</option>
                            <option value="locker">eLocker only</option>
                            <option value="secure_line">Secure Line only</option>
                            <option value="subscription">Subscriptions only</option>
                            <option value="both">Both (eLocker &amp; Secure Line)</option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Restricts which products this coupon applies to. Requires active products with Stripe price IDs.
                        </p>
                        <InputError :message="form.errors.applies_to" class="mt-1" />
                    </div>

                    <!-- Promotion Code -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Promotion Code</label>
                        <TextInput
                            v-model="form.promo_code"
                            type="text"
                            class="w-full font-mono uppercase"
                            placeholder="LAUNCH20"
                            data-testid="promo-code"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Letters, numbers, hyphens, underscores — e.g. LAUNCH20, SUMMER-20
                        </p>
                        <InputError :message="form.errors.promo_code" class="mt-1" />
                    </div>

                    <!-- Max Redemptions (coupon-level) -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Max Redemptions <span class="text-gray-400 font-normal">(optional — leave blank for unlimited)</span>
                        </label>
                        <TextInput
                            v-model.number="form.max_redemptions"
                            type="number"
                            min="1"
                            class="w-32 font-mono"
                            placeholder="Unlimited"
                            data-testid="max-redemptions"
                        />
                        <InputError :message="form.errors.max_redemptions" class="mt-1" />
                    </div>

                    <!-- Per-User Limit -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Per-User Limit <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <TextInput
                            v-model.number="form.max_redemptions_per_user"
                            type="number"
                            min="1"
                            class="w-32 font-mono"
                            placeholder="1"
                            data-testid="max-redemptions-per-user"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            How many times a single customer can redeem this code.
                        </p>
                        <InputError :message="form.errors.max_redemptions_per_user" class="mt-1" />
                    </div>

                    <!-- Minimum Order Amount -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Minimum Order Amount (AUD) <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <div class="relative w-40">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                            <TextInput
                                v-model="form.minimum_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="pl-7 w-full font-mono"
                                placeholder="0.00"
                                data-testid="minimum-amount"
                            />
                        </div>
                        <p v-if="showMinAmountCurrencyHint" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Amounts are interpreted in AUD.
                        </p>
                        <InputError :message="form.errors.minimum_amount" class="mt-1" />
                    </div>

                    <!-- Expiry Date -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Expiry Date <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <TextInput
                            v-model="form.expires_at"
                            type="date"
                            class="w-48"
                            data-testid="expires-at"
                        />
                        <InputError :message="form.errors.expires_at" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <PrimaryButton :disabled="form.processing" data-testid="submit-coupon">
                            Create Coupon
                        </PrimaryButton>
                        <Link :href="route('admin.coupons.index')" prefetch>
                            <SecondaryButton type="button">Cancel</SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </Page>
    </AdminLayout>
</template>
