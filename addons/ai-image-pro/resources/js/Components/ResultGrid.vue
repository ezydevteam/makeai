<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import ResultTile from './ResultTile.vue'
import type { Asset, Op, TrackedJob } from '../Composables/useImageJobs'

const { t } = useTranslate()

// Kept structurally identical to ResultTile's action so the relayed event
// type-checks without importing across single-file components.
type TileActionType =
    | 'focus'
    | 'preview'
    | 'upscale'
    | 'bg_remove'
    | 'edit'
    | 'variations'
    | 'download'
    | 'favorite'
    | 'delete'

interface TileAction {
    type: TileActionType
    asset: Asset
    op?: Op
}

const props = withDefaults(
    defineProps<{
        assets: Asset[]
        pendingJobs: TrackedJob[]
        failedJobs: TrackedJob[]
        operations: Op[]
        isGuest?: boolean
        focusedUlid?: string | null
    }>(),
    { isGuest: false, focusedUlid: null },
)

const emit = defineEmits<{
    (e: 'action', action: TileAction): void
    (e: 'retry', ulid: string): void
    (e: 'dismiss', ulid: string): void
}>()

// One skeleton per expected output across every in-flight job.
const skeletonCount = computed(() =>
    props.pendingJobs.reduce((total, job) => total + Math.max(1, job.expected), 0),
)

const isEmpty = computed(
    () => props.assets.length === 0 && props.pendingJobs.length === 0 && props.failedJobs.length === 0,
)
</script>

<template>
    <div>
        <!-- Empty state -->
        <div
            v-if="isEmpty"
            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 px-6 py-16 text-center dark:border-surface-700"
        >
            <i class="ti ti-photo-ai mb-3 text-4xl text-gray-300 dark:text-gray-600"></i>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Nothing here yet') }}</p>
            <p class="mt-1 max-w-xs text-xs text-gray-500 dark:text-gray-400">
                {{ t('Describe an image above and generate it — your results will appear here, ready to refine.') }}
            </p>
        </div>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <!-- Failed jobs: retry / dismiss -->
            <div
                v-for="job in failedJobs"
                :key="`failed-${job.ulid}`"
                class="flex aspect-square flex-col items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-center dark:border-red-800/40 dark:bg-red-500/10"
            >
                <i class="ti ti-alert-triangle text-2xl text-red-500"></i>
                <p class="line-clamp-3 text-[11px] font-medium text-red-700 dark:text-red-300">
                    {{ job.error || t('This job failed.') }}
                </p>
                <div class="flex gap-1.5">
                    <button
                        v-if="job.retry"
                        type="button"
                        class="rounded-lg bg-red-500 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-red-600"
                        @click="emit('retry', job.ulid)"
                    >
                        {{ t('Retry') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 transition hover:bg-gray-100 dark:bg-surface-800 dark:text-gray-300"
                        @click="emit('dismiss', job.ulid)"
                    >
                        {{ t('Dismiss') }}
                    </button>
                </div>
            </div>

            <!-- Skeletons for in-flight jobs -->
            <div
                v-for="index in skeletonCount"
                :key="`skeleton-${index}`"
                class="relative aspect-square animate-pulse overflow-hidden rounded-xl bg-gray-200 dark:bg-surface-800"
            >
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="ti ti-loader-2 animate-spin text-xl text-gray-400 dark:text-gray-500"></i>
                </div>
            </div>

            <!-- Completed results -->
            <ResultTile
                v-for="asset in assets"
                :key="asset.ulid"
                :asset="asset"
                :operations="operations"
                :is-guest="isGuest"
                :focused="asset.ulid === focusedUlid"
                @action="(action) => emit('action', action)"
            />
        </div>
    </div>
</template>
