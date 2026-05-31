<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    plan:              Object,
    defaultStripeMode: String,
});

const isEditing = computed(() => props.plan !== null);

const form = useForm({
    tier:                 props.plan?.tier             ?? 'text',
    years:                props.plan?.years            ?? 1,
    file_size_mb:         props.plan?.file_size_mb     ?? 50,
    amount_cents:         props.plan?.amount_cents      ?? '',
    stripe_price_id:      props.plan?.stripe_price_id  ?? '',
    create_stripe_price:  props.defaultStripeMode === 'create',
    is_active:            props.plan?.is_active        ?? true,
});

// Clear stripe_price_id when switching to auto-create mode
watch(() => form.create_stripe_price, (val) => {
    if (val) form.stripe_price_id = '';
});

// Clear file_size_mb when switching to text
watch(() => form.tier, (val) => {
    if (val === 'text') form.file_size_mb = null;
    else if (!form.file_size_mb) form.file_size_mb = 50;
});

const amountDollars = computed({
    get: () => form.amount_cents ? (form.amount_cents / 100).toFixed(2) : '',
    set: (val) => { form.amount_cents = Math.round(parseFloat(val || 0) * 100); },
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.locker-plans.update', props.plan.id));
    } else {
        form.post(route('admin.locker-plans.store'));
    }
};

const FILE_SIZE_PRESETS = [10, 25, 50, 100, 250, 500];
</script>

<template>
    <AdminLayout :title="isEditing ? 'Edit Locker Plan' : 'New Locker Plan'">
        <template #title>{{ isEditing ? 'Edit Locker Plan' : 'New Locker Plan' }}</template>

        <Page>
            <div class="max-w-lg">
                <div class="mb-6">
                    <Link :href="route('admin.locker-plans.index')" class="text-sm text-gamboge-300 hover:text-gamboge-200">
                        ← Back to Locker Plans
                    </Link>
                </div>

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
                                step="0.01"
                                min="0.01"
                                class="pl-7 w-full"
                                placeholder="20.00"
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
                        <Link :href="route('admin.locker-plans.index')">
                            <SecondaryButton type="button">Cancel</SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </Page>
    </AdminLayout>
</template>
