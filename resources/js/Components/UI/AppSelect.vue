<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick as vueNextTick } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

export interface SelectOption {
    value: string | number | null
    label: string
    icon?: string
    color?: string
    tone?: 'default' | 'success' | 'warning' | 'danger'
    dividerBefore?: boolean
    disabled?: boolean
}

const props = withDefaults(defineProps<{
    modelValue?: string | number | null | (string | number)[]
    options?: SelectOption[]
    placeholder?: string
    label?: string
    error?: string
    searchPlaceholder?: string
    liveSearch?: boolean
    size?: number
    multiple?: boolean
    compactMultiple?: boolean
    disabled?: boolean
    required?: boolean
    id?: string
    name?: string
    dropdownPlacement?: 'auto' | 'top' | 'bottom'
    /** Auto-enable virtual scrolling when options exceed this threshold. Default 100. */
    virtualThreshold?: number
}>(), {
    modelValue: undefined,
    options: () => [],
    placeholder: '',
    label: '',
    error: '',
    searchPlaceholder: '',
    liveSearch: false,
    size: 8,
    multiple: false,
    compactMultiple: false,
    disabled: false,
    required: false,
    id: undefined,
    name: undefined,
    dropdownPlacement: 'auto',
    virtualThreshold: 100,
})

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null | (string | number)[]]
}>()
const { t } = useTranslate()

const ITEM_HEIGHT = 38

const isOpen = ref(false)
const searchQuery = ref('')
const highlightedIndex = ref(-1)
const dropdownRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const wrapperRef = ref<HTMLElement | null>(null)
const placement = ref<'bottom' | 'top'>('bottom')
const listScrollTop = ref(0)
const safeOptions = computed(() => props.options ?? [])

const selectedValues = computed<(string | number)[]>(() => {
    if (props.multiple) {
        return (Array.isArray(props.modelValue) ? props.modelValue : []) as (string | number)[]
    }
    return props.modelValue != null && props.modelValue !== '' ? [props.modelValue as string | number] : []
})

const isSelected = (value: string | number | null) => {
    if (value === null) return false
    return selectedValues.value.some(sv => String(sv).toLowerCase() === String(value).toLowerCase())
}

const filtered = computed(() => {
    if (!searchQuery.value.trim()) return safeOptions.value
    const q = searchQuery.value.toLowerCase()
    return safeOptions.value.filter(
        (o) => o.label.toLowerCase().includes(q) || String(o.value).toLowerCase().includes(q),
    )
})

const useVirtual = computed(() => {
    return (props.liveSearch || props.multiple) && filtered.value.length > props.virtualThreshold
})

const showSearch = computed(() => props.liveSearch && (safeOptions.value.length > props.size || props.multiple))

const selectedOptions = computed(() =>
    safeOptions.value.filter((o) => isSelected(o.value)),
)

const optionToneClass = (option: SelectOption) => {
    switch (option.tone) {
        case 'success':
            return 'text-green-700 dark:text-green-300'
        case 'warning':
            return 'text-amber-700 dark:text-amber-300'
        case 'danger':
            return 'text-red-700 dark:text-red-300'
        default:
            return 'text-gray-700 dark:text-gray-200'
    }
}

const displayText = computed(() => {
    if (props.multiple && props.compactMultiple && selectedOptions.value.length > 0) {
        return selectedOptions.value.map((option) => option.label).join(', ')
    }
    if (props.multiple && selectedOptions.value.length > 0) {
        return ''
    }
    if (!props.multiple && selectedOptions.value.length >= 1) {
        return selectedOptions.value[0]?.label ?? props.placeholder
    }
    return props.placeholder
})

// --- Virtual scroll ---
const visibleCount = computed(() => Math.min(filtered.value.length, props.size))
const totalVirtualHeight = computed(() => filtered.value.length * ITEM_HEIGHT)

const startIndex = computed(() => {
    if (!useVirtual.value) return 0
    return Math.floor(listScrollTop.value / ITEM_HEIGHT)
})
const endIndex = computed(() => {
    if (!useVirtual.value) return filtered.value.length
    return Math.min(filtered.value.length, startIndex.value + visibleCount.value + 3)
})

