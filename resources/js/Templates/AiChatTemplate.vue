<script setup lang="ts">
import { computed, provide, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useChat } from '@/Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue'
import ChatWelcome from '@/Components/Chat/ChatWelcome.vue'
import ChatMessages from '@/Components/Chat/ChatMessages.vue'
import ChatInput from '@/Components/Chat/ChatInput.vue'

const props = defineProps<{
    template?: Record<string, any>
    tools?: any[]
    hide_footer?: boolean
    chatbot_only?: boolean
}>()

const { t } = useTranslate()
const page = usePage()
const user = computed(() => page.props.auth?.user)
const isAuthenticated = computed(() => Boolean(user.value))
const isProAvailable = computed(() => Boolean(page.props.isProAvailable as boolean))

const chat = useChat()

const sidebarCollapsed = ref(false)

function handleKeydown(e: KeyboardEvent) {
    const mod = e.metaKey || e.ctrlKey
    if (mod && e.shiftKey && (e.key === 'o' || e.key === 'O')) {
        e.preventDefault()
        chat.newChat()
        return
    }
    if (mod && e.key === 'b') {
        e.preventDefault()
        sidebarCollapsed.value = !sidebarCollapsed.value
        return
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))

provide('chat', chat)
provide('isProAvailable', isProAvailable)
provide('sidebarCollapsed', sidebarCollapsed)
</script>

<template>
    <Head :title="template?.name || 'AI Chat'" />

    <div v-if="isAuthenticated" class="chat-layout">
        <ChatSidebar />
        <div class="chat-main">
            <ChatWelcome v-if="!chat.activeConversation.value" />
            <ChatMessages v-else />
            <ChatInput />
        </div>
    </div>

    <div v-else class="flex items-center justify-center h-screen bg-[#f6f5f4] dark:bg-[#1a1a1a]">
        <div class="text-center max-w-md px-6">
            <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-black/5 dark:bg-white/5 flex items-center justify-center">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-gray-700 dark:text-gray-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-[#1a1a1a] dark:text-[#e8e6e3] mb-3">{{ template?.name || t('AI Chatbot') }}</h1>
            <p class="text-[#6e6a65] dark:text-white/40 mb-6">{{ template?.tagline || t('Sign in to start chatting with our AI assistant.') }}</p>
            <div class="flex items-center justify-center gap-3">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center px-5 py-2.5 rounded-xl bg-[#1a1a1a] dark:bg-white text-white dark:text-[#1a1a1a] text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors"
                >
                    {{ t('Sign In') }}
                </Link>
                <Link
                    :href="route('register')"
                    class="inline-flex items-center px-5 py-2.5 rounded-xl bg-white dark:bg-white/5 border border-black/5 dark:border-white/10 text-[#6e6a65] dark:text-white/50 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/10 transition-colors"
                >
                    {{ t('Create Account') }}
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.chat-layout {
    position: absolute;
    inset: 0;
    display: flex;
    overflow: hidden;
    background: #f6f5f4;
    color: #1a1a1a;
}

:global(.dark) .chat-layout {
    background: #1a1a1a;
    color: #e8e6e3;
}

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
}
</style>
