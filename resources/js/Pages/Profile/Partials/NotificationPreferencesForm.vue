<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import ActionSection from '@/Components/ActionSection.vue';
import FormSection from '@/Components/FormSection.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const planSupportsNotifications = computed(() =>
    page.props.auth.planSupportsEmailNotifications ?? false
);

const form = useForm({
    notify_secret_retrieved: user.value.notify_secret_retrieved ?? false,
});

const updateNotificationPreferences = () => {
    form.put(route('user.notification-preferences.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection v-if="planSupportsNotifications" @submitted="updateNotificationPreferences">
        <template #title>
            Email Notifications
        </template>

        <template #description>
            Manage your email notification preferences.
        </template>

        <template #form>
            <div class="col-span-6">
                <label class="flex items-center">
                    <Checkbox
                        v-model:checked="form.notify_secret_retrieved"
                    />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                        Notify me via email when my secret is retrieved
                    </span>
                </label>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    You will receive an email each time any of your secrets is opened by a recipient.
                </p>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>

    <ActionSection v-else>
        <template #title>
            Email Notifications
        </template>

        <template #description>
            Manage your email notification preferences.
        </template>

        <template #content>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Secret retrieval notifications are available on paid plans.
                <Link :href="route('plans.index')" class="underline text-sm text-gamboge-300 dark:text-gamboge-200 hover:text-gamboge-200 dark:hover:text-gamboge-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gamboge-500 dark:focus:ring-offset-gray-900">
                    View plans
                </Link>
            </p>
        </template>
    </ActionSection>
</template>
