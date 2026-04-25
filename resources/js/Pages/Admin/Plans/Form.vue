<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Alert from '@/Components/Alert.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const FEATURE_KEYS = [
    'untracked',
    'messages',
    'expiry',
    'throttling',
    'file_upload',
    'email_notification',
    'webhook_notification',
    'support',
    'api',
    'sender_identity',
];

const FEATURE_DEFAULTS = {
    untracked: { label: 'Unlimited messages', order: 1, type: 'feature', config: {} },
    messages: { label: ':message_length character limit per message', order: 2, type: 'feature', config: { message_length: 1000 } },
    expiry: { label: 'Maximum expiry of :expiry_label', order: 3, type: 'limit', config: { expiry_minutes: 20160, expiry_label: '14 days' } },
    throttling: { label: 'Throttled at :per_minute messages per minute', order: 4, type: 'limit', config: { per_minute: 60 } },
    file_upload: { label: 'File uploads up to :max_file_size_mb MB', order: 4.3, type: 'limit', config: { max_file_size_mb: 10 } },
    email_notification: { label: 'Email Notifications', order: 4.5, type: 'missing', config: { email: false } },
    webhook_notification: { label: 'Webhook Notifications', order: 5.5, type: 'missing', config: { webhook: false } },
    support: { label: 'Support', order: 5, type: 'missing', config: {} },
    api: { label: 'API Access', order: 6, type: 'missing', config: {} },
    sender_identity: { label: 'Verified Sender Identity (optional)', order: 7, type: 'missing', config: {} },
};

const props = defineProps({
    plan: Object,
    defaultStripeMode: String,
});

const isEditing = computed(() => props.plan !== null);
const pageTitle = computed(() => isEditing.value ? `Edit ${props.plan.name}` : 'New Plan');

const initFeatures = () => {
    const features = {};
    for (const key of FEATURE_KEYS) {
        const existing = props.plan?.features?.[key];
        features[key] = existing
            ? { ...existing, config: { ...existing.config } }
            : { ...FEATURE_DEFAULTS[key], config: { ...FEATURE_DEFAULTS[key].config } };
    }
    return features;
};

const form = reactive({
    name: props.plan?.name ?? '',
    price_per_month: props.plan?.price_per_month ?? 0,
    price_per_year: props.plan?.price_per_year ?? 0,
    create_stripe_product: props.defaultStripeMode === 'create',
    stripe_product_id: props.plan?.stripe_product_id ?? '',
    stripe_monthly_price_id: props.plan?.stripe_monthly_price_id ?? '',
    stripe_yearly_price_id: props.plan?.stripe_yearly_price_id ?? '',
    features: initFeatures(),
});

const errors = ref({});
const processing = ref(false);
const showUnchangedPriceWarning = ref(false);

const pricesUnchanged = computed(() => {
    if (!isEditing.value || !form.create_stripe_product) {
        return false;
    }
    return (
        Number(form.price_per_month) === Number(props.plan?.price_per_month) &&
        Number(form.price_per_year) === Number(props.plan?.price_per_year)
    );
});

const typeOptions = [
    { label: 'Feature', value: 'feature' },
    { label: 'Limit', value: 'limit' },
    { label: 'Missing', value: 'missing' },
];

const buildPayload = () => {
    const features = {};
    for (const key of FEATURE_KEYS) {
        features[key] = {
            label: form.features[key].label,
            order: Number(form.features[key].order),
            type: form.features[key].type,
            config: buildConfig(key),
        };
    }
    return {
        name: form.name,
        price_per_month: Number(form.price_per_month),
        price_per_year: Number(form.price_per_year),
        create_stripe_product: form.create_stripe_product,
        stripe_product_id: form.stripe_product_id,
        stripe_monthly_price_id: form.stripe_monthly_price_id,
        stripe_yearly_price_id: form.stripe_yearly_price_id,
        features,
    };
};

const buildConfig = (key) => {
    const config = form.features[key].config;
    switch (key) {
        case 'messages': return { message_length: Number(config.message_length ?? 0) };
        case 'expiry': return { expiry_minutes: Number(config.expiry_minutes ?? 0), expiry_label: config.expiry_label ?? '' };
        case 'throttling': return form.features[key].type === 'feature' ? {} : { per_minute: Number(config.per_minute ?? 0) };
        case 'file_upload': return { max_file_size_mb: Number(config.max_file_size_mb ?? 0) };
        case 'email_notification': return { email: Boolean(config.email) };
        case 'webhook_notification': return { webhook: Boolean(config.webhook) };
        default: return {};
    }
};

