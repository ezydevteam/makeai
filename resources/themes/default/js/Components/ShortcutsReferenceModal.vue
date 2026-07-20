<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppModal from '@/Components/UI/AppModal.vue'

const open = ref(false)
const page = usePage()
const chatbotEnabled = computed(() => !!page.props.chatbotEnabled)

interface ShortcutDef {
    keys: string
    description: string
}

const sections = computed(() => {
    const list: { title: string; shortcuts: ShortcutDef[] }[] = [
        {
            title: 'Global',
            shortcuts: [
                { keys: '?', description: 'Show this reference' },
                { keys: 'Ctrl+K', description: 'Open command palette' },
                { keys: 'Ctrl+/', description: 'Focus global search' },
                { keys: 'Ctrl+Shift+D', description: 'Toggle dark/light mode' },
                { keys: 'Ctrl+Shift+S', description: 'Go to settings' },
                { keys: 'Ctrl+Shift+N', description: 'New document' },
                ...(chatbotEnabled.value ? [{ keys: 'Ctrl+Shift+C', description: 'New chat' }] : []),
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
    ]

    if (chatbotEnabled.value) {
        list.push({
            title: 'Chat',
            shortcuts: [
                { keys: 'Ctrl+Shift+O', description: 'New chat' },
                { keys: 'Ctrl+B', description: 'Toggle sidebar' },
                { keys: 'Enter', description: 'Send message' },
                { keys: 'Shift+Enter', description: 'New line' },
                { keys: 'Escape', description: 'Stop streaming' },
            ],
        })
    }

    list.push({
        title: 'Command Palette',
        shortcuts: [
            { keys: '↑ ↓', description: 'Navigate items' },
            { keys: 'Enter', description: 'Select item' },
            { keys: 'Escape', description: 'Close palette' },
            { keys: 'Type to search', description: 'Fuzzy search all items' },
        ],
    })

    return list
})

function show() { open.value = true }
function hide() { open.value = false }

onMounted(() => {
    window.addEventListener('shortcuts:show', show)
})

onUnmounted(() => {
    window.removeEventListener('shortcuts:show', show)
})
</script>

<template>
    <AppModal
        :open="open"
        max-width="max-w-2xl"
        @close="hide"
    >
        <template #header>
            <div class="px-6 py-3">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Keyboard Shortcuts</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Press <kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[10px] font-semibold">?</kbd> to open this reference anytime</p>
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="section in sections" :key="section.title">
                <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">{{ section.title }}</h4>
                <ul class="space-y-2">
                    <li v-for="sc in section.shortcuts" :key="sc.keys" class="flex items-center justify-between gap-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ sc.description }}</span>
                        <kbd class="shrink-0 inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600 font-mono dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">{{ sc.keys }}</kbd>
                    </li>
                </ul>
            </div>
        </div>
    </AppModal>
</template>
