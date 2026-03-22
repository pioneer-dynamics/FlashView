<script setup>
import { ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
    port: Number,
    state: String,
    hasApiAccess: Boolean,
    defaultPermissions: Array,
})

const page = usePage()
const processing = ref(false)

function submit(action) {
    processing.value = true
    router.post(route('cli.authorize.store'), {
        port: props.port,
        state: props.state,
        action: action,
    })
}
</script>

<template>
    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <!-- No API Access — still routes through POST to send error to CLI -->
        <div v-if="!hasApiAccess" class="text-center">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                API Access Required
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Your current plan does not include API access.
                Please upgrade to a plan with API support to use the CLI.
            </p>
            <div class="mt-4 flex justify-center gap-4">
                <SecondaryButton @click="submit('deny')" :disabled="processing">
                    Close
                </SecondaryButton>
                <Link :href="route('plans.index')">
                    <PrimaryButton>
                        View Plans
                    </PrimaryButton>
                </Link>
            </div>
        </div>

        <!-- Authorization Prompt -->
        <div v-else>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-center">
                Authorize FlashView CLI
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 text-center">
                The FlashView CLI is requesting access to create an API token
                for your account ({{ page.props.auth.user.email }}).
            </p>

            <div v-if="defaultPermissions?.length" class="mt-4 rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                    This token will be able to:
                </p>
                <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <li v-for="permission in defaultPermissions" :key="permission" class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ permission }}
                    </li>
                </ul>
            </div>

            <div class="mt-6 flex justify-center gap-4">
                <SecondaryButton @click="submit('deny')" :disabled="processing">
                    Deny
                </SecondaryButton>
                <PrimaryButton @click="submit('approve')" :disabled="processing">
                    <span v-if="processing">Authorizing...</span>
                    <span v-else>Approve</span>
                </PrimaryButton>
            </div>
        </div>
    </AuthenticationCard>
</template>
