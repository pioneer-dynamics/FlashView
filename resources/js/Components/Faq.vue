<script setup lang="ts">
    interface Props {
        question?: string;
        message?: string;
    }

    defineProps<Props>();
</script>
<template>
    <div class="my-4">
        <h6 class="text-lg font-bold dark:text-white">{{ question }}</h6>
        <span><slot/></span>
    </div>
</template>