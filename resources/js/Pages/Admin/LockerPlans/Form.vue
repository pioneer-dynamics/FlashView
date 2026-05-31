<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    plan: Object,
});

const isEditing = computed(() => props.plan !== null);

const form = useForm({
    tier:            props.plan?.tier            ?? 'text',
    years:           props.plan?.years           ?? 1,
    amount_cents:    props.plan?.amount_cents     ?? '',
    stripe_price_id: props.plan?.stripe_price_id ?? '',
    is_active:       props.plan?.is_active       ?? true,
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.locker-plans.update', props.plan.id));
    } else {
        form.post(route('admin.locker-plans.store'));
    }
};

const amountDollars = computed({
    get: () => form.amount_cents ? (form.amount_cents / 100).toFixed(2) : '',
    set: (val) => { form.amount_cents = Math.round(parseFloat(val || 0) * 100); },
});

const tierOptions = [
    { value: 'text', label: 'Text (up to 100 KB)' },
    { value: 'file', label: 'File (up to 50 MB)' },
];
const yearOptions = [1, 3, 5];
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

                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Tier -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tier</label>
                        <select
                            v-model="form.tier"
                            class="block w-full border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-gamboge-300 focus:ring-gamboge-300"
                        >
                            <option v-for="opt in tierOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <InputError :message="form.errors.tier" class="mt-1" />
                    </div>

                    <!-- Years -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Duration (years)</label>
                        <div class="flex gap-2">
                            <button
                                v-for="y in yearOptions" :key="y"
                                type="button"
                                @click="form.years = y"
                                :class="form.years === y
                                    ? 'bg-gamboge-300 text-gray-900 shadow-neon-cyan-sm'
                                    : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gamboge-300/50'"
                                class="flex-1 py-2 rounded-lg text-sm font-mono transition-all"
                            >
                                {{ y }}yr
                            </button>
                        </div>
                        <InputError :message="form.errors.years" class="mt-1" />
                    </div>

                    <!-- Price -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Price (AUD)
                        </label>
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

                    <!-- Stripe Price ID -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Stripe Price ID
                        </label>
                        <TextInput
                            v-model="form.stripe_price_id"
                            type="text"
                            class="w-full font-mono"
                            placeholder="price_1ABC..."
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            The <code class="font-mono">price_*</code> ID from your Stripe dashboard. Required for checkout to work.
                        </p>
                        <InputError :message="form.errors.stripe_price_id" class="mt-1" />
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
