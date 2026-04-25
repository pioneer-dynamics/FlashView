<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Alert from '@/Components/Alert.vue';
import Feature from '../../Plan/Partials/Feature.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const props = defineProps({
    plan: Object,
    defaultStripeMode: String,
    availableFeatures: {
        type: Array,
        default: () => [],
    },
});

const isEditing = computed(() => props.plan !== null);
const pageTitle = computed(() => isEditing.value ? `Edit ${props.plan.name}` : 'New Plan');

// ── Feature helpers ──────────────────────────────────────────────────────────

const featMeta = (key) => props.availableFeatures.find((f) => f.key === key);

const buildDefaultConfig = (key, stored = {}) => {
    const meta = featMeta(key);
    if (!meta) { return { ...stored }; }
    const defaults = {};
    for (const field of meta.configSchema) {
        defaults[field.key] = stored[field.key] ?? field.default;
    }
    return defaults;
};

const deriveType = (key) => featMeta(key)?.canBeLimit ? 'limit' : 'feature';

const initFromPlan = () => {
    if (!props.plan?.features) { return []; }
    return Object.entries(props.plan.features)
        .filter(([, f]) => f.type !== 'missing')
        .sort(([, a], [, b]) => a.order - b.order)
        .map(([key, f]) => ({
            key,
            type: deriveType(key),
            config: buildDefaultConfig(key, f.config ?? {}),
        }));
};

const includedFeatures = ref(initFromPlan());

const poolFeatures = computed(() =>
    props.availableFeatures.filter(
        (f) => !includedFeatures.value.some((i) => i.key === f.key),
    ),
);

const previewFeatures = computed(() =>
    includedFeatures.value.map((feat) => {
        const meta = featMeta(feat.key);
        let label;
        if (meta?.canBeLimit) {
            label = meta.label;
            for (const [k, v] of Object.entries(feat.config ?? {})) {
                const formatted = v !== '' && !isNaN(Number(v)) ? Number(v).toLocaleString() : v;
                label = label.replace(`:${k}`, formatted);
            }
        } else {
            label = meta?.description ?? feat.key;
        }
        return { key: feat.key, label, type: deriveType(feat.key) };
    })
);

// ── Drag-and-drop state ──────────────────────────────────────────────────────

const dragKey = ref(null);
const dragFrom = ref(null);
const dropIndex = ref(null);
const isDraggingPool = computed(() => dragFrom.value === 'pool');
const isDraggingPlan = computed(() => dragFrom.value === 'plan');

const onDragStart = (key, from) => {
    dragKey.value = key;
    dragFrom.value = from;
};

const onDragEnd = () => {
    dragKey.value = null;
    dragFrom.value = null;
    dropIndex.value = null;
};

const addToPlan = (key) => {
    includedFeatures.value.push({
        key,
        type: deriveType(key),
        config: buildDefaultConfig(key, {}),
    });
};

const removeFromPlan = (key) => {
    includedFeatures.value = includedFeatures.value.filter((f) => f.key !== key);
};

const onDropToPlan = (targetIndex = null) => {
    if (dragFrom.value === 'pool') {
        addToPlan(dragKey.value);
    } else if (dragFrom.value === 'plan' && targetIndex !== null && dragKey.value !== null) {
        const fromIndex = includedFeatures.value.findIndex((f) => f.key === dragKey.value);
        if (fromIndex !== -1 && fromIndex !== targetIndex) {
            const item = includedFeatures.value.splice(fromIndex, 1)[0];
            includedFeatures.value.splice(targetIndex, 0, item);
        }
    }
    onDragEnd();
};

const onDropToPool = () => {
    if (dragFrom.value === 'plan') {
        removeFromPlan(dragKey.value);
    }
    onDragEnd();
};

// ── Form state ───────────────────────────────────────────────────────────────

