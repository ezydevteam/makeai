<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import MaskCanvas from './MaskCanvas.vue'
import {
    defaultParams,
    fieldsFor,
    type Asset,
    type Op,
    type ParamMap,
    type StudioLimits,
    type ToolField,
} from '../Composables/useImageJobs'

const { t } = useTranslate()

/** Emitted to the Studio, which posts it to /ai-image/ops/{op} (multipart when a mask is present). */
interface EditorSubmit {
    op: Op
    params: ParamMap
    mask: Blob | null
}

const props = withDefaults(
    defineProps<{
        asset: Asset
        operations: Op[]
        limits: StudioLimits
        isGuest?: boolean
        busy?: boolean
        error?: string | null
    }>(),
    { isGuest: false, busy: false, error: null },
)

const emit = defineEmits<{
    (e: 'close'): void
    (e: 'submit', payload: EditorSubmit): void
    (e: 'signin'): void
}>()

// The editor drives any available op that either paints a mask (inpaint,
// object removal) or extends the canvas (outpaint). All of it comes from the
// operations prop — nothing about which tools exist is hardcoded here.
const editorOps = computed<Op[]>(() =>
    props.operations.filter((op) => op.available && (op.inputs.includes('mask') || op.key === 'outpaint')),
)

const selectedKey = ref<string | null>(editorOps.value[0]?.key ?? null)
const selectedOp = computed<Op | null>(() => editorOps.value.find((op) => op.key === selectedKey.value) ?? null)
const isMaskMode = computed(() => Boolean(selectedOp.value?.inputs.includes('mask')))

const params = ref<ParamMap>(selectedOp.value ? defaultParams(selectedOp.value) : {})
const fields = computed<ToolField[]>(() => (selectedOp.value ? fieldsFor(selectedOp.value) : []))

const brushSize = ref(30)
const localError = ref('')
const maskCanvas = ref<InstanceType<typeof MaskCanvas> | null>(null)

watch(selectedOp, (op) => {
    params.value = op ? defaultParams(op) : {}
    localError.value = ''
})

function clearMask(): void {
    maskCanvas.value?.clear()
}

async function submit(): Promise<void> {
    const op = selectedOp.value
    if (!op || props.busy) return

    if (op.locked) {
        if (props.isGuest) emit('signin')

        return
    }

    localError.value = ''
    let mask: Blob | null = null

    if (isMaskMode.value) {
        mask = (await maskCanvas.value?.exportMask()) ?? null
        if (!mask) {
            localError.value = t('Brush over the area you want to change first.')

            return
        }
    }

    emit('submit', { op, params: { ...params.value }, mask })
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>

        <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-surface-900">
            <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                    <i class="ti ti-photo-edit text-base text-primary-600 dark:text-primary-400"></i>
                    {{ t('Edit image') }}
                </h2>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800"
                    :aria-label="t('Close')"
                    @click="emit('close')"
                >
                    <i class="ti ti-x"></i>
                </button>
            </header>

            <!-- No editor-capable ops -->
            <div v-if="editorOps.length === 0 || !selectedOp" class="flex flex-col items-center justify-center gap-2 p-12 text-center">
                <i class="ti ti-tools-off text-3xl text-gray-300 dark:text-gray-600"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('No editing tools are available right now.') }}</p>
            </div>

            <div v-else class="flex flex-1 flex-col gap-4 overflow-y-auto p-5 lg:flex-row">
                <!-- Canvas / preview -->
                <div class="min-w-0 flex-1">
                    <MaskCanvas
                        v-if="isMaskMode"
                        ref="maskCanvas"
                        :image-url="asset.url"
                        :brush-size="brushSize"
                    />
                    <div v-else class="flex items-center justify-center rounded-xl bg-gray-100 p-2 dark:bg-surface-950">
                        <img :src="asset.url" :alt="t('Image to extend')" class="max-h-[460px] max-w-full rounded-lg object-contain" />
                    </div>

                    <div v-if="isMaskMode" class="mt-3 flex items-center gap-3">
                        <label class="flex flex-1 items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ t('Brush size') }}
                            <input v-model.number="brushSize" type="range" min="10" max="120" class="flex-1 accent-primary-600" />
                        </label>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="clearMask"
                        >
                            {{ t('Clear') }}
                        </button>
                    </div>
                </div>

                <!-- Controls -->
                <div class="w-full shrink-0 space-y-4 lg:w-72">
                    <!-- Mode selector -->
                    <div v-if="editorOps.length > 1">
                        <span class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Mode') }}</span>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="op in editorOps"
                                :key="op.key"
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition"
                                :class="selectedKey === op.key
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/50 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-300'"
                                @click="selectedKey = op.key"
                            >
                                <i :class="op.icon"></i>
                                {{ op.label }}
                            </button>
                        </div>
                    </div>

                    <p v-if="isMaskMode" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Paint over the part of the image you want to change.') }}
                    </p>

                    <!-- Parameter fields -->
                    <div v-for="field in fields" :key="field.name" class="space-y-1">
                        <label :for="`aip-edit-${field.name}`" class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ t(field.label) }}
                        </label>
                        <textarea
                            v-if="field.type === 'textarea'"
                            :id="`aip-edit-${field.name}`"
                            v-model="params[field.name] as string"
                            rows="2"
                            :placeholder="field.placeholder ? t(field.placeholder) : ''"
                            class="w-full resize-y rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                        ></textarea>
                        <input
                            v-else-if="field.type === 'number'"
                            :id="`aip-edit-${field.name}`"
                            v-model.number="params[field.name] as number"
                            type="number"
                            :min="field.min"
                            :max="field.max"
                            :step="field.step ?? 1"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                        />
                        <input
                            v-else
                            :id="`aip-edit-${field.name}`"
                            v-model="params[field.name] as string"
                            type="text"
                            :placeholder="field.placeholder ? t(field.placeholder) : ''"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                        />
                    </div>

                    <!-- Locked CTA -->
                    <div
                        v-if="selectedOp.locked"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-xs text-amber-800 dark:border-amber-800/40 dark:bg-amber-500/10 dark:text-amber-300"
                    >
                        {{ isGuest ? t('Sign in to use this tool') : t('Upgrade to use this tool') }}
                    </div>

                    <p v-if="localError || error" class="flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                        <i class="ti ti-alert-triangle"></i>
                        {{ localError || error }}
                    </p>

                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="busy"
                        @click="submit"
                    >
                        <i v-if="busy" class="ti ti-loader-2 animate-spin"></i>
                        <i v-else class="ti ti-check"></i>
                        {{ busy ? t('Applying…') : t('Apply') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
