<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div>
        <p class="text-sm text-gray-600 dark:text-gray-400 italic">
            <slot>{{ message }}</slot>
        </p>
    </div>
</template>