const visibleItems = computed(() => {
    if (!useVirtual.value) return filtered.value
    return filtered.value.slice(startIndex.value, endIndex.value)
})

const virtualOffsetY = computed(() => {
    if (!useVirtual.value) return 0
    return startIndex.value * ITEM_HEIGHT
})

function onListScroll(e: Event) {
    listScrollTop.value = (e.target as HTMLElement).scrollTop
}
// --- End virtual scroll ---

const detectPlacement = () => {
    if (props.dropdownPlacement !== 'auto') {
        placement.value = props.dropdownPlacement
        return
    }

    if (!triggerRef.value) return
    const rect = triggerRef.value.getBoundingClientRect()
    const menuHeight = props.size * ITEM_HEIGHT + (showSearch.value ? 50 : 0) + 8
    const spaceBelow = window.innerHeight - rect.bottom
    placement.value = spaceBelow < menuHeight ? 'top' : 'bottom'
}

function toggle() {
    if (props.disabled) return
    if (!isOpen.value) detectPlacement()
    isOpen.value = !isOpen.value
    if (isOpen.value) {
        searchQuery.value = ''
        highlightedIndex.value = -1
        listScrollTop.value = 0
        vueNextTick(() => {
            if (showSearch.value) inputRef.value?.focus()
        })
    }
}

function select(option: SelectOption) {
    if (option.disabled) return

    if (props.multiple) {
        const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        if (option.value !== null) {
            const idx = current.indexOf(option.value)
            if (idx === -1) {
                current.push(option.value)
            } else {
                current.splice(idx, 1)
            }
            emit('update:modelValue', current)
        }
        vueNextTick(() => { if (showSearch.value) inputRef.value?.focus() })
        return
    }

    emit('update:modelValue', option.value)
    isOpen.value = false
}

function removeTag(value: string | number | null, e: Event) {
    e.stopPropagation()
    if (!props.multiple || value === null) return
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
    emit('update:modelValue', current.filter((v) => v !== value))
}

function selectAll() {
    if (!props.multiple) return
    const all = filtered.value.filter((o) => !o.disabled && o.value !== null).map((o) => o.value) as (string | number)[]
    emit('update:modelValue', all)
}

function deselectAll() {
    if (!props.multiple) return
    emit('update:modelValue', [])
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        isOpen.value = false
        triggerRef.value?.focus()
        return
    }
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, filtered.value.length - 1)
        scrollToHighlighted()
        return
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault()
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
        scrollToHighlighted()
        return
    }
    if (e.key === 'Enter' && isOpen.value) {
        e.preventDefault()
        if (highlightedIndex.value >= 0 && highlightedIndex.value < filtered.value.length) {
            select(filtered.value[highlightedIndex.value])
        }
        return
    }
}

function scrollToHighlighted() {
    if (!dropdownRef.value || highlightedIndex.value < 0) return

    if (useVirtual.value) {
        const targetScroll = highlightedIndex.value * ITEM_HEIGHT
        listScrollTop.value = targetScroll
        return
    }

    vueNextTick(() => {
        const el = dropdownRef.value?.querySelector('[data-highlighted]')
        el?.scrollIntoView({ block: 'nearest' })
    })
}

function clickOutside(e: MouseEvent) {
    const target = e.target as HTMLElement
    if (!wrapperRef.value?.contains(target)) {
        isOpen.value = false
    }
}

onMounted(() => document.addEventListener('click', clickOutside))
onBeforeUnmount(() => document.removeEventListener('click', clickOutside))

watch(isOpen, (val) => {
    if (!val) {
        searchQuery.value = ''
        listScrollTop.value = 0
    }
})

// Reset scroll when search filter changes
watch(searchQuery, () => {
    listScrollTop.value = 0
    highlightedIndex.value = -1
})
</script>

