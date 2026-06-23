<script setup lang="ts">
    import SecretCreateForm from './SecretCreateForm.vue';
    import SecretViewForm from './SecretViewForm.vue';

    interface Props {
        secret?: string | null
        decryptUrl?: string | null
        senderCompanyName?: string | null
        senderDomain?: string | null
        senderEmail?: string | null
        isFileSecret?: boolean
        hasMessage?: boolean
        fileSize?: number | null
        fileMimeType?: string | null
        fileDownloadUrl?: string | null
    }

    const props = withDefaults(defineProps<Props>(), {
        secret: null,
        decryptUrl: null,
        senderCompanyName: null,
        senderDomain: null,
        senderEmail: null,
        isFileSecret: false,
        hasMessage: false,
        fileSize: null,
        fileMimeType: null,
        fileDownloadUrl: null,
    });
</script>

<template>
    <SecretCreateForm v-if="!props.secret" />
    <SecretViewForm
        v-else
        :secret="props.secret"
        :decrypt-url="props.decryptUrl"
        :sender-company-name="props.senderCompanyName"
        :sender-domain="props.senderDomain"
        :sender-email="props.senderEmail"
        :is-file-secret="props.isFileSecret"
        :has-message="props.hasMessage"
        :file-size="props.fileSize"
        :file-mime-type="props.fileMimeType"
        :file-download-url="props.fileDownloadUrl"
    />
</template>
