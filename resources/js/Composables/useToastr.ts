import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'

interface ToastrOptions {
    closeButton?: boolean
    progressBar?: boolean
    positionClass?: string
    timeOut?: number
}

const defaultOptions: ToastrOptions = {
    closeButton: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 4000,
}

/**
 * Simple toast notification composable.
 * Uses native notification styling (no external toastr dependency).
 * Can be swapped to toastr.js later when installed.
 */
export function useToastr() {
    const show = (message: string, type: 'success' | 'error' | 'warning' | 'info', title?: string) => {
        // Create toast element
        const toast = document.createElement('div')
        toast.className = `toast-notification toast-${type}`
        toast.innerHTML = `
            ${title ? `<div class="toast-title">${title}</div>` : ''}
            <div class="toast-message">${message}</div>
            <button class="toast-close">&times;</button>
        `

        // Get or create container
        let container = document.getElementById('toast-container')
        if (!container) {
            container = document.createElement('div')
            container.id = 'toast-container'
            document.body.appendChild(container)
        }

        container.appendChild(toast)

        // Close button
        toast.querySelector('.toast-close')?.addEventListener('click', () => {
            toast.classList.add('toast-hiding')
            setTimeout(() => toast.remove(), 300)
        })

        // Auto remove
        setTimeout(() => {
            toast.classList.add('toast-hiding')
            setTimeout(() => toast.remove(), 300)
        }, defaultOptions.timeOut)

        // Animate in
        requestAnimationFrame(() => toast.classList.add('toast-visible'))
    }

    const success = (message: string, title?: string) => show(message, 'success', title)
    const error = (message: string, title?: string) => show(message, 'error', title)
    const warning = (message: string, title?: string) => show(message, 'warning', title)
    const info = (message: string, title?: string) => show(message, 'info', title)

    return { success, error, warning, info }
}

/**
 * Watch Inertia flash messages and display toasts automatically.
 * Call this once in your root App/Layout component.
 */
export function useFlashToasts() {
    const page = usePage()
    const toast = useToastr()

    watch(
        () => page.props.flash as unknown as Record<string, string | null>,
        (flash) => {
            if (flash?.success) toast.success(flash.success)
            if (flash?.error) toast.error(flash.error)
            if (flash?.warning) toast.warning(flash.warning)
            if (flash?.info) toast.info(flash.info)
        },
        { immediate: true }
    )
}
