<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { initAuth, useAuth } from '@/composables/useAuth'
import { initShareIntent, useShareIntent } from '@/composables/useShareIntent'

const router = useRouter()
const { isAuthenticated } = useAuth()
const { sharedText } = useShareIntent()

onMounted(async () => {
    await Promise.all([initAuth(), initShareIntent()])

    // Cold start: shared text was stored before JS loaded.
    if (sharedText.value) {
        if (isAuthenticated.value) {
            router.push({ name: 'create' })
        } else {
            router.push({ name: 'login' })
        }
    }

    // Warm start: share arrives while app is already open.
    window.addEventListener('shareIntentReceived', () => {
        if (isAuthenticated.value) {
            router.push({ name: 'create' })
        }
    })
})
</script>

<template>
    <router-view />
</template>
