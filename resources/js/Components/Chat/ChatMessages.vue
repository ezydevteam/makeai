<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import ChatMessage from '@/Components/Chat/ChatMessage.vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { useChat } from '@/Composables/useChat'

const chat = inject<ReturnType<typeof useChat>>('chat')!
const page = usePage()
const { t } = useTranslate()

const scrollRef = ref<HTMLElement | null>(null)
const userCredits = computed(() => (page.props.auth?.user as any)?.credits ?? 0)
const creditThreshold = computed(() => (page.props.chat_credits_low_threshold as number) ?? 100)

function handleRepeat(messageId: number | string) {
    const messages = chat.messages.value
    const index = messages.findIndex(m => m.id === messageId)
    if (index > 0 && messages[index - 1].role === 'user') {
        const userMessage = messages[index - 1]
        chat.sendMessage(userMessage.content, chat.selectedProduct.value?.slug ?? undefined)
    }
}

watch(() => chat.messages.value.length, async () => {
    await nextTick()
    if (scrollRef.value) {
        scrollRef.value.scrollTo({ top: scrollRef.value.scrollHeight, behavior: 'smooth' })
    }
})
</script>

<template>
    <div ref="scrollRef" class="flex-1 overflow-y-auto">
        <div class="max-w-[768px] mx-auto px-6 py-6 flex flex-col gap-6">
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
