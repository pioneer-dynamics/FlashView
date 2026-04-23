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

    if (sharedText.value) {
        if (isAuthenticated.value) {
            router.push({ name: 'create' })
        } else {
            router.push({ name: 'login' })
        }
    }
})
</script>

<template>
    <router-view />
</template>
