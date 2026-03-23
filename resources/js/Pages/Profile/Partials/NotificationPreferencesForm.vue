<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const planSupportsNotifications = computed(() =>
    user.value.plan?.settings?.notification?.notifications ?? false
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
    <FormSection @submitted="updateNotificationPreferences">
        <template #title>
            Notification Preferences
        </template>

        <template #description>
            Manage your email notification preferences.
        </template>

        <template #form>
            <div v-if="planSupportsNotifications" class="col-span-6">
                <label class="flex items-center">
                    <Checkbox
                        v-model:checked="form.notify_secret_retrieved"
                    />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                        Notify me via email when my secret is retrieved
                    </span>
                </label>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                    You will receive an email each time any of your secrets is opened by a recipient.
                </p>
            </div>

            <div v-else class="col-span-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Secret retrieval notifications are available on paid plans.
                    <a :href="route('plans.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                        View plans
                    </a>
                </p>
            </div>
        </template>

        <template v-if="planSupportsNotifications" #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
