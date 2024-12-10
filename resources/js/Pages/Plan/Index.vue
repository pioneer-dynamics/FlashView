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
                    class="w-full max-w-sm p-4 bg-gray-50 border border-gray-200 rounded-lg shadow sm:p-8 dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex flex-wrap gap-2">
                        <h5 class="mb-4 text-xl font-medium text-gray-500 dark:text-gray-400">{{ plan.name }}</h5>
                        <div 
                            class="mb-4 text-xl font-medium text-xs text-red-500 dark:text-red-400" 
                            v-if="userIsSubscribedTo(plan) && $page.props?.auth?.user?.subscription?.ends_at"
                        >
                            Expires on: {{ DateTime.fromISO($page.props?.auth?.user?.subscription?.ends_at).toLocaleString(DateTime.DATEMED) }}
                        </div>
                    </div>
                    <div class="flex items-baseline text-gray-900 dark:text-white">
                        <span class="text-3xl font-semibold">A$</span>
                        <span class="text-5xl font-extrabold tracking-tight">
                            {{ planFrequency == 'monthly' ? plan.price_per_month : plan.price_per_year }}
                        </span>
                        <span class="ms-1 text-xl font-normal text-gray-500 dark:text-gray-400">/
                            <span>{{ planFrequency == 'monthly' ? 'month' : 'year' }}</span>
                        </span>
                    </div>
                    <ul role="list" class="space-y-5 my-7">
                        <Feature v-for="feature in plan.features" :key="feature" :feature="feature" />
                    </ul>
                    <span v-if="isFreePlan(plan)">
                        <Link 
                            v-if="!$page.props.auth.user" 
                            :href="route('register')"
                            class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"
                        >
                            Sign Up
                        </Link>
                    </span>
                    <span v-else> <!-- Not a free plan -->
                        <span v-if="userIsSubscribedTo(plan)">
                            <span class="flex flex-wrap gap-2 justify-center">
                                <Link
                                    v-if="$page.props.auth.user.subscription.ends_at"
                                    method="post"
                                    :href="route('plans.resume')"
                                    class="inline-flex w-full items-center px-4 py-2 bg-green-800 dark:bg-green-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-green-800 uppercase tracking-widest hover:bg-green-700 dark:hover:bg-white focus:bg-green-700 dark:focus:bg-white active:bg-green-900 dark:active:bg-green-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-green-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"
                                >
                                    Resume Plan
                                </Link>
                                <span v-else
                                    class="opacity-25 inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"
                                    :class="{'w-full': $page.props.auth.user.subscription.ends_at}"
                                >
                                    Current Plan
                                </span>
                                <Link
                                    v-if="!$page.props.auth.user.subscription.ends_at"
                                    method="post"
                                    :href="route('plans.unsubscribe')"
                                    :class="{'w-full': $page.props.auth.user.subscription.ends_at}"
                                    class="inline-flex items-center px-4 py-2 bg-red-800 dark:bg-red-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-red-800 uppercase tracking-widest hover:bg-red-700 dark:hover:bg-white focus:bg-red-700 dark:focus:bg-white active:bg-red-900 dark:active:bg-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-red-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"
                                >
                                    Cancel Plan
                                </Link>
                            </span>
                        </span>
                        <span v-else> <!-- User is not subscribed -->
                            <span 
                                v-if="!$page.props.auth.user"
                                class="opacity-25 inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"
                            >
                                Login to Subscribe
                            </span>
                            <a v-else 
                                :href="route('plans.subscribe', { plan: plan.id, period: planFrequency })"
                                class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"
                            >
                                Choose This Plan
                            </a>
                        </span>
                    </span>
                </div>
            </div>
        </Page>
    </AppLayout>
</template>