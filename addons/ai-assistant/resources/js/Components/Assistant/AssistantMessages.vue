<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import AssistantMessage from './AssistantMessage.vue'
import { renderMarkdown, handleMarkdownCopy } from '../../Composables/useAssistantMarkdown'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantMessageItem } from '../../types'

const props = defineProps<{
    messages: AssistantMessageItem[]
    error: string | null
    greeting: string | null
    isStreaming: boolean
    sessionId: string
    feedbackEndpoint: string
    avatarUrl?: string | null
    assistantName?: string | null
}>()

defineEmits<{
    (e: 'retry'): void
}>()

const { t } = useTranslate()

const container = ref<HTMLElement | null>(null)

watch(() => props.messages.length, () => {
    nextTick(() => {
        if (container.value) {
            container.value.scrollTop = container.value.scrollHeight
        }
    })
})

defineExpose({ container })
</script>

<template>
    <div ref="container" class="ai-messages min-h-0 flex-1 overflow-y-auto px-4 py-3 space-y-3">
        <!-- Greeting / empty state -->
        <div
            v-if="messages.length === 0 && !isStreaming"
            class="flex flex-col items-center justify-center h-full text-center pt-8 px-2"
        >
            <div
                class="ai-markdown text-sm text-gray-500 dark:text-gray-400"
                v-html="renderMarkdown(greeting || t('Hi! How can I help you today?'))"
                @click="handleMarkdownCopy"
            />
        </div>

        <!-- Messages -->
        <AssistantMessage
            v-for="(msg, idx) in messages"
            :key="idx"
            :message="msg"
            :session-id="sessionId"
            :feedback-endpoint="feedbackEndpoint"
            :show-feedback="msg.role === 'assistant'"
            :avatar-url="avatarUrl"
            :assistant-name="assistantName"
        />

        <!-- Error -->
        <div v-if="error" class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400 px-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span>{{ error }}</span>
            <button
                class="ml-auto text-xs underline hover:no-underline shrink-0"
                @click="$emit('retry')"
            >
                {{ t('Retry') }}
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
