<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'

const open = ref(false)

interface ShortcutDef {
    keys: string
    description: string
}

const sections: { title: string; shortcuts: ShortcutDef[] }[] = [
    {
        title: 'Global',
        shortcuts: [
            { keys: '?', description: 'Show this reference' },
            { keys: 'Ctrl+K', description: 'Open command palette' },
            { keys: 'Ctrl+/', description: 'Focus global search' },
            { keys: 'Ctrl+Shift+D', description: 'Toggle dark/light mode' },
            { keys: 'Ctrl+Shift+S', description: 'Go to settings' },
            { keys: 'Ctrl+Shift+N', description: 'New document' },
            { keys: 'Ctrl+Shift+C', description: 'New chat' },
            { keys: 'Ctrl+Shift+L', description: 'Copy last output' },
            { keys: 'Escape', description: 'Close modal / blur input' },
        ],
    },
    {
        title: 'Tool Pages',
        shortcuts: [
            { keys: 'Ctrl+Enter', description: 'Generate / Submit' },
            { keys: 'Ctrl+R', description: 'Regenerate' },
            { keys: 'Ctrl+S', description: 'Save document' },
            { keys: 'Ctrl+C', description: 'Copy output (when focused)' },
            { keys: 'Ctrl+Shift+E', description: 'Open in editor' },
        ],
    },
    {
        title: 'Editor',
        shortcuts: [
            { keys: 'Ctrl+Z', description: 'Undo' },
            { keys: 'Ctrl+Y', description: 'Redo' },
            { keys: 'Ctrl+B', description: 'Bold' },
            { keys: 'Ctrl+I', description: 'Italic' },
            { keys: 'Ctrl+U', description: 'Underline' },
            { keys: '/ (line start)', description: 'Slash command palette' },
        ],
    },
    {
        title: 'Chat',
        shortcuts: [
            { keys: 'Ctrl+Shift+O', description: 'New chat' },
            { keys: 'Ctrl+B', description: 'Toggle sidebar' },
            { keys: 'Enter', description: 'Send message' },
            { keys: 'Shift+Enter', description: 'New line' },
            { keys: 'Escape', description: 'Stop streaming' },
        ],
    },
    {
        title: 'Command Palette',
        shortcuts: [
            { keys: '↑ ↓', description: 'Navigate items' },
            { keys: 'Enter', description: 'Select item' },
            { keys: 'Escape', description: 'Close palette' },
            { keys: 'Type to search', description: 'Fuzzy search all items' },
        ],
    },
]

function show() { open.value = true }
function hide() { open.value = false }

function onKeydown(e: KeyboardEvent) {
    if (!open.value) return
    if (e.key === 'Escape') {
        e.preventDefault()
        hide()
    }
}

onMounted(() => {
    window.addEventListener('shortcuts:show', show)
    window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
    window.removeEventListener('shortcuts:show', show)
    window.removeEventListener('keydown', onKeydown)
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
                class="fixed inset-0 z-[110] flex items-start justify-center bg-black/45 pt-[8vh] backdrop-blur-sm"
                @click.self="hide"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                >
                    <div class="mx-4 w-full max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Keyboard Shortcuts</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Press <kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[10px] font-semibold">?</kbd> to open this reference anytime</p>
                            </div>
                            <button
                                @click="hide"
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-800 transition"
                            >
                                <i class="ti ti-x text-lg"></i>
                            </button>
                        </div>

                        <div class="max-h-[60vh] overflow-y-auto p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="section in sections" :key="section.title">
                                    <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">{{ section.title }}</h3>
                                    <ul class="space-y-2">
                                        <li v-for="sc in section.shortcuts" :key="sc.keys" class="flex items-center justify-between gap-4">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ sc.description }}</span>
                                            <kbd class="shrink-0 inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600 font-mono dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">{{ sc.keys }}</kbd>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
