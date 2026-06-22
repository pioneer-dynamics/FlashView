<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    checked?: boolean | unknown[];
    value?: string | null;
}

const emit = defineEmits<{
    'update:checked': [value: boolean | unknown[]];
}>();

const props = withDefaults(defineProps<Props>(), {
    checked: false,
    value: null,
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val: boolean | unknown[]) {
        emit('update:checked', val);
    },
});
</script>


<template>
    <input
        v-model="proxyChecked"
        type="checkbox"
        :value="value"
        class="cyber-toggle"
    >
</template>
