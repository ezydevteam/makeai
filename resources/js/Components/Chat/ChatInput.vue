<script setup lang="ts">
import { computed, inject, nextTick, onMounted, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { useChat } from '@/Composables/useChat'
import ModelSelector from '@/Components/Chat/ModelSelector.vue'

const { t } = useTranslate()
const chat = inject<ReturnType<typeof useChat>>('chat')!
const sidebarMobileOpen = inject<Ref<boolean>>('sidebarMobileOpen', ref(false))

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

const focusTextarea = async () => {
    await nextTick()
    textareaRef.value?.focus({ preventScroll: true })
}

onMounted(() => {
    void focusTextarea()
})

watch(
    () => [chat.activeConversation.value?.ulid, chat.selectedProduct.value?.slug],
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
        <div class="mx-auto max-w-[768px]">
            <!-- File preview -->
            <div v-if="attachedFile" class="mb-2 flex items-center gap-2 rounded-2xl border border-black/5 bg-black/[0.03] px-3 py-2 text-sm dark:border-white/10 dark:bg-white/[0.04]">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#6e6a65] dark:text-white/40"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                <span class="text-xs text-[#6e6a65] dark:text-white/50 truncate flex-1">{{ attachedFile.name }}</span>
                <button class="text-[#b0aca8] dark:text-white/30 hover:text-[#1a1a1a] dark:hover:text-white/70 transition-colors" @click="removeFile">&times;</button>
            </div>

            <div class="rounded-[1.5rem] border border-black/10 bg-white px-3 py-3 shadow-[0_10px_30px_rgba(17,24,39,0.06)] transition-colors dark:border-white/10 dark:bg-[#1c1c1c] dark:shadow-[0_10px_30px_rgba(0,0,0,0.25)] focus-within:border-primary-500/30 dark:focus-within:border-primary-500/30">
                <textarea
                    ref="textareaRef"
                    v-model="inputText"
                    :placeholder="chat.selectedProduct.value
                        ? t('Ask anything in :product mode...', { product: chat.selectedProduct.value.name })
                        : t('Ask anything...')
                    "
                    class="chat-textarea w-full resize-none rounded-2xl border border-transparent bg-transparent px-1 py-2 text-[15px] leading-relaxed text-gray-900 placeholder:text-gray-400 dark:text-white/90 dark:placeholder:text-white/25"
                    rows="1"
                    @keydown="onKeydown"
                    @input="autoResize"
                ></textarea>

                <div class="flex items-center justify-between gap-3 dark:border-white/10">
                    <button class="flex h-9 items-center justify-center gap-2 rounded-xl px-3 text-gray-500 transition-colors hover:bg-black/5 hover:text-gray-700 dark:text-white/40 dark:hover:bg-white/5 dark:hover:text-white/70" title="Attach file" @click="fileInputRef?.click()">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-5.7l-1.415-1.414" /></svg>
                        <span class="text-sm font-medium">{{ t('Attach') }}</span>
                    </button>
                    <input ref="fileInputRef" type="file" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.csv,.txt,.md" @change="onFileSelect" />

                    <div class="flex items-center gap-2">
                        <ModelSelector />

                        <template v-if="chat.isStreaming.value">
                            <button class="flex h-9 min-w-9 items-center justify-center rounded-xl bg-red-500/10 px-3 text-red-600 transition-colors hover:bg-red-500/15 dark:bg-red-500/15 dark:text-red-400 dark:hover:bg-red-500/25" title="Stop" @click="stop">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" /></svg>
                            </button>
                        </template>
                        <template v-else>
                            <button
                                class="flex h-9 min-w-9 items-center justify-center rounded-xl bg-primary-500 px-3 text-white shadow-sm transition-all hover:bg-primary-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-30 dark:bg-primary-500 dark:hover:bg-primary-600"
                                :disabled="!inputText.trim()"
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
