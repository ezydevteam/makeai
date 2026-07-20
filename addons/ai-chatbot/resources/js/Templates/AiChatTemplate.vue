<script setup lang="ts">
import { computed, provide, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useChat } from '../Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import ChatSidebar from '../Components/ChatSidebar.vue'
import ChatWelcome from '../Components/ChatWelcome.vue'
import ChatMessages from '../Components/ChatMessages.vue'
import ChatInput from '../Components/ChatInput.vue'

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
const isMobile = ref(false)
// Created in onMounted so this template is safe to render server-side (no `window` at setup).
let mobileQuery: MediaQueryList | null = null

const syncSidebarMode = () => {
    if (!mobileQuery) return
    isMobile.value = mobileQuery.matches
    sidebarCollapsed.value = mobileQuery.matches
    sidebarMobileOpen.value = false
}

function handleKeydown(e: KeyboardEvent) {
    const mod = e.metaKey || e.ctrlKey
    if (mod && e.shiftKey && (e.key === 'o' || e.key === 'O')) {
        e.preventDefault()
        chat.newChat()
        return
    }
    if (mod && e.key === 'b') {
        e.preventDefault()
        if (mobileQuery?.matches) {
            sidebarMobileOpen.value = !sidebarMobileOpen.value
        } else {
            sidebarCollapsed.value = !sidebarCollapsed.value
        }
        return
    }
}

onMounted(() => {
    mobileQuery = window.matchMedia('(max-width: 1023px)')
    syncSidebarMode()
    document.addEventListener('keydown', handleKeydown)
    mobileQuery.addEventListener('change', syncSidebarMode)
})
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
    mobileQuery?.removeEventListener('change', syncSidebarMode)
})

provide('chat', chat)
provide('isProAvailable', isProAvailable)
provide('sidebarCollapsed', sidebarCollapsed)
provide('sidebarMobileOpen', sidebarMobileOpen)
provide('isMobileSidebar', isMobile)
</script>

<template>
    <Head :title="template?.name || 'AI Chat'" />

    <div v-if="isAuthenticated" class="chat-layout">
        <!-- Mobile overlay: tap to close the sidebar -->
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
            <!-- Mobile hamburger to open the sidebar -->
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

    :global(.dark) .sidebar-shell {
        background-color: var(--color-surface-900);
    }
}
</style>
