<script setup lang="ts">
import { computed } from 'vue'

interface Secret {
    hash_id: string
    expires_at: string | null
    created_at: string
    is_expired: boolean
    is_retrieved: boolean
}

const props = defineProps<{
    secret: Secret
}>()

const emit = defineEmits<{
    burn: [hashId: string]
}>()

const expiresLabel = computed(() => {
    if (props.secret.is_expired) {
        return 'Expired'
    }
    if (!props.secret.expires_at) {
        return 'No expiry'
    }
    const diff = new Date(props.secret.expires_at).getTime() - Date.now()
    if (diff <= 0) {
        return 'Expired'
    }
    const hours = Math.floor(diff / 3_600_000)
    if (hours < 1) {
        return 'Expires < 1h'
    }
    if (hours < 24) {
        return `Expires in ${hours}h`
    }
    const days = Math.floor(hours / 24)
    return `Expires in ${days}d`
})

const createdLabel = computed(() => {
    return new Date(props.secret.created_at).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})
</script>

<template>
    <div
        class="rounded-xl bg-gray-900 border border-gray-700 px-4 py-3 flex items-center justify-between gap-3"
        :class="{ 'opacity-50': secret.is_expired || secret.is_retrieved }"
    >
        <div class="flex flex-col gap-0.5 min-w-0">
            <p class="font-mono text-xs text-gray-300 truncate">{{ secret.hash_id }}</p>
            <p class="text-xs text-gray-500">{{ createdLabel }}</p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <span
                class="text-xs font-mono px-2 py-0.5 rounded-full border"
                :class="secret.is_expired
                    ? 'text-gray-500 border-gray-700'
                    : 'text-gamboge-300 border-gamboge-800 shadow-neon-cyan-sm'"
            >
                {{ expiresLabel }}
            </span>

            <span
                v-if="secret.is_retrieved"
                class="text-xs text-gray-500 font-mono"
            >Retrieved</span>

            <button
                v-else-if="!secret.is_expired"
                type="button"
                @click.stop="emit('burn', secret.hash_id)"
                class="text-xs text-red-400 hover:text-red-300 transition-colors px-1"
                aria-label="Burn secret"
            >
                Burn
            </button>
        </div>
    </div>
</template>
