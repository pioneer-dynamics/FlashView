<script setup>
    defineProps({
        question: String,
        message: String,
    })
</script>
<template>
    <div class="my-4">
        <h6 class="text-lg font-bold dark:text-white">{{ question }}</h6>
        <span><slot/></span>
    </div>
</template>