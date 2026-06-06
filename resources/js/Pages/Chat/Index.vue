<script setup lang="ts">
import { computed, provide, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useChat } from '@/Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue'
import ChatWelcome from '@/Components/Chat/ChatWelcome.vue'
import ChatMessages from '@/Components/Chat/ChatMessages.vue'
import ChatInput from '@/Components/Chat/ChatInput.vue'

defineOptions({ layout: AppLayout })

const { t } = useTranslate()
const page = usePage()
const user = computed(() => page.props.auth?.user)
const isAuthenticated = computed(() => Boolean(user.value))
const allowGuest = computed(() => Boolean(page.props.allow_guest_messages as boolean))
const isProAvailable = computed(() => Boolean(page.props.isProAvailable as boolean))
const exploreOpen = ref(false)
const exploreRef = ref<HTMLElement | null>(null)
const chat = useChat()
const sidebarCollapsed = ref(false)

function handleKeydown(e: KeyboardEvent) {
    const mod = e.metaKey || e.ctrlKey

    // Ctrl+Shift+O — new chat
    if (mod && e.shiftKey && (e.key === 'o' || e.key === 'O')) {
        e.preventDefault()
        chat.newChat()
        return
    }

    // Ctrl+K — focus sidebar
    if (mod && e.key === 'k') {
        e.preventDefault()
        return
    }

    // Ctrl+B — toggle sidebar
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

onClickOutside(exploreRef, () => { exploreOpen.value = false })
</script>

<template>
    <Head title="AI Chat" />

    <!-- Top-right bar (always visible for Explore + Sign In when not auth'd) -->
    <div class="fixed top-0 right-0 z-40 flex items-center gap-2 px-4 py-3">
        <div ref="exploreRef" class="relative">
            <button
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white dark:bg-white/5 border border-black/5 dark:border-white/10 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
                @click="exploreOpen = !exploreOpen"
            >
                {{ t('Explore') }}
                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div v-if="exploreOpen" class="absolute right-0 top-full mt-1 min-w-[180px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1.5 z-50">
                <Link href="/ai-tools" class="block px-3.5 py-1.5 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="exploreOpen = false">{{ t('All AI Tools') }}</Link>
                <Link href="/blog" class="block px-3.5 py-1.5 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="exploreOpen = false">{{ t('Blog') }}</Link>
                <Link href="/pricing" class="block px-3.5 py-1.5 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="exploreOpen = false">{{ t('Pricing') }}</Link>
            </div>
        </div>
        <Link v-if="!isAuthenticated" :href="route('login')" class="px-4 py-1.5 rounded-full bg-[#1a1a1a] dark:bg-white text-white dark:text-[#1a1a1a] text-sm font-medium hover:opacity-90 transition-opacity no-underline">{{ t('Sign In') }}</Link>
    </div>

    <!-- Authenticated: full layout with sidebar -->
    <div v-if="isAuthenticated" class="chat-layout">
        <ChatSidebar />
        <div class="chat-main">
            <ChatWelcome v-if="!chat.activeConversation.value" />
            <ChatMessages v-else />
            <ChatInput />
        </div>
    </div>

    <!-- Guest with allow_guest_messages: no sidebar, just chat -->
    <div v-else-if="allowGuest" class="chat-layout">
        <div class="chat-main chat-main-full">
            <ChatWelcome v-if="!chat.activeConversation.value" />
            <ChatMessages v-else />
            <ChatInput />
        </div>
    </div>

    <!-- Guest without permissions: login prompt -->
    <div v-else class="flex items-center justify-center h-screen bg-[#f6f5f4] dark:bg-[#1a1a1a]">
        <div class="text-center max-w-md px-6">
            <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-black/5 dark:bg-white/5 flex items-center justify-center">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-gray-700 dark:text-gray-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ t('Chat with AI') }}</h1>
            <p class="text-gray-500 dark:text-gray-400 mb-6">{{ t('Sign in to start chatting with our AI assistant.') }}</p>
            <div class="flex items-center justify-center gap-3">
                <Link :href="route('login')" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors">{{ t('Sign In') }}</Link>
                <Link :href="route('register')" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/10 transition-colors">{{ t('Create Account') }}</Link>
            </div>
        </div>
    </div>
</template>

<style>
main { flex: 1 1 0% !important; min-height: 0 !important; position: relative !important; overflow: hidden !important; }

.chat-layout {
    position: absolute;
    inset: 0;
    display: flex;
    overflow: hidden;
    background: #f6f5f4;
    color: #1a1a1a;
}

.dark .chat-layout {
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

.chat-main-full {
    max-width: 100%;
}
</style>
