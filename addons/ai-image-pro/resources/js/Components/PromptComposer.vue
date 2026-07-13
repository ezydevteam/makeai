<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { AspectRatio, Model, Preset, StudioFeatures } from '../Composables/useImageJobs'

const { t } = useTranslate()

/**
 * Payload emitted to the Studio, matching the JSON body of
 * POST /ai-image/generate. `reference_file` is a presentation-only extra:
 * the Studio uploads it first and forwards the resulting asset ulid as
 * `reference_asset_ulid` — the endpoint itself never receives a raw file.
 */
interface GeneratePayload {
    prompt: string
    negative_prompt?: string
    model?: string
    aspect?: string
    count?: number
    seed?: number
    preset?: string
    reference_file?: File | null
}

/** Which chip dropdown is open, if any. */
type MenuKey = 'model' | 'aspect' | 'count' | 'preset'

const props = withDefaults(
    defineProps<{
        /** v-model — the Studio owns the prompt so examples / ?prompt= can fill it. */
        modelValue: string
        models: Model[]
        defaultModel: string | null
        allowModelChoice: boolean
        aspectRatios: AspectRatio[]
        /** Preselected aspect, e.g. the one chosen on the landing page. */
        defaultAspect?: string | null
        presets: Preset[]
        maxBatchSize: number
        features: StudioFeatures
        isGuest?: boolean
        busy?: boolean
        error?: string | null
    }>(),
    { defaultAspect: null, isGuest: false, busy: false, error: null },
)

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'generate', payload: GeneratePayload): void
    /** The "Tools" button — the Studio owns the operation grid. */
    (e: 'tools'): void
}>()

const root = ref<HTMLElement | null>(null)
const textarea = ref<HTMLTextAreaElement | null>(null)
const referenceInput = ref<HTMLInputElement | null>(null)

const negativePrompt = ref('')
const seed = ref<number | null>(null)
const selectedModelSlug = ref<string | null>(props.defaultModel ?? props.models[0]?.slug ?? null)
// defaultAspect carries the choice the visitor made on the landing page's prompt
// panel; without it their aspect selection would be silently dropped on the hop.
const selectedAspect = ref<string | null>(
    props.defaultAspect ?? props.aspectRatios[0]?.key ?? null,
)
const selectedPreset = ref<string | null>(null)
const count = ref(1)
const advancedOpen = ref(false)
// Below md the option chips would wrap into three or four rows and push the prompt off
// the top of a phone screen, so they collapse behind a "More" toggle. From md up they are
// always shown and the toggle disappears — `moreOpen` is simply ignored there.
const moreOpen = ref(false)
const openMenu = ref<MenuKey | null>(null)
const referenceFile = ref<File | null>(null)
const referencePreview = ref<string>('')

const prompt = computed<string>({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
})

const selectedModel = computed<Model | null>(
    () => props.models.find((model) => model.slug === selectedModelSlug.value) ?? null,
)

const selectedAspectRatio = computed<AspectRatio | null>(
    () => props.aspectRatios.find((ratio) => ratio.key === selectedAspect.value) ?? null,
)

const selectedPresetOption = computed<Preset | null>(
    () => props.presets.find((preset) => preset.slug === selectedPreset.value) ?? null,
)

// The admin's feature toggle is authoritative: if Negative Prompt / Seed is enabled
// in settings, the field shows regardless of the selected model. A model that does
// not use the parameter simply ignores it — which is the price of the addon's
// "admin controls everything" contract, and avoids the Advanced panel silently
// disappearing just because the only configured models happen not to support it.
// Reference has no feature flag, so it still follows the model's own capability.
const showNegative = computed(() => props.features.negativePrompt)
const showSeed = computed(() => props.features.seed)
const showReference = computed(() => Boolean(selectedModel.value?.supports.reference))
const showAdvancedToggle = computed(() => showNegative.value || showSeed.value)

const maxCount = computed(() => {
    const modelAllowsBatch = selectedModel.value?.supports.batch ?? true
    if (!modelAllowsBatch) return 1

    return Math.max(1, props.maxBatchSize)
})

