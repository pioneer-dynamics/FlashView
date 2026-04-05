<script setup>
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import Checkbox from '@/Components/Checkbox.vue';
import ConfirmsPasswordOrPasskey from '@/Components/ConfirmsPasswordOrPasskey.vue';
import FormSection from '@/Components/FormSection.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    storeMaskedRecipientEmail: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    store_masked_recipient_email: props.storeMaskedRecipientEmail,
});

const saveConfiguration = () => {
    form.put(route('user.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection>
        <template #title>
            Email Privacy
        </template>

        <template #description>
            Control how recipient email addresses are handled when creating secrets.
        </template>

        <template #form>
            <div class="col-span-6">
                <label class="flex items-center">
                    <Checkbox v-model:checked="form.store_masked_recipient_email" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                        Store masked recipient email address
                    </span>
                </label>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                    When enabled, a masked version of the recipient's email (e.g. <code>j***@e***.com</code>) will be stored alongside the secret so you can identify who it was sent to. The original email address is never stored.
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                    Turning this off only affects new secrets — masked emails on existing secrets are not removed.
                </p>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <ConfirmsPasswordOrPasskey @confirmed="saveConfiguration">
                <PrimaryButton type="button" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Save
                </PrimaryButton>
            </ConfirmsPasswordOrPasskey>
        </template>
    </FormSection>
</template>
