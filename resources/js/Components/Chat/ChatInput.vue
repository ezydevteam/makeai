<script setup lang="ts">
import { computed, inject, nextTick, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { useChat } from '@/Composables/useChat'
import ModelSelector from '@/Components/Chat/ModelSelector.vue'

const { t } = useTranslate()
const chat = inject<ReturnType<typeof useChat>>('chat')!

const inputText = ref('')
const fileInputRef = ref<HTMLInputElement | null>(null)
const textareaRef = ref<HTMLTextAreaElement | null>(null)
const attachedFile = ref<File | null>(null)

const send = () => {
    const text = inputText.value.trim()
    if (!text || chat.isStreaming.value) return
    inputText.value = ''
    attachedFile.value = null
    resetTextareaHeight()
    chat.sendMessage(text, chat.selectedProduct.value?.slug ?? undefined)
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
        if (chat.isStreaming.value) stop()
        else send()
    }
    if (e.key === 'Escape') {
        attachedFile.value = null
    }
}

const onFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement
    if (input.files && input.files.length > 0) {
        attachedFile.value = input.files[0]
    }
}

const removeFile = () => {
    attachedFile.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
}
</script>

<template>
    <div class="shrink-0 px-6 pb-6 pt-2">
        <div class="max-w-[768px] mx-auto">
            <!-- File preview -->
            <div v-if="attachedFile" class="flex items-center gap-2 px-3 py-1.5 mb-1.5 bg-black/5 dark:bg-white/5 rounded-lg">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#6e6a65] dark:text-white/40"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                <span class="text-xs text-[#6e6a65] dark:text-white/50 truncate flex-1">{{ attachedFile.name }}</span>
                <button class="text-[#b0aca8] dark:text-white/30 hover:text-[#1a1a1a] dark:hover:text-white/70 transition-colors" @click="removeFile">&times;</button>
            </div>

            <div class="flex items-end gap-2 px-4 py-3 rounded-2xl bg-white dark:bg-white/5 border border-black/5 dark:border-white/10 transition-colors focus-within:border-black/10 dark:focus-within:border-white/20">
                <button class="shrink-0 w-7 h-7 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 flex items-center justify-center text-[#b0aca8] dark:text-white/30 hover:text-[#6e6a65] dark:hover:text-white/50 transition-colors" title="Attach file" @click="fileInputRef?.click()">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                </button>
                <input ref="fileInputRef" type="file" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.csv,.txt,.md" @change="onFileSelect" />

                <textarea
                    ref="textareaRef"
                    v-model="inputText"
                    :placeholder="chat.selectedProduct.value
                        ? t('Ask anything in :product mode...', { product: chat.selectedProduct.value.name })
                        : t('Ask anything...')
                    "
                    class="chat-textarea flex-1 min-h-[44px] resize-none leading-relaxed text-[15px] text-[#1a1a1a] dark:text-[#e8e6e3] placeholder:text-[#b0aca8] dark:placeholder:text-white/25"
                    rows="1"
                    @keydown="onKeydown"
                    @input="autoResize"
                ></textarea>

                <ModelSelector />

                <template v-if="chat.isStreaming.value">
                    <button class="w-[34px] h-[34px] rounded-full bg-red-100 dark:bg-red-500/15 hover:bg-red-200 dark:hover:bg-red-500/25 text-red-500 dark:text-red-400 flex items-center justify-center shrink-0 transition-colors" title="Stop" @click="stop">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" /></svg>
                    </button>
                </template>
                <template v-else>
                    <button
                        class="w-[34px] h-[34px] rounded-full bg-[#d9cec7] hover:bg-[#cfc3bb] dark:bg-white/10 dark:hover:bg-white/15 text-[#1a1a1a] dark:text-white/80 flex items-center justify-center shrink-0 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                        :disabled="!inputText.trim()"
                        @click="send"
                    >
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" /></svg>
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.chat-textarea {
    border: none;
    outline: none;
    background: transparent;
    box-shadow: none;
}

.chat-textarea:focus {
    outline: none;
    border: none;
    box-shadow: none;
}
</style>
