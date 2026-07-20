<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    activeFiltersCount?: number
    align?: 'left' | 'right'
}>(), {
    activeFiltersCount: 0,
    align: 'right'
})

const { t } = useTranslate()
const isOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

const toggleDropdown = () => {
    isOpen.value = !isOpen.value
}

const closeDropdown = () => {
    isOpen.value = false
}

const handleClickOutside = (event: MouseEvent) => {
    if (!isOpen.value) return
    const target = event.target as Node | null
    if (dropdownRef.value && target && !dropdownRef.value.contains(target)) {
        closeDropdown()
    }
}

const handleEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault()
        closeDropdown()
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside)
    document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside)
    document.removeEventListener('keydown', handleEscape)
})

defineExpose({
    isOpen,
    close: closeDropdown,
    open: () => { isOpen.value = true },
    toggle: toggleDropdown
})
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 h-[38px] w-full sm:w-auto"
            :aria-expanded="isOpen"
            @click="toggleDropdown"
        >
            <i class="ti ti-adjustments-horizontal text-base"></i>
            <span>{{ t('Filters') }}</span>
            <span
                v-if="activeFiltersCount > 0"
                class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary-100 px-1.5 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 animate-pulse"
            >
                {{ activeFiltersCount }}
            </span>
            <i :class="isOpen ? 'ti ti-chevron-up' : 'ti ti-chevron-down'" class="text-sm"></i>
        </button>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                :class="[
                    'absolute z-20 mt-2 w-[min(92vw,20rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-xl dark:border-surface-700 dark:bg-surface-900',
                    align === 'right' ? 'right-0' : 'left-0'
                ]"
            >
                <slot :close="closeDropdown"></slot>
            </div>
        </transition>
    </div>
</template>
