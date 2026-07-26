<script setup lang="ts">
import { computed, inject, nextTick, onMounted, ref, watch, type Ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { toastChatError } from '../Composables/useChatErrors'
import type { useChat, ChatAttachment } from '../Composables/useChat'
import { useSpeechRecognition } from '@/Composables/useSpeechRecognition'
import ModelSelector from './ModelSelector.vue'

const { t } = useTranslate()
const page = usePage()
const chat = inject<ReturnType<typeof useChat>>('chat')!
const sidebarMobileOpen = inject<Ref<boolean>>('sidebarMobileOpen', ref(false))

// Admin feature toggles (shared globally as `chatbot.*`).
const enableFileUpload = computed(() => ((page.props.chatbot as any)?.enableFileUpload as boolean) ?? true)
const enableVoice = computed(() => ((page.props.chatbot as any)?.enableVoice as boolean) ?? true)

const inputText = ref('')
const fileInputRef = ref<HTMLInputElement | null>(null)
const textareaRef = ref<HTMLTextAreaElement | null>(null)
const uploadedAttachments = ref<ChatAttachment[]>([])
const uploading = ref(false)
const uploadError = ref('')

const { isRecording, transcript, isSupported: speechSupported, startRecording, stopRecording, resetTranscript } = useSpeechRecognition()

// Watch for transcript changes and update input text
watch(transcript, (newTranscript) => {
    if (newTranscript) {
        inputText.value = newTranscript
    }
})

const toggleRecording = () => {
    if (isRecording.value) {
        stopRecording()
    } else {
        resetTranscript()
        startRecording()
    }
}

function csrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

const send = async () => {
    const text = inputText.value.trim()
    if ((!text && !uploadedAttachments.value.length) || chat.isStreaming.value) return
    const attachments = uploadedAttachments.value.length ? [...uploadedAttachments.value] : undefined
    inputText.value = ''
    uploadedAttachments.value = []
    uploadError.value = ''
    resetTextareaHeight()
    await chat.sendMessage(text || '(file attached)', chat.selectedMode.value?.slug ?? undefined, attachments)
    void focusTextarea()
}

const autoResize = () => {
    const textarea = textareaRef.value
    if (!textarea) return
    textarea.style.height = 'auto'
    textarea.style.height = Math.min(textarea.scrollHeight, 300) + 'px'
}

const resetTextareaHeight = () => {
    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto'
        }
    })
}

const stop = () => {
    chat.stopStreaming()
}

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        // Ignore Enter while streaming — previously it aborted the in-progress reply,
        // so a user typing their next thought and hitting Enter lost the response.
        // Cancelling now requires the explicit Stop button.
        if (chat.isStreaming.value) return
        void send()
    }
    if (e.key === 'Escape') {
        uploadedAttachments.value = []
        uploadError.value = ''
    }
}

const onFileSelect = async (e: Event) => {
    const input = e.target as HTMLInputElement
    if (!input.files || input.files.length === 0) return

    const file = input.files[0]
    input.value = ''

    const maxSize = 10 * 1024 * 1024
    if (file.size > maxSize) {
        uploadError.value = t('File too large. Maximum size is 10MB.')
        return
    }

    uploading.value = true
    uploadError.value = ''

    try {
        const formData = new FormData()
        formData.append('file', file)

        const res = await fetch('/api/v1/chat/attachments', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: formData,
        })

        // Toasts the server's own wording — demo mode's block, the size/type rejection, the
        // upload rate limit — as well as showing it under the input.
        if (!res.ok) {
            throw new Error(await toastChatError(res, t('File upload failed.')))
        }

        const json = await res.json()
        if (json.success && json.data) {
            uploadedAttachments.value = [...uploadedAttachments.value, json.data]
        }
    } catch (e) {
        uploadError.value = e instanceof Error ? e.message : t('File upload failed.')
    } finally {
        uploading.value = false
    }
}

const removeAttachment = (id: string) => {
    uploadedAttachments.value = uploadedAttachments.value.filter(a => a.id !== id)
}

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const isImageAttachment = (att: ChatAttachment): boolean => {
    return att.mime_type?.startsWith('image/') ?? false
}

const getAttachmentUrl = (att: ChatAttachment): string => {
    // For image previews, we need to get the URL from the storage path
    // The attachment has a storage_path like "chat-attachments/1/01JXYZ..."
    // We need to construct a URL to access it
    return `/api/v1/chat/attachments/${att.id}/preview`
}

const focusTextarea = async () => {
    await nextTick()
    textareaRef.value?.focus({ preventScroll: true })
}

onMounted(() => {
    void focusTextarea()
})

watch(
    () => [chat.activeConversation.value?.ulid, chat.selectedMode.value?.slug],
    () => {
        void focusTextarea()
    },
    { flush: 'post' },
)

watch(
    () => sidebarMobileOpen.value,
    (open, previousOpen) => {
        if (previousOpen && !open) {
            void focusTextarea()
        }
    },
    { flush: 'post' },
)
</script>

