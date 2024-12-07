<script setup>
    const props = defineProps({
        type: {
            type: String,
            default: 'info'
        },
        message: {
            type: String,
            default: ''
        },
        hideTitle: {
            type: Boolean,
            default: false
        }
    })

    const classes = {
        'flex items-center p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400': props.type == 'Success',
        'flex items-center p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400': props.type == 'Error',
        'flex items-center p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300': props.type == 'Warning',
        'flex items-center p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400': props.type == 'Info',
    }
</script>
<template>
    <div :class="classes" role="alert" class="relative">
        <svg class="absolute top-5 flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
        </svg>
        <span class="sr-only">{{props.type}}</span>
        <div>
            <span v-if="!hideTitle" class="font-medium">{{props.type}}</span> <div class="pl-6"><slot>{{ props.message }}</slot></div>
        </div>
    </div>
</template>