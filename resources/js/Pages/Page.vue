<template>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div v-if="$slots.standard" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <slot name="standard"/>
            </div>
            
            <slot/>
        </div>
    </div>
</template>