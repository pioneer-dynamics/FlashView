<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="md:grid md:grid-cols-3">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <form @submit.prevent="$emit('submitted')">
                <div class="cyber-corners" :class="hasActions ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md'">
                    <div
                        class="px-4 py-5 bg-white dark:bg-gray-800 dark:border dark:border-gamboge-800/30 sm:p-6 shadow dark:shadow-none overflow-hidden"
                        :class="hasActions ? 'dark:border-b-0 sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md'"
                    >
                        <div class="grid grid-cols-6 gap-6">
                            <slot name="form" />
                        </div>
                    </div>

                    <div v-if="hasActions" class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-800 dark:border dark:border-gamboge-800/30 dark:border-t-gamboge-800/20 text-end sm:px-6 shadow dark:shadow-none sm:rounded-bl-md sm:rounded-br-md">
                        <slot name="actions" />
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
