<script setup lang="ts">
import { onBeforeUnmount, watch, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    open: boolean
    maxWidth?: string
    title?: string
    subtitle?: string
    closeOnOutsideClick?: boolean
    closeOnEsc?: boolean
    hasForm?: boolean
    cancelText?: string | null
    confirmText?: string
    confirmLoading?: boolean
    confirmLoadingText?: string
    confirmIcon?: string
    confirmDisabled?: boolean
    confirmVariant?: 'admin' | 'submit' | 'delete'
    overflowVisible?: boolean
}>(), {
    maxWidth: 'max-w-2xl',
    title: '',
    subtitle: '',
    closeOnOutsideClick: true,
    closeOnEsc: true,
    hasForm: false,
    cancelText: 'Cancel',
    confirmText: '',
    confirmLoading: false,
    confirmLoadingText: '',
    confirmIcon: '',
    confirmDisabled: false,
    confirmVariant: 'submit',
    overflowVisible: false,
})

const { t } = useTranslate()
const page = usePage()

const isFrontend = computed(() => {
    try {
        return !page.url.startsWith('/admin')
    } catch {
        return true
    }
})

const emit = defineEmits<{
    close: []
    submit: []
    confirm: []
}>()

const handleClose = () => {
    emit('close')
}

const handleOutsideClick = () => {
    if (props.closeOnOutsideClick) {
        handleClose()
    }
}

const handleKeyDown = (e: KeyboardEvent) => {
    if (props.open && props.closeOnEsc && e.key === 'Escape') {
        handleClose()
    }
}

// Watch 'open' prop to handle scrolling of document body & keyboard listeners
watch(() => props.open, (value) => {
    if (value) {
        document.body.classList.add('overflow-hidden')
        window.addEventListener('keydown', handleKeyDown)
    } else {
        document.body.classList.remove('overflow-hidden')
        window.removeEventListener('keydown', handleKeyDown)
    }
}, { immediate: true })

onBeforeUnmount(() => {
    document.body.classList.remove('overflow-hidden')
    window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="handleOutsideClick"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <div :class="{ 'frontend-theme': isFrontend }" class="contents">
                        <component
                            :is="hasForm ? 'form' : 'section'"
                            v-if="open"
                            class="flex max-h-[90vh] w-full flex-col rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900"
                            :class="[maxWidth, overflowVisible ? 'overflow-visible' : 'overflow-hidden']"
                            @submit.prevent="hasForm ? emit('submit') : null"
                        >
                            <!-- Header -->
                            <slot name="header">
                                <div class="flex items-start justify-between gap-4 rounded-t-2xl border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                                    <div>
                                        <h3 v-if="title" class="text-lg font-bold text-gray-900 dark:text-white">{{ title }}</h3>
                                        <p v-if="subtitle" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 dark:text-gray-500 dark:hover:bg-surface-800 dark:hover:text-gray-400"
                                        @click="handleClose"
                                    >
                                        <i class="ti ti-x text-lg"></i>
                                    </button>
                                </div>
                            </slot>

                            <!-- Body (default slot) -->
                            <div class="flex-1 min-h-0 p-6 space-y-6" :class="overflowVisible ? 'overflow-visible' : 'overflow-y-auto'">
                                <slot></slot>
                            </div>

                            <!-- Footer -->
                            <div v-if="$slots.footer || cancelText !== null || confirmText" class="shrink-0 rounded-b-2xl border-t border-gray-100 bg-gray-50/80 px-6 py-3 dark:border-surface-800 dark:bg-surface-950">
                                <slot name="footer">
                                    <div class="flex items-center justify-end gap-3">
                                        <button
                                            v-if="cancelText !== null"
                                            type="button"
                                            class="inline-flex items-center justify-center border border-gray-200 bg-white px-5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                                            :class="confirmVariant === 'admin' ? 'rounded-xl' : 'rounded-full'"
                                            @click="handleClose"
                                        >
                                            {{ t(cancelText) }}
                                        </button>
                                        <button
                                            v-if="confirmText"
                                            :type="hasForm ? 'submit' : 'button'"
                                            :disabled="confirmLoading || confirmDisabled"
                                            class="inline-flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60"
                                            :class="confirmVariant === 'submit' ? 'btn-primary' : confirmVariant === 'delete' ? 'btn-danger' : 'btn-primary-admin'"
                                            @click="!hasForm ? emit('confirm') : null"
                                        >
                                            <i v-if="confirmLoading" class="ti ti-loader-2 animate-spin text-base"></i>
                                            <i v-else-if="confirmIcon" :class="confirmIcon" class="text-base"></i>
                                            <span>{{ confirmLoading && confirmLoadingText ? confirmLoadingText : confirmText }}</span>
                                        </button>
                                    </div>
                                </slot>
                            </div>
                        </component>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
