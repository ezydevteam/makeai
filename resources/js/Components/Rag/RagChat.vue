<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted, computed, watch } from 'vue'
import { marked } from 'marked'
import RagSourceChips from '@/Components/Rag/RagSourceChips.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import { sanitizeErrorMessage } from '@/Composables/useErrorSanitizer'

interface Source { doc: string; chunk?: number; score?: number; snippet?: string; start?: number; doc_label?: string }
interface Message {
    id: string
    role: 'user' | 'assistant'
    content: string
    sources?: Source[]
    input_tokens?: number
    output_tokens?: number
    credits_used?: number
    created_at?: string
}

const props = defineProps<{
    sessionId: string
    toolSlug: string
    sourceType?: 'file' | 'url' | 'youtube' | 'collection'
    mode: string
}>()
const { t } = useTranslate()
const toastr = useToastr()
const copiedId = ref<string | null>(null)

const messages = ref<Message[]>([])
const input = ref('')
const isStreaming = ref(false)
const isLoading = ref(true)
const streamingContent = ref('')
const streamingSources = ref<Source[]>([])
const error = ref<string | null>(null)
const abortController = ref<AbortController | null>(null)
const chatContainer = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)

const reasoningVerbs = [
    t('Thinking'),
    t('Retrieving relevant sections'),
    t('Analyzing context'),
    t('Formulating answer')
]
const reasoningIndex = ref(0)
const currentReasoningVerb = computed(() => reasoningVerbs[reasoningIndex.value])
let reasoningInterval: ReturnType<typeof setInterval> | null = null

watch(isStreaming, (streaming) => {
    if (streaming) {
        reasoningIndex.value = 0
        if (reasoningInterval) clearInterval(reasoningInterval)
        reasoningInterval = setInterval(() => {
            reasoningIndex.value = (reasoningIndex.value + 1) % reasoningVerbs.length
        }, 2000)
    } else {
        if (reasoningInterval) {
            clearInterval(reasoningInterval)
            reasoningInterval = null
        }
    }
})

onUnmounted(() => {
    if (reasoningInterval) clearInterval(reasoningInterval)
})

onMounted(async () => { 
    await loadMessages()
    nextTick(() => {
        inputRef.value?.focus()
    })
})

watch(() => props.sessionId, async (newId) => {
    if (newId) {
        await loadMessages()
        nextTick(() => {
            inputRef.value?.focus()
        })
    }
})

async function loadMessages() {
    isLoading.value = true
    try {
        const res = await fetch(`/tools/rag/sessions/${props.sessionId}`, {
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
        })
        if (res.ok) {
            const data = await res.json()
            messages.value = data.messages || []
        }
    } catch {
    } finally {
        isLoading.value = false
    }
}

function getCsrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

