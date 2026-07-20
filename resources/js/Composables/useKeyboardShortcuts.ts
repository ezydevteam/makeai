import { onMounted, onUnmounted, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'

export interface ShortcutHandler {
    key: string
    ctrl?: boolean
    shift?: boolean
    alt?: boolean
    action: () => void
    description: string
}

function isTypingInInput(): boolean {
    const el = document.activeElement
    if (!el) return false
    const tag = el.tagName.toLowerCase()
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true
    if ((el as HTMLElement).isContentEditable) return true
    if (el.getAttribute('role') === 'textbox') return true
    return false
}

export function useKeyboardShortcuts(
    handlers: ShortcutHandler[],
    options?: { enabled?: Ref<boolean>; disableWhenTyping?: boolean },
) {
    const disableWhenTyping = options?.disableWhenTyping ?? true

    function onKeydown(e: KeyboardEvent) {
        if (options?.enabled && !options.enabled.value) return
        if (disableWhenTyping && isTypingInInput()) {
            if (e.key === 'Escape') {
                ;(document.activeElement as HTMLElement)?.blur()
            }
            return
        }

        const mod = e.ctrlKey || e.metaKey
        const eventKey = typeof e.key === 'string' ? e.key.toLowerCase() : ''

        for (const h of handlers) {
            if (typeof h.key !== 'string' || h.key.length === 0) {
                continue
            }

            const keyMatch = eventKey === h.key.toLowerCase()
            const ctrlMatch = h.ctrl ? mod : !mod
            const shiftMatch = h.shift ? e.shiftKey : !e.shiftKey
            const altMatch = h.alt ? e.altKey : !e.altKey

            if (keyMatch && ctrlMatch && shiftMatch && altMatch) {
                e.preventDefault()
                h.action()
                return
            }
        }

        if (e.key === 'Escape') {
            ;(document.activeElement as HTMLElement)?.blur()
        }
    }

    onMounted(() => document.addEventListener('keydown', onKeydown))
    onUnmounted(() => document.removeEventListener('keydown', onKeydown))
}

export function useGlobalShortcuts(extra: ShortcutHandler[] = []) {
    const toggleDarkMode = () => {
        const html = document.documentElement
        if (html.classList.contains('dark')) {
            html.classList.remove('dark')
        } else {
            html.classList.add('dark')
        }
        fetch('/profile/theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ theme: html.classList.contains('dark') ? 'dark' : 'light' }),
        }).catch(() => {})
    }

    const handlers: ShortcutHandler[] = [
        { key: '?', description: 'Open shortcuts reference', action: () => window.dispatchEvent(new CustomEvent('shortcuts:show')) },
        { key: '/', ctrl: true, description: 'Focus global search', action: () => {
            const searchInput = document.querySelector<HTMLInputElement>('[data-global-search]')
            searchInput?.focus()
        }},
        { key: 'D', ctrl: true, shift: true, description: 'Toggle dark/light mode', action: toggleDarkMode },
        { key: 'S', ctrl: true, shift: true, description: 'Go to settings', action: () => router.visit('/user/dashboard/profile') },
        { key: 'N', ctrl: true, shift: true, description: 'New document', action: () => router.visit('/ai-tools') },
        { key: 'C', ctrl: true, shift: true, description: 'New chat', action: () => router.visit('/chat') },
        { key: 'L', ctrl: true, shift: true, description: 'Copy last output', action: () => {
            const outputs = document.querySelectorAll('[data-last-output]')
            if (outputs.length > 0) {
                navigator.clipboard.writeText((outputs[0] as HTMLElement).innerText).catch(() => {})
            }
        }},
        ...extra,
    ]

    function onKeydown(e: KeyboardEvent) {
        if (isTypingInInput()) {
            if (e.key === 'Escape') {
                ;(document.activeElement as HTMLElement)?.blur()
            }
            return
        }

        const mod = e.ctrlKey || e.metaKey
        const eventKey = typeof e.key === 'string' ? e.key.toLowerCase() : ''

        for (const h of handlers) {
            if (typeof h.key !== 'string' || h.key.length === 0) {
                continue
            }

            const keyMatch = eventKey === h.key.toLowerCase()
            const ctrlMatch = h.ctrl ? mod : !mod
            const shiftMatch = h.shift ? e.shiftKey : !e.shiftKey
            const altMatch = h.alt ? e.altKey : !e.altKey

            if (keyMatch && ctrlMatch && shiftMatch && altMatch) {
                e.preventDefault()
                h.action()
                return
            }
        }

        if (e.key === 'Escape') {
            ;(document.activeElement as HTMLElement)?.blur()
        }
    }

    document.addEventListener('keydown', onKeydown)
}

export function useToolPageShortcuts(options: {
    onGenerate?: () => void
    onRegenerate?: () => void
    onSave?: () => void
    onCopy?: () => void
    onOpenInEditor?: () => void
}) {
    const handlers: ShortcutHandler[] = []

    if (options.onGenerate) {
        handlers.push({ key: 'Enter', ctrl: true, description: 'Generate', action: options.onGenerate })
    }
    if (options.onRegenerate) {
        handlers.push({ key: 'r', ctrl: true, description: 'Regenerate', action: options.onRegenerate })
    }
    if (options.onSave) {
        handlers.push({ key: 's', ctrl: true, description: 'Save document', action: options.onSave })
    }
    if (options.onCopy) {
        handlers.push({ key: 'c', ctrl: true, description: 'Copy output', action: options.onCopy })
    }
    if (options.onOpenInEditor) {
        handlers.push({ key: 'E', ctrl: true, shift: true, description: 'Open in editor', action: options.onOpenInEditor })
    }

    useKeyboardShortcuts(handlers)
}
