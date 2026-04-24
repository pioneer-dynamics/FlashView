<script setup>
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
    port: {
        type: Number,
        default: null,
    },
    state: String,
    name: String,
    redirectUri: {
        type: String,
        default: null,
    },
    clientType: {
        type: String,
        default: 'cli',
    },
    hasApiAccess: Boolean,
    availablePermissions: Array,
    defaultPermissions: Array,
    existingDeviceName: {
        type: String,
        default: null,
    },
})

const page = usePage()
const processing = ref(false)
const selectedPermissions = ref([...props.defaultPermissions])
const installationName = ref(props.existingDeviceName || props.name || '')

function submit(action) {
    processing.value = true
    router.post(route('cli.authorize.store'), {
        port: props.port,
        state: props.state,
        redirect_uri: props.redirectUri,
        client_type: props.clientType,
        action: action,
        permissions: selectedPermissions.value,
        name: installationName.value || null,
    })
}
</script>

<template>
    <Head :title="clientType === 'mobile' ? 'Mobile Login' : 'CLI Login'" />

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
                Authorize {{ clientType === 'mobile' ? 'FlashView Mobile' : 'FlashView CLI' }}
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 text-center">
                {{ clientType === 'mobile' ? 'The FlashView mobile app' : 'The FlashView CLI' }} is requesting access to create an API token
                for your account ({{ page.props.auth.user.email }}).
            </p>
            <p v-if="existingDeviceName" class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">
                Re-authorizing your existing {{ clientType === 'mobile' ? 'mobile' : 'CLI' }} installation. Your token will be refreshed with the selected permissions.
            </p>
            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">
                This will create a new {{ clientType === 'mobile' ? 'mobile' : 'CLI' }} installation. Your existing connections will not be affected.
            </p>

            <div class="mt-4">
                <InputLabel for="installation-name" value="Installation Name" />

                <!-- Existing device: read-only display -->
                <div v-if="existingDeviceName" class="mt-1">
                    <p class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-900 dark:text-gray-100">
                        {{ existingDeviceName }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        This device was previously authorized. To use a different name, remove the existing installation from your
                        <Link :href="route('api-tokens.index')" class="underline hover:text-gray-700 dark:hover:text-gray-200">API Tokens</Link>
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
                <p class="text-xs font-medium text-gamboge-300 uppercase tracking-widest mb-2">
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
                    <span v-if="processing">Authorizing...</span>
                    <span v-else-if="existingDeviceName">Re-authorize</span>
                    <span v-else>Approve</span>
                </PrimaryButton>
            </div>
        </div>
    </AuthenticationCard>
</template>
