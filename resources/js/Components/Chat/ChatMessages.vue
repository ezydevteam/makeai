<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import ChatMessage from '@/Components/Chat/ChatMessage.vue'
import type { useChat } from '@/Composables/useChat'

const chat = inject<ReturnType<typeof useChat>>('chat')!
const page = usePage()

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
            <ChatMessage
                v-for="msg in chat.messages.value"
                :key="msg.id"
                :message="msg"
                :is-streaming="chat.isStreaming.value && msg === chat.messages.value[chat.messages.value.length - 1]"
                :user-credits="userCredits"
                :credit-threshold="creditThreshold"
                @repeat="handleRepeat"
            />
        </div>
    </div>
</template>
