<script setup lang="ts">
import { computed, provide, ref, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppLayout from '@themes/default/js/Layouts/AppLayout.vue'
import { useChat } from '../../Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import ChatSidebar from '../../Components/ChatSidebar.vue'
import ChatWelcome from '../../Components/ChatWelcome.vue'
import ChatMessages from '../../Components/ChatMessages.vue'
import ChatInput from '../../Components/ChatInput.vue'

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

// Head tags from the addon's SEO settings. The title falls back to the previous
// hardcoded value so an unconfigured install is unchanged; the description tag is
// omitted when unset so app.blade.php's site-wide default stands rather than being
// overridden with an empty one on hydration.
const meta = computed(() => (page.props.meta as { title?: string; description?: string } | undefined) ?? {})
const metaTitle = computed(() => meta.value.title || t('AI Chat'))
const metaDescription = computed(() => meta.value.description ?? '')

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

const getModeIconClass = (icon?: string | null) => {
    const value = icon?.trim()
    if (!value) return 'ti ti-grid-dots'

    if (value === 'ti-world-search' || value === 'ti ti-world-search') {
        return 'ti ti-world-www'
    }

    if (value.startsWith('ti ')) return value
    if (value.startsWith('ti-')) return `ti ${value}`
    return `ti ti-${value}`
}

const activeProject = computed(() => {
    if (!chat.activeConversation.value?.project_id) return null
    return chat.projects.value.find(p => p.id === chat.activeConversation.value?.project_id) || null
})

const otherProjects = computed(() => {
    const currentProjId = chat.activeConversation.value?.project_id
    return chat.projects.value.filter(p => p.id !== currentProjId)
})

const breadcrumbMenuRef = ref<HTMLElement | null>(null)
const breadcrumbMenuOpen = ref(false)
onClickOutside(breadcrumbMenuRef, () => {
    breadcrumbMenuOpen.value = false
})

const renamingActiveConversation = ref(false)
const activeConversationRenameValue = ref('')
const renameInputRef = ref<HTMLInputElement | null>(null)

const startActiveConversationRename = async () => {
    if (!chat.activeConversation.value) return
    activeConversationRenameValue.value = chat.activeConversation.value.title || ''
    renamingActiveConversation.value = true
    breadcrumbMenuOpen.value = false
    await nextTick()
    renameInputRef.value?.focus()
}

const saveActiveConversationRename = async () => {
    if (!renamingActiveConversation.value || !chat.activeConversation.value) return
    const title = activeConversationRenameValue.value.trim()
    await chat.renameConversation(chat.activeConversation.value.ulid, title || null)
    renamingActiveConversation.value = false
}

const cancelActiveConversationRename = () => {
    renamingActiveConversation.value = false
}

const onMoveToProject = async (projectId: number | null) => {
    if (!chat.activeConversation.value) return
    const convUlid = chat.activeConversation.value.ulid
    await chat.moveToProject(convUlid, projectId)
    breadcrumbMenuOpen.value = false
    if (chat.activeConversation.value?.ulid === convUlid) {
        chat.activeConversation.value = { ...chat.activeConversation.value, project_id: projectId }
    }
}

const togglePin = async () => {
    if (!chat.activeConversation.value) return
    await chat.togglePin(chat.activeConversation.value.ulid)
    breadcrumbMenuOpen.value = false
}

const showDeleteConfirm = ref(false)
const deleteProcessing = ref(false)

const deleteActiveConversation = () => {
    breadcrumbMenuOpen.value = false
    showDeleteConfirm.value = true
}

const confirmDeleteActiveConversation = async () => {
    if (!chat.activeConversation.value) return
    deleteProcessing.value = true
    try {
        await chat.deleteConversation(chat.activeConversation.value.ulid)
    } finally {
        deleteProcessing.value = false
        showDeleteConfirm.value = false
    }
}
</script>

<template>
    <Head :title="metaTitle">
        <meta v-if="metaDescription" name="description" :content="metaDescription" head-key="description" />
    </Head>

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

    <!-- Full layout with sidebar. Guests get it too when guest messaging is on: their
         conversations are persisted against session_id, so they have real history to
         browse — the sidebar just hides the account-only sections (see ChatSidebar). -->
    <div v-if="isAuthenticated || allowGuest" class="chat-layout">
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
            <!-- Breadcrumbs -->
            <div v-if="chat.activeConversation.value" class="absolute top-[18px] left-16 lg:left-6 z-20 flex items-center gap-1.5 text-xs text-[#6e6a65] dark:text-white/40 font-medium select-none">
                <Link :href="route('chat.index')" class="!text-[#6e6a65] !dark:text-white/40 hover:!text-[#1a1a1a] dark:hover:!text-white transition-colors no-underline flex items-center gap-1">
                    <span>{{ t('Home') }}</span>
                </Link>
                <span class="text-gray-300 dark:text-white/10">/</span>
                <template v-if="activeProject">
                    <button class="hover:text-[#1a1a1a] dark:hover:text-white transition-colors cursor-pointer bg-transparent border-0 p-0 text-xs font-medium" @click="chat.selectProject(activeProject)">
                        {{ activeProject.name }}
                    </button>
                    <span class="text-gray-300 dark:text-white/10">/</span>
                </template>

                <!-- Chat title with inline rename and action menu -->
                <div class="relative flex items-center gap-1 group">
                    <template v-if="renamingActiveConversation">
                        <input
                            ref="renameInputRef"
                            v-model="activeConversationRenameValue"
                            class="px-1.5 py-0.5 rounded border border-black/10 dark:border-white/20 bg-white dark:bg-[#1a1a1a] text-xs text-[#1a1a1a] dark:text-white/80 outline-none max-w-[120px] sm:max-w-[200px]"
                            @keyup.enter="saveActiveConversationRename"
                            @keyup.escape="cancelActiveConversationRename"
                            @blur="saveActiveConversationRename"
                            @click.stop
                        />
                    </template>
                    <template v-else>
                        <span
                            class="text-[#1a1a1a] dark:text-white/80 max-w-[120px] sm:max-w-[200px] truncate hover:underline cursor-text font-semibold flex items-center gap-1"
                            :title="t('Double click to rename')"
                            @dblclick.stop="startActiveConversationRename"
                        >
                            {{ chat.activeConversation.value.title || t('New conversation') }}
                        </span>

                        <div class="relative" ref="breadcrumbMenuRef">
                            <button
                                class="flex h-5 w-5 items-center justify-center rounded-full text-gray-400 dark:text-white/40 hover:bg-black/5 dark:hover:bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity"
                                :class="{ 'opacity-100 bg-black/5 dark:bg-white/5': breadcrumbMenuOpen }"
                                @click.stop="breadcrumbMenuOpen = !breadcrumbMenuOpen"
                            >
                                <i class="ti ti-chevron-down text-[10px]"></i>
                            </button>
                            <div v-if="breadcrumbMenuOpen" class="absolute left-0 top-full mt-1.5 min-w-[160px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1 z-50">
                                <div v-if="otherProjects.length" class="px-3 py-1.5 text-[11px] font-medium text-[#b0aca8] dark:text-white/30">{{ t('Move to Project') }}</div>
                                <button v-for="proj in otherProjects" :key="proj.id" class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="onMoveToProject(proj.id)">
                                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: proj.color_hex || '#6b7280' }"></span>
                                    <span class="truncate">{{ proj.name }}</span>
                                </button>
                                <button v-if="chat.activeConversation.value.project_id" class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="onMoveToProject(null)">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    <span>{{ t('Remove from project') }}</span>
                                </button>
                                <div class="border-t border-black/5 dark:border-white/10 my-0.5"></div>

                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="togglePin">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z" /></svg>
                                    <span>{{ chat.activeConversation.value.is_pinned ? t('Unpin') : t('Pin') }}</span>
                                </button>

                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="startActiveConversationRename">
                                    <i class="ti ti-pencil text-[12px]"></i>
                                    <span>{{ t('Rename') }}</span>
                                </button>

                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-500/5 transition-colors" @click.stop="deleteActiveConversation">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    <span>{{ t('Delete') }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Floating Mode Badge -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="-translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-4 opacity-0"
            >
                <div v-if="chat.selectedMode.value" class="absolute top-4 left-1/2 -translate-x-1/2 z-30 pointer-events-auto">
                    <div
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-black/10 bg-white/80 dark:bg-black/40 dark:border-white/10 shadow-lg backdrop-blur-md transition-all hover:bg-white/95 dark:hover:bg-black/60 group"
                    >
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :style="{ backgroundColor: chat.selectedMode.value.color_hex || '#6b7280' }"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2" :style="{ backgroundColor: chat.selectedMode.value.color_hex || '#6b7280' }"></span>
                        </span>
                        <i :class="getModeIconClass(chat.selectedMode.value.icon)" class="text-sm text-gray-700 dark:text-gray-300"></i>
                        <span class="text-xs font-semibold text-gray-900 dark:text-white capitalize select-none">{{ chat.selectedMode.value.name }} {{ t('Mode') }}</span>

                        <button
                            type="button"
                            class="ml-1 flex items-center justify-center w-4 h-4 rounded-full bg-black/5 dark:bg-white/10 hover:bg-red-500 hover:text-white dark:hover:bg-red-500 transition-colors text-gray-400"
                            :title="t('Exit Mode')"
                            @click.stop="chat.newChat()"
                        >
                            <i class="ti ti-x text-[9px]"></i>
                        </button>
                    </div>
                </div>
            </Transition>

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

    <!-- Active Chat Delete Confirmation Modal -->
    <ActionConfirmModal
        :open="showDeleteConfirm"
        :title="t('Delete conversation?')"
        :message="t('This will permanently delete this conversation and its messages.')"
        :confirm-label="t('Delete conversation')"
        processing-label="Deleting..."
        :processing="deleteProcessing"
        variant="danger"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmDeleteActiveConversation"
    />
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
