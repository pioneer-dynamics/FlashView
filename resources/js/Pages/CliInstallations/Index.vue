<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ActionSection from '@/Components/ActionSection.vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import DialogModal from '@/Components/DialogModal.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

defineProps({
    installations: Array,
    availablePermissions: Array,
})

const installationBeingRenamed = ref(null)
const installationBeingDeleted = ref(null)

const renameForm = useForm({
    name: '',
})

const deleteForm = useForm({})

const startRename = (installation) => {
    renameForm.name = installation.name
    installationBeingRenamed.value = installation
}

const renameInstallation = () => {
    renameForm.put(route('cli-installations.update', installationBeingRenamed.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (installationBeingRenamed.value = null),
    })
}

const confirmDeletion = (installation) => {
    installationBeingDeleted.value = installation
}

const deleteInstallation = () => {
    deleteForm.delete(route('cli-installations.destroy', installationBeingDeleted.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (installationBeingDeleted.value = null),
    })
}
</script>

<template>
    <AppLayout title="CLI Installations">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                CLI Installations
            </h2>
        </template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <ActionSection>
                    <template #title>
                        Manage CLI Installations
                    </template>

                    <template #description>
                        Manage your FlashView CLI installations across your devices. You can rename or revoke access for individual installations.
                    </template>

                    <template #content>
                        <div v-if="installations.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                            No CLI installations found. Install the
                            <Link :href="route('cli.index')" class="underline font-semibold">FlashView CLI</Link>
                            and run <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">flashview login</code> to get started.
                        </div>

                        <div v-else class="space-y-6">
                            <div v-for="installation in installations" :key="installation.id" class="flex items-center justify-between">
                                <div>
                                    <div class="break-all dark:text-white">
                                        {{ installation.name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <span>Created {{ installation.created_ago }}</span>
                                        <span v-if="installation.last_used_ago" class="ml-3">Last used {{ installation.last_used_ago }}</span>
                                    </div>
                                    <div v-if="installation.abilities.length" class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="ability in installation.abilities"
                                            :key="ability"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            {{ ability }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center ms-2">
                                    <button
                                        class="cursor-pointer text-sm text-gray-400 underline"
                                        @click="startRename(installation)"
                                    >
                                        Rename
                                    </button>

                                    <button
                                        class="cursor-pointer ms-6 text-sm text-red-500"
                                        @click="confirmDeletion(installation)"
                                    >
                                        Revoke
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </ActionSection>
            </div>
        </div>

        <!-- Rename Modal -->
        <DialogModal :show="installationBeingRenamed != null" @close="installationBeingRenamed = null">
            <template #title>
                Rename CLI Installation
            </template>

            <template #content>
                <div>
                    <InputLabel for="rename-name" value="Installation Name" />
                    <TextInput
                        id="rename-name"
                        v-model="renameForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        @keyup.enter="renameInstallation"
                    />
                    <InputError :message="renameForm.errors.name" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="installationBeingRenamed = null">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ms-3"
                    :class="{ 'opacity-25': renameForm.processing }"
                    :disabled="renameForm.processing"
                    @click="renameInstallation"
                >
                    Save
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal :show="installationBeingDeleted != null" @close="installationBeingDeleted = null">
            <template #title>
                Revoke CLI Installation
            </template>

            <template #content>
                Are you sure you would like to revoke access for this CLI installation? The device will no longer be able to access your account.
            </template>

            <template #footer>
                <SecondaryButton @click="installationBeingDeleted = null">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deleteInstallation"
                >
                    Revoke
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
