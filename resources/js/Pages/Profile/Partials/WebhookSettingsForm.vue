<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import ActionSection from '@/Components/ActionSection.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const hasApiAccess = computed(() => page.props.auth?.hasApiAccess ?? false);

const showSecret = ref(false);
const confirmingSecretRegeneration = ref(false);
const secretCopied = ref(false);

const form = useForm({
    webhook_url: user.value.webhook_url ?? '',
});

const updateWebhookSettings = () => {
    form.put(route('user.webhook-settings.update'), {
        preserveScroll: true,
    });
};

const copySecret = () => {
    if (user.value.webhook_secret) {
        navigator.clipboard.writeText(user.value.webhook_secret);
        secretCopied.value = true;
        setTimeout(() => { secretCopied.value = false; }, 2000);
    }
};

const regenerateSecret = () => {
    router.post(route('user.webhook-settings.regenerate-secret'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            confirmingSecretRegeneration.value = false;
            showSecret.value = true;
        },
    });
};
</script>

<template>
    <FormSection v-if="hasApiAccess" @submitted="updateWebhookSettings">
        <template #title>
            Webhook Settings
        </template>

        <template #description>
            Configure a webhook URL to receive HTTP POST notifications when your secrets are retrieved.
        </template>

        <template #form>
            <div class="col-span-6">
                <InputLabel for="webhook_url" value="Webhook URL" />
                <TextInput
                    id="webhook_url"
                    v-model="form.webhook_url"
                    type="url"
                    class="mt-1 block w-full"
                    placeholder="https://example.com/webhook"
                />
                <InputError :message="form.errors.webhook_url" class="mt-2" />
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                    We will send a signed HTTP POST to this URL when your secrets are retrieved. Must be HTTPS.
                </p>
            </div>

            <div v-if="user.webhook_secret" class="col-span-6">
                <InputLabel value="Webhook Secret" />
                <div class="mt-1 flex items-center gap-3">
                    <code class="flex-1 rounded-md border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        {{ showSecret ? user.webhook_secret : '••••••••••••••••••••••••••••••••' }}
                    </code>
                    <SecondaryButton type="button" @click="showSecret = !showSecret">
                        {{ showSecret ? 'Hide' : 'Show' }}
                    </SecondaryButton>
                    <SecondaryButton type="button" @click="copySecret">
                        {{ secretCopied ? 'Copied!' : 'Copy' }}
                    </SecondaryButton>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                    Use this secret to verify webhook signatures via the <code class="text-xs">X-Signature-256</code> header.
                </p>

                <div class="mt-4">
                    <DangerButton type="button" @click="confirmingSecretRegeneration = true">
                        Regenerate Secret
                    </DangerButton>
                </div>
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
            Webhook Settings
        </template>

        <template #description>
            Configure webhook notifications for secret retrieval events.
        </template>

        <template #content>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Webhook notifications are available on the Prime plan.
                <a :href="route('plans.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                    View plans
                </a>
            </p>
        </template>
    </ActionSection>

    <ConfirmationModal :show="confirmingSecretRegeneration" @close="confirmingSecretRegeneration = false">
        <template #title>
            Regenerate Webhook Secret
        </template>

        <template #content>
            Are you sure you want to regenerate your webhook secret? Your existing integration will stop verifying correctly until you update the new secret on your receiving server.
        </template>

        <template #footer>
            <SecondaryButton @click="confirmingSecretRegeneration = false">
                Cancel
            </SecondaryButton>

            <DangerButton class="ms-3" @click="regenerateSecret">
                Regenerate Secret
            </DangerButton>
        </template>
    </ConfirmationModal>
</template>
