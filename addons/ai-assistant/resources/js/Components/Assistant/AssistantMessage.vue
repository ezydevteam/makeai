<script setup lang="ts">
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface Message {
    role: 'user' | 'assistant'
    content: string
    messageHash?: string
}

const props = defineProps<{
    message: Message
    sessionId: string
    showFeedback: boolean
    avatarUrl?: string
    assistantName?: string
}>()

const emit = defineEmits<{
    (e: 'feedback', data: { rating: number; comment?: string }): void
    (e: 'copy'): void
}>()

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

const hasRatedUp = ref(false)
const hasRatedDown = ref(false)
const copied = ref(false)

function renderMarkdown(text: string): string {
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded text-xs">$1</code>')
        .replace(/\n/g, '<br>')
}

function rate(rating: number) {
    if (rating === 1) {
        hasRatedUp.value = true
        hasRatedDown.value = false
    } else {
        hasRatedDown.value = true
        hasRatedUp.value = false
    }

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

    fetch('/api/assistant/feedback', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
            session_id: props.sessionId,
            message_hash: props.message.messageHash ?? '',
            rating,
            context_page: window.location.pathname,
        }),
    }).catch(() => {})

    emit('feedback', { rating })
}

function onCopy() {
    emit('copy')
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
    <div
        class="ai-message flex gap-2"
        :class="message.role === 'user' ? 'justify-end' : ''"
    >
        <!-- User message (right-aligned) -->
        <div
            v-if="message.role === 'user'"
            class="max-w-[80%] rounded-xl px-4 py-2 text-sm whitespace-pre-wrap"
            style="background: var(--ai-accent, #1F75FE); color: #fff;"
        >
            <span v-html="renderMarkdown(message.content)" />
        </div>

        <!-- Assistant message (left-aligned) -->
        <template v-else>
            <div class="w-6 h-6 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0 flex items-center justify-center mt-0.5">
                <img
                    v-if="avatarUrl"
                    :src="avatarUrl"
                    :alt="assistantName"
                    class="w-full h-full object-cover"
                />
                <svg v-else class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="bg-gray-100 dark:bg-gray-800 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                    <span v-if="message.content" v-html="renderMarkdown(message.content)" />
                    <span v-else class="ai-typing-dots text-gray-500 dark:text-gray-400">Thinking</span>
                </div>

                <!-- Actions: copy + thumbs -->
                <div v-if="showFeedback && message.content" class="flex items-center gap-1 mt-1 px-1">
                    <button
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                        :title="$t('Copy')"
                        @click="onCopy"
                    >
                        <i v-if="!copied" class="ti ti-copy text-[14px] leading-none block"></i>
                        <i v-else class="ti ti-check text-[14px] leading-none block text-green-500"></i>
                    </button>

                    <button
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        :class="hasRatedUp ? 'text-green-500' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                        :title="$t('Helpful')"
                        @click="rate(1)"
                    >
                        <i class="ti ti-thumb-up text-[14px] leading-none block"></i>
                    </button>

                    <button
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        :class="hasRatedDown ? 'text-red-500' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                        :title="$t('Not helpful')"
                        @click="rate(-1)"
                    >
                        <i class="ti ti-thumb-down text-[14px] leading-none block"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.ai-typing-dots::after {
    content: '';
    animation: ai-dots 1.4s infinite;
}

@keyframes ai-dots {
    0% { content: ''; }
    25% { content: '.'; }
    50% { content: '..'; }
    75% { content: '...'; }
}
</style>
