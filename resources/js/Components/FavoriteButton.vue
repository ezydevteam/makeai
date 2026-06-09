<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { SharedPageProps } from '@/types'

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{
    modelType: string
    modelId: number
    isFavorited: boolean
    count?: number
    size?: 'sm' | 'md' | 'lg'
    showCount?: boolean
}>()

const page = usePage<SharedPageProps>()
const { t } = useTranslate()

const processing = ref(false)
const favorited = ref(props.isFavorited)
const favoriteCount = ref(props.count ?? 0)

const iconSize = computed(() => {
    if (props.size === 'sm') return 'h-4 w-4'
    if (props.size === 'lg') return 'h-6 w-6'
    return 'h-5 w-5'
})

const label = computed(() => favorited.value ? t('Remove from favorites') : t('Add to favorites'))

watch(() => props.isFavorited, (value) => {
    favorited.value = value
})

watch(() => props.count, (value) => {
    favoriteCount.value = value ?? 0
})

const toggle = () => {
    if (processing.value) return

    if (!page.props.auth?.user) {
        router.visit(route('login'))
        return
    }

    const previousFavorited = favorited.value
    const previousCount = favoriteCount.value

    processing.value = true
    favorited.value = !favorited.value
    favoriteCount.value = Math.max(0, favoriteCount.value + (favorited.value ? 1 : -1))

    router.post(route('favorites.toggle'), {
        favoriteable_type: props.modelType,
        favoriteable_id: props.modelId,
    }, {
        preserveScroll: true,
        onError: () => {
            favorited.value = previousFavorited
            favoriteCount.value = previousCount
        },
        onFinish: () => {
            processing.value = false
        },
    })
}
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        :disabled="processing"
        :class="[
            favorited
                ? 'border-danger-100 bg-danger-50 text-danger-600 dark:border-danger-500/20 dark:bg-danger-500/10 dark:text-danger-200'
                : 'border-gray-100 bg-gray-50 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-gray-100',
            processing ? 'cursor-wait opacity-70' : '',
        ]"
        class="group inline-flex items-center gap-1.5 rounded-xl border p-2 shadow-sm transition-all"
        @click.stop.prevent="toggle"
    >
        <svg :class="iconSize" viewBox="0 0 24 24" stroke="currentColor" :stroke-width="favorited ? '0' : '2'" :fill="favorited ? 'currentColor' : 'none'">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
        </svg>
        <span v-if="showCount" class="text-xs font-bold">{{ favoriteCount }}</span>
    </button>
</template>
