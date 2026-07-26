<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    open: boolean
    title: string
    message?: string
    confirmLabel?: string
    cancelLabel?: string
    processingLabel?: string
    processing?: boolean
    variant?: 'danger' | 'primary'
    closeOnBackdrop?: boolean
}>(), {
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    processingLabel: 'Processing...',
    processing: false,
    variant: 'danger',
    closeOnBackdrop: true,
})

const emit = defineEmits<{
    cancel: []
    confirm: []
}>()

const { t } = useTranslate()
const modalRef = ref<HTMLElement | null>(null)
const cancelButtonRef = ref<HTMLButtonElement | null>(null)
let previousActiveElement: HTMLElement | null = null

const close = () => {
    if (!props.processing) {
        emit('cancel')
    }
}

const closeFromBackdrop = () => {
    if (props.closeOnBackdrop) {
        close()
    }
}

const handleKeydown = (event: KeyboardEvent) => {
    if (!props.open) {
        return
    }

    if (event.key === 'Escape') {
        close()
        return
    }

    if (event.key !== 'Tab' || !modalRef.value) {
        return
    }

    const focusable = Array.from(
        modalRef.value.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    )

    if (focusable.length === 0) {
        event.preventDefault()
        return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

watch(() => props.open, (isOpen) => {
    document.body.classList.toggle('overflow-hidden', isOpen)

    if (isOpen) {
        previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null
        nextTick(() => cancelButtonRef.value?.focus())
        return
    }

    previousActiveElement?.focus()
    previousActiveElement = null
})

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    document.body.classList.remove('overflow-hidden')
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
            <!-- z-[120] for the same reason as AppModal: the sticky announcement stack
                 (z-[60]) and mobile menu (z-[80]) were painting over a z-50 backdrop. -->
            <div
                v-if="open"
                class="fixed inset-0 z-[120] flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="action-confirm-title"
                :aria-describedby="message ? 'action-confirm-message' : undefined"
                @click.self="closeFromBackdrop"
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
                    <div ref="modalRef" class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-800 dark:bg-surface-900">
                        <div class="p-5">
                            <h3 id="action-confirm-title" class="text-lg font-bold text-gray-900 dark:text-white">{{ t(title) }}</h3>
                            <p v-if="message" id="action-confirm-message" class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                {{ t(message) }}
                            </p>
                            <slot />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                            <button
                                ref="cancelButtonRef"
                                type="button"
                                :disabled="processing"
                                class="rounded-full px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 disabled:opacity-60 dark:text-gray-300 dark:hover:bg-surface-800"
                                @click="close"
                            >
                                {{ t(cancelLabel) }}
                            </button>
                            <button
                                type="button"
                                :disabled="processing"
                                class="rounded-full px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                                :class="variant === 'danger' ? 'bg-red-600 hover:bg-red-500' : 'btn-primary'"
                                @click="emit('confirm')"
                            >
                                {{ processing ? t(processingLabel) : t(confirmLabel) }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