const countOptions = computed<number[]>(() =>
    Array.from({ length: maxCount.value }, (_unused, index) => index + 1),
)

const canGenerate = computed(() => prompt.value.trim().length > 0 && !props.busy)

/**
 * Whether there is anything behind the "More" toggle. An admin can disable model choice,
 * ship no presets, cap the batch at one and turn off both advanced fields — in which case
 * the toggle would open onto nothing, so it is not rendered at all.
 */
const hasMoreOptions = computed(
    () =>
        props.aspectRatios.length > 0 ||
        props.presets.length > 0 ||
        maxCount.value > 1 ||
        showAdvancedToggle.value ||
        (props.allowModelChoice && props.models.length > 0),
)

function toggleMore(): void {
    moreOpen.value = !moreOpen.value
    // A chip dropdown left open would otherwise linger, detached, over a collapsed row.
    if (!moreOpen.value) closeMenus()
}

/* ------------------------------------------------------------------ *
 * Working status
 *
 * A generation is a queued job, so there is no token stream to narrate and no
 * honest progress to report — the server knows "queued" and "done" and little in
 * between. These verbs are a deliberate stand-in for that: they say the machine is
 * awake, in the order the work actually happens, and they never claim a percentage
 * the backend could not back up. Cycling stops the moment `busy` clears.
 * ------------------------------------------------------------------ */
const BUSY_VERBS = ['Thinking', 'Composing', 'Sketching', 'Painting', 'Rendering', 'Adding the details', 'Finishing up']
const BUSY_VERB_MS = 2600

const busyStep = ref(0)
let busyTimer: ReturnType<typeof setInterval> | null = null

// Ellipsis kept out of the source string so a translator cannot lose it.
const busyVerb = computed(() => `${t(BUSY_VERBS[busyStep.value % BUSY_VERBS.length])}…`)

function stopBusyCycle(): void {
    if (busyTimer === null) return

    clearInterval(busyTimer)
    busyTimer = null
}

watch(
    () => props.busy,
    (busy) => {
        stopBusyCycle()

        if (!busy) return

        // Always open on "Thinking" — the run should read the same way every time.
        busyStep.value = 0
        busyTimer = setInterval(() => {
            // Hold on the last verb rather than looping: a job that outlives the list is
            // slow, and restarting at "Thinking" would suggest it had started over.
            if (busyStep.value >= BUSY_VERBS.length - 1) return stopBusyCycle()

            busyStep.value += 1
        }, BUSY_VERB_MS)
    },
)

/* ------------------------------------------------------------------ *
 * Chip dropdowns
 * ------------------------------------------------------------------ */
function toggleMenu(key: MenuKey): void {
    openMenu.value = openMenu.value === key ? null : key
}

function closeMenus(): void {
    openMenu.value = null
}

function onDocumentPointerDown(event: MouseEvent): void {
    if (!openMenu.value) return
    if (root.value && !root.value.contains(event.target as Node)) closeMenus()
}

function onDocumentKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') closeMenus()
}

onMounted(() => {
    document.addEventListener('mousedown', onDocumentPointerDown)
    document.addEventListener('keydown', onDocumentKeydown)
    autoGrow()
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentPointerDown)
    document.removeEventListener('keydown', onDocumentKeydown)
    stopBusyCycle()
    if (referencePreview.value) URL.revokeObjectURL(referencePreview.value)
})

/* ------------------------------------------------------------------ *
 * Textarea
 * ------------------------------------------------------------------ */
const MAX_TEXTAREA_HEIGHT = 240

function autoGrow(): void {
    const element = textarea.value
    if (!element) return

    element.style.height = 'auto'
    element.style.height = `${Math.min(element.scrollHeight, MAX_TEXTAREA_HEIGHT)}px`
}

// The prompt can also arrive from outside (an example card, or ?prompt=).
watch(
    () => props.modelValue,
    () => void nextTick(autoGrow),
)

function focus(): void {
    textarea.value?.focus()
}