const submit = (confirmed = false) => {
    if (pricesUnchanged.value && !confirmed) {
        showUnchangedPriceWarning.value = true;
        return;
    }
    showUnchangedPriceWarning.value = false;
    errors.value = {};
    processing.value = true;

    const payload = buildPayload();
    const onError = (errs) => { errors.value = errs; processing.value = false; };
    const onFinish = () => { processing.value = false; };

    if (isEditing.value) {
        router.put(route('admin.plans.update', props.plan.id), payload, { onError, onFinish });
    } else {
        router.post(route('admin.plans.store'), payload, { onError, onFinish });
    }
};
</script>

<template>
    <AdminLayout :title="`Admin — ${pageTitle}`">
        <template #title>{{ pageTitle }}</template>

        <Page>
            <div class="mb-4">
                <Link :href="route('admin.plans.index')" class="text-xs text-gamboge-300 hover:underline font-mono">
                    ← Back to Plans
                </Link>
            </div>

            <form @submit.prevent="submit()" class="space-y-8">

                <!-- Plan Details -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Plan Details</h2>
                    <div>
                        <InputLabel for="name" value="Plan Name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" />
                        <InputError :message="errors.name" class="mt-2" />
                    </div>
                </section>

                <!-- Pricing -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Pricing</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="price_per_month" value="Monthly Price (USD)" />
                            <TextInput id="price_per_month" type="number" step="0.01" min="0" v-model="form.price_per_month" class="mt-1 block w-full" />
                            <InputError :message="errors.price_per_month" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="price_per_year" value="Yearly Price (USD)" />
                            <TextInput id="price_per_year" type="number" step="0.01" min="0" v-model="form.price_per_year" class="mt-1 block w-full" />
                            <InputError :message="errors.price_per_year" class="mt-2" />
                        </div>
                    </div>
                </section>

                <!-- Stripe Integration -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Stripe Integration</h2>

                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" :value="false" v-model="form.create_stripe_product" class="mt-1 text-gamboge-300 focus:ring-gamboge-500" />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">Map existing Stripe IDs</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Use Stripe product and price IDs you already have. Recommended for test and preview environments.
                                </p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" :value="true" v-model="form.create_stripe_product" class="mt-1 text-gamboge-300 focus:ring-gamboge-500" />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">Create new Stripe product/price</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    This makes a live call to Stripe and is <strong>irreversible</strong>. In test or preview environments, prefer "Map existing Stripe IDs".
                                </p>
                            </div>
                        </label>
                    </div>

                    <Alert v-if="form.create_stripe_product" type="Warning" class="mt-3 p-3 text-xs">
                        A new Stripe product and prices will be created on save. Any existing Stripe prices on this plan will be archived — existing subscribers are unaffected but this cannot be undone.
                    </Alert>

                    <!-- Map existing fields -->
                    <div v-if="!form.create_stripe_product" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                        <div>
                            <InputLabel for="stripe_product_id" value="Stripe Product ID" />
                            <TextInput id="stripe_product_id" v-model="form.stripe_product_id" placeholder="prod_..." class="mt-1 block w-full font-mono text-xs" />
                            <InputError :message="errors.stripe_product_id" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="stripe_monthly_price_id" value="Monthly Price ID" />
                            <TextInput id="stripe_monthly_price_id" v-model="form.stripe_monthly_price_id" placeholder="price_..." class="mt-1 block w-full font-mono text-xs" />
                            <InputError :message="errors.stripe_monthly_price_id" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="stripe_yearly_price_id" value="Yearly Price ID" />
                            <TextInput id="stripe_yearly_price_id" v-model="form.stripe_yearly_price_id" placeholder="price_..." class="mt-1 block w-full font-mono text-xs" />
                            <InputError :message="errors.stripe_yearly_price_id" class="mt-2" />
                        </div>
                    </div>
                </section>

                <!-- Features -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Features</h2>

                    <div v-for="key in FEATURE_KEYS" :key="key"
                        class="border border-gray-100 dark:border-gray-700 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs text-gamboge-300 uppercase tracking-widest">{{ key }}</span>
                            <div class="flex items-center gap-2">
                                <InputLabel :for="`type_${key}`" value="Type" class="text-xs" />
                                <SelectInput :id="`type_${key}`" v-model="form.features[key].type"
                                    :options="typeOptions"
                                    class="text-xs py-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel :for="`label_${key}`" value="Label" class="text-xs" />
                                <TextInput :id="`label_${key}`" v-model="form.features[key].label" class="mt-1 block w-full text-xs" />
                                <InputError :message="errors[`features.${key}.label`]" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel :for="`order_${key}`" value="Order" class="text-xs" />
                                <TextInput :id="`order_${key}`" type="number" step="0.1" v-model="form.features[key].order" class="mt-1 block w-full text-xs" />
                                <InputError :message="errors[`features.${key}.order`]" class="mt-1" />
                            </div>
                        </div>

                        <!-- Config fields per feature key -->
                        <div v-if="key === 'messages'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel :for="`messages_length`" value="Character Limit" class="text-xs" />
                                <TextInput id="messages_length" type="number" min="1" v-model="form.features.messages.config.message_length" class="mt-1 block w-full text-xs" />
                            </div>
                        </div>

                        <div v-if="key === 'expiry'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel for="expiry_minutes" value="Max Expiry (minutes)" class="text-xs" />
                                <TextInput id="expiry_minutes" type="number" min="1" v-model="form.features.expiry.config.expiry_minutes" class="mt-1 block w-full text-xs" />
                            </div>
                            <div>
                                <InputLabel for="expiry_label" value="Expiry Label" class="text-xs" />
                                <TextInput id="expiry_label" v-model="form.features.expiry.config.expiry_label" placeholder="e.g. 30 days" class="mt-1 block w-full text-xs" />
                            </div>
                        </div>

                        <div v-if="key === 'throttling' && form.features.throttling.type !== 'feature'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel for="throttling_per_minute" value="Requests Per Minute" class="text-xs" />
                                <TextInput id="throttling_per_minute" type="number" min="1" v-model="form.features.throttling.config.per_minute" class="mt-1 block w-full text-xs" />
                            </div>
                        </div>

                        <div v-if="key === 'file_upload'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel for="file_upload_max" value="Max File Size (MB)" class="text-xs" />
                                <TextInput id="file_upload_max" type="number" min="0" step="1" v-model="form.features.file_upload.config.max_file_size_mb" class="mt-1 block w-full text-xs" />
                            </div>
                        </div>

                        <div v-if="key === 'email_notification'" class="flex items-center gap-2">
                            <input type="checkbox" :id="`email_notif`" v-model="form.features.email_notification.config.email" class="text-gamboge-300 focus:ring-gamboge-500 rounded" />
                            <InputLabel for="email_notif" value="Email notifications enabled" class="text-xs" />
                        </div>

                        <div v-if="key === 'webhook_notification'" class="flex items-center gap-2">
                            <input type="checkbox" :id="`webhook_notif`" v-model="form.features.webhook_notification.config.webhook" class="text-gamboge-300 focus:ring-gamboge-500 rounded" />
                            <InputLabel for="webhook_notif" value="Webhook notifications enabled" class="text-xs" />
                        </div>
                    </div>
                </section>

                <!-- Unchanged price warning -->
                <Alert v-if="showUnchangedPriceWarning" type="Warning" class="p-4">
                    The monthly and yearly prices haven't changed. Creating new Stripe prices will still archive the existing ones. This cannot be undone.
                    <div class="flex gap-3 mt-3">
                        <PrimaryButton type="button" @click="submit(true)">Proceed Anyway</PrimaryButton>
                        <SecondaryButton type="button" @click="showUnchangedPriceWarning = false">Cancel</SecondaryButton>
                    </div>
                </Alert>

                <div class="flex items-center gap-4">
                    <PrimaryButton type="submit" :disabled="processing" :class="{ 'opacity-25': processing }">
                        {{ isEditing ? 'Update Plan' : 'Create Plan' }}
                    </PrimaryButton>
                    <Link :href="route('admin.plans.index')">
                        <SecondaryButton type="button">Cancel</SecondaryButton>
                    </Link>
                </div>

            </form>
        </Page>
    </AdminLayout>
</template>
