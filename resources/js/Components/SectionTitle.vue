<template>
    <div class="md:col-span-1 flex justify-between bg-white dark:bg-gray-900 rounded-l p-6">
        <div class="px-4 sm:px-0">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                <slot name="title" />
            </h3>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                <slot name="description" />
            </p>
        </div>

        <div class="px-4 sm:px-0">
            <slot name="aside" />
        </div>
    </div>
</template>