<template>
    <div
        ref="wrapperRef"
        class="relative min-w-0 w-full"
        :class="{ 'opacity-60 pointer-events-none': disabled }"
    >
        <label v-if="label" :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ label }}
            <span v-if="required" class="text-danger-500">*</span>
        </label>

        <div class="relative min-w-0 w-full">
            <button
                ref="triggerRef"
                type="button"
                :id="id"
                :name="name"
                :disabled="disabled"
                class="w-full min-w-0 min-h-[2.5rem] flex items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white text-left transition-colors focus:ring-1 focus:ring-primary-500/40 focus:border-primary-500"
                :class="multiple && compactMultiple ? 'h-10 overflow-hidden' : ''"
                @click.stop="toggle"
                @keydown="handleKeydown"
            >
                <span
                    class="flex min-w-0 items-center gap-1.5 truncate"
                    :class="multiple && compactMultiple ? 'min-w-0 flex-1 flex-nowrap overflow-hidden' : 'flex-wrap'"
                >
                    <i v-if="!multiple && displayText && selectedOptions[0]?.icon" :class="selectedOptions[0].icon" class="text-base shrink-0" aria-hidden="true" />
                    <template v-if="multiple && selectedOptions.length > 0 && !compactMultiple">
                        <span
                            v-for="opt in selectedOptions"
                            :key="String(opt.value)"
                            class="inline-flex items-center gap-1 rounded-md bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-300"
                        >
                            <span
                                v-if="opt.color"
                                class="h-2.5 w-2.5 shrink-0 rounded-full border border-white/70 dark:border-surface-700"
                                :style="{ backgroundColor: opt.color }"
                            ></span>
                            <i v-if="opt.icon" :class="opt.icon" class="text-xs" />
                            {{ opt.label }}
                            <button type="button" class="ml-0.5 rounded-full p-0.5 hover:!bg-primary-200 dark:!hover:bg-primary-900/20" @click.stop="removeTag(opt.value, $event as any)">
                                <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </span>
                    </template>
                    <span
                        v-if="displayText"
                        class="block min-w-0 flex-1 truncate"
                        :class="{ 'text-gray-400 dark:text-gray-500': !selectedOptions.length }"
                    >{{ displayText }}</span>
                </span>
                <svg class="h-3 w-3 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <Transition
                enter-active-class="transition ease-out duration-150"
                :enter-from-class="placement === 'top' ? 'opacity-0 translate-y-1 scale-95' : 'opacity-0 -translate-y-1 scale-95'"
                :enter-to-class="placement === 'top' ? 'opacity-100 -translate-y-0 scale-100' : 'opacity-100 translate-y-0 scale-100'"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="isOpen"
                    ref="dropdownRef"
                    class="absolute z-40 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900"
                    :class="placement === 'top' ? 'bottom-full' : 'top-full mt-1'"
                    @click.stop
                    @mousedown.stop.prevent
                >
                    <div v-if="showSearch && placement === 'bottom'" class="border-b border-gray-100 dark:border-surface-700 p-2">
                        <input
                            ref="inputRef"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="searchPlaceholder || t('Search...')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white focus:ring-0"
                            @keydown.stop="handleKeydown"
                        />
                    </div>

                    <div v-if="multiple && filtered.length > 1" class="flex gap-2 border-b border-gray-100 dark:border-surface-700 px-3 py-1.5">
                        <button type="button" class="text-xs font-medium text-primary-600 hover:!text-primary-700 dark:!text-primary-400" @click.stop="selectAll">Select all</button>
                        <button type="button" class="text-xs font-medium text-gray-400 hover:!text-gray-600 dark:!text-gray-500 dark:!hover:text-gray-300" @click.stop="deselectAll">Clear</button>
                    </div>

                    <!-- Virtual scroll list -->
                    <div
                        v-if="useVirtual"
                        class="overflow-y-auto"
                        :style="{ maxHeight: `${size * ITEM_HEIGHT}px` }"
                        @scroll="onListScroll"
                    >
                        <div :style="{ height: `${totalVirtualHeight}px`, position: 'relative' }">
                            <div :style="{ transform: `translateY(${virtualOffsetY}px)` }">
                                <div v-for="(option, index) in visibleItems" :key="String(option.value)">
                                    <div v-if="option.dividerBefore" class="mx-3 border-t border-gray-200 dark:border-surface-700"></div>
                                    <div
                                        :data-highlighted="(startIndex + index) === highlightedIndex ? '' : undefined"
                                        class="flex items-center gap-2 px-3 text-sm cursor-pointer transition-colors"
                                        :style="{ height: `${ITEM_HEIGHT}px` }"
                                        :class="[
                                            option.disabled
                                                ? 'opacity-40 cursor-not-allowed'
                                                : 'hover:!bg-primary-50 dark:hover:!bg-primary-900/20',
                                            isSelected(option.value)
                                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 font-semibold'
                                                : optionToneClass(option),
                                            (startIndex + index) === highlightedIndex && !option.disabled && !isSelected(option.value)
                                                ? 'bg-gray-100 dark:bg-surface-700'
                                                : '',
                                        ]"
                                        @mousedown.stop.prevent
                                        @click.stop="select(option)"
                                        @mouseenter="highlightedIndex = startIndex + index"
                                        @mouseleave="highlightedIndex = -1"
                                    >
                                        <span
                                            v-if="option.color"
                                            class="h-2.5 w-2.5 shrink-0 rounded-full border border-white/70 dark:border-surface-700"
                                            :style="{ backgroundColor: option.color }"
                                        ></span>
                                        <i v-if="!multiple && option.icon" :class="option.icon" class="text-base shrink-0" aria-hidden="true" />
                                        <span class="truncate">{{ option.label }}</span>
                                        <i
                                            v-if="isSelected(option.value)"
                                            class="ti ti-check ml-auto shrink-0 text-base text-primary-600 dark:text-primary-400"
                                            aria-hidden="true"
                                        ></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Normal (non-virtual) list -->
                    <div
                        v-else
                        class="overflow-y-auto"
                        :style="{ maxHeight: `${size * ITEM_HEIGHT}px` }"
                    >
                        <div v-for="(option, index) in filtered" :key="String(option.value)">
                            <div v-if="option.dividerBefore" class="mx-3 border-t border-gray-200 dark:border-surface-700"></div>
                            <div
                                :data-highlighted="highlightedIndex === index ? '' : undefined"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer transition-colors"
                                :class="[
                                    option.disabled
                                        ? 'opacity-40 cursor-not-allowed'
                                        : 'hover:!bg-primary-50 dark:hover:!bg-primary-900/20',
                                    isSelected(option.value)
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 font-semibold'
                                        : optionToneClass(option),
                                    highlightedIndex === index && !option.disabled && !isSelected(option.value)
                                        ? 'bg-gray-100 dark:bg-surface-700'
                                        : '',
                                ]"
                                @mousedown.stop.prevent
                                @click.stop="select(option)"
                                @mouseenter="highlightedIndex = index"
                                @mouseleave="highlightedIndex = -1"
                            >
                                <span
                                    v-if="option.color"
                                    class="h-2.5 w-2.5 shrink-0 rounded-full border border-white/70 dark:border-surface-700"
                                    :style="{ backgroundColor: option.color }"
                                ></span>
                                <i v-if="!multiple && option.icon" :class="option.icon" class="text-base shrink-0" aria-hidden="true" />
                                <span class="truncate">{{ option.label }}</span>
                                <i
                                    v-if="isSelected(option.value)"
                                    class="ti ti-check ml-auto shrink-0 text-base text-primary-600 dark:text-primary-400"
                                    aria-hidden="true"
                                ></i>
                            </div>
                        </div>

                        <div
                            v-if="filtered.length === 0"
                            class="px-3 py-4 text-center text-sm text-gray-400 dark:text-gray-500"
                        >
                            {{ t('No results found') }}
                        </div>
                    </div>

                    <div v-if="showSearch && placement === 'top'" class="border-t border-gray-100 dark:border-surface-700 p-2">
                        <input
                            ref="inputRef"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="searchPlaceholder || t('Search...')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white focus:ring-0"
                            @keydown.stop="handleKeydown"
                        />
                    </div>
                </div>
            </Transition>

            <span v-if="error" class="mt-1 block text-xs text-danger-600">{{ error }}</span>
        </div>
    </div>
</template>