<template>
    <div class="shrink-0 px-4 sm:px-6 pb-4 sm:pb-6 pt-2">
        <div class="mx-auto !max-w-[768px]">
            <!-- File previews -->
            <div v-if="uploadedAttachments.length" class="mb-2 flex flex-wrap gap-2">
                <div
                    v-for="att in uploadedAttachments"
                    :key="att.id"
                    class="relative group rounded-xl border border-black/5 bg-black/[0.03] dark:border-white/10 dark:bg-white/[0.04] overflow-hidden"
                    :class="isImageAttachment(att) ? 'w-20 h-20' : 'flex items-center gap-2 px-3 py-1.5'"
                >
                    <!-- Image thumbnail -->
                    <template v-if="isImageAttachment(att)">
                        <img
                            :src="getAttachmentUrl(att)"
                            :alt="att.name"
                            class="w-full h-full object-cover"
                        />
                        <button
                            class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity"
                            @click="removeAttachment(att.id)"
                        >&times;</button>
                    </template>
                    <!-- File info -->
                    <template v-else>
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#6e6a65] dark:text-white/40 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                        <span class="text-xs text-[#6e6a65] dark:text-white/50 truncate max-w-[140px]">{{ att.name }}</span>
                        <span class="text-[10px] text-[#b0aca8] dark:text-white/25">{{ formatFileSize(att.size) }}</span>
                        <button class="text-[#b0aca8] dark:text-white/30 hover:text-red-500 transition-colors" @click="removeAttachment(att.id)">&times;</button>
                    </template>
                </div>
            </div>

            <!-- Upload error -->
            <div v-if="uploadError" class="mb-2 text-xs text-red-500 dark:text-red-400">{{ uploadError }}</div>

            <div class="rounded-[1.5rem] border border-black/10 bg-white px-3 py-3 shadow-[0_10px_30px_rgba(17,24,39,0.06)] transition-colors dark:border-white/10 dark:bg-[#1c1c1c] dark:shadow-[0_10px_30px_rgba(0,0,0,0.25)] focus-within:border-primary-500/30 dark:focus-within:border-primary-500/30">
                <textarea
                    ref="textareaRef"
                    v-model="inputText"
                    :placeholder="chat.selectedMode.value
                        ? t('Ask anything in :mode mode...', { mode: chat.selectedMode.value.name })
                        : t('Ask anything...')
                    "
                    class="chat-textarea w-full resize-none rounded-2xl border border-transparent bg-transparent px-1 py-2 text-[15px] leading-relaxed text-gray-900 placeholder:text-gray-400 dark:text-white/90 dark:placeholder:text-white/25"
                    rows="1"
                    @keydown="onKeydown"
                    @input="autoResize"
                ></textarea>

                <div class="flex items-center justify-between gap-3 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <button
                            v-if="enableFileUpload"
                            class="flex h-9 items-center justify-center gap-2 rounded-xl px-3 text-gray-500 transition-colors hover:bg-black/5 hover:text-gray-700 dark:text-white/40 dark:hover:bg-white/5 dark:hover:text-white/70 disabled:opacity-40"
                            :title="t('Attach file')"
                            :aria-label="t('Attach file')"
                            :disabled="uploading"
                            @click="fileInputRef?.click()"
                        >
                            <svg v-if="!uploading" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                            <svg v-else class="animate-spin" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            <span class="text-sm font-medium">{{ uploading ? t('Uploading...') : t('Attach') }}</span>
                        </button>
                        <input v-if="enableFileUpload" ref="fileInputRef" type="file" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.csv,.txt,.md,.docx" @change="onFileSelect" />

                        <button
                            v-if="chat.kbAvailable"
                            class="flex h-9 items-center justify-center gap-2 rounded-xl px-3 transition-colors"
                            :class="chat.useKnowledgeBase.value
                                ? 'bg-primary-500/10 text-primary-600 dark:bg-primary-500/15 dark:text-primary-400'
                                : 'text-gray-500 hover:bg-black/5 hover:text-gray-700 dark:text-white/40 dark:hover:bg-white/5 dark:hover:text-white/70'"
                            :title="chat.useKnowledgeBase.value ? t('Knowledge base enabled') : t('Use knowledge base')"
                            @click="chat.useKnowledgeBase.value = !chat.useKnowledgeBase.value"
                        >
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                            <span class="text-sm font-medium">{{ t('KB') }}</span>
                        </button>

                        <button
                            v-if="speechSupported && enableVoice"
                            class="flex h-9 items-center justify-center gap-2 rounded-xl px-3 transition-colors"
                            :class="isRecording
                                ? 'bg-red-500/10 text-red-600 dark:bg-red-500/15 dark:text-red-400 animate-pulse'
                                : 'text-gray-500 hover:bg-black/5 hover:text-gray-700 dark:text-white/40 dark:hover:bg-white/5 dark:hover:text-white/70'"
                            :title="isRecording ? t('Stop recording') : t('Voice input')"
                            @click="toggleRecording"
                        >
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                            </svg>
                            <span class="text-sm font-medium">{{ isRecording ? t('Recording...') : t('Voice') }}</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <ModelSelector />

                        <template v-if="chat.isStreaming.value">
                            <button class="flex h-9 min-w-9 items-center justify-center rounded-xl bg-red-500/10 px-3 text-red-600 transition-colors hover:bg-red-500/15 dark:bg-red-500/15 dark:text-red-400 dark:hover:bg-red-500/25" :title="t('Stop')" :aria-label="t('Stop')" @click="stop">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" /></svg>
                            </button>
                        </template>
                        <template v-else>
                            <button
                                class="flex h-9 min-w-9 items-center justify-center rounded-xl bg-primary-500 px-3 text-white shadow-sm transition-all hover:bg-primary-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-30 dark:bg-primary-500 dark:hover:bg-primary-600"
                                :disabled="!inputText.trim() && !uploadedAttachments.length"
                                @click="send"
                            >
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" /></svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.chat-textarea {
    outline: none;
    box-shadow: none;
    caret-color: var(--color-primary-500);
}

.chat-textarea:focus {
    outline: none;
    border-color: transparent;
    box-shadow: none;
}

.chat-textarea:focus-visible {
    outline: none;
    border-color: transparent;
    box-shadow: none;
}
</style>
