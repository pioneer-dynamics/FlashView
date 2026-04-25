<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import Faq from '../Partials/Faq.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DialogModal from '@/Components/DialogModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { DateTime } from 'luxon';
import ToggleButton from '@/Components/ToggleButton.vue';
import Feature from './Partials/Feature.vue';
import Alert from '@/Components/Alert.vue';

const props = defineProps({
    plans: Array,
})

const page = usePage();
const planFrequency = ref(page.props.auth?.user?.frequency || 'monthly');

const userIsSubscribedTo = (plan) => {
    let user = page.props.auth?.user;

    if(user?.plan?.id == plan.id) // plan matches
    {
        if(planFrequency.value == 'monthly')
            return user?.subscription?.stripe_price == plan.stripe_monthly_price_id;
        else
            return user?.subscription?.stripe_price == plan.stripe_yearly_price_id;
    }

    return false;
}

const isFreePlan = (plan) => plan.price_per_month == 0

const userHasActiveSubscription = computed(() => page.props.auth?.user?.subscription != null);

const showCancellationModal = ref(false);
const cancelForm = useForm({});

const confirmCancellation = () => {
    showCancellationModal.value = true;
};

const submitCancellation = () => {
    cancelForm.post(route('plans.unsubscribe'), {
        onSuccess: () => { showCancellationModal.value = false; },
    });
};

const planBeingSwitchedTo = ref(null);

const confirmPlanSwitch = (plan) => {
    planBeingSwitchedTo.value = plan;
};

const proceedWithSwitch = () => {
    router.visit(route('plans.subscribe', {
        plan: planBeingSwitchedTo.value.id,
        period: planFrequency.value,
    }));
};

