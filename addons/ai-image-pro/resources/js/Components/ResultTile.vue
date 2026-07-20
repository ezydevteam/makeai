<script setup lang="ts">
import { computed } from 'vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { Asset, Op } from '../Composables/useImageJobs'

const { t } = useTranslate()

/** Dispatched up to the Studio, which turns each type into an op run or asset call. */
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
        asset: Asset
        operations: Op[]
        isGuest?: boolean
        focused?: boolean
    }>(),
    { isGuest: false, focused: false },
)

const emit = defineEmits<{ (e: 'action', action: TileAction): void }>()

// Quick actions are derived from the live operations prop — never a hardcoded
// list. A tool that the admin disabled (or that has no key configured) simply
// does not offer its hover button.
function opByKey(key: string): Op | undefined {
    return props.operations.find((op) => op.key === key && op.available)
}

const upscaleOp = computed(() => opByKey('upscale'))
const removeBgOp = computed(() => opByKey('bg_remove'))
const canEdit = computed(() =>
    props.operations.some((op) => op.available && (op.inputs.includes('mask') || op.key === 'outpaint')),
)
const canVariations = computed(
    () => Boolean(props.asset.prompt) && props.operations.some((op) => op.available && op.key === 'generate'),
)

function dispatch(type: TileActionType, op?: Op): void {
    emit('action', { type, asset: props.asset, op })
}
</script>

<template>
    <figure
        class="group relative aspect-square overflow-hidden rounded-xl border bg-gray-100 transition dark:bg-surface-950"
        :class="focused
            ? 'border-primary-400 ring-2 ring-primary-200 dark:border-primary-600 dark:ring-primary-500/20'
            : 'border-gray-200 dark:border-surface-800'"
    >
        <img
            :src="asset.thumb_url ?? asset.url"
            :alt="asset.prompt ?? t('Generated image')"
            loading="lazy"
            class="h-full w-full cursor-pointer object-cover"
            @click="dispatch('focus')"
        />

        <!-- Star marker (always visible when set) -->
        <Tooltip :content="asset.is_favorite ? t('Remove star') : t('Star')">
            <button
                type="button"
                class="absolute left-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur transition hover:bg-black/60"
                :class="{ 'opacity-0 group-hover:opacity-100': !asset.is_favorite }"
                :aria-label="asset.is_favorite ? t('Remove star') : t('Star')"
                @click.stop="dispatch('favorite')"
            >
                <i class="ti text-sm" :class="asset.is_favorite ? 'ti-star-filled text-amber-400' : 'ti-star'"></i>
            </button>
        </Tooltip>

        <!-- Hover action bar -->
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 flex flex-wrap items-center justify-center gap-1 bg-gradient-to-t from-black/70 via-black/40 to-transparent p-2 opacity-0 transition group-hover:pointer-events-auto group-hover:opacity-100"
        >
            <!-- Preview. A separate button rather than the image click, which still selects
                 the tile as the target for the tool panel — opening a modal on every select
                 would make choosing a target for an upscale needlessly noisy. -->
            <Tooltip :content="t('Preview')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 transition hover:bg-white"
                    :aria-label="t('Preview')"
                    @click.stop="dispatch('preview')"
                >
                    <i class="ti ti-eye text-sm"></i>
                </button>
            </Tooltip>
            <Tooltip v-if="upscaleOp" :content="t('Upscale')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 transition hover:bg-white"
                    :aria-label="t('Upscale')"
                    @click.stop="dispatch('upscale', upscaleOp)"
                >
                    <i class="ti ti-zoom-in text-sm"></i>
                </button>
            </Tooltip>
            <Tooltip v-if="removeBgOp" :content="t('Remove background')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 transition hover:bg-white"
                    :aria-label="t('Remove background')"
                    @click.stop="dispatch('bg_remove', removeBgOp)"
                >
                    <i class="ti ti-eraser text-sm"></i>
                </button>
            </Tooltip>
            <Tooltip v-if="canEdit" :content="t('Edit')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 transition hover:bg-white"
                    :aria-label="t('Edit')"
                    @click.stop="dispatch('edit')"
                >
                    <i class="ti ti-brush text-sm"></i>
                </button>
            </Tooltip>
            <Tooltip v-if="canVariations" :content="t('Variations')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 transition hover:bg-white"
                    :aria-label="t('Variations')"
                    @click.stop="dispatch('variations')"
                >
                    <i class="ti ti-versions text-sm"></i>
                </button>
            </Tooltip>
            <Tooltip :content="t('Download')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 transition hover:bg-white"
                    :aria-label="t('Download')"
                    @click.stop="dispatch('download')"
                >
                    <i class="ti ti-download text-sm"></i>
                </button>
            </Tooltip>
            <Tooltip :content="t('Delete')">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-red-600 transition hover:bg-white"
                    :aria-label="t('Delete')"
                    @click.stop="dispatch('delete')"
                >
                    <i class="ti ti-trash text-sm"></i>
                </button>
            </Tooltip>
        </div>
    </figure>
</template>
