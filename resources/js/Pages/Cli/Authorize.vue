<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import CliAuthController from '@/actions/App/Http/Controllers/CliAuthController'
import PlanController from '@/actions/App/Http/Controllers/PlanController'
import type { PageProps } from '@/types'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

interface Props {
    port?: number;
    state?: string;
    name?: string;
    hasApiAccess: boolean;
    availablePermissions: string[];
    defaultPermissions: string[];
    existingDeviceName?: string | null;
}

const props = defineProps<Props>()

const page = usePage<PageProps>()
const processing = ref(false)
const selectedPermissions = ref<string[]>([...props.defaultPermissions])
const installationName = ref(props.existingDeviceName || props.name || '')

function submit(action: string): void {
    processing.value = true
    router.post(CliAuthController.authorize.url(), {
        port: props.port,
        state: props.state,
        action: action,
        permissions: selectedPermissions.value,
        name: installationName.value || null,
    })
}
</script>

<template>
    <Head title="CLI Login" />

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
                <Link :href="PlanController.index.url()" prefetch>
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
            <p v-if="existingDeviceName" class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">
                Re-authorising your existing CLI installation. Your token will be refreshed with the selected permissions.
            </p>
            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">
                This will create a new CLI installation. Your existing CLI connections will not be affected.
            </p>

            <div class="mt-4">
                <InputLabel for="installation-name" value="Installation Name" />

                <!-- Existing device: read-only display -->
                <div v-if="existingDeviceName" class="mt-1">
                    <p class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-900 dark:text-gray-100">
                        {{ existingDeviceName }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        This device was previously authorised. To use a different name, remove the existing installation from your
                        <Link :href="route('api-tokens.index')" prefetch class="underline hover:text-gray-700 dark:hover:text-gray-200">API Tokens</Link>
                        page first.
                    </p>
                </div>

                <!-- New device: editable input -->
                <div v-else>
                    <TextInput
                        id="installation-name"
                        v-model="installationName"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g., Work Laptop, CI Server"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Give this device a name so you can identify it later.
                    </p>
                </div>
            </div>

            <div v-if="availablePermissions?.length" class="mt-4 rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                    Token permissions:
                </p>
                <div class="space-y-2">
                    <label
                        v-for="permission in availablePermissions"
                        :key="permission"
                        class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer"
                    >
                        <Checkbox
                            :value="permission"
                            v-model:checked="selectedPermissions"
                        />
                        {{ permission }}
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-4">
                <SecondaryButton @click="submit('deny')" :disabled="processing">
                    Deny
                </SecondaryButton>
                <PrimaryButton
                    @click="submit('approve')"
                    :disabled="processing || selectedPermissions.length === 0"
                >
                    <span v-if="processing">Authorising...</span>
                    <span v-else-if="existingDeviceName">Re-authorise</span>
                    <span v-else>Approve</span>
                </PrimaryButton>
            </div>
        </div>
    </AuthenticationCard>
</template>
