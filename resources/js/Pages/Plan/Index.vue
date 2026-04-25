<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import Faq from '../Partials/Faq.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { DateTime } from 'luxon';
import ToggleButton from '@/Components/ToggleButton.vue';
import Feature from './Partials/Feature.vue';
import Alert from '@/Components/Alert.vue';

const props = defineProps({
    plans: Array,
})

const planFrequency = ref(usePage().props.auth?.user?.frequency || 'monthly');

const userIsSubscribedTo = (plan) => {
    let user = usePage().props.auth?.user;

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

</script>
<template>
    <AppLayout title="Pricing">
        <Page>
            <ToggleButton class="justify-center" :options="[{ label: 'Monthly', value: 'monthly' }, { label: 'Yearly', value: 'yearly' }]" v-model="planFrequency"/>
            <div class="flex flex-col md:flex-row gap-4 justify-center p-4">
                <div v-for="plan in plans.data" :key="plan.id"
                    class="w-full max-w-sm p-4 bg-gray-50 border border-gray-200 rounded-lg shadow sm:p-8 dark:bg-gray-800 dark:border-gamboge-300/20 flex flex-col">
                    <div class="flex flex-wrap gap-2">
                        <h5 class="mb-4 text-xl font-mono font-medium text-gamboge-700 dark:text-gamboge-200">
                            {{ plan.name }} {{ planFrequency }}
                            <span v-if="planFrequency == 'yearly' && plan.price_per_month > 0" class="ml-2 bg-gamboge-100 text-gamboge-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded-full dark:bg-gamboge-900/30 dark:text-gamboge-300">
                                Save {{(( (( plan.price_per_month * 12 ) - plan.price_per_year) / ( plan.price_per_month * 12 )) * 100).toFixed(2) }}%
                            </span>
                        </h5>
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
                            <span class="flex flex-wrap gap-2 justify-center">
                                <Link
                                    v-if="$page.props.auth.user.subscription.ends_at"
                                    method="post"
                                    :href="route('plans.resume')"
                                    class="inline-flex w-full items-center px-4 py-2 bg-green-800 dark:bg-green-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-green-800 uppercase tracking-widest hover:bg-green-700 dark:hover:bg-white dark:hover:shadow-neon-cyan-sm focus:bg-green-700 dark:focus:bg-white active:bg-green-900 dark:active:bg-green-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-green-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"
                                >
                                    Resume Plan
                                </Link>
                                <PrimaryButton
                                    v-else
                                    type="button"
                                    disabled
                                    class="opacity-25 w-full justify-center cursor-not-allowed"
                                >
                                    Current Plan
                                </PrimaryButton>
                                <Link
                                    v-if="!$page.props.auth.user.subscription.ends_at"
                                    method="post"
                                    :href="route('plans.unsubscribe')"
                                    class="inline-flex items-center px-4 py-2 bg-red-800 dark:bg-red-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-red-800 uppercase tracking-widest hover:bg-red-700 dark:hover:bg-white dark:hover:shadow-neon-cyan-sm focus:bg-red-700 dark:focus:bg-white active:bg-red-900 dark:active:bg-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-red-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"
                                >
                                    Cancel Plan
                                </Link>
                            </span>
                        </span>
                        <span v-else> <!-- User is not subscribed -->
                            <PrimaryButton
                                v-if="!$page.props.auth.user"
                                type="button"
                                disabled
                                class="opacity-25 w-full justify-center cursor-not-allowed"
                            >
                                Login to Subscribe
                            </PrimaryButton>
                            <PrimaryButton
                                v-else
                                :href="route('plans.subscribe', { plan: plan.id, period: planFrequency })"
                                class="w-full justify-center"
                            >
                                Choose This Plan
                            </PrimaryButton>
                        </span>
                    </span>
                </div>
            </div>
        </Page>
    </AppLayout>
</template>