/**
 * Clears the prompt and the attachment that went with it — a reference image left behind
 * would silently ride along into the next, unrelated generation. The chip selections
 * (model, aspect, style) are settings, not content, so they stay.
 */
function clearPrompt(): void {
    prompt.value = ''
    clearReference()
    closeMenus()
    void nextTick(() => {
        autoGrow()
        focus()
    })
}

/* ------------------------------------------------------------------ *
 * Reference image
 * ------------------------------------------------------------------ */
function pickReference(): void {
    referenceInput.value?.click()
}

function onReferenceChange(event: Event): void {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file) return

    if (referencePreview.value) URL.revokeObjectURL(referencePreview.value)
    referenceFile.value = file
    referencePreview.value = URL.createObjectURL(file)
}

function clearReference(): void {
    if (referencePreview.value) URL.revokeObjectURL(referencePreview.value)
    referenceFile.value = null
    referencePreview.value = ''
    if (referenceInput.value) referenceInput.value.value = ''
}

/* ------------------------------------------------------------------ *
 * Selection helpers
 * ------------------------------------------------------------------ */
function selectModel(slug: string): void {
    selectedModelSlug.value = slug
    clampCount()
    closeMenus()
}

function selectAspect(key: string): void {
    selectedAspect.value = key
    closeMenus()
}

function selectCount(value: number): void {
    count.value = value
    closeMenus()
}

function selectPreset(slug: string | null): void {
    selectedPreset.value = slug
    closeMenus()
}

// Keep the variant count valid when a model that forbids batching is chosen.
function clampCount(): void {
    if (count.value > maxCount.value) count.value = maxCount.value
}

/* ------------------------------------------------------------------ *
 * Submit
 * ------------------------------------------------------------------ */
function submit(): void {
    if (!canGenerate.value) return

    closeMenus()
    clampCount()

    const payload: GeneratePayload = { prompt: prompt.value.trim() }

    if (props.allowModelChoice && selectedModelSlug.value) payload.model = selectedModelSlug.value
    if (selectedAspect.value) payload.aspect = selectedAspect.value
    if (count.value > 1) payload.count = count.value
    if (selectedPreset.value) payload.preset = selectedPreset.value
    if (showNegative.value && negativePrompt.value.trim()) payload.negative_prompt = negativePrompt.value.trim()
    if (showSeed.value && seed.value !== null && !Number.isNaN(seed.value)) payload.seed = seed.value
    if (showReference.value && referenceFile.value) payload.reference_file = referenceFile.value

    emit('generate', payload)
}

// The Studio drives these for the example cards and the ?prompt= hand-off.
defineExpose({ submit, focus })
</script>

