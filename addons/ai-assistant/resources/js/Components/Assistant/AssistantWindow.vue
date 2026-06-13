<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AssistantHeader from './AssistantHeader.vue'
import AssistantMessages from './AssistantMessages.vue'
import AssistantInput from './AssistantInput.vue'

interface Message {
    role: 'user' | 'assistant'
    content: string
    messageHash?: string
}

const props = defineProps<{
    settings: Record<string, any>
    sessionId: string
}>()

defineEmits<{
    (e: 'close'): void
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

const messages = ref<Message[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)
const messagesContainer = ref<HTMLElement | null>(null)

const isAdmin = computed(() => !!(page.props.admin as any)?.user)

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })
}

async function sendMessage(text: string) {
    if (!text.trim() || isLoading.value) return

    error.value = null
    messages.value.push({ role: 'user', content: text })
    scrollToBottom()
    isLoading.value = true

    const assistantMsg: Message = { role: 'assistant', content: '' }
    messages.value.push(assistantMsg)

    // Build conversation history (exclude the just-added assistant placeholder)
    const history = messages.value
        .slice(0, -1)
        .filter(m => m.role === 'user' || m.role === 'assistant')
        .slice(-20)

    const endpoint = isAdmin.value ? '/api/admin/assistant/chat' : '/api/assistant/chat'

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': '*/*',
            },
            body: JSON.stringify({
                message: text,
                history: history.map(m => ({ role: m.role, content: m.content })),
                session_id: props.sessionId,
                context_page: window.location.pathname,
            }),
        })

        if (response.status === 429) {
            const data = await response.json()
            error.value = data.error === 'daily_limit_reached'
                ? $t('Daily message limit reached. Please try again tomorrow.')
                : $t('Too many requests. Please slow down.')
            messages.value.pop() // remove assistant placeholder
            isLoading.value = false
            return
        }

        if (!response.ok) {
            error.value = $t('Something went wrong. Please try again.')
            messages.value.pop()
            isLoading.value = false
            return
        }

        const reader = response.body?.getReader()
        if (!reader) {
            error.value = $t('Unable to read response.')
            messages.value.pop()
            isLoading.value = false
            return
        }

        const decoder = new TextDecoder()
        let buffer = ''
        let streamStarted = false

        try {
            while (true) {
                const { done, value } = await reader.read()
                if (done) break

                const chunk = decoder.decode(value, { stream: true })
                buffer += chunk

                // Handle the first READY heartbeat — consume it, don't show
                if (!streamStarted && buffer.includes('READY\n')) {
                    const idx = buffer.indexOf('READY\n')
                    buffer = buffer.slice(idx + 6)
                    streamStarted = true
                    assistantMsg.content = ''
                    scrollToBottom()
                    continue
                }

                // Handle ERROR lines
                if (buffer.includes('\nERROR:')) {
                    const errorIdx = buffer.indexOf('\nERROR:')
                    if (errorIdx > 0) {
                        const contentBefore = buffer.slice(0, errorIdx).trim()
                        assistantMsg.content = contentBefore
                    }
                    const errorLine = buffer.slice(buffer.indexOf('ERROR:'))
                    const errorMsg = errorLine.split('\n')[0].replace('ERROR:', '').trim()
                    error.value = errorMsg || $t('Something went wrong. Please try again.')
                    break
                }

                // Streaming content — update in real-time
                if (streamStarted || buffer.trim()) {
                    streamStarted = true
                    assistantMsg.content = buffer.trim()
                    scrollToBottom()
                }
            }

            if (!error.value) {
                assistantMsg.content = buffer.trim()
                assistantMsg.messageHash = await sha256(assistantMsg.content)
            }
        } finally {
            reader.releaseLock()
        }
    } catch (e: any) {
        error.value = $t('Connection lost. Please try again.')
        if (assistantMsg.content === '') {
            messages.value.pop()
        }
    } finally {
        isLoading.value = false
        scrollToBottom()
    }
}

async function sha256(message: string): Promise<string> {
    const encoder = new TextEncoder()
    const data = encoder.encode(message)
    const hashBuffer = await crypto.subtle.digest('SHA-256', data)
    const hashArray = Array.from(new Uint8Array(hashBuffer))
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
}

function onFeedback(data: { messageIndex: number; rating: number; comment?: string }) {
    // Feedback is handled at the message level (see AssistantMessages)
}

function retryLast() {
    // Find the last user message and resend
    const lastUserMsg = messages.value.filter(m => m.role === 'user').pop()
    // Remove last assistant response
    const lastAssistantIdx = messages.value.map(m => m.role).lastIndexOf('assistant')
    if (lastAssistantIdx >= 0) messages.value.splice(lastAssistantIdx, 1)
    if (lastUserMsg) {
        sendMessage(lastUserMsg.content)
    }
}
</script>

<template>
    <div
        class="ai-window w-[380px] sm:w-[420px] max-h-[600px] bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden transition-all duration-200"
    >
        <AssistantHeader
            :settings="settings"
            @close="$emit('close')"
        />

        <AssistantMessages
            ref="messagesContainer"
            :messages="messages"
            :error="error"
            :greeting="settings.greeting_message"
            :is-loading="isLoading"
            :session-id="sessionId"
            :is-admin="isAdmin"
            @feedback="onFeedback"
            @retry="retryLast"
        />

        <AssistantInput
            :disabled="isLoading"
            :is-admin="isAdmin"
            @send="sendMessage"
        />
    </div>
</template>

<style scoped>
.ai-window {
    animation: ai-slide-up 0.2s ease-out;
}

@keyframes ai-slide-up {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
