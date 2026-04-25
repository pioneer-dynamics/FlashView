<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Page from '@/Pages/Page.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Array,
});

const page = usePage();
const currentUserId = page.props?.auth?.user?.id;

const userBeingSuspended = ref(null);
const suspendForm = useForm({});
const unsuspendForm = useForm({});

const confirmSuspend = (user) => {
    userBeingSuspended.value = user;
};

const suspendUser = () => {
    suspendForm.post(route('admin.users.suspend', userBeingSuspended.value.id), {
        preserveScroll: true,
        onSuccess: () => { userBeingSuspended.value = null; },
        onError: () => { userBeingSuspended.value = null; },
    });
};

const unsuspendUser = (user) => {
    unsuspendForm.delete(route('admin.users.unsuspend', user.id), {
        preserveScroll: true,
    });
};

const subscriptionStatusClass = (status) => {
    if (status === 'active') {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
    }
    if (status === 'canceled' || status === 'past_due' || status === 'unpaid') {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
    }
    if (status === 'trialing') {
        return 'bg-gamboge-100 text-gamboge-800 dark:bg-gamboge-900/30 dark:text-gamboge-200';
    }
    return null;
};
</script>

<template>
    <AdminLayout title="Admin — Users">
        <template #title>Users</template>

        <Page>
            <div class="mb-6">
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300 font-mono">User Management</h1>
            </div>

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg dark:shadow-neon-cyan-sm">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gamboge-300 tracking-widest">
                        <tr>
                            <th scope="col" class="px-6 py-3">Name</th>
                            <th scope="col" class="px-6 py-3">Email</th>
                            <th scope="col" class="px-6 py-3">Plan</th>
                            <th scope="col" class="px-6 py-3">Subscription Status</th>
                            <th scope="col" class="px-6 py-3">Joined</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="users.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500">
                                No users found.
                            </td>
                        </tr>
                        <tr v-for="user in users" :key="user.id"
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700">
                            <th scope="row" class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                {{ user.name }}
                            </th>
                            <td class="px-6 py-4 font-mono text-xs dark:text-gray-300">
                                {{ user.email }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs dark:text-gray-300">
                                {{ user.plan_name }}
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="subscriptionStatusClass(user.subscription_status)"
                                    :class="subscriptionStatusClass(user.subscription_status)"
                                    class="px-2 py-0.5 rounded text-xs font-mono">
                                    {{ user.subscription_status }}
                                </span>
                                <span v-else class="text-gray-400 dark:text-gray-600">
                                    {{ user.subscription_status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs dark:text-gray-300">
                                {{ user.joined_at }}
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="user.is_suspended"
                                    class="px-2 py-0.5 rounded text-xs font-mono bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                    Suspended
                                </span>
                                <span v-else
                                    class="px-2 py-0.5 rounded text-xs font-mono bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                    Active
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div v-if="user.id !== currentUserId">
                                    <DangerButton v-if="!user.is_suspended" class="text-xs"
                                        @click="confirmSuspend(user)">
                                        Suspend
                                    </DangerButton>
                                    <SecondaryButton v-else class="text-xs"
                                        :disabled="unsuspendForm.processing"
                                        @click="unsuspendUser(user)">
                                        Unsuspend
                                    </SecondaryButton>
                                </div>
                                <span v-else class="text-xs text-gray-400 dark:text-gray-600">You</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Page>

        <ConfirmationModal :show="userBeingSuspended !== null" @close="userBeingSuspended = null">
            <template #title>Suspend Account</template>
            <template #content>
                Are you sure you want to suspend <strong>{{ userBeingSuspended?.name }}</strong>?
                They will be logged out immediately and unable to log in until unsuspended.
            </template>
            <template #footer>
                <SecondaryButton @click="userBeingSuspended = null">Cancel</SecondaryButton>
                <DangerButton class="ms-3"
                    :class="{ 'opacity-25': suspendForm.processing }"
                    :disabled="suspendForm.processing"
                    @click="suspendUser">
                    Suspend Account
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
