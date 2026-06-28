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
    isGradient?: boolean
    cardStyle?: string
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
            isGradient
                ? (favorited
                    ? 'border-danger-400/30 bg-danger-500/40 text-white hover:bg-danger-500/50 backdrop-blur-md'
                    : 'border-white/10 bg-white/10 text-white hover:bg-white/20 hover:text-white backdrop-blur-md')
                : (cardStyle === 'style-2'
                    ? (favorited
                        ? 'border-danger-200 bg-danger-100/70 text-danger-600 hover:bg-danger-100 dark:border-danger-500/30 dark:bg-danger-500/20 dark:text-danger-300 dark:hover:bg-danger-500/30'
                        : 'border-gray-200/50 bg-white/40 text-gray-500 hover:bg-white/60 hover:text-gray-700 backdrop-blur-md dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-gray-100')
                    : (favorited
                        ? 'border-danger-200 bg-danger-100/70 text-danger-600 hover:bg-danger-100 dark:border-danger-500/30 dark:bg-danger-500/20 dark:text-danger-300 dark:hover:bg-danger-500/30'
                        : 'border-gray-100 bg-gray-50 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-gray-100')),
            processing ? 'cursor-wait opacity-70' : '',
            size === 'sm' ? 'h-[34px]' : (size === 'lg' ? 'h-[42px]' : 'h-[38px]')
        ]"
        class="group inline-flex items-center gap-1.5 rounded-xl border p-2 shadow-sm transition-all"
        @click.stop.prevent="toggle"
    >
        <i
            :class="[
                favorited ? 'ti ti-heart-filled' : 'ti ti-heart',
                size === 'sm' ? 'text-sm' : (size === 'lg' ? 'text-xl' : 'text-base'),
                'shrink-0'
            ]"
        ></i>
        <span v-if="showCount" class="text-xs font-bold">{{ favoriteCount }}</span>
    </button>
</template>
