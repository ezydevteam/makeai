import { reactive } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { sanitizeErrorMessage } from '@/Composables/useErrorSanitizer'

export interface ToastItem {
    id: number
    message: string
    type: 'success' | 'error' | 'warning' | 'info'
    title?: string
    duration: number
    removing: boolean
    paused: boolean
}

let nextId = 1

export const toastStore = reactive<{ toasts: ToastItem[] }>({
    toasts: [],
})

function addToast(message: string, type: ToastItem['type'], title?: string, duration = 4500) {
    const toast: ToastItem = {
        id: nextId++,
        message: sanitizeErrorMessage(message),
        type,
        title,
        duration,
        removing: false,
        paused: false,
    }
    toastStore.toasts.push(toast)
}

export function removeToast(id: number) {
    const t = toastStore.toasts.find(toast => toast.id === id)
    if (!t || t.removing) return
    t.removing = true
    setTimeout(() => {
        const idx = toastStore.toasts.findIndex(toast => toast.id === id)
        if (idx !== -1) toastStore.toasts.splice(idx, 1)
    }, 350)
}

export function useToastr() {
    const success = (message: string, title?: string) => addToast(message, 'success', title)
    const error = (message: string, title?: string) => addToast(message, 'error', title)
    const warning = (message: string, title?: string) => addToast(message, 'warning', title)
    const info = (message: string, title?: string) => addToast(message, 'info', title)

    return { success, error, warning, info }
}

export function useFlashToasts() {
    const page = usePage()
    const toast = useToastr()
    let lastFlashSignature = ''

    watch(
        () => page.props.flash as any,
        (flash) => {
            const signature = [flash?.success, flash?.error, flash?.warning, flash?.info]
                .map(value => value ?? '')
                .join('|')

            if (signature === lastFlashSignature) {
                return
            }

            lastFlashSignature = signature

            if (flash?.success) {
                toast.success(flash.success)
                return
            }

            if (flash?.error) {
                toast.error(flash.error)
                return
            }

            if (flash?.warning) {
                toast.warning(flash.warning)
                return
            }

            if (flash?.info) {
                toast.info(flash.info)
            }
        },
        { immediate: true },
    )
}