</script>
<template>
    <AppLayout title="Pricing">
        <Page>
            <ToggleButton class="justify-center" :options="[{ label: 'Monthly', value: 'monthly' }, { label: 'Yearly', value: 'yearly' }]" v-model="planFrequency"/>
            <div class="flex flex-col md:flex-row gap-4 justify-center p-4">
                <div v-for="plan in plans.data" :key="plan.id"
                    class="w-full max-w-sm p-4 bg-gray-50 border border-gray-200 rounded-lg shadow sm:p-8 dark:bg-gray-800 flex flex-col transition-colors duration-200"
                    :class="userIsSubscribedTo(plan) ? 'dark:border-gamboge-300/60' : 'dark:border-gamboge-300/20'">
                    <div class="flex flex-wrap gap-2">
                        <h5 class="mb-4 text-xl font-mono font-medium text-gamboge-700 dark:text-gamboge-200">
                            {{ plan.name }} {{ planFrequency }}
                            <span v-if="planFrequency == 'yearly' && plan.price_per_month > 0" class="ml-2 bg-gamboge-100 text-gamboge-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded-full dark:bg-gamboge-900/30 dark:text-gamboge-300">
                                Save {{(( (( plan.price_per_month * 12 ) - plan.price_per_year) / ( plan.price_per_month * 12 )) * 100).toFixed(2) }}%
                            </span>
                        </h5>
                        <span v-if="userIsSubscribedTo(plan) && !$page.props?.auth?.user?.subscription?.ends_at" class="mb-4 font-mono text-xs uppercase tracking-widest text-gamboge-300 border border-gamboge-300/40 px-2 py-0.5 rounded self-start">
                            Current Plan
                        </span>
                        <div
                            class="mb-4 text-xl font-medium text-xs text-red-500 dark:text-red-400"
                            v-if="userIsSubscribedTo(plan) && $page.props?.auth?.user?.subscription?.ends_at"
                        >
                            Expires on: {{ DateTime.fromISO($page.props?.auth?.user?.subscription?.ends_at).toLocaleString(DateTime.DATEMED) }}
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <div class="flex items-baseline text-gray-900 dark:text-white">
                            <span class="text-3xl font-semibold">A$</span>
                            <span class="text-5xl font-extrabold tracking-tight">
                                {{ planFrequency == 'monthly' ? plan.price_per_month : plan.price_per_year }}
                            </span>
                            <span class="ms-1 text-xl font-normal text-gray-700 dark:text-gray-400">/
                                <span>{{ planFrequency == 'monthly' ? 'month' : 'year' }}</span>
                            </span>
                        </div>
                        <div class="line-through decoration-gray-500 flex items-baseline text-gray-400 dark:text-gray-200" v-if="planFrequency =='yearly'">
                            <span class="text-3xl font-semibold">A$</span>
                            <span class="text-3xl font-extrabold tracking-tight">
                                {{ plan.price_per_month * 12 }}
                            </span>
                        </div>
                    </div>
                    <ul role="list" class="space-y-5 my-7">
                        <Feature v-for="feature in plan.features" :key="feature" :feature="feature" />
                    </ul>
                    <span v-if="isFreePlan(plan)" class="mt-auto">
                        <PrimaryButton
                            v-if="!$page.props.auth.user"
                            :href="route('register')"
                            class="w-full justify-center"
                        >
                            Sign Up
                        </PrimaryButton>
                    </span>
                    <span v-else class="mt-auto"> <!-- Not a free plan -->
                        <span v-if="userIsSubscribedTo(plan)">
                            <span class="flex flex-col gap-2">
                                <Link
                                    v-if="$page.props.auth.user.subscription.ends_at"
                                    method="post"
                                    :href="route('plans.resume')"
                                    class="inline-flex w-full items-center justify-center px-4 py-2 bg-green-800 dark:bg-transparent border border-transparent dark:border-green-400/60 rounded-md font-semibold text-xs text-white dark:text-green-400 uppercase tracking-widest hover:bg-green-700 dark:hover:bg-green-400/10 dark:hover:border-green-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150"
                                >
                                    Resume Plan
                                </Link>
                                <button
                                    v-if="!$page.props.auth.user.subscription.ends_at"
                                    type="button"
                                    @click="confirmCancellation"
                                    class="inline-flex w-full items-center justify-center px-4 py-2 bg-transparent border border-red-400/60 rounded-md font-semibold text-xs text-red-400 uppercase tracking-widest hover:bg-red-400/10 hover:border-red-400 dark:hover:shadow-[0_0_8px_rgba(248,113,113,0.3)] focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150"
                                >
                                    Cancel Plan
                                </button>
                            </span>
                        </span>
                        <span v-else>
                            <PrimaryButton
                                v-if="!$page.props.auth.user"
                                type="button"
                                disabled
                                class="opacity-25 w-full justify-center cursor-not-allowed"
                            >
                                Login to Subscribe
                            </PrimaryButton>
                            <template v-else>
                                <PrimaryButton
                                    v-if="userHasActiveSubscription"
                                    type="button"
                                    class="w-full justify-center"
                                    @click="confirmPlanSwitch(plan)"
                                >
                                    Choose This Plan
                                </PrimaryButton>
                                <PrimaryButton
                                    v-else
                                    :href="route('plans.subscribe', { plan: plan.id, period: planFrequency })"
                                    class="w-full justify-center"
                                >
                                    Choose This Plan
                                </PrimaryButton>
                            </template>
                        </span>
                    </span>
                </div>
            </div>

            <ConfirmationModal :show="showCancellationModal" @close="showCancellationModal = false">
                <template #title>Cancel Your Plan</template>
                <template #content>
                    Are you sure you want to cancel your plan? You will retain access to paid features
                    until the end of your current billing period, after which your account will revert
                    to the free plan.
                </template>
                <template #footer>
                    <SecondaryButton @click="showCancellationModal = false">Keep Plan</SecondaryButton>
                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': cancelForm.processing }"
                        :disabled="cancelForm.processing"
                        @click="submitCancellation"
                    >
                        Yes, Cancel Plan
                    </DangerButton>
                </template>
            </ConfirmationModal>

            <DialogModal :show="planBeingSwitchedTo !== null" @close="planBeingSwitchedTo = null">
                <template #title>Switch Plan</template>
                <template #content>
                    You are currently on
                    <strong>{{ $page.props.auth.user?.plan?.name }}</strong>.
                    Switching to
                    <strong>{{ planBeingSwitchedTo?.name }}</strong>
                    will take effect immediately. Any difference in cost will be pro-rated and applied to your next invoice.
                </template>
                <template #footer>
                    <SecondaryButton @click="planBeingSwitchedTo = null">Keep Current Plan</SecondaryButton>
                    <PrimaryButton class="ms-3" @click="proceedWithSwitch">
                        Switch Plan
                    </PrimaryButton>
                </template>
            </DialogModal>
        </Page>
    </AppLayout>
</template>
