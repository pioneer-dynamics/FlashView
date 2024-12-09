<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Page from '../Page.vue';
import Faq from '../Partials/Faq.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { DateTime } from 'luxon';

const props = defineProps({
    plans: Array,
})

const isMonthly = ref(usePage().props.auth?.user?.frequency == 'monthly' ? true : false);

</script>
<template>
    <AppLayout title="Pricing">
        <Page>
            <div class="flex flex-row justify-center mb-4">
                <div class="flex flex-row gap-0 justify-center rounded-md bg-white">
                    <button @click="() => isMonthly = true" :class="{ 'bg-gamboge-800 dark:bg-gamboge-200': isMonthly }"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest focus:outline-none disabled:opacity-50 transition ease-in-out duration-150 justify-center p-2 ">Monthly</button>
                    <button @click="() => isMonthly = false" :class="{ 'bg-gamboge-800 dark:bg-gamboge-200': !isMonthly }"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest focus:outline-none disabled:opacity-50 transition ease-in-out duration-150 justify-center p-2">Yearly</button>
                </div>
            </div>
            <div class="flex flex-row gap-4 justify-center">
                <div v-for="plan in plans" :key="plan.id"
                    class="w-full max-w-sm p-4 bg-white border border-gray-200 rounded-lg shadow sm:p-8 dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex flex-wrap gap-2">
                        <h5 class="mb-4 text-xl font-medium text-gray-500 dark:text-gray-400">{{ plan.name }}</h5>
                        <div class="mb-4 text-xl font-medium text-xs text-red-500 dark:text-red-400" v-if="$page.props?.auth?.user?.subscription?.ends_at && (($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly))">Expires on: {{ DateTime.fromISO($page.props?.auth?.user?.subscription?.ends_at).toLocaleString(DateTime.DATEMED) }}</div>
                    </div>
                    <div class="flex items-baseline text-gray-900 dark:text-white">
                        <span class="text-3xl font-semibold">A$</span>
                        <span class="text-5xl font-extrabold tracking-tight">
                            <span v-if="isMonthly">{{ plan.price_per_month }}</span>
                            <span v-else>{{ plan.price_per_year }}</span>
                        </span>
                        <span class="ms-1 text-xl font-normal text-gray-500 dark:text-gray-400">/
                            <span>{{ isMonthly ? 'month' : 'year' }}</span>
                        </span>
                    </div>
                    <ul role="list" class="space-y-5 my-7">
                        <li class="flex items-center" v-for="feature in plan.features.has" :key="feature">
                            <svg class="flex-shrink-0 w-4 h-4 text-gamboge-700 dark:text-gamboge-200" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                            </svg>
                            <span class="text-base font-normal leading-tight text-gray-500 dark:text-gray-400 ms-3">{{
                                feature }}</span>
                        </li>
                        <li class="flex line-through decoration-gray-500" v-for="feature in plan.features.does_not_have"
                            :key="feature">
                            <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                            </svg>
                            <span class="flex flex-wrap">
                                <span class="text-base font-normal leading-tight text-gray-500 ms-3">{{ feature
                                    }}</span>
                            </span>
                        </li>
                    </ul>
                    <span v-if="plan.price_per_month == 0">
                        <Link v-if="!$page.props.auth.user" :href="route('register')"
                            class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"
                            :disabled="$page.props.auth.user">Sign Up</Link>
                    </span>
                    <span v-else>
                        <span v-if="($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)"
                            :class="{'opacity-25': ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)}"
                            class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"
                            :disabled="!$page.props.auth.user || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)">
                            <span v-if="!$page.props.auth.user">Login to Subscribe</span>
                            <span v-else-if="($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)">Current Plan</span>
                            <span v-else>Choose This Plan</span>
                    </span>
                        <a v-else :href="route('plans.subscribe', { plan: plan.id, period: isMonthly ? 'monthly' : 'yearly' })"
                            :class="{'opacity-25': ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)}"
                            class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"
                            :disabled="!$page.props.auth.user || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)">
                            <span v-if="!$page.props.auth.user">Login to Subscribe</span>
                            <span v-else-if="($page.props.auth.user?.subscription?.stripe_price == plan.stripe_monthly_price_id && isMonthly) || ($page.props.auth.user?.subscription?.stripe_price == plan.stripe_yearly_price_id && !isMonthly)">Current Plan</span>
                            <span v-else>Choose This Plan</span>
                        </a>
                    </span>
                </div>
            </div>
        </Page>
    </AppLayout>
</template>