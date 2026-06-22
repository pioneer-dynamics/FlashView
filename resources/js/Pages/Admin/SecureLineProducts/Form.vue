<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { SecureLineProduct } from '@/types';
import AdminSecureLineProductController from '@/actions/App/Http/Controllers/Admin/AdminSecureLineProductController';

interface Props {
    product: SecureLineProduct | null
    defaultStripeMode: string
}

const props = defineProps<Props>();

const isEditing = computed(() => props.product !== null);

const form = useForm({
    name:                props.product?.name                                             ?? '',
    duration_minutes:    props.product?.duration_minutes != null ? String(props.product.duration_minutes) : '',
    max_participants:    props.product?.max_participants != null  ? String(props.product.max_participants)  : '2',
    amount_cents:        props.product?.amount_cents != null ? props.product.amount_cents : ('' as string | number),
    stripe_price_id:     props.product?.stripe_price_id                                 ?? '',
    create_stripe_price: props.defaultStripeMode === 'create',
    is_active:           props.product?.is_active                                       ?? true,
});

// Clear stripe_price_id when switching to auto-create mode
watch(() => form.create_stripe_price, (val: boolean) => {
    if (val) { form.stripe_price_id = ''; }
});

const amountDollars = computed({
    get: (): string => form.amount_cents ? String(Number(form.amount_cents) / 100) : '',
    set: (val: string): void => { form.amount_cents = Math.round(parseFloat(val || '0') * 100); },
});

const durationLabel = computed((): string => {
    const m = parseInt(form.duration_minutes as string) || 0;
    if (m === 0) { return ''; }
    const hours = Math.floor(m / 60);
    const mins = m % 60;
    if (hours === 0) { return `${mins} min`; }
    if (mins === 0) { return `${hours} hr`; }
    return `${hours} hr ${mins} min`;
});

const showActiveWarning = computed((): boolean =>
    form.is_active && !form.create_stripe_price && !form.stripe_price_id
);

const submit = (): void => {
    if (isEditing.value) {
        form.submit(AdminSecureLineProductController.update(props.product!.id));
    } else {
        form.submit(AdminSecureLineProductController.store());
    }
};

// Preview helpers
const previewPrice = computed((): string =>
    form.amount_cents ? `$${(Number(form.amount_cents) / 100).toFixed(2)}` : '$—'
);

const previewStripeStatus = computed((): string => {
    if (form.create_stripe_price) { return 'auto'; }
    if (form.stripe_price_id) { return 'mapped'; }
    return 'missing';
});
</script>

