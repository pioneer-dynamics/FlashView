<script setup lang="ts">
import Modal from './Modal.vue';

const emit = defineEmits<{
    close: [];
}>();

interface Props {
    show?: boolean;
    maxWidth?: string;
    closeable?: boolean;
}

withDefaults(defineProps<Props>(), {
    show: false,
    maxWidth: '2xl',
    closeable: true,
});

const close = (): void => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        @close="close"
    >
        <div class="px-6 py-4">
            <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
                <slot name="title" />
            </div>

            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                <slot name="content" />
            </div>
        </div>

        <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-800 text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
