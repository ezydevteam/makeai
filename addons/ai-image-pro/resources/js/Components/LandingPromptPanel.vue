<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import type { Op, OpGroup, Preset } from '../Composables/useImageJobs'

/* ------------------------------------------------------------------ *
 * The hero prompt card — the single most important control on the
 * marketing page. It does not generate anything and it does not run a
 * single operation: it shows a visitor the *whole* composer they would
 * get inside the Studio (tools, styles, model, aspect), collects their
 * intent, and hands it over.
 *
 * Every list on it — operations, presets, models, ratios — arrives from
 * the server. Nothing here is hardcoded, so an operation the admin
 * disabled simply never appears.
 * ------------------------------------------------------------------ */

/* The panel only ever needs a model's identity — the Studio's richer Model
 * (provider, sizes, capability flags) is not its business, so it declares the
 * narrow shape it actually reads. Op / Preset are the real shared contracts and
 * come straight from the composable. */
interface ModelOption {
    slug: string
    name: string
}

interface AspectOption {
    key: string
    label: string
    width: number
    height: number
}

interface PromptSubmit {
    prompt: string
    model: string | null
    aspect: string | null
    preset: string | null
}

const props = withDefaults(
    defineProps<{
        models: ModelOption[]
        defaultModel: string | null
        allowModelChoice: boolean
        aspectRatios: AspectOption[]
        /** The full tool rail, exactly as the Studio receives it. */
        operations: Op[]
        /** Admin-authored style presets. */
        presets: Preset[]
        /** Where a tool click sends the visitor. */
        studioUrl: string
        /** Purple→blue treatment for the send button, matching the page. */
        gradient?: boolean
        placeholder?: string
        busy?: boolean
    }>(),
    {
        gradient: false,
        placeholder: '',
        busy: false,
    },
)

const emit = defineEmits<{
    (event: 'submit', payload: PromptSubmit): void
}>()

const { t } = useTranslate()

type MenuKey = 'tools' | 'style' | 'model' | 'aspect'

const prompt = ref('')
const textarea = ref<HTMLTextAreaElement | null>(null)
const root = ref<HTMLElement | null>(null)
const openMenu = ref<MenuKey | null>(null)

const selectedModel = ref<string | null>(props.defaultModel ?? props.models[0]?.slug ?? null)
const selectedAspect = ref<string | null>(props.aspectRatios[0]?.key ?? null)
const selectedPreset = ref<string | null>(null)

/**
 * A tool whose engine has no API key behind it is hidden outright rather than shown
 * greyed-out — an operator's missing key is not the visitor's problem, and a dead row
 * they can never click is worse than no row at all. The Studio hides them the same way.
 */
const usableOps = computed<Op[]>(() => props.operations.filter((op) => op.available))

const showToolsChip = computed(() => usableOps.value.length > 0)
const showStyleChip = computed(() => props.presets.length > 0)
const showModelChip = computed(() => props.allowModelChoice && props.models.length > 0)
const showAspectChip = computed(() => props.aspectRatios.length > 0)

// Below md the chips wrap into two or three rows and push the prompt up the screen, so
// everything past Tools collapses behind a "More" toggle. From md up they are always
// shown and the toggle disappears — `moreOpen` is simply ignored there. Matches the
// Studio composer, which the visitor lands on next.
const moreOpen = ref(false)

/** Nothing to reveal if the admin shipped no presets, no aspects and no model choice. */
const hasMoreOptions = computed(
    () => showAspectChip.value || showStyleChip.value || showModelChip.value,
)

const activeModel = computed<ModelOption | null>(
    () => props.models.find((model) => model.slug === selectedModel.value) ?? null,
)

const activeAspect = computed<AspectOption | null>(
    () => props.aspectRatios.find((aspect) => aspect.key === selectedAspect.value) ?? null,
)

const activePreset = computed<Preset | null>(
    () => props.presets.find((preset) => preset.slug === selectedPreset.value) ?? null,
)

const canSubmit = computed(() => prompt.value.trim().length > 0 && !props.busy)

const resolvedPlaceholder = computed(
    () => props.placeholder || t('Describe the image you want to create…'),
)

/** The send button is a primary CTA, so it follows the page's colour scheme. */
const sendButtonClass = computed(() =>
    props.gradient
        ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white enabled:hover:from-purple-500 enabled:hover:to-blue-500'
        : 'bg-gray-900 text-white enabled:hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:enabled:hover:bg-gray-100',
)

/* ------------------------------------------------------------------ *
 * Tools — the same rail the Studio renders, grouped the same way.
 * ------------------------------------------------------------------ */
const GROUP_ORDER: OpGroup[] = ['create', 'enhance', 'adjust']

