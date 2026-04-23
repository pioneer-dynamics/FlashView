<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import MobileLayout from '@/layouts/MobileLayout.vue'
import SecretCard from '@/components/SecretCard.vue'

interface Secret {
    hash_id: string
    expires_at: string | null
    created_at: string
    is_expired: boolean
}

const router = useRouter()
const { getClient, reAuthenticate } = useAuth()

const secrets = ref<Secret[]>([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const error = ref('')
const currentPage = ref(1)
const hasMore = ref(false)

onMounted(() => {
    load()
})

async function load(): Promise<void> {
    isLoading.value = true
    error.value = ''
    secrets.value = []
    currentPage.value = 1

    try {
        const client = await getClient()
        const response = await client.listSecrets(1)
        secrets.value = response.data ?? []
        hasMore.value = !!response.meta?.next_page_url || (response.meta?.current_page < response.meta?.last_page)
    } catch (err: unknown) {
        const apiErr = err as { status?: number; message?: string }
        if (apiErr.status === 401) {
            await reAuthenticate()
        } else {
            error.value = apiErr.message || 'Failed to load secrets.'
        }
    } finally {
        isLoading.value = false
    }
}

async function loadMore(): Promise<void> {
    if (isLoadingMore.value || !hasMore.value) {
        return
    }

    isLoadingMore.value = true

    try {
        const client = await getClient()
        const nextPage = currentPage.value + 1
        const response = await client.listSecrets(nextPage)
        secrets.value.push(...(response.data ?? []))
        currentPage.value = nextPage
        hasMore.value = !!response.meta?.next_page_url || (response.meta?.current_page < response.meta?.last_page)
    } catch (err: unknown) {
        const apiErr = err as { status?: number; message?: string }
        if (apiErr.status === 401) {
            await reAuthenticate()
        }
    } finally {
        isLoadingMore.value = false
    }
}

async function burn(hashId: string): Promise<void> {
    try {
        const client = await getClient()
        await client.burnSecret(hashId)
        secrets.value = secrets.value.filter((s) => s.hash_id !== hashId)
    } catch (err: unknown) {
        const apiErr = err as { status?: number; message?: string }
        if (apiErr.status === 401) {
            await reAuthenticate()
        } else {
            error.value = apiErr.message || 'Failed to burn secret.'
        }
    }
}

function openRetrieve(hashId: string): void {
    router.push({ name: 'retrieve', params: { id: hashId } })
}
</script>

<template>
    <MobileLayout>
        <div class="px-4 pt-6 pb-4">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xs uppercase tracking-widest text-gamboge-300">My Secrets</h1>
                <button
                    @click="load"
                    :disabled="isLoading"
                    class="text-xs text-gray-400 hover:text-gray-200 transition-colors disabled:opacity-40"
                >
                    Refresh
                </button>
            </div>

            <div v-if="isLoading" class="flex flex-col gap-3">
                <div
                    v-for="n in 4"
                    :key="n"
                    class="h-14 rounded-xl bg-gray-800 animate-pulse"
                />
            </div>

            <p v-else-if="error" class="text-sm text-red-400 rounded-xl bg-red-950/30 border border-red-800/50 px-3 py-2">
                {{ error }}
            </p>

            <div v-else-if="secrets.length === 0" class="text-center py-16">
                <p class="text-gray-500 text-sm">No secrets yet.</p>
                <button
                    @click="router.push({ name: 'create' })"
                    class="mt-4 text-gamboge-300 text-xs uppercase tracking-widest hover:text-gamboge-200 transition-colors"
                >
                    Create one
                </button>
            </div>

            <div v-else class="flex flex-col gap-3">
                <SecretCard
                    v-for="secret in secrets"
                    :key="secret.hash_id"
                    :secret="secret"
                    @click="openRetrieve(secret.hash_id)"
                    @burn="burn"
                />

                <button
                    v-if="hasMore"
                    @click="loadMore"
                    :disabled="isLoadingMore"
                    class="w-full py-2 rounded-xl border border-gray-700 text-xs text-gray-400 hover:text-gray-200 transition-colors disabled:opacity-40"
                >
                    {{ isLoadingMore ? 'Loading…' : 'Load more' }}
                </button>
            </div>
        </div>
    </MobileLayout>
</template>
