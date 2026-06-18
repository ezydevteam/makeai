<script setup lang="ts">
import { computed, inject, onUnmounted, ref, watch } from 'vue'
import { marked } from 'marked'
import hljs from 'highlight.js/lib/core'
import javascript from 'highlight.js/lib/languages/javascript'
import typescript from 'highlight.js/lib/languages/typescript'
import python from 'highlight.js/lib/languages/python'
import php from 'highlight.js/lib/languages/php'
import css from 'highlight.js/lib/languages/css'
import bash from 'highlight.js/lib/languages/bash'
import json from 'highlight.js/lib/languages/json'
import sql from 'highlight.js/lib/languages/sql'
import xml from 'highlight.js/lib/languages/xml'
import yaml from 'highlight.js/lib/languages/yaml'
import markdownLang from 'highlight.js/lib/languages/markdown'
import ruby from 'highlight.js/lib/languages/ruby'
import go from 'highlight.js/lib/languages/go'
import rust from 'highlight.js/lib/languages/rust'
import java from 'highlight.js/lib/languages/java'
import cpp from 'highlight.js/lib/languages/cpp'
import csharp from 'highlight.js/lib/languages/csharp'
import swift from 'highlight.js/lib/languages/swift'
import kotlin from 'highlight.js/lib/languages/kotlin'
import dart from 'highlight.js/lib/languages/dart'
import shell from 'highlight.js/lib/languages/shell'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSpeechSynthesis } from '@/Composables/useSpeechSynthesis'

const { t } = useTranslate()
const { isSpeaking, isPaused, isSupported: speechSupported, currentTextId, speak, pause, resume, stop } = useSpeechSynthesis()

function toggleSpeak() {
    const messageId = String(props.message.id)
    if (currentTextId.value === messageId) {
        if (isPaused.value) {
            resume()
        } else {
            pause()
        }
    } else {
        speak(props.message.content, messageId)
    }
}

hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('js', javascript)
hljs.registerLanguage('typescript', typescript)
hljs.registerLanguage('ts', typescript)
hljs.registerLanguage('python', python)
hljs.registerLanguage('py', python)
hljs.registerLanguage('php', php)
hljs.registerLanguage('css', css)
hljs.registerLanguage('bash', bash)
hljs.registerLanguage('sh', bash)
hljs.registerLanguage('json', json)
hljs.registerLanguage('sql', sql)
hljs.registerLanguage('xml', xml)
hljs.registerLanguage('html', xml)
hljs.registerLanguage('yaml', yaml)
hljs.registerLanguage('yml', yaml)
hljs.registerLanguage('markdown', markdownLang)
hljs.registerLanguage('md', markdownLang)
hljs.registerLanguage('ruby', ruby)
hljs.registerLanguage('rb', ruby)
hljs.registerLanguage('go', go)
hljs.registerLanguage('rust', rust)
hljs.registerLanguage('rs', rust)
hljs.registerLanguage('java', java)
hljs.registerLanguage('cpp', cpp)
hljs.registerLanguage('c++', cpp)
hljs.registerLanguage('csharp', csharp)
hljs.registerLanguage('cs', csharp)
hljs.registerLanguage('swift', swift)
hljs.registerLanguage('kotlin', kotlin)
hljs.registerLanguage('kt', kotlin)
hljs.registerLanguage('dart', dart)
hljs.registerLanguage('shell', shell)

const emit = defineEmits<{
    (e: 'repeat', messageId: number | string): void
}>()

const props = defineProps<{
    message: { id: number | string; role: string; content: string; model?: string; input_tokens: number; output_tokens: number; credits_charged: number; created_at?: string; attachments?: Array<{ id: string; name: string; mime_type: string; size: number; url?: string }> | null; kb_sources?: Array<{ ulid: string; title: string; slug: string }> }
    isStreaming: boolean
    userCredits: number
    creditThreshold: number
    conversationUlid?: string
}>()

const messageDate = computed(() => {
    if (!props.message.created_at) return ''
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(new Date(props.message.created_at))
})

const tokenOpen = ref(false)

const reasoningVerbs = ['Thinking', 'Analyzing', 'Processing', 'Reasoning', 'Evaluating']
const currentVerbIndex = ref(0)
const showReasoning = ref(false)
let reasoningInterval: ReturnType<typeof setInterval> | null = null