/** Source strings; run through t() at render time, never stored translated. */
const GROUP_LABELS: Record<OpGroup, string> = {
    create: 'Create',
    enhance: 'Enhance',
    adjust: 'Adjust',
}

interface OpSection {
    key: OpGroup
    label: string
    ops: Op[]
}

const opSections = computed<OpSection[]>(() =>
    GROUP_ORDER.map((group) => ({
        key: group,
        label: t(GROUP_LABELS[group]),
        ops: usableOps.value.filter((op) => op.group === group),
    })).filter((section) => section.ops.length > 0),
)

function iconClass(icon: string | null | undefined): string {
    const raw = (icon ?? '').trim()
    if (!raw) return 'ti ti-wand'
    if (raw.startsWith('ti ti-')) return raw
    if (raw.startsWith('ti-')) return `ti ${raw}`

    return `ti ti-${raw}`
}

/**
 * The landing page runs nothing. Picking a tool means "take me somewhere I
 * can use this" — so we hand the prompt (when there is one) to the Studio and
 * let it decide what the visitor is allowed to do.
 */
function openTool(op: Op): void {
    // Unusable ops are filtered out of the menu, so this can't normally fire — it stays
    // as a guard in case an op is rendered from a stale prop payload.
    if (!op.available) return

    openMenu.value = null

    const trimmed = prompt.value.trim()
    router.get(props.studioUrl, trimmed ? { prompt: trimmed } : {})
}

/* ------------------------------------------------------------------ *
 * Composer
 * ------------------------------------------------------------------ */

/* Keep the composer breathing as the visitor types, without letting it
 * swallow the page on a very long prompt. */
function autoGrow(): void {
    const element = textarea.value
    if (!element) return

    element.style.height = 'auto'
    element.style.height = `${Math.min(element.scrollHeight, 240)}px`
}

watch(prompt, () => void nextTick(autoGrow))

function toggleMenu(key: MenuKey): void {
    openMenu.value = openMenu.value === key ? null : key
}

function toggleMore(): void {
    moreOpen.value = !moreOpen.value
    // A chip dropdown left open would otherwise linger, detached, over a collapsed row.
    if (!moreOpen.value) openMenu.value = null
}

function pickModel(slug: string): void {
    selectedModel.value = slug
    openMenu.value = null
}

function pickAspect(key: string): void {
    selectedAspect.value = key
    openMenu.value = null
}

function pickPreset(slug: string | null): void {
    selectedPreset.value = slug
    openMenu.value = null
}

function submit(): void {
    if (!canSubmit.value) return

    openMenu.value = null

    emit('submit', {
        prompt: prompt.value.trim(),
        model: showModelChip.value ? selectedModel.value : null,
        aspect: showAspectChip.value ? selectedAspect.value : null,
        preset: showStyleChip.value ? selectedPreset.value : null,
    })
}

/** Enter sends, Shift+Enter makes a new line — the convention people expect. */
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault()
        submit()
    }
}

function onDocumentClick(event: MouseEvent): void {
    if (!openMenu.value) return
    if (root.value && !root.value.contains(event.target as Node)) openMenu.value = null
}

function onDocumentKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') openMenu.value = null
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onDocumentKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
})

defineExpose({
    focus: (): void => {
        textarea.value?.focus()
    },
})
</script>

