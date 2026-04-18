<template>
    <div class="md:col-span-1 flex justify-between bg-white dark:bg-gray-800 sm:rounded-l-lg p-6 dark:border-r dark:border-gamboge-800/30">
        <div class="px-4 sm:px-0">
            <h3 class="font-mono text-sm font-semibold uppercase tracking-widest text-gray-900 dark:text-gamboge-300">
                <slot name="title" />
            </h3>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                <slot name="description" />
            </p>
        </div>

        <div class="px-4 sm:px-0">
            <slot name="aside" />
        </div>
    </div>
</template>
