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
    port: Number,
    state: String,
    name: String,
    hasApiAccess: Boolean,
    availablePermissions: Array,
    defaultPermissions: Array,
})

const page = usePage()
const processing = ref(false)
const selectedPermissions = ref([...props.defaultPermissions])
const installationName = ref(props.name || '')

function submit(action) {
    processing.value = true
    router.post(route('cli.authorize.store'), {
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
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">
                This will create a new CLI installation. Your existing CLI connections will not be affected.
            </p>

            <div class="mt-4">
                <InputLabel for="installation-name" value="Installation Name" />
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
                    <span v-if="processing">Authorizing...</span>
                    <span v-else>Approve</span>
                </PrimaryButton>
            </div>
        </div>
    </AuthenticationCard>
</template>