<template>
    <div
        ref="root"
        class="relative rounded-2xl border border-gray-200/80 bg-white p-2 shadow-[0_8px_40px_-12px_rgba(15,23,42,0.15)] ring-1 ring-black/[0.02] transition focus-within:border-primary-300 focus-within:shadow-[0_12px_50px_-12px_rgba(15,23,42,0.22)] dark:border-surface-700 dark:bg-surface-900 dark:shadow-[0_8px_40px_-12px_rgba(0,0,0,0.6)] dark:ring-white/[0.03] dark:focus-within:border-primary-700"
    >
        <form @submit.prevent="submit">
            <label class="sr-only" for="aip-landing-prompt">{{ t('Prompt') }}</label>

            <textarea
                id="aip-landing-prompt"
                ref="textarea"
                v-model="prompt"
                rows="2"
                :placeholder="resolvedPlaceholder"
                :disabled="busy"
                class="chat-textarea block w-full resize-none border-0 bg-transparent px-4 pb-2 pt-3.5 text-[15px] leading-relaxed text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 disabled:opacity-60 sm:text-base dark:text-white dark:placeholder:text-gray-500"
                @keydown="onKeydown"
                @input="autoGrow"
            ></textarea>

            <!-- Chip row + send. The chips wrap rather than overflow: on a phone
                 they stack into two or three short rows, never a cut-off line. -->
            <div class="flex items-end justify-between gap-2 px-2 pb-1.5 pt-1 sm:gap-3">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 sm:gap-2">
                    <!-- Tools -->
                    <div v-if="showToolsChip" class="relative">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="openMenu === 'tools'
                                ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700'"
                            :aria-expanded="openMenu === 'tools'"
                            aria-haspopup="menu"
                            @click="toggleMenu('tools')"
                        >
                            <i class="ti ti-adjustments shrink-0 text-sm" aria-hidden="true"></i>
                            <span>{{ t('Tools') }}</span>
                            <i class="ti ti-chevron-down shrink-0 text-xs text-gray-400" aria-hidden="true"></i>
                        </button>

                        <div
                            v-if="openMenu === 'tools'"
                            role="menu"
                            class="absolute bottom-full left-0 z-30 mb-2 max-h-[22rem] w-[21rem] max-w-[calc(100vw-3rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl dark:border-surface-700 dark:bg-surface-800"
                        >
                            <div v-for="section in opSections" :key="section.key" class="mb-1 last:mb-0">
                                <p
                                    class="px-2 pb-1 pt-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                                >
                                    {{ section.label }}
                                </p>

                                <button
                                    v-for="op in section.ops"
                                    :key="op.key"
                                    type="button"
                                    role="menuitem"
                                    :title="op.label"
                                    class="group flex w-full items-start gap-2.5 rounded-lg px-2 py-2 text-left transition hover:bg-gray-50 dark:hover:bg-surface-700"
                                    @click="openTool(op)"
                                >
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-primary-500 dark:bg-surface-900 dark:text-primary-400"
                                    >
                                        <i :class="iconClass(op.icon)" class="text-sm" aria-hidden="true"></i>
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate text-xs font-semibold text-gray-800 dark:text-gray-100">
                                                {{ op.label }}
                                            </span>
                                            <i
                                                v-if="op.locked"
                                                class="ti ti-lock shrink-0 text-xs text-amber-500"
                                                :title="t('Sign in to use this tool')"
                                            ></i>
                                        </span>
                                        <!-- Clamped, not truncated: the menu is narrow and a
                                             one-word tail is worse than a tidy second line. -->
                                        <span
                                            v-if="op.description"
                                            class="mt-0.5 line-clamp-2 block text-[10px] leading-snug text-gray-400 dark:text-gray-500"
                                        >
                                            {{ op.description }}
                                        </span>
                                    </span>

                                    <i
                                        class="ti ti-arrow-up-right mt-0.5 shrink-0 text-sm text-gray-300 transition-transform duration-200 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-0.5 dark:text-surface-600"
                                        aria-hidden="true"
                                    ></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- More / Less — phones only. From md up the chips below are always
                         visible, so this is hidden and its state stops mattering. -->
                    <button
                        v-if="hasMoreOptions"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition md:hidden"
                        :class="moreOpen
                            ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700'"
                        :aria-expanded="moreOpen"
                        aria-controls="aip-landing-more"
                        @click="toggleMore"
                    >
                        <i class="ti ti-dots shrink-0 text-sm" aria-hidden="true"></i>
                        <span>{{ moreOpen ? t('Less') : t('More') }}</span>
                        <i
                            class="ti ti-chevron-down shrink-0 text-xs text-gray-400 transition-transform"
                            :class="{ 'rotate-180': moreOpen }"
                            aria-hidden="true"
                        ></i>
                    </button>

                    <!-- Collapsible chips. `md:flex` beats `hidden` from 768px up, so above
                         that they always show, inline, exactly as before. Below it they take a
                         full row of their own rather than wrapping around the Tools chip. -->
                    <div
                        id="aip-landing-more"
                        class="w-full flex-wrap items-center gap-1.5 sm:gap-2 md:flex md:w-auto"
                        :class="moreOpen ? 'flex' : 'hidden'"
                    >
                        <!-- Aspect chip -->
                        <div v-if="showAspectChip" class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                :class="openMenu === 'aspect'
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700'"
                                :aria-expanded="openMenu === 'aspect'"
                                aria-haspopup="listbox"
                                @click="toggleMenu('aspect')"
                            >
                                <i class="ti ti-crop shrink-0 text-sm text-gray-400" aria-hidden="true"></i>
                                <span>{{ activeAspect?.label ?? t('Aspect') }}</span>
                                <i class="ti ti-chevron-down shrink-0 text-xs text-gray-400" aria-hidden="true"></i>
                            </button>

                            <ul
                                v-if="openMenu === 'aspect'"
                                role="listbox"
                                class="absolute bottom-full left-0 z-30 mb-2 max-h-64 w-48 max-w-[calc(100vw-3rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-800"
                            >
                                <li v-for="aspect in aspectRatios" :key="aspect.key">
                                    <button
                                        type="button"
                                        role="option"
                                        :aria-selected="aspect.key === selectedAspect"
                                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium transition"
                                        :class="aspect.key === selectedAspect
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700'"
                                        @click="pickAspect(aspect.key)"
                                    >
                                        <span>{{ aspect.label }}</span>
                                        <span class="text-[10px] text-gray-400">{{ aspect.width }}×{{ aspect.height }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                         <!-- Style preset -->
                        <div v-if="showStyleChip" class="relative">
                            <button
                                type="button"
                                class="inline-flex max-w-[10rem] items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                :class="openMenu === 'style' || selectedPreset
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700'"
                                :aria-expanded="openMenu === 'style'"
                                aria-haspopup="listbox"
                                @click="toggleMenu('style')"
                            >
                                <i class="ti ti-palette shrink-0 text-sm" aria-hidden="true"></i>
                                <span class="truncate">{{ activePreset?.name ?? t('Style') }}</span>
                                <i class="ti ti-chevron-down shrink-0 text-xs text-gray-400" aria-hidden="true"></i>
                            </button>

                            <ul
                                v-if="openMenu === 'style'"
                                role="listbox"
                                class="absolute bottom-full left-0 z-30 mb-2 max-h-72 w-56 max-w-[calc(100vw-3rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-800"
                            >
                                <li>
                                    <button
                                        type="button"
                                        role="option"
                                        :aria-selected="selectedPreset === null"
                                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium transition"
                                        :class="selectedPreset === null
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700'"
                                        @click="pickPreset(null)"
                                    >
                                        <i class="ti ti-ban shrink-0 text-sm" aria-hidden="true"></i>
                                        <span class="truncate">{{ t('No style') }}</span>
                                    </button>
                                </li>

                                <li v-for="preset in presets" :key="preset.slug">
                                    <button
                                        type="button"
                                        role="option"
                                        :aria-selected="preset.slug === selectedPreset"
                                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium transition"
                                        :class="preset.slug === selectedPreset
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700'"
                                        @click="pickPreset(preset.slug)"
                                    >
                                        <img
                                            v-if="preset.thumb_url"
                                            :src="preset.thumb_url"
                                            :alt="preset.name"
                                            loading="lazy"
                                            decoding="async"
                                            class="h-6 w-6 shrink-0 rounded object-cover"
                                        />
                                        <i v-else class="ti ti-brush shrink-0 text-sm" aria-hidden="true"></i>
                                        <span class="truncate">{{ preset.name }}</span>
                                        <i
                                            v-if="preset.slug === selectedPreset"
                                            class="ti ti-check ml-auto shrink-0 text-sm"
                                            aria-hidden="true"
                                        ></i>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Model chip -->
                        <div v-if="showModelChip" class="relative">
                            <button
                                type="button"
                                class="inline-flex max-w-[11rem] items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                :class="openMenu === 'model'
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700'"
                                :aria-expanded="openMenu === 'model'"
                                aria-haspopup="listbox"
                                @click="toggleMenu('model')"
                            >
                                <i class="ti ti-sparkles shrink-0 text-sm text-primary-500" aria-hidden="true"></i>
                                <span class="truncate">{{ activeModel?.name ?? t('Model') }}</span>
                                <i class="ti ti-chevron-down shrink-0 text-xs text-gray-400" aria-hidden="true"></i>
                            </button>

                            <ul
                                v-if="openMenu === 'model'"
                                role="listbox"
                                class="absolute bottom-full left-0 z-30 mb-2 max-h-64 w-56 max-w-[calc(100vw-3rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-surface-700 dark:bg-surface-800"
                            >
                                <li v-for="model in models" :key="model.slug">
                                    <button
                                        type="button"
                                        role="option"
                                        :aria-selected="model.slug === selectedModel"
                                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium transition"
                                        :class="model.slug === selectedModel
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700'"
                                        @click="pickModel(model.slug)"
                                    >
                                        <span class="truncate">{{ model.name }}</span>
                                        <i v-if="model.slug === selectedModel" class="ti ti-check shrink-0 text-sm"></i>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!-- /collapsible chips -->
                </div>

                <!-- Circular send -->
                <button
                    type="submit"
                    :disabled="!canSubmit"
                    :aria-label="t('Generate image')"
                    :title="t('Generate image')"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full shadow-sm transition enabled:hover:scale-105 disabled:cursor-not-allowed disabled:opacity-30"
                    :class="sendButtonClass"
                >
                    <i v-if="busy" class="ti ti-loader-2 animate-spin text-lg"></i>
                    <i v-else class="ti ti-arrow-up text-lg"></i>
                </button>
            </div>
        </form>
    </div>
</template>
