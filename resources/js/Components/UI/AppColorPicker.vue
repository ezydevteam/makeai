<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    modelValue: string
    label?: string
    error?: string
    placeholder?: string
    disabled?: boolean
    id?: string
}>(), {
    label: '',
    error: '',
    placeholder: '#000000',
    disabled: false,
    id: undefined,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const { t } = useTranslate()

const swatches = [
    '#ef4444', '#f97316', '#eab308', '#22c55e',
    '#10b981', '#14b8a6', '#06b6d4', '#3b82f6',
    '#6366f1', '#8b5cf6', '#d946ef', '#ec4899',
    '#f43f5e', '#64748b', '#1e293b', '#000000',
    '#ffffff', '#f8fafc',
]

const isOpen = ref(false)
const wrapperRef = ref<HTMLElement | null>(null)
const pickerRef = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLElement | null>(null)
const nativeInput = ref<HTMLInputElement | null>(null)

const initialValue = ref(props.modelValue || '')

watch(() => props.modelValue, (newVal) => {
    if (!initialValue.value && newVal) {
        initialValue.value = newVal
    }
})

const resetColor = () => {
    hexValue.value = initialValue.value
}

const hexValue = computed({
    get: () => props.modelValue || '',
    set: (val) => emit('update:modelValue', val),
})

const selectColor = (color: string) => {
    hexValue.value = color
}

const openNative = () => {
    nativeInput.value?.click()
}

const onNativeChange = (e: Event) => {
    hexValue.value = (e.target as HTMLInputElement).value
}

const toggle = () => {
    if (props.disabled) return
    isOpen.value = !isOpen.value
}

const close = () => {
    isOpen.value = false
}

const clickOutside = (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!wrapperRef.value?.contains(target)) {
        close()
    }
}

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') close()
}

onMounted(() => {
    document.addEventListener('click', clickOutside)
    document.addEventListener('keydown', handleKeydown)
})
onUnmounted(() => {
    document.removeEventListener('click', clickOutside)
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <div ref="wrapperRef" class="relative w-full min-w-0">
        <label v-if="label" :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ label }}
        </label>

        <!-- Trigger -->
        <div ref="triggerRef" class="relative w-full">
            <span
                class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 rounded-md border border-gray-300 shadow-sm dark:border-surface-600"
                :style="{ backgroundColor: hexValue || '#cccccc' }"
            />
            <input
                :id="id"
                :value="hexValue"
                type="text"
                :placeholder="placeholder"
                :disabled="disabled"
                class="w-full cursor-pointer rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                :class="{ 'opacity-60 pointer-events-none': disabled }"
                readonly
                @click.stop="toggle"
            />
        </div>

        <!-- Popover -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                ref="pickerRef"
                class="absolute left-0 top-full z-50 mt-2 w-full min-w-[16rem] rounded-xl border border-gray-200 bg-white p-3 shadow-lg sm:w-auto dark:border-surface-700 dark:bg-surface-900"
            >
                <!-- Hex input -->
                <div class="mb-3 flex items-center gap-1.5">
                    <span
                        class="h-7 w-7 shrink-0 rounded-md border border-gray-300 dark:border-surface-600"
                        :style="{ backgroundColor: hexValue || '#cccccc' }"
                    />
                    <input
                        v-model="hexValue"
                        type="text"
                        placeholder="#000000"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                </div>

                <!-- Swatches -->
                <div class="mb-3 flex flex-wrap gap-[2px]">
                    <button
                        v-for="swatch in swatches"
                        :key="swatch"
                        type="button"
                        class="h-5 w-5 rounded-sm border border-gray-200 transition-transform hover:scale-125 focus:outline-none dark:border-surface-600"
                        :class="{ 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-surface-900': hexValue.toLowerCase() === swatch.toLowerCase() }"
                        :style="{ backgroundColor: swatch }"
                        :aria-label="`Pick color ${swatch}`"
                        @click="selectColor(swatch)"
                    />
                </div>

                <!-- Native color picker & Reset -->
                <div class="flex gap-2">
                    <input
                        ref="nativeInput"
                        type="color"
                        :value="hexValue"
                        class="sr-only"
                        @input="onNativeChange"
                    />
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                        @click="openNative"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                        {{ t('Custom') }}
                    </button>
                    <button
                        v-if="hexValue.toLowerCase() !== initialValue.toLowerCase()"
                        type="button"
                        class="flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                        :title="t('Reset color')"
                        @click="resetColor"
                    >
                        <i class="ti ti-rotate text-sm" />
                    </button>
                </div>
            </div>
        </Transition>

        <span v-if="error" class="mt-1 block text-xs text-danger-600">{{ error }}</span>
    </div>
</template>