watch(() => props.isStreaming, (streaming) => {
    if (streaming) {
        showReasoning.value = true
        currentVerbIndex.value = 0
        reasoningInterval = setInterval(() => {
            currentVerbIndex.value = (currentVerbIndex.value + 1) % reasoningVerbs.length
        }, 2000)
    } else {
        showReasoning.value = false
        if (reasoningInterval) {
            clearInterval(reasoningInterval)
            reasoningInterval = null
        }
    }
})

onUnmounted(() => {
    if (reasoningInterval) clearInterval(reasoningInterval)
})

const copied = ref(false)
const liked = ref(false)
const disliked = ref(false)
const isEditing = ref(false)
const editContent = ref('')

function startEditing() {
    editContent.value = props.message.content
    isEditing.value = true
}

function cancelEditing() {
    isEditing.value = false
    editContent.value = ''
}

async function saveEdit() {
    if (!props.conversationUlid || !editContent.value.trim()) return
    
    const chat = inject<ReturnType<typeof import('@/Composables/useChat').useChat>>('chat')
    if (chat) {
        await chat.editMessage(props.conversationUlid, props.message.id, editContent.value.trim())
        isEditing.value = false
        editContent.value = ''
    }
}

const imagePreviewOpen = ref(false)
const imagePreviewUrl = ref('')
const imagePreviewName = ref('')

function openImagePreview(att: { id: string; name: string; mime_type: string }) {
    imagePreviewUrl.value = `/api/v1/chat/attachments/${att.id}/preview`
    imagePreviewName.value = att.name
    imagePreviewOpen.value = true
}

function closeImagePreview() {
    imagePreviewOpen.value = false
    imagePreviewUrl.value = ''
    imagePreviewName.value = ''
}

function copyMessage() {
    navigator.clipboard.writeText(props.message.content).then(() => {
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    })
}

function shareMessage() {
    const text = props.message.content
    if (navigator.share) {
        navigator.share({ text })
    } else {
        navigator.clipboard.writeText(text).then(() => {
            copied.value = true
            setTimeout(() => { copied.value = false }, 2000)
        })
    }
}

function toggleLike() {
    const wasLiked = liked.value
    liked.value = !wasLiked
    if (liked.value) disliked.value = false
    
    // Submit to API
    if (props.conversationUlid && props.message.role === 'assistant') {
        submitFeedback(wasLiked ? -1 : 1)
    }
}

function toggleDislike() {
    const wasDisliked = disliked.value
    disliked.value = !wasDisliked
    if (disliked.value) liked.value = false
    
    // Submit to API
    if (props.conversationUlid && props.message.role === 'assistant') {
        submitFeedback(wasDisliked ? 1 : -1)
    }
}

async function submitFeedback(rating: number) {
    if (!props.conversationUlid) return
    
    try {
        const csrf = () => {
            const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
            return cookie ? decodeURIComponent(cookie.pop() || '') : ''
        }
        
        await fetch('/api/v1/chat/feedback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                conversation_ulid: props.conversationUlid,
                message_id: props.message.id,
                rating: rating,
            }),
        })
    } catch (error) {
        console.error('Failed to submit feedback:', error)
        // Revert on error
        liked.value = false
        disliked.value = false
    }
}

function repeatMessage() {
    emit('repeat', props.message.id)
}

function branchFromHere() {
    if (!props.conversationUlid) return
    const chat = inject<ReturnType<typeof import('@/Composables/useChat').useChat>>('chat')
    if (chat) {
        chat.branchConversation(props.conversationUlid, props.message.id)
    }
}

function handleCodeCopy(e: Event) {
    const btn = (e.target as HTMLElement).closest('.code-copy-btn') as HTMLElement | null
    if (!btn) return
    const encoded = btn.getAttribute('data-code')
    if (!encoded) return
    const decoded = decodeURIComponent(escape(atob(encoded)))
    navigator.clipboard.writeText(decoded).then(() => {
        btn.textContent = 'Copied!'
        setTimeout(() => { btn.textContent = 'Copy' }, 1500)
    })
}