async function sendMessage() {
    const trimmed = input.value.trim()
    if (!trimmed || isStreaming.value) return
    error.value = null
    messages.value.push({ id: crypto.randomUUID(), role: 'user', content: trimmed })
    input.value = ''
    scrollToBottom()
    nextTick(() => {
        inputRef.value?.focus()
    })
    isStreaming.value = true
    streamingContent.value = ''
    streamingSources.value = []
    abortController.value = new AbortController()

    try {
        const res = await fetch(`/tools/rag/sessions/${props.sessionId}/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
            signal: abortController.value.signal,
            body: JSON.stringify({ message: trimmed, mode: props.mode }),
        })
        if (!res.ok) {
            const err = await res.json().catch(() => ({ message: 'Request failed' }))
            throw new Error(err.message || 'Request failed')
        }
        const reader = res.body?.getReader()
        if (!reader) throw new Error('Streaming not supported')

        const decoder = new TextDecoder()
        let buffer = ''
        let usageStats: Record<string, unknown> = {}

        while (true) {
            const { done, value } = await reader.read()
            if (done) break
            buffer += decoder.decode(value, { stream: true })
            const lines = buffer.split('\n')
            buffer = lines.pop() || ''
            for (const line of lines) {
                if (!line.startsWith('data: ')) continue
                try {
                    const event = JSON.parse(line.slice(6))
                    if (event.type === 'sources') streamingSources.value = event.items || []
                    else if (event.type === 'token') { streamingContent.value += event.content; scrollToBottom() }
                    else if (event.type === 'usage') usageStats = event
                    else if (event.type === 'error') throw new Error(event.message)
                } catch (e) { if (e instanceof SyntaxError) continue; throw e }
            }
        }

        messages.value.push({
            id: crypto.randomUUID(), role: 'assistant', content: streamingContent.value,
            sources: streamingSources.value.length > 0 ? streamingSources.value : undefined,
            input_tokens: usageStats.input_tokens as number,
            output_tokens: usageStats.output_tokens as number,
            credits_used: usageStats.credits as number,
        })
    } catch (e: unknown) {
        const err = e instanceof Error ? e : new Error('Stream failed')
        if (err.name !== 'AbortError') {
            error.value = sanitizeErrorMessage(err.message || 'Stream failed')
            if (streamingContent.value) {
                messages.value.push({
                    id: crypto.randomUUID(), role: 'assistant',
                    content: streamingContent.value + '\n\n*Error: ' + error.value + '*',
                    sources: streamingSources.value.length > 0 ? streamingSources.value : undefined,
                })
            }
        }
    } finally {
        isStreaming.value = false
        streamingContent.value = ''
        streamingSources.value = []
        abortController.value = null
    }
}

function stopStreaming() { abortController.value?.abort() }

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage() }
}

function scrollToBottom() {
    nextTick(() => {
        if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    })
}

function retryLastMessage() {
    error.value = null
    const lastUserMsg = [...messages.value].reverse().find(m => m.role === 'user')
    if (lastUserMsg) {
        const lastMsg = messages.value[messages.value.length - 1]
        if (lastMsg?.role === 'assistant' && lastMsg.content.includes('*Error:')) {
            messages.value.pop()
        }
        input.value = lastUserMsg.content
        messages.value = messages.value.filter(m => m.id !== lastUserMsg.id)
        sendMessage()
    }
}

function renderMarkdown(content: string): string {
    return marked.parse(content || '') as string
}

function copyToClipboard(text: string, id: string) {
    navigator.clipboard.writeText(text).then(() => {
        copiedId.value = id
        setTimeout(() => {
            if (copiedId.value === id) {
                copiedId.value = null
            }
        }, 2000)
    }).catch(() => {})
}

const welcomeTitle = computed(() => {
    if (props.sourceType === 'youtube') return t('Your video is ready')
    if (props.sourceType === 'url') return t('Your web page is ready')
    if (props.sourceType === 'collection') return t('Your knowledge base is ready')
    return t('Your document is ready')
})

const welcomeDesc = computed(() => {
    if (props.sourceType === 'youtube') {
        return t('Ask questions about the video and get answers grounded in the transcript with citations.')
    }
    if (props.sourceType === 'url') {
        return t('Ask questions about the web page and get answers grounded in its content with citations.')
    }
    if (props.sourceType === 'collection') {
        return t('Ask questions about your knowledge base and get answers grounded in the stored documents with citations.')
    }
    return t('Ask questions about your document and get answers grounded in its content with page-level citations.')
})

const suggestedQuestions = computed(() => {
    if (props.sourceType === 'youtube') {
        return [
            t('What is this video about?'),
            t('What are the key points discussed?'),
            t('Summarize this video'),
        ]
    }
    if (props.sourceType === 'url') {
        return [
            t('What is this web page about?'),
            t('What are the main takeaways?'),
            t('Summarize this article'),
        ]
    }
    if (props.sourceType === 'collection') {
        return [
            t('What information is in this collection?'),
            t('Summarize the documents here'),
            t('Search for core topics'),
        ]
    }
    return [
        t('What is this document about?'),
        t('What are the key points?'),
        t('Summarize this for me'),
    ]
})
</script>

<template>
    <div class="flex flex-col h-full mx-auto w-full bg-transparent overflow-hidden">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-2 border-primary-500 border-t-transparent mb-3"></div>
            <span class="text-xs text-surface-500 dark:text-surface-450 font-medium">{{ t('Loading chat...') }}</span>
        </div>

        <!-- Centered Empty State (No chat history / first chat start) -->
        <div v-else-if="messages.length === 0 && !isStreaming" class="flex-1 flex flex-col items-center justify-center text-center p-6 max-w-2xl mx-auto w-full space-y-6">
            <!-- Welcome Logo -->
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/10 shrink-0">
                <i class="ti ti-messages text-3xl text-white"></i>
            </div>
            
            <!-- Welcome Title & Desc -->
            <div>
                <h2 class="text-xl font-bold mb-2 text-surface-900 dark:text-surface-50">{{ welcomeTitle }}</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400 max-w-sm font-medium mx-auto">
                    {{ welcomeDesc }}
                </p>
            </div>

            <!-- Centered Chat Input Box -->
            <div class="w-full">
                <div class="relative flex items-center bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-850 focus-within:border-primary-500 dark:focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/15 rounded-2xl shadow-sm transition-all duration-200">
                    <input
                        ref="inputRef"
                        v-model="input"
                        type="text"
                        class="chat-input w-full h-12 pl-5 pr-14 bg-transparent outline-none border-none text-[13.5px] text-surface-900 dark:text-surface-100 placeholder-surface-450 dark:placeholder-surface-550 font-medium"
                        :placeholder="t('Ask a question...')"
                        :disabled="isStreaming"
                        @keydown="handleKeydown"
                    />
                    <button
                        v-if="isStreaming"
                        class="absolute right-2 w-8.5 h-8.5 inline-flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-xl shadow-sm transition-all hover:scale-95"
                        @click="stopStreaming"
                    >
                        <i class="ti ti-player-stop text-sm"></i>
                    </button>
                    <button
                        v-else
                        class="absolute right-2 w-8.5 h-8.5 inline-flex items-center justify-center text-white rounded-xl shadow-sm transition-all duration-200"
                        :class="input.trim() 
                            ? 'bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 hover:scale-95' 
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-400 dark:text-surface-600 pointer-events-none'"
                        :disabled="!input.trim()"
                        @click="sendMessage"
                    >
                        <i class="ti ti-send text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Suggested Questions -->
            <div class="flex flex-wrap gap-2 justify-center w-full max-w-lg">
                <button
                    v-for="q in suggestedQuestions"
                    :key="q"
                    class="px-4 py-2 rounded-xl border border-surface-200 dark:border-surface-800 text-xs font-semibold text-surface-700 dark:text-surface-350 hover:text-primary-500 dark:hover:text-primary-400 hover:border-primary-500/50 dark:hover:border-primary-500/40 hover:bg-surface-100/30 dark:hover:bg-surface-900/30 transition-all duration-200 bg-white dark:bg-surface-900 shadow-sm"
                    @click="input = q; sendMessage()"
                >
                    {{ q }}
                </button>
            </div>
        </div>

        <!-- Active Chat State (Messages List + Input Box) -->
        <div v-else class="flex-1 flex flex-col min-h-0 overflow-hidden relative">
            <div 
                ref="chatContainer" 
                class="flex-1 overflow-y-auto px-4 py-6 space-y-6 scrollbar-thin flex flex-col"
            >
                <div v-for="msg in messages" :key="msg.id" class="flex gap-3.5 max-w-3xl mx-auto w-full" :class="msg.role === 'user' ? 'flex-row-reverse' : ''">
                    <!-- Avatar -->
                    <div class="shrink-0">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-semibold shadow-sm border" :class="msg.role === 'user' ? 'bg-surface-100 dark:bg-surface-800 border-surface-200/50 dark:border-surface-700/50 text-surface-600 dark:text-surface-300' : 'bg-primary-500/10 dark:bg-primary-500/20 border-primary-500/10 text-primary-500'">
                            <i :class="msg.role === 'user' ? 'ti ti-user' : 'ti ti-sparkles'"></i>
                        </div>
                    </div>
                    
                    <!-- Content bubble -->
                    <div class="max-w-[78%] min-w-0 flex flex-col" :class="msg.role === 'user' ? 'items-end' : ''">
                        <div class="px-4 py-3 text-[13.5px] leading-relaxed shadow-sm border" :class="msg.role === 'user' ? 'bg-gradient-to-r from-primary-500 to-primary-600 dark:from-primary-600 dark:to-primary-700 border-primary-500/10 text-white rounded-2xl rounded-tr-sm' : 'bg-white dark:bg-surface-900 border-surface-200/60 dark:border-surface-850/60 text-surface-900 dark:text-surface-100 rounded-2xl rounded-tl-sm'">
                            <div v-if="msg.role === 'assistant'" class="rag-markdown prose prose-sm max-w-none dark:prose-invert" v-html="renderMarkdown(msg.content)"></div>
                            <div v-else class="whitespace-pre-wrap font-medium">{{ msg.content }}</div>
                        </div>
                        
                        <!-- Assistant footer tools: sources and copy -->
                        <div v-if="msg.role === 'assistant'" class="flex items-center gap-3 mt-1.5 px-1 w-full">
                            <RagSourceChips v-if="msg.sources && msg.sources.length" :sources="msg.sources" class="mt-0" />
                            <div v-else class="flex-1"></div>
                            <Tooltip :content="t('Copy to clipboard')">
                                <button @click="copyToClipboard(msg.content, msg.id)" class="inline-flex items-center justify-center w-7 h-7 text-surface-400 dark:text-surface-500 hover:text-primary-500 dark:hover:text-primary-400 hover:bg-surface-100 dark:hover:bg-surface-850 rounded-lg border border-transparent hover:border-surface-200/40 dark:hover:border-surface-800/40 hover:shadow-sm transition-all ml-auto shrink-0">
                                    <i :class="copiedId === msg.id ? 'ti ti-check text-emerald-500' : 'ti ti-copy'"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>
                </div>

                <!-- Streaming response block -->
                <div v-if="isStreaming || streamingContent" class="flex gap-3.5 max-w-3xl mx-auto w-full">
                    <!-- Avatar -->
                    <div class="shrink-0 mt-0.5">
                        <div class="w-8 h-8 rounded-xl bg-primary-500/10 dark:bg-primary-500/20 border border-primary-500/10 flex items-center justify-center text-primary-500 shadow-sm animate-pulse">
                            <i class="ti ti-sparkles"></i>
                        </div>
                    </div>
                    
                    <!-- Content Bubble -->
                    <div class="max-w-[78%] min-w-0 flex flex-col">
                        <div class="px-4 py-3 text-[13.5px] leading-relaxed shadow-sm border bg-white dark:bg-surface-900 border-surface-200/60 dark:border-surface-850/60 text-surface-900 dark:text-surface-100 rounded-2xl rounded-tl-sm">
                            <div v-if="streamingContent" class="rag-markdown prose prose-sm max-w-none dark:prose-invert streaming-text" v-html="renderMarkdown(streamingContent)"></div>
                            <div v-else-if="isStreaming" class="flex gap-1.5 items-center py-0.5">
                                <div class="flex gap-1.5 items-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500/70 dark:bg-primary-400/70 animate-bounce" style="animation-delay: 0ms"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500/70 dark:bg-primary-400/70 animate-bounce" style="animation-delay: 150ms"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500/70 dark:bg-primary-400/70 animate-bounce" style="animation-delay: 300ms"></span>
                                </div>
                                <span class="text-xs text-surface-500 dark:text-surface-400 font-medium ml-1.5">{{ currentReasoningVerb }}...</span>
                            </div>
                            <RagSourceChips v-if="streamingSources.length" :sources="streamingSources" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Error Block -->
                <div v-if="error" class="max-w-3xl mx-auto w-full">
                    <div class="alert alert-error text-sm rounded-xl flex items-center justify-between gap-3 bg-red-50 dark:bg-red-950/20 border border-red-200/50 dark:border-red-800/30 text-red-600 dark:text-red-400 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-alert-circle text-base"></i>
                            <span>{{ error }}</span>
                        </div>
                        <button @click="retryLastMessage" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold text-red-500 hover:bg-red-500/10 rounded-lg transition-all">
                            <i class="ti ti-refresh"></i>
                            <span>{{ t('Retry') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bottom Input Panel -->
            <div class="px-4 pb-5 pt-3 shrink-0 bg-gradient-to-t from-surface-50/90 to-transparent dark:from-surface-950/90 z-10 border-t border-surface-100 dark:border-surface-900/50">
                <div class="max-w-3xl mx-auto w-full">
                    <div class="relative flex items-center bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-850 focus-within:border-primary-500 dark:focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/15 rounded-2xl shadow-sm transition-all duration-200">
                        <input
                            ref="inputRef"
                            v-model="input"
                            type="text"
                            class="chat-input w-full h-12 pl-5 pr-14 bg-transparent outline-none border-none text-[13.5px] text-surface-900 dark:text-surface-100 placeholder-surface-450 dark:placeholder-surface-550 font-medium"
                            :placeholder="t('Ask a question...')"
                            :disabled="isStreaming"
                            @keydown="handleKeydown"
                        />
                        <button
                            v-if="isStreaming"
                            class="absolute right-2 w-8.5 h-8.5 inline-flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-xl shadow-sm transition-all hover:scale-95"
                            @click="stopStreaming"
                        >
                            <i class="ti ti-player-stop text-sm"></i>
                        </button>
                        <button
                            v-else
                            class="absolute right-2 w-8.5 h-8.5 inline-flex items-center justify-center text-white rounded-xl shadow-sm transition-all duration-200"
                            :class="input.trim() 
                                ? 'bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 hover:scale-95' 
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-400 dark:text-surface-600 pointer-events-none'"
                            :disabled="!input.trim()"
                            @click="sendMessage"
                        >
                            <i class="ti ti-send text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rag-markdown :deep(h1),
.rag-markdown :deep(h2),
.rag-markdown :deep(h3) {
    font-weight: 700;
    margin-top: 0.75em;
    margin-bottom: 0.4em;
    line-height: 1.3;
    color: inherit;
}
.rag-markdown :deep(h1) { font-size: 1.2em; }
.rag-markdown :deep(h2) { font-size: 1.1em; }
.rag-markdown :deep(h3) { font-size: 1em; }
.rag-markdown :deep(p) { margin-bottom: 0.6em; }
.rag-markdown :deep(p:last-child) { margin-bottom: 0; }
.rag-markdown :deep(ul),
.rag-markdown :deep(ol) {
    margin-bottom: 0.6em;
    padding-left: 1.5em;
}
.rag-markdown :deep(li) { margin-bottom: 0.2em; }
.rag-markdown :deep(strong) { font-weight: 700; color: inherit; }
.rag-markdown :deep(em) { font-style: italic; }
.rag-markdown :deep(code:not(pre code)) {
    background: rgba(0, 0, 0, 0.05);
    padding: 0.15em 0.35em;
    border-radius: 4px;
    font-size: 0.85em;
}
.dark .rag-markdown :deep(code:not(pre code)) {
    background: rgba(255, 255, 255, 0.08);
}
.rag-markdown :deep(pre) {
    background: #1e1e2e;
    color: #cdd6f4;
    padding: 0.75em 1em;
    border-radius: 0.75em;
    overflow-x: auto;
    margin: 0.6em 0;
    font-size: 0.85em;
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.rag-markdown :deep(blockquote) {
    border-left: 3px solid var(--color-primary-500);
    padding-left: 0.75em;
    margin: 0.5em 0;
    opacity: 0.85;
}
.rag-markdown :deep(table) {
    border-collapse: collapse;
    margin: 0.6em 0;
    width: 100%;
}
.rag-markdown :deep(th),
.rag-markdown :deep(td) {
    border: 1px solid rgba(0, 0, 0, 0.08);
    padding: 0.4em 0.6em;
    text-align: left;
}
.dark .rag-markdown :deep(th),
.dark .rag-markdown :deep(td) {
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.rag-markdown :deep(th) {
    font-weight: 600;
    background: rgba(0, 0, 0, 0.02);
}
.dark .rag-markdown :deep(th) {
    background: rgba(255, 255, 255, 0.02);
}
.streaming-text::after {
    content: '▋';
    color: var(--color-primary-500);
    animation: blink 0.8s step-end infinite;
    margin-left: 2px;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0; }
}
</style>