const form = reactive({
    name: props.plan?.name ?? '',
    price_per_month: props.plan?.price_per_month ?? 0,
    price_per_year: props.plan?.price_per_year ?? 0,
    create_stripe_product: props.defaultStripeMode === 'create',
    stripe_product_id: props.plan?.stripe_product_id ?? '',
    stripe_monthly_price_id: props.plan?.stripe_monthly_price_id ?? '',
    stripe_yearly_price_id: props.plan?.stripe_yearly_price_id ?? '',
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

const canSubmit = computed(() => includedFeatures.value.length > 0);

const buildPayload = () => {
    const features = {};
    includedFeatures.value.forEach((f, index) => {
        const meta = featMeta(f.key);
        const type = deriveType(f.key);
        features[f.key] = {
            type,
            order: index + 1,
            config: meta?.canBeLimit ? { ...f.config } : {},
        };
    });

    return {
        name: form.name,
        price_per_month: Number(form.price_per_month),
        price_per_year: Number(form.price_per_year),
        create_stripe_product: isEditing.value ? false : form.create_stripe_product,
        stripe_product_id: isEditing.value ? (props.plan?.stripe_product_id ?? '') : form.stripe_product_id,
        stripe_monthly_price_id: isEditing.value ? (props.plan?.stripe_monthly_price_id ?? '') : form.stripe_monthly_price_id,
        stripe_yearly_price_id: isEditing.value ? (props.plan?.stripe_yearly_price_id ?? '') : form.stripe_yearly_price_id,
        features,
    };
};

const submit = (confirmed = false) => {
    if (!canSubmit.value) {
        errors.value = { ...errors.value, features: 'At least one feature is required.' };
        return;
    }

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
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-neon-cyan-sm p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Plan Details</h2>
                    <div>
                        <InputLabel for="name" value="Plan Name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" />
                        <InputError :message="errors.name" class="mt-2" />
                    </div>
                </section>

                <!-- Pricing -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-neon-cyan-sm p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Pricing</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="price_per_month" value="Monthly Price (AUD)" />
                            <TextInput id="price_per_month" type="number" step="0.01" min="0" v-model="form.price_per_month" class="mt-1 block w-full" />
                            <InputError :message="errors.price_per_month" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="price_per_year" value="Yearly Price (AUD)" />
                            <TextInput id="price_per_year" type="number" step="0.01" min="0" v-model="form.price_per_year" class="mt-1 block w-full" />
                            <InputError :message="errors.price_per_year" class="mt-2" />
                        </div>
                    </div>
                </section>

                <!-- Stripe Integration -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-neon-cyan-sm p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Stripe Integration</h2>

                    <!-- Edit mode: read-only -->
                    <div v-if="isEditing" class="space-y-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Stripe integration is fixed after plan creation.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="Stripe Product ID" />
                                <p class="mt-1 text-xs font-mono text-gray-700 dark:text-gray-300 truncate">{{ plan.stripe_product_id || '—' }}</p>
                            </div>
                            <div>
                                <InputLabel value="Monthly Price ID" />
                                <p class="mt-1 text-xs font-mono text-gray-700 dark:text-gray-300 truncate">{{ plan.stripe_monthly_price_id || '—' }}</p>
                            </div>
                            <div>
                                <InputLabel value="Yearly Price ID" />
                                <p class="mt-1 text-xs font-mono text-gray-700 dark:text-gray-300 truncate">{{ plan.stripe_yearly_price_id || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Create mode: selectable -->
                    <template v-else>
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
                    </template>
                </section>

                <!-- Features -->
                <section class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-neon-cyan-sm p-6 space-y-4">
                    <h2 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">Features</h2>
                    <p v-if="errors.features" class="text-red-500 text-xs">{{ errors.features }}</p>

                    <!-- Top: Available Feature Pool -->
                    <div
                        @dragover.prevent
                        @drop.prevent="onDropToPool()"
                        :class="[
                            'rounded-lg border-2 border-dashed p-3 transition-colors',
                            isDraggingPlan
                                ? 'border-red-400 bg-red-400/5'
                                : 'border-gray-200 dark:border-gray-700'
                        ]"
                    >
                        <p class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-2">Available Features</p>
                        <div v-if="poolFeatures.length" class="flex flex-wrap gap-2">
                            <div
                                v-for="feat in poolFeatures"
                                :key="feat.key"
                                draggable="true"
                                @dragstart="onDragStart(feat.key, 'pool')"
                                @dragend="onDragEnd"
                                :class="[
                                    'flex items-center gap-2 bg-gray-50 dark:bg-gray-900 rounded-lg px-3 py-2 border border-gray-100 dark:border-gray-700 dark:shadow-neon-cyan-sm cursor-grab',
                                    dragKey === feat.key ? 'opacity-50' : ''
                                ]"
                            >
                                <p class="font-mono text-xs text-gamboge-300 uppercase tracking-widest">{{ feat.key }}</p>
                                <button
                                    type="button"
                                    @click="addToPlan(feat.key)"
                                    class="shrink-0 text-gamboge-300 hover:text-gamboge-400 text-lg leading-none font-mono"
                                    title="Add to plan"
                                >+</button>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
                            All features have been added to this plan.
                        </p>
                    </div>

                    <!-- Bottom: Plan features (left) + Preview (right) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Left: Plan Features -->
                        <div
                            @dragover.prevent
                            @drop.prevent="onDropToPlan()"
                            :class="[
                                'min-h-24 rounded-lg border-2 border-dashed p-3 space-y-2 transition-colors',
                                isDraggingPool
                                    ? 'border-gamboge-300 bg-gamboge-300/5'
                                    : 'border-gray-200 dark:border-gray-700'
                            ]"
                        >
                            <p class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-2">Plan Features</p>

                            <template v-for="(feat, idx) in includedFeatures" :key="feat.key">
                                <div
                                    v-if="dropIndex === idx && isDraggingPlan"
                                    class="h-0.5 bg-gamboge-300 rounded mx-1"
                                />
                                <div
                                    draggable="true"
                                    @dragstart="onDragStart(feat.key, 'plan')"
                                    @dragend="onDragEnd"
                                    @dragover.prevent="dropIndex = idx"
                                    @drop.stop.prevent="onDropToPlan(idx)"
                                    :class="[
                                        'bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-100 dark:border-gray-700 dark:shadow-neon-cyan-sm space-y-2',
                                        dragKey === feat.key ? 'opacity-50' : ''
                                    ]"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="cursor-grab text-gray-400 dark:text-gray-500 select-none text-lg leading-none">≡</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-mono text-xs text-gamboge-300 uppercase tracking-widest truncate">{{ feat.key }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ featMeta(feat.key)?.description }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeFromPlan(feat.key)"
                                            class="shrink-0 text-gray-400 hover:text-red-500 text-lg leading-none"
                                            title="Remove from plan"
                                        >×</button>
                                    </div>

                                    <!-- Config inputs — shown for features with configurable limits -->
                                    <div
                                        v-if="featMeta(feat.key)?.configSchema?.length"
                                        class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-gray-100 dark:border-gray-700"
                                    >
                                        <div v-for="field in featMeta(feat.key).configSchema" :key="field.key">
                                            <InputLabel :value="field.label" class="text-xs" />
                                            <TextInput
                                                :type="field.type === 'number' ? 'number' : 'text'"
                                                :min="field.min"
                                                v-model="feat.config[field.key]"
                                                class="mt-1 block w-full text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div
                                v-if="!includedFeatures.length"
                                class="text-xs text-gray-400 dark:text-gray-500 text-center py-6"
                            >
                                Drag features here to include them in this plan.
                            </div>
                        </div>

                        <!-- Right: User-facing preview -->
                        <div class="rounded-lg border border-gray-200 dark:border-gamboge-300/20 bg-gray-50 dark:bg-gray-900 p-4 space-y-3">
                            <p class="text-xs uppercase tracking-widest text-gamboge-300 font-mono mb-2">Preview</p>
                            <h5 class="text-lg font-mono font-medium text-gamboge-700 dark:text-gamboge-200">
                                {{ form.name || 'Plan Name' }}
                            </h5>
                            <div class="flex items-baseline text-gray-900 dark:text-white">
                                <span class="text-xl font-semibold">A$</span>
                                <span class="text-3xl font-extrabold tracking-tight">{{ form.price_per_month }}</span>
                                <span class="ms-1 text-sm font-normal text-gray-500 dark:text-gray-400">/month</span>
                            </div>
                            <ul v-if="previewFeatures.length" role="list" class="space-y-3 pl-4">
                                <Feature v-for="feat in previewFeatures" :key="feat.key" :feature="feat" />
                            </ul>
                            <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic">No features added yet.</p>
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
                    <PrimaryButton
                        type="submit"
                        :disabled="processing || !canSubmit"
                        :class="{ 'opacity-25': processing || !canSubmit }"
                    >
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