<template>
    <div ref="root" class="relative">
        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-[0_10px_40px_-24px_rgba(15,23,42,0.35)] transition focus-within:border-primary-300 focus-within:shadow-[0_16px_50px_-24px_rgba(15,23,42,0.45)] dark:border-surface-700 dark:bg-surface-900 dark:focus-within:border-primary-700/60"
        >
            <!-- Attached reference -->
            <div v-if="referencePreview" class="flex items-center gap-3 px-4 pt-4 sm:px-5">
                <div class="relative">
                    <img
                        :src="referencePreview"
                        :alt="t('Reference image')"
                        class="h-14 w-14 rounded-xl border border-gray-200 object-cover dark:border-surface-700"
                    />
                    <button
                        type="button"
                        class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-900 text-[10px] text-white shadow transition hover:bg-red-600"
                        :aria-label="t('Remove')"
                        @click="clearReference"
                    >
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <span class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ t('Reference image') }}
                </span>
            </div>

            <!-- Prompt.
                 `chat-textarea` is the app-wide opt-out from the global form-control
                 styling in app.css, which is unlayered and so beats Tailwind utilities —
                 it forces a 12px radius on every textarea, and in dark mode an
                 !important surface-800 background and border. Without the opt-out this
                 textarea paints an opaque box straight over the card's rounded top
                 corners, squaring them off; `border-0 bg-transparent` alone cannot win.

                 `rounded-t-2xl` matches the card's own radius, so even if something does
                 give this a background again, it is clipped to the card's curve instead
                 of squaring it. The card itself cannot use `overflow-hidden` — the chip
                 dropdowns open upward, out past its top edge. -->
            <label for="aip-prompt" class="sr-only">{{ t('Describe your image') }}</label>
            <textarea
                id="aip-prompt"
                ref="textarea"
                v-model="prompt"
                rows="2"
                :placeholder="t('Describe the image you want to create…')"
                class="chat-textarea block max-h-60 w-full resize-none rounded-t-2xl border-0 bg-transparent px-4 pb-2 pt-4 text-base leading-relaxed text-gray-900 outline-none placeholder:text-gray-400 focus:ring-0 sm:px-5 sm:pt-5 dark:text-white dark:placeholder:text-gray-500"
                @input="autoGrow"
                @keydown.enter.exact.prevent="submit"
                @keydown.ctrl.enter.prevent="submit"
                @keydown.meta.enter.prevent="submit"
            ></textarea>

            <!-- Toolbar -->
            <div class="flex items-end gap-2 px-3 pb-3 sm:px-4 sm:pb-4">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                    <!-- Attach a reference image -->
                    <input
                        ref="referenceInput"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="onReferenceChange"
                    />
                    <button
                        v-if="showReference"
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-600 dark:border-surface-700 dark:text-gray-400 dark:hover:border-primary-700/60 dark:hover:bg-primary-500/10 dark:hover:text-primary-300"
                        :title="t('Add a reference image')"
                        :aria-label="t('Add a reference image')"
                        @click="pickReference"
                    >
                        <i class="ti ti-plus text-base"></i>
                    </button>

                    <!-- Tools -->
                    <button
                        type="button"
                        class="flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60 dark:hover:bg-primary-500/10 dark:hover:text-primary-300"
                        @click="emit('tools')"
                    >
                        <i class="ti ti-adjustments text-sm"></i>
                        {{ t('Tools') }}
                    </button>

                    <!-- More — phones only. From md up the chips below are always visible,
                         so this is hidden and its state stops mattering. -->
                    <button
                        v-if="hasMoreOptions"
                        type="button"
                        class="flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition md:hidden"
                        :class="moreOpen
                            ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                            : 'border-gray-200 text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60'"
                        :aria-expanded="moreOpen"
                        aria-controls="aip-more-options"
                        @click="toggleMore"
                    >
                        <i class="ti ti-dots text-sm"></i>
                        {{ moreOpen ? t('Less') : t('More') }}
                        <i class="ti ti-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': moreOpen }"></i>
                    </button>

                    <!-- Collapsible option chips. `md:flex` beats `hidden` from 768px up, so
                         above that they always show, inline, exactly as before. Below it they
                         take a full row of their own rather than wrapping around the buttons. -->
                    <div
                        id="aip-more-options"
                        class="w-full flex-wrap items-center gap-1.5 md:flex md:w-auto"
                        :class="moreOpen ? 'flex' : 'hidden'"
                    >
                        <!-- Aspect ratio -->
                        <div v-if="aspectRatios.length > 0" class="relative shrink-0">
                            <button
                                type="button"
                                class="flex h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition"
                                :class="openMenu === 'aspect'
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60'"
                                :aria-expanded="openMenu === 'aspect'"
                                :title="t('Aspect ratio')"
                                @click="toggleMenu('aspect')"
                            >
                                <i class="ti ti-crop text-sm"></i>
                                {{ selectedAspectRatio?.label ?? t('Aspect ratio') }}
                                <i class="ti ti-chevron-down text-[10px]"></i>
                            </button>

                            <div
                                v-if="openMenu === 'aspect'"
                                class="absolute bottom-full left-0 z-30 mb-2 max-h-64 w-48 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                            >
                                <button
                                    v-for="ratio in aspectRatios"
                                    :key="ratio.key"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition"
                                    :class="selectedAspect === ratio.key
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800'"
                                    @click="selectAspect(ratio.key)"
                                >
                                    <span>{{ ratio.label }}</span>
                                    <span class="text-[10px] text-gray-400">{{ ratio.width }}×{{ ratio.height }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Style preset -->
                        <div v-if="presets.length > 0" class="relative shrink-0">
                            <button
                                type="button"
                                class="flex h-9 max-w-[11rem] items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition"
                                :class="openMenu === 'preset' || selectedPreset
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60'"
                                :aria-expanded="openMenu === 'preset'"
                                :title="t('Style')"
                                @click="toggleMenu('preset')"
                            >
                                <i class="ti ti-palette text-sm"></i>
                                <span class="truncate">{{ selectedPresetOption?.name ?? t('Style') }}</span>
                                <i class="ti ti-chevron-down text-[10px]"></i>
                            </button>

                            <div
                                v-if="openMenu === 'preset'"
                                class="absolute bottom-full left-0 z-30 mb-2 max-h-72 w-56 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition"
                                    :class="selectedPreset === null
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800'"
                                    @click="selectPreset(null)"
                                >
                                    <i class="ti ti-ban text-sm"></i>
                                    {{ t('No style') }}
                                </button>
                                <button
                                    v-for="preset in presets"
                                    :key="preset.slug"
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition"
                                    :class="selectedPreset === preset.slug
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800'"
                                    @click="selectPreset(preset.slug)"
                                >
                                    <img
                                        v-if="preset.thumb_url"
                                        :src="preset.thumb_url"
                                        :alt="preset.name"
                                        class="h-5 w-5 shrink-0 rounded object-cover"
                                    />
                                    <i v-else class="ti ti-brush text-sm"></i>
                                    <span class="truncate">{{ preset.name }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Variant count -->
                        <div v-if="maxCount > 1" class="relative shrink-0">
                            <button
                                type="button"
                                class="flex h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition"
                                :class="openMenu === 'count'
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60'"
                                :aria-expanded="openMenu === 'count'"
                                :title="t('Variants')"
                                @click="toggleMenu('count')"
                            >
                                <i class="ti ti-stack-2 text-sm"></i>
                                <span aria-hidden="true">{{ count }}</span>
                                <span class="sr-only">{{ t(':count images', { count: String(count) }) }}</span>
                                <i class="ti ti-chevron-down text-[10px]"></i>
                            </button>

                            <div
                                v-if="openMenu === 'count'"
                                class="absolute bottom-full left-0 z-30 mb-2 w-40 rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                            >
                                <button
                                    v-for="option in countOptions"
                                    :key="option"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition"
                                    :class="count === option
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800'"
                                    @click="selectCount(option)"
                                >
                                    <span>{{ t(':count images', { count: String(option) }) }}</span>
                                    <i v-if="count === option" class="ti ti-check text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Advanced -->
                        <button
                            v-if="showAdvancedToggle"
                            type="button"
                            class="flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition"
                            :class="advancedOpen
                                ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                : 'border-gray-200 text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60'"
                            :aria-expanded="advancedOpen"
                            @click="advancedOpen = !advancedOpen"
                        >
                            <i class="ti ti-settings text-sm"></i>
                            {{ t('Advanced') }}
                            <i class="ti ti-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': advancedOpen }"></i>
                        </button>

                        <!-- Model -->
                        <div v-if="allowModelChoice && models.length > 0" class="relative shrink-0">
                            <button
                                type="button"
                                class="flex h-9 max-w-[12rem] items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition"
                                :class="openMenu === 'model'
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:text-gray-300 dark:hover:border-primary-700/60'"
                                :aria-expanded="openMenu === 'model'"
                                :title="t('Model')"
                                @click="toggleMenu('model')"
                            >
                                <i class="ti ti-sparkles text-sm"></i>
                                <span class="truncate">{{ selectedModel?.name ?? t('Model') }}</span>
                                <i class="ti ti-chevron-down text-[10px]"></i>
                            </button>

                            <div
                                v-if="openMenu === 'model'"
                                class="absolute bottom-full left-0 z-30 mb-2 max-h-72 w-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                            >
                                <button
                                    v-for="model in models"
                                    :key="model.slug"
                                    type="button"
                                    class="flex w-full items-start justify-between gap-2 rounded-lg px-2.5 py-2 text-left transition"
                                    :class="selectedModelSlug === model.slug
                                        ? 'bg-primary-50 dark:bg-primary-500/10'
                                        : 'hover:bg-gray-50 dark:hover:bg-surface-800'"
                                    @click="selectModel(model.slug)"
                                >
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-xs font-semibold"
                                            :class="selectedModelSlug === model.slug
                                                ? 'text-primary-700 dark:text-primary-300'
                                                : 'text-gray-800 dark:text-gray-200'"
                                        >
                                            {{ model.name }}
                                        </span>
                                        <span class="mt-0.5 block truncate text-[10px] uppercase tracking-wide text-gray-400">
                                            {{ model.provider }}
                                        </span>
                                    </span>
                                    <i
                                        v-if="selectedModelSlug === model.slug"
                                        class="ti ti-check mt-0.5 shrink-0 text-xs text-primary-600 dark:text-primary-400"
                                    ></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- /collapsible option chips -->
                </div>

                <!-- Clear. The prompt survives a generation on purpose (so you can tweak a word
                     and run it again), which is exactly why starting from scratch needs a
                     one-click way out. Hidden while busy: clearing mid-run would suggest it
                     cancels the job, which it does not. -->
                <button
                    v-if="prompt.trim() && !busy"
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                    :title="t('Clear prompt')"
                    :aria-label="t('Clear prompt')"
                    @click="clearPrompt"
                >
                    <i class="ti ti-eraser text-lg"></i>
                </button>

                <!-- Submit -->
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white shadow-sm transition disabled:cursor-not-allowed"
                    :class="canGenerate
                        ? 'bg-primary-600 hover:bg-primary-700'
                        : 'bg-gray-200 text-gray-400 dark:bg-surface-700 dark:text-gray-500'"
                    :disabled="!canGenerate"
                    :title="busy ? t('Generating…') : t('Generate')"
                    :aria-label="busy ? t('Generating…') : t('Generate')"
                    @click="submit"
                >
                    <i v-if="busy" class="ti ti-loader-2 animate-spin text-lg"></i>
                    <i v-else class="ti ti-arrow-up text-lg"></i>
                </button>
            </div>

            <!-- Advanced fields -->
            <div v-if="advancedOpen && showAdvancedToggle" class="border-t border-gray-100 px-4 py-4 sm:px-5 dark:border-surface-800">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-if="showNegative" class="sm:col-span-2">
                        <label for="aip-negative" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ t('Negative prompt') }}
                        </label>
                        <input
                            id="aip-negative"
                            v-model="negativePrompt"
                            type="text"
                            :placeholder="t('Things to avoid (blurry, extra fingers…)')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white dark:placeholder:text-gray-500"
                        />
                    </div>

                    <div v-if="showSeed">
                        <label for="aip-seed" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ t('Seed') }}
                        </label>
                        <input
                            id="aip-seed"
                            v-model.number="seed"
                            type="number"
                            min="0"
                            :placeholder="t('Random')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white dark:placeholder:text-gray-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Error -->
        <p
            v-if="error"
            class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-red-600 dark:text-red-400"
            role="alert"
        >
            <i class="ti ti-alert-triangle"></i>
            {{ error }}
        </p>

        <!-- Working. Replaces the keyboard hint rather than sitting beside it: while the
             image is rendering, "Press Enter to generate" is advice the user cannot act on. -->
        <p
            v-else-if="busy"
            class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-primary-600 dark:text-primary-400"
            role="status"
            aria-live="polite"
        >
            <i class="ti ti-loader-2 animate-spin"></i>
            {{ busyVerb }}
        </p>

        <p v-else class="mt-3 text-center text-[11px] text-gray-400 dark:text-gray-500">
            <template v-if="isGuest">
                {{ t('Free to try — no signup required. Press Enter to generate.') }}
            </template>
            <template v-else>
                {{ t('Press Enter to generate, Shift + Enter for a new line.') }}
            </template>
        </p>
    </div>
</template>
