<script setup lang="ts">
import { computed, provide, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useChat } from '@/Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@/Components/AdSection.vue'
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
const sidebarMobileOpen = ref(false)
const mobileQuery = window.matchMedia('(max-width: 1023px)')
const isMobile = ref(mobileQuery.matches)
const activeChatUlid = computed(() => (page.props.active_chat_ulid as string | null) ?? null)

const syncSidebarMode = () => {
    isMobile.value = mobileQuery.matches
    if (mobileQuery.matches) {
        sidebarCollapsed.value = true
        sidebarMobileOpen.value = false
        return
    }

    sidebarCollapsed.value = false
    sidebarMobileOpen.value = false
}

function handleKeydown(e: KeyboardEvent) {
    const mod = e.metaKey || e.ctrlKey

    // Ctrl+Shift+O — new chat
    if (mod && e.shiftKey && (e.key === 'o' || e.key === 'O')) {
        e.preventDefault()
        chat.newChat()
        return
    }

    // Ctrl+B — toggle sidebar
    if (mod && e.key === 'b') {
        e.preventDefault()
        if (mobileQuery.matches) {
            sidebarMobileOpen.value = !sidebarMobileOpen.value
        } else {
            sidebarCollapsed.value = !sidebarCollapsed.value
        }
        return
    }
}

onMounted(() => {
    syncSidebarMode()
    document.addEventListener('keydown', handleKeydown)
    mobileQuery.addEventListener('change', syncSidebarMode)

    if (activeChatUlid.value) {
        void chat.selectConversationByUlid(activeChatUlid.value)
    }
})
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
    mobileQuery.removeEventListener('change', syncSidebarMode)
})

provide('chat', chat)
provide('isProAvailable', isProAvailable)
provide('sidebarCollapsed', sidebarCollapsed)
provide('sidebarMobileOpen', sidebarMobileOpen)
provide('isMobileSidebar', isMobile)

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
                <i class="ti ti-chevron-down text-[12px]"></i>
            </button>
            <div v-if="exploreOpen" class="absolute right-0 top-full mt-1 min-w-[140px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1.5 z-50">
                <Link href="/ai-tools" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="exploreOpen = false">
                    <i class="ti ti-sparkles text-[14px]"></i>
                    <span>{{ t('AI Tools') }}</span>
                </Link>
                <Link href="/blog" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="exploreOpen = false">
                    <i class="ti ti-article text-[14px]"></i>
                    <span>{{ t('Blog') }}</span>
                </Link>
                <Link href="/pricing" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="exploreOpen = false">
                    <i class="ti ti-credit-card text-[14px]"></i>
                    <span>{{ t('Pricing') }}</span>
                </Link>
            </div>
        </div>
        <Link v-if="!isAuthenticated" :href="route('login')" class="px-4 py-1.5 rounded-full bg-[#1a1a1a] dark:bg-white text-white dark:text-[#1a1a1a] text-sm font-medium hover:opacity-90 transition-opacity no-underline">{{ t('Sign In') }}</Link>
    </div>

    <!-- Authenticated: full layout with sidebar -->
    <div v-if="isAuthenticated" class="chat-layout">
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <button
                    v-if="sidebarMobileOpen"
                    type="button"
                    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    :aria-label="t('Close sidebar')"
                    @click="sidebarMobileOpen = false"
                ></button>
            </Transition>
        </Teleport>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="-translate-x-full opacity-0"
        >
            <div v-if="!isMobile || sidebarMobileOpen" class="sidebar-shell">
                <ChatSidebar />
            </div>
        </Transition>

        <div class="chat-main">
            <button
                type="button"
                class="absolute left-4 top-4 z-30 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/90 text-[#1a1a1a] shadow-lg dark:bg-white/10 dark:text-white lg:hidden"
                :aria-label="t('Open sidebar')"
                @click="sidebarMobileOpen = true"
            >
                <i class="ti ti-layout-sidebar-left-expand text-[18px]"></i>
            </button>
            <ChatWelcome v-if="!chat.activeConversation.value" />
            <ChatMessages v-else />
            <AdSection zone="chat_banner" class="mx-auto max-w-[768px] px-4" />
            <ChatInput />
        </div>
    </div>

    <!-- Guest with allow_guest_messages: no sidebar, just chat -->
    <div v-else-if="allowGuest" class="chat-layout">
        <div class="chat-main chat-main-full">
            <ChatWelcome v-if="!chat.activeConversation.value" />
            <ChatMessages v-else />
            <AdSection zone="chat_banner" class="mx-auto max-w-[768px] px-4" />
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

.sidebar-shell {
    flex-shrink: 0;
    block-size: 100vh;
    align-self: stretch;
}

@media (max-width: 1023px) {
    .sidebar-shell {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        z-index: 50;
        width: min(100vw, 320px);
        max-width: 100vw;
        background-color: #ffffff;
        background-image: none;
        border-inline-end: 1px solid var(--border-color);
        box-shadow: 18px 0 40px rgb(0 0 0 / 0.18);
        overflow: hidden;
        block-size: 100vh;
    }

    .dark .sidebar-shell {
        background-color: var(--color-surface-900);
    }

    .chat-layout {
        padding-inline-start: 0;
    }

    .chat-main {
        background: inherit;
    }
}
</style>
