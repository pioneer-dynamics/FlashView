<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import Checkbox from '@/Components/Checkbox.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    hasApiAccess: Boolean,
    availablePermissions: Array,
    defaultPermissions: Array,
})

const page = usePage()
const processing = ref(false)
const userCode = ref('')
const installationName = ref('')
const selectedPermissions = ref([...(props.defaultPermissions ?? [])])
const cancelled = ref(false)

const success = computed(() => page.props.flash?.success)
const errors = computed(() => page.props.errors)

function handleInput(event) {
    userCode.value = event.target.value.toUpperCase()
}

function submit() {
    processing.value = true
    cancelled.value = false
    router.post(route('cli.device.activate'), {
        user_code: userCode.value,
        name: installationName.value || null,
        permissions: selectedPermissions.value,
    }, {
        onFinish: () => {
            processing.value = false
        },
    })
}

function cancel() {
    userCode.value = ''
    cancelled.value = true
    processing.value = false
}
</script>

<template>
    <Head title="CLI Device Login" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <!-- No API Access -->
        <div v-if="!hasApiAccess" class="text-center">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                API Access Required
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Your current plan does not include API access.
                Please upgrade to a plan with API support to use the CLI.
            </p>
            <div class="mt-4 flex justify-center">
                <Link :href="route('plans.index')">
                    <PrimaryButton>
                        View Plans
                    </PrimaryButton>
                </Link>
            </div>
        </div>

        <!-- Success state (after PRG redirect) -->
        <div v-else-if="success" class="text-center">
            <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                Authentication Successful
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                You can close this window and return to your terminal.
            </p>
        </div>

        <!-- Cancel confirmation state -->
        <div v-else-if="cancelled" class="text-center">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                No Code Authorized
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                No code was authorized. Your terminal session will time out on its own.
            </p>
        </div>

        <!-- Code entry form -->
        <div v-else>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-center">
                Authorize FlashView CLI
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 text-center">
                Signed in as {{ page.props.auth.user.email }}. Enter the code shown in your terminal to authenticate the CLI.
            </p>

            <div class="mt-4">
                <InputLabel for="user-code" value="Device Code" />
                <TextInput
                    id="user-code"
                    :value="userCode"
                    type="text"
                    class="mt-1 block w-full font-mono tracking-widest text-center text-lg uppercase"
                    placeholder="XXXX-XXXX"
                    autocomplete="off"
                    @input="handleInput"
                />
                <InputError class="mt-2" :message="errors.user_code" />
            </div>

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
                    Leave blank to use the device name sent by the CLI.
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

            <div class="mt-6 flex justify-center">
                <PrimaryButton
                    :disabled="processing || !userCode || selectedPermissions.length === 0"
                    @click="submit"
                >
                    <span v-if="processing">Authorizing...</span>
                    <span v-else>Authorize</span>
                </PrimaryButton>
            </div>

            <div class="mt-4 text-center">
                <button
                    type="button"
                    class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 underline"
                    @click="cancel"
                >
                    I don't recognize this code
                </button>
            </div>
        </div>
    </AuthenticationCard>
</template>