const rendered = computed(() => {
    if (props.message.role !== 'assistant') return null
    let html = marked.parse(props.message.content || '') as string

    html = html.replace(/<pre><code(?:\s+class="language-([^"]*)")?>([\s\S]*?)<\/code><\/pre>/g, (_m, lang, code) => {
        const decoded = code.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"')
        const idx = Math.random().toString(36).slice(2)
        let highlighted = ''
        if (lang && hljs.getLanguage(lang)) {
            highlighted = hljs.highlight(decoded, { language: lang }).value
        } else {
            highlighted = hljs.highlightAuto(decoded).value
        }
        return `<div class="code-block-wrapper"><div class="code-block-header"><span class="code-lang">${lang || 'auto'}</span><button class="code-copy-btn" data-code="${btoa(unescape(encodeURIComponent(decoded)))}">Copy</button></div><pre class="!bg-[#1e1e1e] !rounded-t-none !rounded-b-lg !p-4 !overflow-x-auto"><code class="!bg-transparent !p-0 !text-sm">${highlighted}</code></pre></div>`
    })

    return html
})

const showTokens = computed(() => {
    if (props.isStreaming) return false
    return props.message.input_tokens > 0 || props.message.output_tokens > 0
})

const showCreditsRemaining = computed(() => {
    return props.userCredits > 0 && props.userCredits < props.creditThreshold
})
</script>

