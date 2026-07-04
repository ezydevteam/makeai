<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import AssistantMessage from './AssistantMessage.vue'

interface Message {
    role: 'user' | 'assistant'
    content: string
    messageHash?: string
}

const props = defineProps<{
    messages: Message[]
    error: string | null
    greeting: string | null
    isLoading: boolean
    sessionId: string
    isAdmin: boolean
    avatarUrl?: string
    assistantName?: string
}>()

defineEmits<{
    (e: 'feedback', data: { messageIndex: number; rating: number; comment?: string }): void
    (e: 'retry'): void
}>()

const container = ref<HTMLElement | null>(null)

const page = usePage()
const $t = (key: string, replace?: Record<string, string | number>) => {
    const translations = (page.props.translations ?? {}) as Record<string, string>
    let text = translations[key] ?? key
    if (replace) {
        for (const [k, v] of Object.entries(replace)) {
            text = text.replace(new RegExp(`:${k}`, 'g'), String(v))
        }
    }
    return text
}

// Auto-scroll to bottom
watch(() => props.messages.length, () => {
    nextTick(() => {
        if (container.value) {
            container.value.scrollTop = container.value.scrollHeight
        }
    })
})

function renderMarkdown(text: string): string {
    const html = text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded text-sm">$1</code>')
        .replace(/\n/g, '<br>')
    return DOMPurify.sanitize(html, { FORBID_TAGS: ['style', 'form', 'input', 'button'], FORBID_ATTR: ['style'] })
}

function copyToClipboard(text: string) {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(text).catch(() => {
            fallbackCopyToClipboard(text)
        })
    } else {
        fallbackCopyToClipboard(text)
    }
}

function fallbackCopyToClipboard(text: string) {
    const el = document.createElement('textarea')
    el.value = text
    el.setAttribute('readonly', '')
    el.style.position = 'absolute'
    el.style.left = '-9999px'
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
}

defineExpose({ container })
</script>

<template>
    <div ref="container" class="ai-messages flex-1 overflow-y-auto px-4 py-3 space-y-3 min-h-[200px]">
        <!-- Greeting / empty state -->
        <div v-if="messages.length === 0 && !isLoading" class="flex flex-col items-center justify-center h-full text-center pt-8 px-2">
            <p class="text-sm text-gray-500 dark:text-gray-400" v-html="renderMarkdown(greeting || $t('Hi! How can I help you today?'))" />
        </div>

        <!-- Messages -->
        <template v-for="(msg, idx) in messages" :key="idx">
            <AssistantMessage
                :message="msg"
                :session-id="sessionId"
                :show-feedback="msg.role === 'assistant'"
                :avatar-url="avatarUrl"
                :assistant-name="assistantName"
                @feedback="(data) => $emit('feedback', { ...data, messageIndex: idx })"
                @copy="copyToClipboard(msg.content)"
            />
        </template>

        <!-- Error -->
        <div v-if="error" class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400 px-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span>{{ error }}</span>
            <button
                class="ml-auto text-xs underline hover:no-underline"
                @click="$emit('retry')"
            >
                {{ $t('Retry') }}
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Thin premium scrollbar for messages list */
.ai-messages::-webkit-scrollbar {
    width: 6px;
}
.ai-messages::-webkit-scrollbar-track {
    background: transparent;
}
.ai-messages::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 3px;
}
.dark .ai-messages::-webkit-scrollbar-thumb {
    background: #374151;
}
.ai-messages::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}
.dark .ai-messages::-webkit-scrollbar-thumb:hover {
    background: #4b5563;
}
</style>