<template>
    <AdminLayout :title="isEditing ? 'Edit Secure Line Product' : 'New Secure Line Product'">
        <template #title>{{ isEditing ? 'Edit Secure Line Product' : 'New Secure Line Product' }}</template>

        <Page>
            <div class="mb-6">
                <Link :href="AdminSecureLineProductController.index.url()" prefetch class="text-sm text-gamboge-300 hover:text-gamboge-200">
                    ← Back to Secure Line Products
                </Link>
            </div>

            <div class="flex gap-10 items-start">

            <!-- Form (left) -->
            <div class="flex-1 max-w-lg">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <TextInput
                            v-model="form.name"
                            type="text"
                            class="w-full"
                            placeholder="30-minute Line"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <!-- Duration -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Duration (minutes)</label>
                        <div class="flex items-center gap-2">
                            <TextInput
                                v-model.number="form.duration_minutes"
                                type="number"
                                min="1"
                                max="1440"
                                class="w-32 font-mono"
                                placeholder="30"
                            />
                            <span v-if="durationLabel" class="text-sm text-gamboge-300 font-mono">{{ durationLabel }}</span>
                        </div>
                        <InputError :message="form.errors.duration_minutes" class="mt-1" />
                    </div>

                    <!-- Max Participants -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Max Participants</label>
                        <TextInput
                            v-model.number="form.max_participants"
                            type="number"
                            min="2"
                            max="100"
                            class="w-28 font-mono"
                            placeholder="10"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Minimum 2 — includes the host and at least one participant.
                        </p>
                        <InputError :message="form.errors.max_participants" class="mt-1" />
                    </div>

                    <!-- Price -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Price (AUD)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                            <TextInput
                                v-model="amountDollars"
                                type="number"
                                step="1"
                                min="0"
                                class="pl-7 w-full"
                                placeholder="20"
                            />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Stored as <span class="font-mono">{{ form.amount_cents }}</span> cents
                        </p>
                        <InputError :message="form.errors.amount_cents" class="mt-1" />
                    </div>

                    <!-- Stripe mode toggle -->
                    <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-4">
                        <div class="text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-widest">Stripe Integration</div>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="form.create_stripe_price = true"
                                :class="form.create_stripe_price
                                    ? 'border-gamboge-300 bg-gamboge-300/10 text-gamboge-300'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gamboge-300/50'"
                                class="flex-1 py-2 text-xs font-mono rounded-lg border transition-all"
                            >
                                Create in Stripe
                            </button>
                            <button
                                type="button"
                                @click="form.create_stripe_price = false"
                                :class="!form.create_stripe_price
                                    ? 'border-gamboge-300 bg-gamboge-300/10 text-gamboge-300'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gamboge-300/50'"
                                class="flex-1 py-2 text-xs font-mono rounded-lg border transition-all"
                            >
                                Map Existing ID
                            </button>
                        </div>

                        <!-- Auto-create: just info -->
                        <p v-if="form.create_stripe_price" class="text-xs text-gray-500 dark:text-gray-400">
                            A new Stripe product and one-time price will be created automatically when you save.
                        </p>

                        <!-- Map existing: price ID field -->
                        <div v-else>
                            <TextInput
                                v-model="form.stripe_price_id"
                                type="text"
                                class="w-full font-mono"
                                placeholder="price_1ABC..."
                            />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                The <code class="font-mono">price_*</code> ID from your Stripe dashboard.
                            </p>
                            <InputError :message="form.errors.stripe_price_id" class="mt-1" />
                        </div>
                    </div>

                    <!-- Active -->
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-gamboge-300 focus:ring-gamboge-300"
                            />
                            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                Active — show as a purchase option in checkout
                            </label>
                        </div>
                        <div v-if="showActiveWarning"
                             class="flex items-center gap-2 text-xs text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded px-3 py-2 font-mono">
                            ⚠ This product is active but has no Stripe price — buyers will not be able to complete a purchase.
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEditing ? 'Update Product' : 'Create Product' }}
                        </PrimaryButton>
                        <Link :href="AdminSecureLineProductController.index.url()" prefetch>
                            <SecondaryButton type="button">Cancel</SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>

            <!-- Preview (right) -->
            <div class="w-72 shrink-0 sticky top-8">
                <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-4">Live Preview</div>

                <!-- Card preview -->
                <div class="dark relative bg-gray-800 border border-gray-700 rounded-xl p-6 flex flex-col gap-4 shadow-neon-cyan-sm">

                    <!-- Status badge -->
                    <div v-if="!form.is_active" class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="bg-gray-600 text-gray-200 text-xs font-bold px-3 py-1 rounded-full font-mono uppercase tracking-wide">
                            Inactive
                        </span>
                    </div>

                    <div>
                        <div class="text-gamboge-300 font-mono text-xs uppercase tracking-widest mb-1">
                            {{ durationLabel || '— min' }}
                        </div>
                        <div class="text-white font-semibold text-base mb-0.5">
                            {{ form.name || 'Product Name' }}
                        </div>
                        <div class="text-3xl font-bold text-white">
                            {{ previewPrice }}
                        </div>
                        <div class="text-gray-400 text-xs mt-1">
                            one-time payment — up to {{ form.max_participants || 2 }} participants
                        </div>
                    </div>

                    <div class="w-full text-center bg-gamboge-300 text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm shadow-neon-cyan-sm cursor-default select-none">
                        Buy Now
                    </div>
                </div>

                <!-- Stripe status indicator -->
                <div class="mt-4 text-xs">
                    <div v-if="previewStripeStatus === 'auto'" class="flex items-center gap-1.5 text-gamboge-300">
                        <span>⚡</span> Stripe price created automatically on save
                    </div>
                    <div v-else-if="previewStripeStatus === 'mapped'" class="flex items-center gap-1.5 text-green-400">
                        <span>✓</span>
                        <span class="font-mono truncate">{{ form.stripe_price_id }}</span>
                    </div>
                    <div v-else class="flex items-center gap-1.5 text-red-400">
                        <span>✗</span> No Stripe price — checkout will be blocked
                    </div>
                </div>
            </div>

            </div><!-- end two-column -->
        </Page>
    </AdminLayout>
</template>