<template>
    <template v-if="message.role === 'user'">
        <div class="group flex justify-end">
            <div class="max-w-[80%]">
                <!-- Attachment badges -->
                <div v-if="message.attachments?.length" class="flex flex-wrap gap-1.5 mb-1.5 justify-end">
                    <template v-for="att in message.attachments" :key="att.id">
                        <!-- Image thumbnail -->
                        <div v-if="att.mime_type?.startsWith('image/')" class="relative group/img">
                            <img
                                :src="`/api/v1/chat/attachments/${att.id}/preview`"
                                :alt="att.name"
                                class="w-20 h-20 object-cover rounded-lg border border-black/5 dark:border-white/10 cursor-pointer hover:opacity-90 transition-opacity"
                                @click="openImagePreview(att)"
                            />
                        </div>
                        <!-- File badge -->
                        <div
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/60 dark:bg-white/5 border border-black/5 dark:border-white/10 px-2 py-1 text-[11px] text-[#6e6a65] dark:text-white/50"
                        >
                            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                            <span class="truncate max-w-[120px]">{{ att.name }}</span>
                        </div>
                    </template>
                </div>
                <div class="px-4 py-2.5 rounded-2xl bg-[#f0edeb] dark:bg-white/10 text-[#1a1a1a] dark:text-[#e8e6e3] rounded-br-sm">
                    <div class="text-[15px] leading-relaxed whitespace-pre-wrap break-words">{{ message.content }}</div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <span v-if="messageDate" class="text-xs text-[#b0aca8] dark:text-white/30">{{ messageDate }}</span>
                    <Tooltip :content="copied ? 'Copied!' : 'Copy'" placement="top">
                        <button class="action-btn" :class="{ 'active': copied }" @click="copyMessage">
                            <svg v-if="!copied" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                            </svg>
                            <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>
                    </Tooltip>
                    <Tooltip :content="'Share'" placement="top">
                        <button class="action-btn" @click="shareMessage">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                            </svg>
                        </button>
                    </Tooltip>
                    <Tooltip :content="'Regenerate'" placement="top">
                        <button class="action-btn" @click="repeatMessage">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.985 4.356v4.992" />
                            </svg>
                        </button>
                    </Tooltip>
                    <Tooltip :content="'Branch from here'" placement="top">
                        <button class="action-btn" @click="branchFromHere">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </button>
                    </Tooltip>
                </div>
            </div>
        </div>
    </template>

    <template v-else>
        <div class="flex gap-4 items-start">
            <div class="w-7 h-7 rounded-full bg-[#d9cec7] dark:bg-white/10 flex items-center justify-center shrink-0 mt-0.5" :class="{ 'ai-logo-streaming': isStreaming }">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#1a1a1a] dark:text-white/60">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <!-- Reasoning verb indicator during streaming -->
                <div v-if="isStreaming && showReasoning && !message.content" class="flex items-center gap-2 mb-2">
                    <div class="flex gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#d9cec7] dark:bg-white/30 animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#d9cec7] dark:bg-white/30 animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#d9cec7] dark:bg-white/30 animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                    <span class="text-sm text-[#6e6a65] dark:text-white/40 transition-opacity duration-300">{{ reasoningVerbs[currentVerbIndex] }}...</span>
                </div>

                <div v-if="rendered" class="chat-markdown text-[15px] leading-relaxed text-[#1a1a1a] dark:text-[#e8e6e3]" v-html="rendered" @click="handleCodeCopy"></div>
                <div v-else-if="message.content" class="text-[15px] leading-relaxed whitespace-pre-wrap break-words text-[#1a1a1a] dark:text-[#e8e6e3]">
                    {{ message.content }}
                </div>
                <span v-if="isStreaming && !rendered && message.content" class="inline-block w-1.5 h-5 bg-[#d9cec7] animate-pulse ml-0.5 align-text-bottom rounded-full"></span>

                <!-- Token Usage (collapsible) -->
                <div v-if="showTokens" class="mt-1.5">
                    <button class="text-[11px] text-[#b0aca8] dark:text-white/30 hover:text-[#6e6a65] dark:hover:text-white/50 transition-colors inline-flex items-center gap-1" @click="tokenOpen = !tokenOpen">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ (message.input_tokens + message.output_tokens).toLocaleString() }} tokens &middot; {{ message.credits_charged }} credits
                        <span v-if="message.model">&middot; {{ message.model }}</span>
                        <span v-if="showCreditsRemaining" class="!text-amber-600 dark:!text-amber-400">&middot; {{ userCredits }} left</span>
                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="transition-transform" :class="{ 'rotate-180': tokenOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div v-if="tokenOpen" class="mt-1.5 p-2.5 rounded-lg bg-black/[0.02] dark:bg-white/[0.02] border border-black/5 dark:border-white/5 text-[11px] text-[#6e6a65] dark:text-white/40 space-y-1">
                        <div>Input: {{ message.input_tokens.toLocaleString() }} tokens</div>
                        <div>Output: {{ message.output_tokens.toLocaleString() }} tokens</div>
                        <div>Total: {{ (message.input_tokens + message.output_tokens).toLocaleString() }} tokens</div>
                        <div v-if="message.model">Model: {{ message.model }}</div>
                        <div>Credits used: {{ message.credits_charged }}</div>
                        <div v-if="userCredits > 0">Credits remaining: {{ userCredits }}</div>
                    </div>
                </div>

                <!-- KB Sources -->
                <div v-if="message.kb_sources?.length" class="mt-2 flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] text-[#6e6a65] dark:text-white/40 flex items-center gap-1">
                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                        {{ t('Sources:') }}
                    </span>
                    <a
                        v-for="source in message.kb_sources"
                        :key="source.ulid"
                        :href="`/kb/article/${source.slug}`"
                        target="_blank"
                        class="inline-flex items-center gap-1 rounded-md bg-primary-500/10 px-2 py-0.5 text-[11px] text-primary-600 dark:text-primary-400 hover:bg-primary-500/20 transition-colors"
                    >
                        {{ source.title }}
                    </a>
                </div>

                <!-- Action buttons -->
                <div v-if="!isStreaming && message.content" class="flex items-center gap-1 mt-3">
                    <span v-if="messageDate" class="text-xs text-[#b0aca8] dark:text-white/30 mr-1">{{ messageDate }}</span>
                    <!-- Copy -->
                    <Tooltip :content="copied ? 'Copied!' : 'Copy'" placement="top">
                        <button class="action-btn" :class="{ 'active': copied }" @click="copyMessage">
                            <svg v-if="!copied" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                            </svg>
                            <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>
                    </Tooltip>

                    <!-- Share -->
                    <Tooltip :content="'Share'" placement="top">
                        <button class="action-btn" @click="shareMessage">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                            </svg>
                        </button>
                    </Tooltip>

                    <!-- Like (thumbs up) -->
                    <Tooltip :content="'Like'" placement="top">
                        <button class="action-btn" :class="{ 'active': liked }" @click="toggleLike">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3" />
                            </svg>
                        </button>
                    </Tooltip>

                    <!-- Dislike (thumbs down) -->
                    <Tooltip :content="t('Dislike')" placement="top">
                        <button class="action-btn" :class="{ 'active': disliked }" @click="toggleDislike">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17" />
                            </svg>
                        </button>
                    </Tooltip>

                    <!-- Repeat -->
                    <Tooltip :content="'Regenerate'" placement="top">
                        <button class="action-btn" @click="repeatMessage">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.985 4.356v4.992" />
                            </svg>
                        </button>
                    </Tooltip>
                </div>
            </div>
        </div>
    </template>

    <!-- Image Preview Modal -->
    <Teleport to="body">
        <div
            v-if="imagePreviewOpen"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
            @click.self="closeImagePreview"
        >
            <div class="relative max-w-[90vw] max-h-[90vh]">
                <button
                    class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                    @click="closeImagePreview"
                >
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <img
                    :src="imagePreviewUrl"
                    :alt="imagePreviewName"
                    class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl"
                />
                <div class="absolute bottom-0 left-0 right-0 p-3 bg-gradient-to-t from-black/60 to-transparent rounded-b-lg">
                    <p class="text-white text-sm truncate">{{ imagePreviewName }}</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style>
.chat-markdown h1, .chat-markdown h2, .chat-markdown h3 {
    font-weight: 600;
    margin-top: 1em;
    margin-bottom: 0.5em;
    line-height: 1.3;
}
.chat-markdown h1 { font-size: 1.25em; }
.chat-markdown h2 { font-size: 1.15em; }
.chat-markdown h3 { font-size: 1.05em; }
.chat-markdown p { margin-bottom: 0.75em; }
.chat-markdown p:last-child { margin-bottom: 0; }
.chat-markdown ul, .chat-markdown ol { margin-bottom: 0.75em; padding-left: 1.5em; }
.chat-markdown li { margin-bottom: 0.25em; }
.chat-markdown strong { font-weight: 600; }
.chat-markdown em { font-style: italic; }
.chat-markdown code:not(pre code) {
    background: rgba(0,0,0,0.05);
    padding: 0.15em 0.4em;
    border-radius: 4px;
    font-size: 0.875em;
    font-family: 'SF Mono', 'Fira Code', monospace;
}
html.dark .chat-markdown code:not(pre code) {
    background: rgba(255,255,255,0.08);
}
.chat-markdown blockquote {
    border-left: 3px solid rgba(0,0,0,0.1);
    padding-left: 0.75em;
    margin: 0.5em 0;
    opacity: 0.8;
}
html.dark .chat-markdown blockquote {
    border-left-color: rgba(255,255,255,0.15);
}

.code-block-wrapper {
    margin: 0.75em 0;
}

.code-block-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5em 0.75em;
    background: #2d2d2d;
    border-radius: 8px 8px 0 0;
}

