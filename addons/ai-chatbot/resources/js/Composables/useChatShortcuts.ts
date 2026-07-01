import { onMounted, onUnmounted } from 'vue'
import type { useChat } from './useChat'

export function useChatShortcuts(chat: ReturnType<typeof useChat>, options?: { onToggleCollapse?: () => void }) {
    function handleKeydown(e: KeyboardEvent) {
        // Escape — close all open dropdowns
        if (e.key === 'Escape') {
            // handled by Vue click-outside directives automatically
            return
        }

        const mod = e.metaKey || e.ctrlKey

        // Ctrl+K — open command palette / focus search
        if (mod && e.key === 'k') {
            e.preventDefault()
            // focus the sidebar search or first conversation
            const sidebar = document.querySelector('.sidebar') as HTMLElement | null
            if (sidebar) {
                const firstConv = sidebar.querySelector('[class*="cursor-pointer"]') as HTMLElement | null
                firstConv?.focus()
            }
            return
        }

        // Ctrl+Shift+O — new chat
        if (mod && e.shiftKey && e.key === 'O') {
            e.preventDefault()
            chat.newChat()
            return
        }

        // Ctrl+B — toggle sidebar collapse
        if (mod && e.key === 'b') {
            e.preventDefault()
            options?.onToggleCollapse?.()
            return
        }

        // Ctrl+, — open settings
        if (mod && e.key === ',') {
            e.preventDefault()
            return
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown)
    })

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown)
    })
}
