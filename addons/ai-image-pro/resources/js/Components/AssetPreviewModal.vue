<script setup lang="ts">
import { computed, ref } from 'vue'
import AppModal from '@/Components/UI/AppModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { modelLabel, operationLabel, type Asset, type Model, type Op } from '../Composables/useImageJobs'

const { t } = useTranslate()

/** Mirrors ResultTile's action so the Studio can relay both through one handler. */
type PreviewActionType = 'download' | 'favorite' | 'delete' | 'variations'

const props = withDefaults(
    defineProps<{
        /** null closes the modal — the parent owns the open state. */
        asset: Asset | null
        /** The admin's model catalogue, used to turn a raw slug into a display name. */
        models?: Model[]
        /**
         * The admin's "let users choose the model" setting. When it is off, the model and
         * provider are not the user's decision and not their business — the operator may
         * well be reselling a model they would rather not name — so those two rows are
         * hidden entirely rather than shown greyed out.
         */
        allowModelChoice?: boolean
        /** The live operation list — turns the asset's `bg_remove` key into its real name. */
        operations?: Op[]
        /** Slug/alias => name for every model on record, so a retired one still reads. */
        modelNames?: Record<string, string>
        canVariations?: boolean
    }>(),
    { models: () => [], allowModelChoice: false, operations: () => [], modelNames: () => ({}), canVariations: false },
)

const emit = defineEmits<{
    (e: 'close'): void
    (e: 'action', type: PreviewActionType, asset: Asset): void
}>()

const model = computed(() => modelLabel(props.models, props.asset?.model, props.modelNames))

/** The operation's real name, never its raw registry key. */
const title = computed(() => operationLabel(props.operations, props.asset?.operation) ?? t('Preview'))

const copied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | null = null

async function copyPrompt(): Promise<void> {
    const prompt = props.asset?.prompt
    if (!prompt) return

    try {
        await navigator.clipboard.writeText(prompt)
    } catch {
        // Clipboard access is denied outside a secure context (plain http, which plenty of
        // self-hosted installs run on). Fall back rather than fail silently.
        const field = document.createElement('textarea')
        field.value = prompt
        field.setAttribute('readonly', '')
        field.style.position = 'fixed'
        field.style.opacity = '0'
        document.body.appendChild(field)
        field.select()
        document.execCommand('copy')
        document.body.removeChild(field)
    }

    copied.value = true
    if (copiedTimer) clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => (copied.value = false), 1800)
}

/** Only rendered when the admin lets users pick a model — see `allowModelChoice`. */
const showModel = computed(() => props.allowModelChoice && model.value !== null)

const dimensions = computed(() => {
    const asset = props.asset
    if (!asset?.width || !asset?.height) return null

    return `${asset.width}×${asset.height}`
})

function formatBytes(bytes: number): string {
    if (!bytes) return '—'
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function act(type: PreviewActionType): void {
    if (props.asset) emit('action', type, props.asset)
}
</script>

<template>
    <AppModal :open="asset !== null" max-width="max-w-5xl" :cancel-text="null" @close="emit('close')">
        <template #header>
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <h3 class="truncate text-lg font-bold text-gray-900 dark:text-white">
                    {{ title }}
                </h3>
                <Tooltip :content="t('Close')">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                        :aria-label="t('Close')"
                        @click="emit('close')"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </Tooltip>
            </div>
        </template>

        <div v-if="asset" class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="flex items-center justify-center rounded-xl bg-gray-100 p-2 dark:bg-surface-800">
                <img
                    :src="asset.url"
                    :alt="asset.prompt || t('Generated image')"
                    class="max-h-[65vh] w-auto rounded-lg object-contain"
                />
            </div>

            <div class="space-y-4 text-sm">
                <div v-if="asset.prompt">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Prompt') }}</p>
                        <Tooltip :content="copied ? t('Copied') : t('Copy prompt')">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                                :aria-label="t('Copy prompt')"
                                @click="copyPrompt"
                            >
                                <i class="ti text-sm" :class="copied ? 'ti-check text-emerald-500' : 'ti-copy'"></i>
                            </button>
                        </Tooltip>
                    </div>
                    <p class="text-gray-700 dark:text-gray-200">{{ asset.prompt }}</p>
                </div>

                <div v-if="asset.negative_prompt">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        {{ t('Negative prompt') }}
                    </p>
                    <p class="text-gray-700 dark:text-gray-200">{{ asset.negative_prompt }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div v-if="showModel">
                        <dt class="text-xs text-gray-400">{{ t('Model') }}</dt>
                        <dd class="text-gray-700 dark:text-gray-200">{{ model?.name }}</dd>
                    </div>
                    <div v-if="showModel && model?.provider">
                        <dt class="text-xs text-gray-400">{{ t('Provider') }}</dt>
                        <dd class="capitalize text-gray-700 dark:text-gray-200">{{ model.provider }}</dd>
                    </div>
                    <div v-if="asset.seed != null">
                        <dt class="text-xs text-gray-400">{{ t('Seed') }}</dt>
                        <dd class="text-gray-700 dark:text-gray-200">{{ asset.seed }}</dd>
                    </div>
                    <div v-if="dimensions">
                        <dt class="text-xs text-gray-400">{{ t('Dimensions') }}</dt>
                        <dd class="text-gray-700 dark:text-gray-200">{{ dimensions }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">{{ t('Size') }}</dt>
                        <dd class="text-gray-700 dark:text-gray-200">{{ formatBytes(asset.bytes) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">{{ t('Format') }}</dt>
                        <dd class="text-gray-700 dark:text-gray-200">{{ asset.mime }}</dd>
                    </div>
                </dl>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700"
                        @click="act('download')"
                    >
                        <i class="ti ti-download"></i>
                        {{ t('Download') }}
                    </button>

                    <button
                        v-if="canVariations && asset.prompt"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-200 dark:hover:bg-surface-800"
                        @click="act('variations')"
                    >
                        <i class="ti ti-versions"></i>
                        {{ t('Variations') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-200 dark:hover:bg-surface-800"
                        @click="act('favorite')"
                    >
                        <i class="ti" :class="asset.is_favorite ? 'ti-star-filled text-amber-500' : 'ti-star'"></i>
                        {{ asset.is_favorite ? t('Starred') : t('Star') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-danger-200 px-4 py-2 text-sm font-medium text-danger-600 transition hover:bg-danger-50 dark:border-danger-800 dark:text-danger-400 dark:hover:bg-danger-900/20"
                        @click="act('delete')"
                    >
                        <i class="ti ti-trash"></i>
                        {{ t('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </AppModal>
</template>
