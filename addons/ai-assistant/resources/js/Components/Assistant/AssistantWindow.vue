<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue'
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

interface AdminProp {
    user?: {
        id: number
        name: string
        email: string
        avatar?: string
    }
}

const storageKey = `ai_assistant_history_${props.sessionId}`

const loadHistory = (): Message[] => {
    try {
        const now = Date.now()
        // Clean up expired chat history keys (older than 24 hours) to save localStorage space
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i)
            if (key && key.startsWith('ai_assistant_history_')) {
                try {
                    const item = localStorage.getItem(key)
                    if (item) {
                        const parsed = JSON.parse(item)
                        if (now - (parsed.timestamp ?? 0) > 24 * 60 * 60 * 1000) {
                            localStorage.removeItem(key)
                            i--
                        }
                    }
                } catch {
                    localStorage.removeItem(key)
                    i--
                }
            }
        }

        const stored = localStorage.getItem(storageKey)
        if (stored) {
            const data = JSON.parse(stored)
            const isRecent = now - (data.timestamp ?? 0) < 24 * 60 * 60 * 1000
            if (isRecent && Array.isArray(data.messages)) {
                // Filter out any trailing empty/thinking assistant messages
                const list = [...data.messages]
                while (list.length > 0 && list[list.length - 1].role === 'assistant' && !list[list.length - 1].content) {
                    list.pop()
                }
                return list
            }
        }
    } catch {
        // ignore
    }
    return []
}

const messages = ref<Message[]>(loadHistory())
const isLoading = ref(false)
const error = ref<string | null>(null)
const messagesContainer = ref<HTMLElement | null>(null)

const isAdmin = computed(() => {
    const adminProps = page.props.admin as AdminProp | undefined
    return !!adminProps?.user && window.location.pathname.startsWith('/admin')
})

const showFileUpload = computed(() => isAdmin.value || !!props.settings.allow_file_upload)
const allowedFileTypes = computed(() => props.settings.allowed_file_types || 'pdf,docx,txt,csv,png,jpg')

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })
}

async function sendMessage(text: string, file?: { name: string; text: string }) {
    if ((!text.trim() && !file) || isLoading.value) return

    error.value = null
    const displayMsg = file ? `${text}\n\n📎 *${file.name}*` : text
    messages.value.push({ role: 'user', content: displayMsg })
    scrollToBottom()
    isLoading.value = true

    messages.value.push({ role: 'assistant', content: '' })

    // Get the reactive proxy reference — mutations on this will trigger Vue reactivity
    const assistantIdx = messages.value.length - 1

    // Build conversation history: exclude the current user message and assistant placeholder
    const history = messages.value
        .slice(0, -2)
        .filter(m => m.role === 'user' || m.role === 'assistant')
        .slice(-20)

    const endpoint = isAdmin.value ? '/api/admin/assistant/chat' : '/api/assistant/chat'

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

    const payloadMessage = file 
        ? `[Attached File: ${file.name}]\n---\n${file.text}\n---\n\n${text}` 
        : text

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                message: payloadMessage,
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
            messages.value.splice(assistantIdx, 1)
            isLoading.value = false
            return
        }

        if (!response.ok) {
            error.value = $t('Something went wrong. Please try again.')
            messages.value.splice(assistantIdx, 1)
            isLoading.value = false
            return
        }

        const reader = response.body?.getReader()
        if (!reader) {
            error.value = $t('Unable to read response.')
            messages.value.splice(assistantIdx, 1)
            isLoading.value = false
            return
        }

        const decoder = new TextDecoder()
        let buffer = ''
        let streamStarted = false

        try {
            while (true) {
                const { done, value } = await reader.read()
                if (done) {
                    break
                }

                const chunk = decoder.decode(value, { stream: true })
                buffer += chunk

                // Handle the first READY heartbeat — consume it, don't show
                if (!streamStarted && buffer.includes('READY\n')) {
                    const idx = buffer.indexOf('READY\n')
                    buffer = buffer.slice(idx + 6)
                    streamStarted = true
                    messages.value[assistantIdx].content = ''
                    scrollToBottom()
                    continue
                }

                // Handle ERROR lines
                if (buffer.startsWith('ERROR:') || buffer.includes('\nERROR:')) {
                    const errorIdx = buffer.indexOf('ERROR:')
                    if (errorIdx > 0) {
                        const contentBefore = buffer.slice(0, errorIdx).trim()
                        messages.value[assistantIdx].content = contentBefore
                    } else {
                        messages.value.splice(assistantIdx, 1)
                    }
                    const errorLine = buffer.slice(errorIdx)
                    const errorMsg = errorLine.split('\n')[0].replace('ERROR:', '').trim()
                    error.value = errorMsg || $t('Something went wrong. Please try again.')
                    break
                }

                // Streaming content — update in real-time
                if (streamStarted || buffer.trim()) {
                    streamStarted = true
                    messages.value[assistantIdx].content = buffer.trim()
                    scrollToBottom()
                }
            }

            if (!error.value && messages.value[assistantIdx]) {
                messages.value[assistantIdx].content = buffer.trim()
                messages.value[assistantIdx].messageHash = await sha256(buffer.trim())
            }
        } finally {
            reader.releaseLock()
        }
    } catch (e: unknown) {
        error.value = $t('Connection lost. Please try again.')
        if (messages.value[assistantIdx]?.content === '') {
            messages.value.splice(assistantIdx, 1)
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
    // Find the last user message content
    const lastUserMsg = messages.value.filter(m => m.role === 'user').pop()
    if (!lastUserMsg) return

    const lastContent = lastUserMsg.content

    // Remove last assistant response
    const lastAssistantIdx = messages.value.map(m => m.role).lastIndexOf('assistant')
    if (lastAssistantIdx >= 0) messages.value.splice(lastAssistantIdx, 1)

    // Remove last user message (sendMessage will re-add it)
    const lastUserIdx = messages.value.map(m => m.role).lastIndexOf('user')
    if (lastUserIdx >= 0) messages.value.splice(lastUserIdx, 1)

    error.value = null
    sendMessage(lastContent)
}

watch(messages, (newVal) => {
    try {
        localStorage.setItem(storageKey, JSON.stringify({
            timestamp: Date.now(),
            messages: newVal
        }))
    } catch {
        // ignore storage quota errors
    }
}, { deep: true })

onMounted(() => {
    if (messages.value.length > 0) {
        scrollToBottom()
    }
})
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
            :avatar-url="settings.avatar_url"
            :assistant-name="settings.assistant_name"
            @feedback="onFeedback"
            @retry="retryLast"
        />

        <AssistantInput
            :disabled="isLoading"
            :is-admin="isAdmin"
            :allow-upload="showFileUpload"
            :allowed-types="allowedFileTypes"
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
