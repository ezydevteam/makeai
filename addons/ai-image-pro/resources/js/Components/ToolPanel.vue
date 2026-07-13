<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import {
    defaultParams,
    fieldsFor,
    type Asset,
    type Op,
    type ParamMap,
    type ParamValue,
    type StudioLimits,
    type ToolField,
} from '../Composables/useImageJobs'

const { t } = useTranslate()

const props = withDefaults(
    defineProps<{
        op: Op
        limits: StudioLimits
        targetAsset: Asset | null
        isGuest?: boolean
        busy?: boolean
        error?: string | null
    }>(),
    { isGuest: false, busy: false, error: null },
)

const emit = defineEmits<{
    (e: 'run', params: ParamMap): void
    (e: 'close'): void
    (e: 'signin'): void
}>()

const fields = computed<ToolField[]>(() => fieldsFor(props.op))
const params = ref<ParamMap>(defaultParams(props.op))

// Rebuild the form whenever the selected operation changes — each op owns its
// own default parameter set (from the shared registry-derived schema).
watch(
    () => props.op.key,
    () => {
        params.value = defaultParams(props.op)
    },
)

// An op that consumes an image cannot run until the Studio hands it a target.
const needsImage = computed(() => props.op.inputs.includes('image'))
const missingImage = computed(() => needsImage.value && !props.targetAsset)

const canRun = computed(
    () => !props.busy && !props.op.locked && props.op.available && !missingImage.value,
)

function ceilingFor(field: ToolField): number | undefined {
    if (field.clampTo === 'maxOutputDimension') return props.limits.maxOutputDimension

    return field.max
}

function clampNumber(field: ToolField, raw: number): number {
    let value = raw
    const ceiling = ceilingFor(field)
    if (typeof field.min === 'number' && value < field.min) value = field.min
    if (typeof ceiling === 'number' && value > ceiling) value = ceiling

    return value
}

function updateNumber(field: ToolField, event: Event): void {
    const raw = Number((event.target as HTMLInputElement).value)
    params.value[field.name] = Number.isNaN(raw) ? (field.default as ParamValue) : clampNumber(field, raw)
}

function costLabel(): string {
    if (props.op.billing === 'free') return t('Free')
    if (props.op.billing === 'media') return t('Charged by model')

    return props.op.credits > 0 ? t(':count credits', { count: props.op.credits }) : t('Free')
}

function submit(): void {
    if (!canRun.value) return
    emit('run', { ...params.value })
}
</script>

