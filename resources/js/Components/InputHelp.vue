<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div>
        <p class="text-sm text-gray-400 dark:text-gray-500 italic">
            <slot>{{ message }}</slot>
        </p>
    </div>
</template>
