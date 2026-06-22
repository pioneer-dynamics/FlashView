<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import AdminLockerPlanController from '@/actions/App/Http/Controllers/Admin/AdminLockerPlanController';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { LockerPlan } from '@/types';

interface Props {
    plan: LockerPlan | null
    defaultStripeMode: string
}

const props = defineProps<Props>();

const isEditing = computed(() => props.plan !== null);

const form = useForm({
    tier:                 props.plan?.tier             ?? 'text',
    years:                (props.plan?.years            ?? 1) as string | number,
    file_size_mb:         (props.plan?.file_size_mb     ?? 50) as string | number | null,
    amount_cents:         props.plan?.amount_cents != null ? props.plan.amount_cents : ('' as string | number),
    stripe_price_id:      props.plan?.stripe_price_id  ?? '',
    create_stripe_price:  props.defaultStripeMode === 'create',
    is_active:            props.plan?.is_active        ?? true,
});

// Clear stripe_price_id when switching to auto-create mode
watch(() => form.create_stripe_price, (val: boolean) => {
    if (val) { form.stripe_price_id = ''; }
});

// Clear file_size_mb when switching to text
watch(() => form.tier, (val: string) => {
    if (val === 'text') { form.file_size_mb = null; } else if (!form.file_size_mb) { form.file_size_mb = 50 as string | number; }
});

const amountDollars = computed({
    get: (): string => form.amount_cents ? String(Number(form.amount_cents) / 100) : '',
    set: (val: string): void => { form.amount_cents = Math.round(parseFloat(val || '0') * 100); },
});

const submit = (): void => {
    if (isEditing.value) {
        form.submit(AdminLockerPlanController.update(props.plan!.id));
    } else {
        form.submit(AdminLockerPlanController.store());
    }
};

const FILE_SIZE_PRESETS = [10, 25, 50, 100, 250, 500];

// Preview helpers
const previewPrice = computed((): string =>
    form.amount_cents ? `$${(Number(form.amount_cents) / 100).toFixed(0)}` : '$—'
);

const previewFileSizeLabel = computed((): string => {
    const mb = form.file_size_mb;
    if (!mb) { return ''; }
    const mbNum = Number(mb);
    return mbNum >= 1024 ? `${(mbNum / 1024).toFixed(1)} GB` : `${mbNum} MB`;
});

const previewTierIcon = computed((): string => form.tier === 'file' ? '🗂' : '📄');
const previewTierLabel = computed((): string => form.tier === 'file' ? 'File Locker' : 'Text Locker');
const previewButtonLabel = computed((): string => {
    const y = form.years || 1;
    return `Buy ${y}-Year Locker`;
});
</script>

<template>
    <AdminLayout :title="isEditing ? 'Edit Locker Plan' : 'New Locker Plan'">
        <template #title>{{ isEditing ? 'Edit Locker Plan' : 'New Locker Plan' }}</template>

        <Page>
            <div class="mb-6">
                <Link :href="AdminLockerPlanController.index.url()" prefetch class="text-sm text-gamboge-300 hover:text-gamboge-200">
                    ← Back to Locker Plans
                </Link>
            </div>

            <div class="flex gap-10 items-start">

            <!-- Form (left) -->
            <div class="flex-1 max-w-lg">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Tier — radio buttons -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Tier</label>
                        <div class="flex gap-3">
                            <label
                                v-for="opt in [{ value: 'text', label: 'Text', desc: 'Plain text / structured data' }, { value: 'file', label: 'File', desc: 'Any file type' }]"
                                :key="opt.value"
                                :class="form.tier === opt.value
                                    ? 'border-gamboge-300 bg-gamboge-300/10 shadow-neon-cyan-sm'
                                    : 'border-gray-300 dark:border-gray-600 hover:border-gamboge-300/50'"
                                class="flex-1 flex items-start gap-3 border rounded-lg p-3 cursor-pointer transition-all"
                            >
                                <input
                                    type="radio"
                                    :value="opt.value"
                                    v-model="form.tier"
                                    class="mt-0.5 text-gamboge-300 border-gray-400 focus:ring-gamboge-300"
                                />
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ opt.label }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ opt.desc }}</div>
                                </div>
                            </label>
                        </div>
                        <InputError :message="form.errors.tier" class="mt-1" />
                    </div>

                    <!-- File size (only when tier = file) -->
                    <div v-if="form.tier === 'file'">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">File Size Limit (MB)</label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <button
                                v-for="preset in FILE_SIZE_PRESETS"
                                :key="preset"
                                type="button"
                                @click="form.file_size_mb = preset"
                                :class="form.file_size_mb === preset
                                    ? 'bg-gamboge-300 text-gray-900 shadow-neon-cyan-sm'
                                    : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gamboge-300/50'"
                                class="px-3 py-1.5 rounded-lg text-xs font-mono transition-all"
                            >
                                {{ preset }} MB
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <TextInput
                                v-model.number="form.file_size_mb"
                                type="number"
                                min="1"
                                max="10000"
                                class="w-32 font-mono"
                                placeholder="Custom"
                            />
                            <span class="text-sm text-gray-500 dark:text-gray-400">MB</span>
                        </div>
                        <InputError :message="form.errors.file_size_mb" class="mt-1" />
                    </div>

                    <!-- Duration — free number input -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Duration (years)</label>
                        <div class="flex items-center gap-2">
                            <TextInput
                                v-model.number="form.years"
                                type="number"
                                min="1"
                                max="100"
                                class="w-28 font-mono"
                                placeholder="1"
                            />
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ form.years === 1 ? 'year' : 'years' }}</span>
                        </div>
                        <InputError :message="form.errors.years" class="mt-1" />
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
                    <div class="flex items-center gap-3">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-gray-300 dark:border-gray-600 text-gamboge-300 focus:ring-gamboge-300"
                        />
                        <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            Active — show on pricing page and allow checkout
                        </label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <PrimaryButton :disabled="form.processing">
                            {{ isEditing ? 'Update Plan' : 'Create Plan' }}
                        </PrimaryButton>
                        <Link :href="AdminLockerPlanController.index.url()" prefetch>
                            <SecondaryButton type="button">Cancel</SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>

            <!-- Preview (right) -->
            <div class="w-72 shrink-0 sticky top-8">
                <div class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-4">Live Preview</div>

                <!-- Tier header (as shown on Buy page) -->
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xl">{{ previewTierIcon }}</span>
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ previewTierLabel }}</div>
                    </div>
                </div>

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
                            {{ form.years || 1 }} {{ form.years === 1 ? 'Year' : 'Years' }}
                        </div>
                        <div class="text-3xl font-bold text-white">
                            {{ previewPrice }}
                        </div>
                        <div class="text-gray-400 text-xs mt-1">
                            one-time payment — no subscription
                        </div>
                        <div v-if="form.tier === 'file' && previewFileSizeLabel" class="text-gray-500 text-xs mt-0.5">
                            up to {{ previewFileSizeLabel }}
                        </div>
                    </div>

                    <div class="w-full text-center bg-gamboge-300 text-gray-900 font-semibold py-2.5 px-4 rounded-lg font-mono text-sm shadow-neon-cyan-sm cursor-default select-none">
                        {{ previewButtonLabel }}
                    </div>
                </div>

                <!-- Stripe status indicator -->
                <div class="mt-4 text-xs">
                    <div v-if="form.create_stripe_price" class="flex items-center gap-1.5 text-gamboge-300">
                        <span>⚡</span> Stripe price created automatically on save
                    </div>
                    <div v-else-if="form.stripe_price_id" class="flex items-center gap-1.5 text-green-400">
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
