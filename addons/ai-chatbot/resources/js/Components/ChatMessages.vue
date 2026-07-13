<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import ChatMessage from './ChatMessage.vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { useChat } from '../Composables/useChat'

const chat = inject<ReturnType<typeof useChat>>('chat')!
const page = usePage()
const { t } = useTranslate()

const scrollRef = ref<HTMLElement | null>(null)
const userCredits = computed(() => (page.props.auth?.user as any)?.credits ?? 0)
const creditThreshold = computed(() => (page.props.chat_credits_low_threshold as number) ?? 100)

// Optional brand logo (admin setting) shown as a small header on the active chat.
const chatLogo = computed(() => (page.props.chatbot as { chatLogo?: string | null } | undefined)?.chatLogo || null)

function handleRepeat(messageId: number | string) {
    const messages = chat.messages.value
    const index = messages.findIndex(m => m.id === messageId)
    if (index > 0 && messages[index - 1].role === 'user') {
        const userMessage = messages[index - 1]
        chat.sendMessage(userMessage.content, chat.selectedMode.value?.slug ?? undefined)
    }
}

function isNearBottom(el: HTMLElement, threshold = 120): boolean {
    return el.scrollHeight - el.scrollTop - el.clientHeight < threshold
}

// Whether to keep the view pinned to the bottom. A freshly appended message re-pins;
// if the user scrolls up mid-stream we stop yanking them back down.
const pinToBottom = ref(true)

// Watch BOTH the message count and the last message's content. During streaming only
// the content of the last message grows (the array length is unchanged), so watching
// length alone — as before — never re-scrolled and long replies streamed off-screen.
watch(
    () => [chat.messages.value.length, chat.messages.value[chat.messages.value.length - 1]?.content] as const,
    async ([len], [prevLen]) => {
        const el = scrollRef.value
        if (!el) return
        const appended = len !== prevLen
        // Evaluate position BEFORE the DOM patches (watcher runs pre-flush).
        if (appended) pinToBottom.value = true
        else pinToBottom.value = isNearBottom(el)
        await nextTick()
        if (pinToBottom.value) {
            el.scrollTo({ top: el.scrollHeight, behavior: appended ? 'smooth' : 'auto' })
        }
    }
)
</script>

<template>
    <div ref="scrollRef" class="flex-1 overflow-y-auto">
        <!-- Brand logo header (admin-configurable); sticky so it stays while chatting -->
        <div v-if="chatLogo" class="sticky top-0 z-10 flex justify-center bg-gradient-to-b from-[#f6f5f4] to-transparent dark:from-[#1a1a1a] px-6 pt-4 pb-3">
            <img :src="chatLogo" alt="" class="h-8 w-auto max-w-[160px] object-contain" />
        </div>
        <div class="!max-w-[768px] mx-auto px-6 py-6 flex flex-col gap-6">
            <!-- Load more button -->
            <div v-if="chat.hasMoreMessages.value" class="flex justify-center">
                <button
                    class="px-4 py-2 text-sm text-[#6e6a65] dark:text-white/50 hover:text-[#1a1a1a] dark:hover:text-white/80 transition-colors disabled:opacity-50"
                    :disabled="chat.loadingOlder.value"
                    @click="chat.activeConversation.value && chat.loadOlderMessages(chat.activeConversation.value.ulid)"
                >
                    <span v-if="chat.loadingOlder.value">{{ t('Loading...') }}</span>
                    <span v-else>{{ t('Load earlier messages') }}</span>
                </button>
            </div>

            <!-- Loading state: fetching an existing conversation's messages -->
            <div v-if="chat.messagesLoading.value && !chat.messages.value.length" class="flex justify-center py-10">
                <span class="inline-flex items-center gap-2 text-sm text-[#6e6a65] dark:text-white/40">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    {{ t('Loading messages...') }}
                </span>
            </div>

            <!-- Error state: load failed — offer a retry instead of a blank panel -->
            <div v-else-if="chat.messagesError.value && !chat.messages.value.length" class="flex flex-col items-center gap-3 py-10 text-center">
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ chat.messagesError.value }}</p>
                <button
                    class="px-4 py-2 text-sm rounded-lg border border-gray-200 dark:border-surface-700 text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                    @click="chat.retryLoadMessages()"
                >
                    {{ t('Retry') }}
                </button>
            </div>

            <ChatMessage
                v-for="msg in chat.messages.value"
                :key="msg.id"
                :message="msg"
                :is-streaming="chat.isStreaming.value && msg === chat.messages.value[chat.messages.value.length - 1]"
                :user-credits="userCredits"
                :credit-threshold="creditThreshold"
                :conversation-ulid="chat.activeConversation.value?.ulid"
                @repeat="handleRepeat"
            />
        </div>
    </div>
</template>