<template>
    <div class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
        <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-surface-800">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                <i :class="op.icon" class="text-base text-primary-600 dark:text-primary-400"></i>
                {{ op.label }}
            </h3>
            <button
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800"
                :aria-label="t('Close')"
                @click="emit('close')"
            >
                <i class="ti ti-x"></i>
            </button>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto p-4">
            <!-- Locked: guests sign in, members upgrade -->
            <div
                v-if="op.locked"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-center dark:border-amber-800/40 dark:bg-amber-500/10"
            >
                <i class="ti ti-lock mb-1 block text-xl text-amber-500"></i>
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                    {{ isGuest ? t('Sign in to use this tool') : t('Upgrade to use this tool') }}
                </p>
                <button
                    v-if="isGuest"
                    type="button"
                    class="mt-3 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-600"
                    @click="emit('signin')"
                >
                    {{ t('Sign in') }}
                </button>
            </div>

            <!-- Engine has no API key configured -->
            <div
                v-else-if="!op.available"
                class="rounded-xl border border-dashed border-gray-200 p-4 text-center dark:border-surface-700"
            >
                <i class="ti ti-plug-connected-x mb-1 block text-xl text-gray-400"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('This tool is not configured yet.') }}</p>
            </div>

            <template v-else>
                <!-- No target image yet -->
                <div
                    v-if="missingImage"
                    class="rounded-xl border border-dashed border-gray-200 p-4 text-center dark:border-surface-700"
                >
                    <i class="ti ti-photo-off mb-1 block text-xl text-gray-400"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Select an image to run this tool on.') }}</p>
                </div>

                <template v-else>
                    <div
                        v-if="targetAsset"
                        class="flex items-center gap-3 rounded-xl bg-gray-50 p-2 dark:bg-surface-950"
                    >
                        <img
                            :src="targetAsset.thumb_url ?? targetAsset.url"
                            :alt="t('Selected image')"
                            class="h-12 w-12 rounded-lg object-cover"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-medium text-gray-700 dark:text-gray-200">
                                {{ targetAsset.prompt || t('Selected image') }}
                            </p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                {{ targetAsset.width }}×{{ targetAsset.height }}
                            </p>
                        </div>
                    </div>

                    <div v-if="fields.length === 0" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ t('No settings — this tool runs with its defaults.') }}
                    </div>

                    <div v-for="field in fields" :key="field.name" class="space-y-1">
                        <label
                            :for="`aip-field-${field.name}`"
                            class="flex items-center justify-between text-xs font-medium text-gray-600 dark:text-gray-300"
                        >
                            <span>{{ t(field.label) }}</span>
                            <span v-if="field.type === 'range'" class="text-gray-400 dark:text-gray-500">
                                {{ params[field.name] }}
                            </span>
                        </label>

                        <textarea
                            v-if="field.type === 'textarea'"
                            :id="`aip-field-${field.name}`"
                            v-model="params[field.name] as string"
                            rows="2"
                            :placeholder="field.placeholder ? t(field.placeholder) : ''"
                            class="w-full resize-y rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                        ></textarea>

                        <select
                            v-else-if="field.type === 'select'"
                            :id="`aip-field-${field.name}`"
                            v-model="params[field.name]"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                        >
                            <option v-for="option in field.options" :key="String(option.value)" :value="option.value">
                                {{ t(option.label) }}
                            </option>
                        </select>

                        <label
                            v-else-if="field.type === 'toggle'"
                            class="flex cursor-pointer items-center gap-2"
                        >
                            <input
                                :id="`aip-field-${field.name}`"
                                v-model="params[field.name]"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-surface-600 dark:bg-surface-950"
                            />
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ t('Enabled') }}</span>
                        </label>

                        <input
                            v-else-if="field.type === 'color'"
                            :id="`aip-field-${field.name}`"
                            v-model="params[field.name] as string"
                            type="color"
                            class="h-9 w-full cursor-pointer rounded-xl border border-gray-200 dark:border-surface-700"
                        />

                        <input
                            v-else-if="field.type === 'range'"
                            :id="`aip-field-${field.name}`"
                            type="range"
                            :min="field.min"
                            :max="ceilingFor(field)"
                            :step="field.step ?? 1"
                            :value="params[field.name] as number"
                            class="w-full accent-primary-600"
                            @input="updateNumber(field, $event)"
                        />

                        <input
                            v-else-if="field.type === 'number'"
                            :id="`aip-field-${field.name}`"
                            type="number"
                            :min="field.min"
                            :max="ceilingFor(field)"
                            :step="field.step ?? 1"
                            :value="params[field.name] as number"
                            :placeholder="field.placeholder ? t(field.placeholder) : ''"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                            @input="updateNumber(field, $event)"
                        />

                        <input
                            v-else
                            :id="`aip-field-${field.name}`"
                            v-model="params[field.name] as string"
                            type="text"
                            :placeholder="field.placeholder ? t(field.placeholder) : ''"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white"
                        />

                        <p v-if="field.help" class="text-[11px] text-gray-400 dark:text-gray-500">{{ t(field.help) }}</p>
                    </div>
                </template>
            </template>
        </div>

        <footer v-if="op.available && !op.locked" class="border-t border-gray-100 p-4 dark:border-surface-800">
            <p v-if="error" class="mb-2 flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                <i class="ti ti-alert-triangle"></i>
                {{ error }}
            </p>
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!canRun"
                @click="submit"
            >
                <i v-if="busy" class="ti ti-loader-2 animate-spin"></i>
                <i v-else class="ti ti-player-play"></i>
                {{ busy ? t('Running…') : t('Run') }}
                <span class="text-[11px] font-normal opacity-80">· {{ costLabel() }}</span>
            </button>
        </footer>
    </div>
</template>