.code-lang {
    font-size: 11px;
    font-weight: 500;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.code-copy-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: rgba(255,255,255,0.6);
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
}
.code-copy-btn:hover {
    background: rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.9);
}

.code-block-wrapper pre {
    margin: 0;
}

.code-block-wrapper code {
    font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: #b0aca8;
    transition: all 0.15s ease;
    cursor: pointer;
    border: none;
    background: transparent;
}

.action-btn:hover {
    color: #6e6a65;
    background: rgba(0, 0, 0, 0.04);
}

.action-btn.active {
    color: #1a1a1a;
}

html.dark .action-btn {
    color: rgba(255, 255, 255, 0.3);
}

html.dark .action-btn:hover {
    color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.05);
}

html.dark .action-btn.active {
    color: rgba(255, 255, 255, 0.8);
}

@keyframes ai-pulse-glow {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(217, 206, 199, 0.4);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 12px 4px rgba(217, 206, 199, 0.6);
        transform: scale(1.08);
    }
}

.ai-logo-streaming {
    animation: ai-pulse-glow 1.5s ease-in-out infinite;
}

html.dark .ai-logo-streaming {
    animation: ai-pulse-glow-dark 1.5s ease-in-out infinite;
}

@keyframes ai-pulse-glow-dark {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.1);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 12px 4px rgba(255, 255, 255, 0.2);
        transform: scale(1.08);
    }
}
</style>