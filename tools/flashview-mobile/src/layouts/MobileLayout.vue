<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()

const tabs = [
    { name: 'create', label: 'Create', icon: '✦' },
    { name: 'secrets', label: 'Secrets', icon: '⊟' },
    { name: 'settings', label: 'Settings', icon: '⚙' },
]
</script>

<template>
    <div class="flex flex-col min-h-screen bg-gray-950">
        <main class="flex-1 overflow-y-auto pb-20">
            <slot />
        </main>

        <nav class="fixed bottom-0 inset-x-0 bg-gray-900 border-t border-gray-800 pb-safe">
            <div class="flex">
                <router-link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :to="{ name: tab.name }"
                    class="flex-1 flex flex-col items-center gap-1 py-3 text-xs transition-colors"
                    :class="route.name === tab.name
                        ? 'text-gamboge-300'
                        : 'text-gray-500 hover:text-gray-300'"
                >
                    <span class="text-lg leading-none">{{ tab.icon }}</span>
                    <span class="uppercase tracking-widest text-[10px]">{{ tab.label }}</span>
                </router-link>
            </div>
        </nav>
    </div>
</template>
