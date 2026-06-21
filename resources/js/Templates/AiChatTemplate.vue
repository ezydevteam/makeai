<script setup lang="ts">
import { computed, provide, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useChat } from '@/Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@/Components/AdSection.vue'
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue'
import ChatWelcome from '@/Components/Chat/ChatWelcome.vue'
import ChatMessages from '@/Components/Chat/ChatMessages.vue'
import ChatInput from '@/Components/Chat/ChatInput.vue'

const props = defineProps<{
    template?: Record<string, any>
}>()

const { t } = useTranslate()
const page = usePage()
const user = computed(() => page.props.auth?.user)
const isAuthenticated = computed(() => Boolean(user.value))
const isProAvailable = computed(() => Boolean(page.props.isProAvailable as boolean))

const chat = useChat()

const sidebarCollapsed = ref(false)
const sidebarMobileOpen = ref(false)
const isMobileSidebar = ref(false)

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
            <AdSection zone="chat_banner" class="mx-auto max-w-[768px] px-4" />
            <ChatInput />
        </div>
    </div>

    <div v-else class="flex h-screen items-center justify-center bg-[var(--color-bg)]">
        <div class="max-w-md px-6 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-gray-700 dark:text-gray-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
            </div>
            <h1 class="mb-3 text-2xl font-semibold text-gray-900">{{ template?.name || t('AI Chatbot') }}</h1>
            <p class="mb-6 text-gray-500">{{ template?.tagline || t('Sign in to start chatting with our AI assistant.') }}</p>
            <div class="flex items-center justify-center gap-3">
                <Link
                    :href="route('login')"
                    class="btn-primary inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-medium text-white transition-colors"
                >
                    {{ t('Sign In') }}
                </Link>
                <Link
                    :href="route('register')"
                    class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50"
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
    background: var(--surface-bg);
    color: var(--color-gray-900);
}

:global(.dark) .chat-layout {
    background: var(--surface-bg);
    color: var(--color-gray-100);
}

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
    background: var(--surface-bg);
    color: inherit;
}

:global(.dark) .chat-main {
    background: var(--surface-bg);
}
</style>
