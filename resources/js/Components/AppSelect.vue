<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'

export interface SelectOption {
    value: string | number
    label: string
    icon?: string
    color?: string
    disabled?: boolean
}

const props = withDefaults(defineProps<{
    modelValue?: string | number | null | (string | number)[]
    options: SelectOption[]
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
}>(), {
    modelValue: undefined,
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
})

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null | (string | number)[]]
}>()

const isOpen = ref(false)
const searchQuery = ref('')
const highlightedIndex = ref(-1)
const dropdownRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const wrapperRef = ref<HTMLElement | null>(null)
const placement = ref<'bottom' | 'top'>('bottom')

const selectedValues = computed<string[] | number[]>(() => {
    if (props.multiple) {
        return (Array.isArray(props.modelValue) ? props.modelValue : []) as (string | number)[]
    }
    return props.modelValue != null && props.modelValue !== '' ? [props.modelValue as string | number] : []
})

const isSelected = (value: string | number) => selectedValues.value.includes(value)

const filtered = computed(() => {
    if (!searchQuery.value.trim()) return props.options
    const q = searchQuery.value.toLowerCase()
    return props.options.filter(
        (o) => o.label.toLowerCase().includes(q) || String(o.value).toLowerCase().includes(q),
    )
})

const showSearch = computed(() => props.liveSearch && (props.options.length > props.size || props.multiple))

const selectedOptions = computed(() =>
    props.options.filter((o) => isSelected(o.value)),
)

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

const detectPlacement = () => {
    if (!triggerRef.value) return
    const rect = triggerRef.value.getBoundingClientRect()
    const menuHeight = props.size * 38 + (showSearch.value ? 50 : 0) + 8
    const spaceBelow = window.innerHeight - rect.bottom
    placement.value = spaceBelow < menuHeight ? 'top' : 'bottom'
}

function toggle() {
    if (props.disabled) return
    if (!isOpen.value) detectPlacement()
    isOpen.value = !isOpen.value
    if (isOpen.value) {
        searchQuery.value = ''
        highlightedIndex.value = 0
        nextTick(() => {
            if (showSearch.value) inputRef.value?.focus()
        })
    }
}

function select(option: SelectOption) {
    if (option.disabled) return

    if (props.multiple) {
        const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        const idx = current.indexOf(option.value)
        if (idx === -1) {
            current.push(option.value)
        } else {
            current.splice(idx, 1)
        }
        emit('update:modelValue', current)
        nextTick(() => { if (showSearch.value) inputRef.value?.focus() })
        return
    }

    emit('update:modelValue', option.value)
    isOpen.value = false
}

function removeTag(value: string | number, e: Event) {
    e.stopPropagation()
    if (!props.multiple) return
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
    emit('update:modelValue', current.filter((v) => v !== value))
}

function selectAll() {
    if (!props.multiple) return
    const all = filtered.value.filter((o) => !o.disabled).map((o) => o.value)
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
    if (e.key === 'Enter' && isOpen.value && highlightedIndex.value >= 0) {
        e.preventDefault()
        select(filtered.value[highlightedIndex.value])
        return
    }
}

function scrollToHighlighted() {
    nextTick(() => {
        const el = dropdownRef.value?.querySelector('[data-highlighted]')
        el?.scrollIntoView({ block: 'nearest' })
    })
}

function clickOutside(e: MouseEvent) {
    const target = e.target as HTMLElement
    if (!dropdownRef.value?.contains(target) && !triggerRef.value?.contains(target)) {
        isOpen.value = false
    }
}

function nextTick(fn: () => void) {
    requestAnimationFrame(() => requestAnimationFrame(fn))
}

onMounted(() => document.addEventListener('click', clickOutside))
onBeforeUnmount(() => document.removeEventListener('click', clickOutside))

watch(isOpen, (val) => {
    if (!val) searchQuery.value = ''
})
</script>

<template>
    <div class="relative" :class="{ 'opacity-60 pointer-events-none': disabled }">
        <label v-if="label" :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ label }}
            <span v-if="required" class="text-danger-500">*</span>
        </label>

        <div class="relative">
            <button
            ref="triggerRef"
            type="button"
            :id="id"
            :name="name"
            :disabled="disabled"
            class="w-full min-h-[2.5rem] flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white text-left transition-colors focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
            @click="toggle"
            @keydown="handleKeydown"
        >
            <span class="flex items-center gap-1.5 flex-wrap truncate">
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
                        <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-primary-200 dark:hover:bg-primary-800" @click="removeTag(opt.value, $event as any)">
                            <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </span>
                </template>
                <span v-if="displayText" :class="{ 'text-gray-400 dark:text-gray-500': !selectedOptions.length }">{{ displayText }}</span>
            </span>
            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                class="absolute z-50 w-full rounded-xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900 overflow-hidden"
                :class="placement === 'top' ? 'bottom-full' : 'top-full mt-1'"
            >
                <div v-if="showSearch && placement === 'bottom'" class="border-b border-gray-100 dark:border-surface-700 p-2">
                    <input
                        ref="inputRef"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="searchPlaceholder || 'Search...'"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white focus:ring-0"
                        @keydown.stop="handleKeydown"
                    />
                </div>

                <div v-if="multiple && filtered.length > 1" class="flex gap-2 border-b border-gray-100 dark:border-surface-700 px-3 py-1.5">
                    <button type="button" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400" @click="selectAll">Select all</button>
                    <button type="button" class="text-xs font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" @click="deselectAll">Clear</button>
                </div>

                <div
                    class="overflow-y-auto"
                    :style="{ maxHeight: `${size * 38}px` }"
                >
                    <div
                        v-for="(option, index) in filtered"
                        :key="String(option.value)"
                        :data-highlighted="highlightedIndex === index ? '' : undefined"
                        class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer transition-colors"
                        :class="[
                            option.disabled
                                ? 'opacity-40 cursor-not-allowed'
                                : 'hover:bg-primary-50 dark:hover:bg-primary-900/20',
                            isSelected(option.value)
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 font-semibold'
                                : 'text-gray-700 dark:text-gray-200',
                            highlightedIndex === index && !option.disabled && !isSelected(option.value)
                                ? 'bg-gray-100 dark:bg-surface-700'
                                : '',
                        ]"
                        @click="select(option)"
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

                    <div
                        v-if="filtered.length === 0"
                        class="px-3 py-4 text-center text-sm text-gray-400 dark:text-gray-500"
                    >
                        No results found
                    </div>
                </div>

                <div v-if="showSearch && placement === 'top'" class="border-t border-gray-100 dark:border-surface-700 p-2">
                    <input
                        ref="inputRef"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="searchPlaceholder || 'Search...'"
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
