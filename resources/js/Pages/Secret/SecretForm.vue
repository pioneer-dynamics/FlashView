<script setup>
    import SecretCreateForm from './SecretCreateForm.vue';
    import SecretViewForm from './SecretViewForm.vue';

    const props = defineProps({
        secret: { type: String, default: null },
        decryptUrl: { type: String, default: null },
        senderCompanyName: { type: String, default: null },
        senderDomain: { type: String, default: null },
        senderEmail: { type: String, default: null },
        isFileSecret: { type: Boolean, default: false },
        hasMessage: { type: Boolean, default: false },
        fileSize: { type: Number, default: null },
        fileMimeType: { type: String, default: null },
        fileDownloadUrl: { type: String, default: null },
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
