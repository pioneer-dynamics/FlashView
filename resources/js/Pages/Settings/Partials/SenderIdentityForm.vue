<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ActionMessage from '@/Components/ActionMessage.vue';
import CodeBlock from '@/Components/CodeBlock.vue';
import ConfirmsPasswordOrPasskey from '@/Components/ConfirmsPasswordOrPasskey.vue';
import DangerButton from '@/Components/DangerButton.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    senderIdentity: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const selectedType = ref(props.senderIdentity?.type ?? 'email');


const form = useForm({
    type: props.senderIdentity?.type ?? 'email',
    company_name: props.senderIdentity?.company_name ?? '',
    domain: props.senderIdentity?.domain ?? '',
    include_by_default: props.senderIdentity?.include_by_default ?? false,
});

const verifyForm = useForm({});

const isDomainType = computed(() => selectedType.value === 'domain');

const isVerified = computed(() => props.senderIdentity?.is_verified ?? false);

const hasVerificationToken = computed(() => !!props.senderIdentity?.verification_token);

const verificationToken = computed(() => props.senderIdentity?.verification_token ?? '');

const hasActiveRetry = computed(() => props.senderIdentity?.has_active_retry ?? false);

const verificationStatus = computed(() => {
    if (!props.senderIdentity) {
        return null;
    }
    if (props.senderIdentity.type === 'email') {
        return 'verified';
    }
    if (props.senderIdentity.is_verified) {
        return 'verified';
    }
    return 'pending';
});

const selectType = (type) => {
    selectedType.value = type;
    form.type = type;
};

const save = () => {
    form.post(route('user.sender-identity.store'), {
        preserveScroll: true,
    });
};

const verifyDomain = () => {
    verifyForm.post(route('user.sender-identity.verify'), {
        preserveScroll: true,
    });
};

const removeIdentity = () => {
    router.delete(route('user.sender-identity.destroy'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection @submitted="save">
        <template #title>
            Verified Sender Identity
        </template>

        <template #description>
            Brand your secret links so recipients know who sent them. Choose between domain verification (for businesses) or your verified account email.
        </template>

        <template #form>
            <!-- Type selector -->
            <div class="col-span-6">
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="identity_type"
                            value="email"
                            :checked="selectedType === 'email'"
                            @change="selectType('email')"
                            :disabled="!!senderIdentity"
                            class="text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700 focus:ring-gray-500 disabled:opacity-50"
                        >
                        <span class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': !!senderIdentity }">Use my email</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="identity_type"
                            value="domain"
                            :checked="selectedType === 'domain'"
                            @change="selectType('domain')"
                            :disabled="!!senderIdentity"
                            class="text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700 focus:ring-gray-500 disabled:opacity-50"
                        >
                        <span class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': !!senderIdentity }">Use a domain</span>
                    </label>
                </div>
            </div>

            <!-- Email type -->
            <div v-if="!isDomainType" class="col-span-6 space-y-3">
                <div>
                    <InputLabel value="Email" />
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-medium">{{ page.props.auth.user.email }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                        Your account email is already verified — no extra steps needed. The badge will reflect your email address at the time you save.
                    </p>
                </div>
                <div v-if="senderIdentity?.type === 'email'" class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-sm font-medium text-green-600 dark:text-green-400">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Badge active
                    </span>
                </div>
                <div v-else class="flex items-center gap-2">
                    <span class="text-sm text-red-400 dark:text-red-500">
                        No badge active — click Save to enable your Verified Sender badge.
                    </span>
                </div>
            </div>

            <!-- Domain type -->
            <template v-if="isDomainType">
                <div class="col-span-6">
                    <InputLabel for="company_name" value="Company Name" />
                    <TextInput
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Acme Corp"
                    />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Logo upload coming soon</p>
                    <InputError :message="form.errors.company_name" class="mt-1" />
                </div>

                <div class="col-span-6">
                    <InputLabel for="domain" value="Domain" />
                    <TextInput
                        id="domain"
                        v-model="form.domain"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="example.com"
                    />
                    <InputError :message="form.errors.domain" class="mt-1" />
                </div>

                <!-- Verification status -->
                <div v-if="senderIdentity?.type === 'domain'" class="col-span-6">
                    <div v-if="isVerified" class="space-y-2">
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-green-600 dark:text-green-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Verified
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-500">
                            Your domain is periodically re-checked to ensure the TXT record is still present. Keep it published to maintain your badge.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Active retry in progress -->
                        <div v-if="hasActiveRetry" class="rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3">
                            <p class="text-sm text-blue-700 dark:text-blue-400">
                                We're checking your domain in the background — you'll get an email when it's verified.
                            </p>
                        </div>

                        <!-- Not yet verified or lapsed -->
                        <div v-else class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3">
                            <p class="text-sm text-amber-700 dark:text-amber-400">
                                <template v-if="hasVerificationToken">
                                    Domain not yet verified — add the TXT record below to activate your badge.
                                </template>
                                <template v-else>
                                    Your domain verification has lapsed — the TXT record could not be found during a routine check. Re-verify below to restore your badge.
                                </template>
                            </p>
                        </div>

                        <!-- DNS instructions panel -->
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">How to verify your domain</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                A DNS TXT record is a small piece of text you publish on your domain to prove you own it. Your domain registrar or DNS provider lets you add these records.
                            </p>

                            <div class="space-y-1">
                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Step 1 — Copy your verification token:</p>
                                <CodeBlock :value="verificationToken" />
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Step 2 — Add a TXT record to your domain:</p>
                                <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                                    <li><span class="font-medium">Cloudflare:</span> DNS → Records → Add record → Type: TXT, Name: @, Content: paste the token above</li>
                                    <li><span class="font-medium">GoDaddy:</span> DNS → Add → Type: TXT, Host: @, TXT Value: paste the token above</li>
                                    <li><span class="font-medium">Namecheap:</span> Advanced DNS → Add New Record → Type: TXT, Host: @, Value: paste the token above</li>
                                </ul>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-500">
                                Step 3 — Wait a few minutes for DNS to propagate, then click "Verify Domain" below.
                            </p>

                            <InputError :message="$page.props.errors?.domain" class="mt-1" />

                            <div>
                                <SecondaryButton
                                    type="button"
                                    :disabled="verifyForm.processing"
                                    @click="verifyDomain"
                                >
                                    Verify Domain
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Default inclusion preference -->
            <div class="col-span-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="form.include_by_default"
                        class="rounded border-gray-300 dark:border-gray-600 text-gamboge-600 focus:ring-gamboge-500"
                    />
                    Include my verified sender identity by default in new secret links
                </label>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                    When enabled, the "Include my verified sender identity" checkbox will be pre-checked on the secret link and stego forms.
                </p>
            </div>

            <!-- Snapshot persistence note -->
            <div v-if="senderIdentity" class="col-span-6">
                <p class="text-xs text-gray-500 dark:text-gray-500">
                    Links you've already sent will continue to show your verified badge even if you change your plan or remove this identity.
                </p>
            </div>
        </template>

        <template #actions>
            <div class="flex items-center gap-3 w-full justify-between">
                <div v-if="senderIdentity">
                    <ConfirmsPasswordOrPasskey @confirmed="removeIdentity">
                        <DangerButton type="button">
                            Remove Identity
                        </DangerButton>
                    </ConfirmsPasswordOrPasskey>
                </div>
                <div v-else />

                <div class="flex items-center gap-3">
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </div>
            </div>
        </template>
    </FormSection>
</template>
