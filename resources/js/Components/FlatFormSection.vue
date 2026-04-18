<script setup>
import { computed, useSlots } from 'vue';

defineEmits(['submitted']);

const slots = useSlots();
const hasActions = computed(() => !! slots.actions);
const hasTitle = computed(() => !! slots.title);
</script>

<template>
    <div class="mt-5 md:mt-0 md:col-span-2">
        <form @submit.prevent="$emit('submitted')">
            <div class="cyber-corners" :class="hasActions ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md'">
                <div
                    class="bg-gray-50 dark:bg-gray-800 dark:border dark:border-gamboge-800/30 overflow-hidden"
                    :class="[hasActions ? 'dark:border-b-0 sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md']"
                >
                    <div v-if="hasTitle" class="px-4 py-2 sm:px-6 bg-gamboge-900/60 dark:bg-gamboge-900/40 border-b border-gamboge-800/40 flex items-center">
                        <span class="font-mono text-sm font-semibold text-gamboge-300 dark:text-gamboge-300 uppercase tracking-widest">
                            <slot name="title" />
                        </span>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-6 gap-6">
                            <slot name="form" />
                        </div>
                    </div>
                </div>

                <div v-if="hasActions" class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-800 dark:border dark:border-gamboge-800/30 dark:border-t-gamboge-800/20 text-end sm:px-6 shadow dark:shadow-none sm:rounded-bl-md sm:rounded-br-md">
                    <slot name="actions" />
                </div>
            </div>
        </form>
    </div>
</template>
