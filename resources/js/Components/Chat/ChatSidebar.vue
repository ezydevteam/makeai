<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import type { Ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import TagManager from '@/Components/Chat/TagManager.vue'
import type { useChat, ChatProject, Conversation, ChatProduct, ConversationTag } from '@/Composables/useChat'

const siteUrl = typeof window !== 'undefined' ? window.location.origin : ''

const { t } = useTranslate()
const { isDark, toggleDark } = useTheme()
const page = usePage()
const chat = inject<ReturnType<typeof useChat>>('chat')!
const isProAvailable = inject<Ref<boolean>>('isProAvailable', ref(false))
const sidebarCollapsed = inject<Ref<boolean>>('sidebarCollapsed', ref(false))
const sidebarMobileOpen = inject<Ref<boolean>>('sidebarMobileOpen', ref(false))
const isMobileSidebar = inject<Ref<boolean>>('isMobileSidebar', ref(false))

const showProjects = ref(false)
const showNewProject = ref(false)
const newProjectName = ref('')
const newProjectColor = ref('#6b7280')
const chatSearchOpen = ref(false)
const chatSearchQuery = ref('')
const chatSearchRef = ref<HTMLInputElement | null>(null)
const menuOpen = ref(false)
const learnMoreOpen = ref(false)
const collapsed = sidebarCollapsed
const isCompactSidebar = computed(() => collapsed.value && !isMobileSidebar.value)
const showSettings = ref(false)
const referralCopied = ref(false)
const profileForm = useForm({
    name: '',
    email: '',
})
const avatarForm = useForm({
    avatar: null as File | null,
})
const avatarPreview = ref<string | null>(null)

const deletingConversation = ref<Conversation | null>(null)
const deletingProject = ref<ChatProject | null>(null)
const deleteProcessing = ref(false)
const moveMenuOpen = ref<string | null>(null)
const moveMenuRef = ref<HTMLElement | null>(null)
const renamingConversationUlid = ref<string | null>(null)

const showProducts = ref(false)
const productRef = ref<HTMLElement | null>(null)

const showTagManager = ref(false)
const showTagFilter = ref(false)
const tagFilterRef = ref<HTMLElement | null>(null)

const renamingProjectId = ref<number | null>(null)
const renameValue = ref('')

type SidebarUser = {
    ulid?: string
    name?: string
    email?: string
    id?: number
    avatar?: string | null
    referral_link?: string | null
    is_pro?: boolean
    plan?: {
        type?: string
    } | null
}

const user = computed(() => page.props.auth?.user as SidebarUser | undefined)
const isPro = computed(() => user.value?.is_pro === true || user.value?.plan?.type === 'pro')
const appName = computed(() => (page.props.appName as string) || t('Application'))
const userDashboardHref = computed(() => route('user.dashboard'))

const menuRef = ref<HTMLElement | null>(null)
const learnMoreRef = ref<HTMLElement | null>(null)
const settingsRef = ref<HTMLElement | null>(null)

onClickOutside(menuRef, () => { menuOpen.value = false; learnMoreOpen.value = false })
onClickOutside(learnMoreRef, () => { learnMoreOpen.value = false })
onClickOutside(settingsRef, () => { if (showSettings.value) showSettings.value = false })
onClickOutside(moveMenuRef, () => { moveMenuOpen.value = null })
onClickOutside(productRef, () => { showProducts.value = false })
onClickOutside(tagFilterRef, () => { showTagFilter.value = false })

const syncProfileForm = () => {
    profileForm.name = user.value?.name || ''
    profileForm.email = user.value?.email || ''
    avatarPreview.value = user.value?.avatar ? `/storage/${user.value.avatar}` : null
}

const openSettings = () => {
    syncProfileForm()
    customInstructions.value = (page.props.auth?.user as any)?.chat_custom_instructions || ''
    customInstructionsSaved.value = false
    showSettings.value = true
    menuOpen.value = false
}

const saveCustomInstructions = async () => {
    savingCustomInstructions.value = true
    customInstructionsSaved.value = false
    try {
        await chat.updateChatSettings(customInstructions.value || null)
        customInstructionsSaved.value = true
        setTimeout(() => { customInstructionsSaved.value = false }, 3000)
    } catch (error) {
        console.error('Failed to save custom instructions:', error)
    } finally {
        savingCustomInstructions.value = false
    }
}

const topProducts = computed(() => chat.products.value.slice(0, 2))
const moreProducts = computed(() => chat.products.value.slice(2))
const hasActiveChat = computed(() => !!chat.activeConversation.value)
const latestConversation = computed(() => {
    return [...chat.conversations.value]
        .sort((a, b) => {
            const aTime = new Date(a.last_message_at || 0).getTime()
            const bTime = new Date(b.last_message_at || 0).getTime()
            return bTime - aTime
        })[0] || null
})

const filteredConversations = computed(() => {
    const query = chatSearchQuery.value.trim().toLowerCase()
    const source = chat.selectedProject.value
        ? chat.conversations.value.filter(c => c.project_id === chat.selectedProject.value!.id)
        : chat.conversations.value

    if (!query) return source

    return source.filter(conv => {
        const title = (conv.title || '').toLowerCase()
        const product = (conv.product_slug || '').toLowerCase()
        const model = (conv.model || '').toLowerCase()
        return title.includes(query) || product.includes(query) || model.includes(query) || conv.ulid.toLowerCase().includes(query)
    })
})

const pinnedConversations = computed(() => {
    return filteredConversations.value.filter(conv => conv.is_pinned)
})

const unpinnedConversations = computed(() => {
    return filteredConversations.value.filter(conv => !conv.is_pinned)
})

const grouped = computed(() => {
    const groups: Record<string, Conversation[]> = {
        today: [],
        yesterday: [],
        last_7_days: [],
        older: [],
    }

    for (const conv of unpinnedConversations.value) {
        const ts = new Date(conv.last_message_at || '')
        const now = new Date()
        const days = Math.floor((now.getTime() - ts.getTime()) / 86400000)

        if (days === 0) groups.today.push(conv)
        else if (days === 1) groups.yesterday.push(conv)
        else if (days < 7) groups.last_7_days.push(conv)
        else groups.older.push(conv)
    }

    return groups
})

const doCreateProject = async () => {
    if (!newProjectName.value.trim()) return
    await chat.createProject(newProjectName.value.trim(), newProjectColor.value)
    newProjectName.value = ''
    showNewProject.value = false
}

const startRename = (proj: ChatProject) => {
    renamingProjectId.value = proj.id
    renameValue.value = proj.name
}

const startConversationRename = (conv: Conversation) => {
    renamingConversationUlid.value = conv.ulid
    renameValue.value = conv.title || ''
    moveMenuOpen.value = null
}

const doRename = async () => {
    if (renamingProjectId.value === null || !renameValue.value.trim()) {
        renamingProjectId.value = null
        return
    }
    await chat.renameProject(renamingProjectId.value, renameValue.value.trim())
    renamingProjectId.value = null
}

const doRenameConversation = async () => {
    if (renamingConversationUlid.value === null) {
        return
    }

    const title = renameValue.value.trim()
    await chat.renameConversation(renamingConversationUlid.value, title || null)
    renamingConversationUlid.value = null
}

const shareConversation = async (conv: Conversation) => {
    try {
        const shareUrl = await chat.shareConversation(conv.ulid)
        await navigator.clipboard.writeText(shareUrl)
        // Show a brief success indication
        moveMenuOpen.value = null
    } catch (e) {
        console.error('Failed to share conversation:', e)
    }
}

const copyShareLink = async (conv: Conversation) => {
    if (!conv.share_token) return
    const shareUrl = `${window.location.origin}/share/${conv.share_token}`
    try {
        await navigator.clipboard.writeText(shareUrl)
        moveMenuOpen.value = null
    } catch (e) {
        console.error('Failed to copy share link:', e)
    }
}

const toggleConversationTag = async (conv: Conversation, tag: ConversationTag) => {
    const currentTags = conv.tags || []
    const hasTag = currentTags.some(t => t.id === tag.id)
    const newTagIds = hasTag
        ? currentTags.filter(t => t.id !== tag.id).map(t => t.id)
        : [...currentTags.map(t => t.id), tag.id]
    
    await chat.tagConversation(conv.ulid, newTagIds)
    moveMenuOpen.value = null
}

const confirmDelete = async () => {
    deleteProcessing.value = true
    try {
        if (deletingConversation.value) {
            await chat.deleteConversation(deletingConversation.value.ulid)
        } else if (deletingProject.value) {
            await chat.deleteProject(deletingProject.value.id)
        }
    } finally {
        deleteProcessing.value = false
        deletingConversation.value = null
        deletingProject.value = null
    }
}

const onSelectProject = (project: ChatProject) => {
    chat.selectProject(project)
}

const onDeselectProject = () => {
    chat.selectProject(null)
    chat.loadConversations()
}

const openSidebarSection = async (section: 'projects' | 'products') => {
    collapsed.value = false
    await nextTick()
    if (section === 'projects') {
        showProjects.value = true
        showProducts.value = false
    } else {
        showProducts.value = true
        showProjects.value = false
    }
}

const openRecentChats = async () => {
    const latest = latestConversation.value
    if (!latest) return

    collapsed.value = false
    showProjects.value = false
    showProducts.value = false
    await nextTick()
    chat.selectProject(null)
    chat.selectConversation(latest)
}

const onSelectConversation = (conv: Conversation) => {
    chat.selectConversation(conv)
}

const onSelectProduct = (p: ChatProduct) => {
    chat.selectedProduct.value = p
    chat.selectedModel.value = p.default_model
    showProducts.value = false
}

const getProductMenuIcon = (product: ChatProduct) => {
    const slug = product.slug.toLowerCase()
    const name = product.name.toLowerCase()

    if (slug.includes('research') || name.includes('research')) return 'ti ti-search'
    if (slug.includes('code') || name.includes('code')) return 'ti ti-code'
    if (slug.includes('image') || slug.includes('design') || name.includes('image') || name.includes('design')) return 'ti ti-palette'
    if (slug.includes('write') || slug.includes('blog') || slug.includes('text') || name.includes('write') || name.includes('blog') || name.includes('text')) return 'ti ti-pencil'
    if (slug.includes('video') || name.includes('video')) return 'ti ti-video'
    if (slug.includes('audio') || slug.includes('voice') || name.includes('audio') || name.includes('voice')) return 'ti ti-microphone'
    if (slug.includes('data') || slug.includes('chart') || name.includes('data') || name.includes('chart')) return 'ti ti-chart-bar'
    if (slug.includes('seo') || name.includes('seo')) return 'ti ti-search-engine'
    if (slug.includes('marketing') || slug.includes('ads') || name.includes('marketing') || name.includes('ads')) return 'ti ti-speakerphone'
    return 'ti ti-sparkles'
}

const onMoveToProject = async (convUlid: string, projectId: number | null) => {
    await chat.moveToProject(convUlid, projectId)
    moveMenuOpen.value = null
    if (chat.activeConversation.value?.ulid === convUlid && projectId === null) {
        chat.activeConversation.value = { ...chat.activeConversation.value, project_id: null }
    }
}

const copyReferralLink = async () => {
    const link = user.value?.referral_link || `${siteUrl}/register?ref=${user.value?.ulid || user.value?.id || ''}`
    try {
        await window.navigator.clipboard.writeText(link)
        referralCopied.value = true
        window.setTimeout(() => {
            referralCopied.value = false
        }, 1800)
    } catch {
        referralCopied.value = false
    }
}

watch(chatSearchOpen, async open => {
    if (!open) return
    await nextTick()
    chatSearchRef.value?.focus({ preventScroll: true })
})

const selectReferralLink = (event: FocusEvent) => {
    const target = event.target
    if (target instanceof HTMLInputElement) {
        target.select()
    }
}

const signOut = () => {
    router.post(route('logout'))
}

const onAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement | null)?.files?.[0]
    if (!file) return

    avatarForm.avatar = file
    avatarPreview.value = URL.createObjectURL(file)
}

const saveProfile = () => {
    profileForm.put(route('user.dashboard.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            syncProfileForm()
        },
    })
}

const saveAvatar = () => {
    if (!avatarForm.avatar) return

    avatarForm.put(route('user.dashboard.avatar.update'), {
        preserveScroll: true,
        onSuccess: () => {
            avatarForm.reset('avatar')
            syncProfileForm()
        },
    })
}

onBeforeUnmount(() => {
    if (avatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(avatarPreview.value)
    }
})

const otherProjects = computed(() => {
    if (!chat.selectedProject.value) return chat.projects.value
    return chat.projects.value.filter(p => p.id !== chat.selectedProject.value!.id)
})
</script>

<template>
    <aside
        class="sidebar"
        :class="{
            collapsed: isCompactSidebar,
            mobileOpen: sidebarMobileOpen,
            mobileClosed: !sidebarMobileOpen,
        }"
    >
        <!-- Top bar: logo + collapse -->
        <div class="flex items-center justify-between p-3 border-b border-black/5 dark:border-white/5">
            <Link v-show="!isCompactSidebar" href="/" class="flex items-center gap-2.5 no-underline shrink-0">
                <div class="w-7 h-7 rounded-lg bg-[#d9cec7] dark:bg-white/10 flex items-center justify-center text-[#1a1a1a] dark:text-white/80 text-xs font-bold">
                    {{ appName.charAt(0) }}
                </div>
                <span class="text-sm font-semibold text-[#1a1a1a] dark:text-white/80">{{ appName }}</span>
            </Link>
            <div class="flex items-center gap-1">
                <Tooltip v-if="!isCompactSidebar" :content="chatSearchOpen ? t('Close search') : t('Search chats')" placement="right">
                    <button
                        class="h-7 w-7 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 flex items-center justify-center text-[#b0aca8] dark:text-white/30 transition-colors shrink-0"
                        :class="{ 'bg-black/5 dark:bg-white/10 text-[#1a1a1a] dark:text-white/80': chatSearchOpen }"
                        @click="chatSearchOpen = !chatSearchOpen"
                    >
                        <i class="ti ti-search text-[17px]"></i>
                    </button>
                </Tooltip>
                <Tooltip :content="isCompactSidebar ? t('Expand sidebar') : t('Collapse sidebar')" placement="right">
                    <button class="h-7 px-1.5 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 flex items-center justify-center text-[#b0aca8] dark:text-white/30 transition-colors shrink-0" :class="{ 'ml-auto w-full justify-end': isCompactSidebar }" @click="collapsed = !collapsed">
                        <i v-if="isCompactSidebar" class="ti ti-layout-sidebar-left-expand text-[18px]"></i>
                        <i v-else class="ti ti-layout-sidebar-left-collapse text-[18px]"></i>
                    </button>
                </Tooltip>
            </div>
        </div>

            <div v-if="chatSearchOpen" class="px-3 pt-2">
            <div class="flex items-center gap-2 rounded-xl border border-black/10 bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5">
                <i class="ti ti-search text-[15px] text-[#b0aca8] dark:text-white/30"></i>
                <input
                    ref="chatSearchRef"
                    v-model="chatSearchQuery"
                    type="text"
                    :placeholder="t('Search chats')"
                    class="chat-sidebar-search w-full bg-transparent text-sm text-[#1a1a1a] outline-none placeholder:text-[#b0aca8] dark:text-white/80 dark:placeholder:text-white/30"
                />
                <button
                    v-if="chatSearchQuery"
                    type="button"
                    class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-light leading-none text-[#b0aca8] transition-colors hover:text-[#1a1a1a] dark:text-white/30 dark:hover:text-white/70"
                    @click="chatSearchQuery = ''"
                >
                    <i class="ti ti-x text-[12px]"></i>
                </button>
            </div>
        </div>

        <!-- Selected project header -->
        <div v-if="chat.selectedProject.value && !isCompactSidebar" class="px-3 pt-2 pb-1">
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-black/5 dark:bg-white/5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: chat.selectedProject.value.color_hex || '#6b7280' }"></span>
                <span class="flex-1 text-sm font-medium text-[#1a1a1a] dark:text-white/80 truncate">{{ chat.selectedProject.value.name }}</span>
                <button class="shrink-0 text-[#b0aca8] dark:text-white/30 hover:text-[#1a1a1a] dark:hover:text-white/70 transition-colors" title="Clear project filter" @click="onDeselectProject">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <!-- Tag filter -->
        <div v-if="chat.tags.value.length > 0 && !isCompactSidebar" class="px-3 pt-1 pb-1">
            <div class="relative" ref="tagFilterRef">
                <button
                    class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                    :class="{ 'bg-black/5 dark:bg-white/5': chat.selectedTag.value }"
                    @click="showTagFilter = !showTagFilter"
                >
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <span class="flex-1 text-left truncate">
                        {{ chat.selectedTag.value ? chat.selectedTag.value.name : t('Filter by tag') }}
                    </span>
                    <svg v-if="chat.selectedTag.value" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0" @click.stop="chat.filterByTag(null)">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <svg v-else width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-if="showTagFilter" class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1 z-50 max-h-60 overflow-y-auto">
                    <button
                        class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                        @click="chat.filterByTag(null); showTagFilter = false"
                    >
                        <span>{{ t('All conversations') }}</span>
                    </button>
                    <button
                        v-for="tag in chat.tags.value"
                        :key="tag.id"
                        class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                        :class="{ 'bg-black/5 dark:bg-white/5': chat.selectedTag.value?.id === tag.id }"
                        @click="chat.filterByTag(tag); showTagFilter = false"
                    >
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color }"></span>
                        <span class="flex-1 truncate">{{ tag.name }}</span>
                        <span class="text-xs text-[#b0aca8] dark:text-white/30">{{ tag.conversations_count || 0 }}</span>
                    </button>
                    <div class="border-t border-black/5 dark:border-white/10 my-1"></div>
                    <button
                        class="flex items-center gap-2 w-full px-3 py-2 text-sm text-primary-500 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                        @click="showTagManager = true; showTagFilter = false"
                    >
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ t('Manage tags') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- New Chat button -->
        <div :class="isCompactSidebar ? 'p-1' : 'px-3 py-2'">
            <template v-if="isCompactSidebar">
                <Tooltip :content="t('New Chat')" placement="right">
                <button
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-black/5 text-[#6e6a65] transition-colors hover:bg-black/10 dark:bg-white/10 dark:text-white/50 dark:hover:bg-white/15"
                    @click="chat.newChat()"
                >
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </Tooltip>
            </template>
            <template v-else>
                <button
                    class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm font-medium text-[#6e6a65] dark:text-white/40 hover:text-black dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                    @click="chat.newChat()"
                >
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    <span>{{ t('New Chat') }}</span>
                </button>
            </template>
            <div v-if="isCompactSidebar" class="mt-2 flex flex-col gap-2 items-center">
                <Tooltip :content="t('Projects')" placement="right">
                    <button
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-black/5 text-[#6e6a65] transition-colors hover:bg-black/10 dark:bg-white/10 dark:text-white/50 dark:hover:bg-white/15"
                        :class="{ 'bg-black/10 text-[#1a1a1a] dark:bg-white/15 dark:text-white': showProjects }"
                        @click="openSidebarSection('projects')"
                    >
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                    </button>
                </Tooltip>
                <Tooltip v-if="hasActiveChat" :content="t('Products')" placement="right">
                    <button
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-black/5 text-[#6e6a65] transition-colors hover:bg-black/10 dark:bg-white/10 dark:text-white/50 dark:hover:bg-white/15"
                        :class="{ 'bg-black/10 text-[#1a1a1a] dark:bg-white/15 dark:text-white': showProducts }"
                        @click="openSidebarSection('products')"
                    >
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </button>
                </Tooltip>
                <Tooltip :content="t('Recent Chats')" placement="right">
                    <button
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-black/5 text-[#6e6a65] transition-colors hover:bg-black/10 dark:bg-white/10 dark:text-white/50 dark:hover:bg-white/15"
                        :class="{ 'bg-black/10 text-[#1a1a1a] dark:bg-white/15 dark:text-white': Boolean(latestConversation) && chat.activeConversation.value?.ulid === latestConversation?.ulid }"
                        :disabled="!latestConversation"
                        @click="openRecentChats"
                    >
                        <i class="ti ti-messages text-[18px]"></i>
                    </button>
                </Tooltip>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-2 pb-2 space-y-0.5" :class="{ 'px-1': isCompactSidebar }">
            <!-- Projects (only when no filter is active) -->
            <template v-if="isProAvailable && !isCompactSidebar && !chat.selectedProject.value">
                <div class="mb-1.5">
                    <button class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm font-medium text-[#6e6a65] dark:text-white/40 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click="showProjects = !showProjects">
                        <i class="ti ti-folder text-[15px]"></i>
                        <span>{{ t('Projects') }}</span>
                        <i class="ti ti-chevron-right ml-auto text-[14px] transition-transform" :class="{ 'rotate-90': showProjects }"></i>
                    </button>
                    <div v-if="showProjects" class="pl-3 space-y-0.5 mt-1">
                        <div v-for="proj in chat.projects.value" :key="proj.id"
                            class="group flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm cursor-pointer transition-colors"
                            :class="renamingProjectId === proj.id ? '' : 'text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5'"
                            @click="renamingProjectId !== proj.id ? onSelectProject(proj) : undefined">
                            <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: proj.color_hex || '#6b7280' }"></span>
                            <template v-if="renamingProjectId === proj.id">
                                <input
                                    v-model="renameValue"
                                    class="flex-1 min-w-0 px-1.5 py-0.5 rounded border border-black/10 dark:border-white/20 bg-white dark:bg-white/5 text-sm outline-none"
                                    @keyup.enter="doRename"
                                    @keyup.escape="renamingProjectId = null"
                                    @blur="doRename"
                                    @click.stop
                                />
                            </template>
                            <template v-else>
                                <span class="flex-1 truncate">{{ proj.name }}</span>
                            </template>
                            <span class="text-[11px] opacity-50">{{ proj.conversations_count }}</span>
                            <button v-if="renamingProjectId !== proj.id" class="opacity-0 group-hover:opacity-50 hover:!opacity-100 shrink-0 text-[#b0aca8] dark:text-white/30 leading-none text-[11px] ml-1" @click.stop="startRename(proj)" title="Rename">
                                <i class="ti ti-pencil text-[11px]"></i>
                            </button>
                            <button v-if="renamingProjectId !== proj.id" class="opacity-0 group-hover:opacity-50 hover:!opacity-100 shrink-0 text-[#b0aca8] dark:text-white/30 hover:text-red-500 leading-none text-[13px] ml-0.5" @click.stop="deletingProject = proj">&times;</button>
                        </div>
                        <div v-if="showNewProject" class="flex gap-1 px-3 py-1">
                            <input v-model="newProjectName" :placeholder="t('Project name')" class="flex-1 min-w-0 px-2 py-1 rounded-md border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-sm outline-none" @keyup.enter="doCreateProject" />
                            <input v-model="newProjectColor" type="color" class="w-7 h-7 rounded-md border-0 cursor-pointer p-0" />
                            <button class="px-2 py-1 rounded-md bg-[#d9cec7] dark:bg-white/10 text-[#1a1a1a] dark:text-white/80 text-[11px] font-medium hover:bg-[#cfc3bb] dark:hover:bg-white/15 transition-colors" @click="doCreateProject">{{ t('Create') }}</button>
                        </div>
                        <button v-else class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-[#b0aca8] dark:text-white/30 hover:text-black dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer transition-colors w-full" @click="showNewProject = true">
                            <i class="ti ti-plus text-[12px]"></i>
                            <span>{{ t('New Project') }}</span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Products section -->
            <div v-if="hasActiveChat && !isCompactSidebar" ref="productRef" class="relative mb-1">
                <div class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm font-medium text-[#6e6a65] dark:text-white/40 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click="showProducts = !showProducts">
                    <i class="ti ti-layout-grid text-[15px]"></i>
                    {{ t('Products') }}
                    <i class="ti ti-chevron-right ml-auto text-[14px] transition-transform" :class="{ 'rotate-90': showProducts }"></i>
                </div>
                <div v-if="showProducts" class="px-1 space-y-0.5">
                    <button
                        v-for="p in chat.products.value"
                        :key="p.slug"
                        class="flex items-center gap-2.5 w-full px-3 py-1.5 rounded-lg text-sm transition-colors text-left"
                        :class="chat.selectedProduct.value?.slug === p.slug
                            ? 'bg-black/5 dark:bg-white/10 font-medium text-[#1a1a1a] dark:text-white/90'
                            : 'hover:bg-black/5 dark:hover:bg-white/5'"
                        @click="onSelectProduct(p)"
                    >
                        <i :class="[getProductMenuIcon(p), 'text-[14px]', 'w-4', 'text-center', 'text-[#6e6a65]', 'dark:text-white/40']"></i>
                        <span class="flex-1 truncate capitalize">{{ p.name }}</span>
                    </button>
                </div>
            </div>

            <!-- Conversations -->
            <template v-for="(convs, group) in grouped" :key="group">
                <div v-if="convs.length && !isCompactSidebar">
                    <div class="px-3 py-1.5 text-[11px] font-medium text-[#b0aca8] dark:text-white/30 tracking-wide">
                        {{ t({ today: 'Today', yesterday: 'Yesterday', last_7_days: 'Last 7 Days', older: 'Older' }[group] || group) }}
                    </div>
                    <div
                        v-for="conv in convs"
                        :key="conv.ulid"
                        class="group flex items-center gap-2 pl-3 pr-1.5 py-1.5 rounded-lg text-sm cursor-pointer transition-colors"
                        :class="renamingConversationUlid === conv.ulid
                            ? ''
                            : chat.activeConversation.value?.ulid === conv.ulid
                                ? 'bg-[#d9cec7]/50 dark:bg-white/10'
                                : 'text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5'"
                        @click="renamingConversationUlid !== conv.ulid ? onSelectConversation(conv) : undefined"
                    >
                        <template v-if="renamingConversationUlid === conv.ulid">
                            <input
                                v-model="renameValue"
                                class="flex-1 min-w-0 px-1.5 py-0.5 rounded border border-black/10 dark:border-white/20 bg-white dark:bg-white/5 text-sm outline-none"
                                @keyup.enter="doRenameConversation"
                                @keyup.escape="renamingConversationUlid = null"
                                @blur="doRenameConversation"
                                @click.stop
                            />
                        </template>
                        <template v-else>
                            <span
                                class="flex-1 truncate cursor-text"
                                :title="t('Double click to rename')"
                                @dblclick.stop="startConversationRename(conv)"
                            >
                                {{ conv.title || t('New conversation') }}
                            </span>
                            <!-- Tag badges -->
                            <div v-if="conv.tags && conv.tags.length" class="flex gap-1 shrink-0">
                                <span
                                    v-for="tag in conv.tags.slice(0, 2)"
                                    :key="tag.id"
                                    class="w-2 h-2 rounded-full"
                                    :style="{ backgroundColor: tag.color }"
                                    :title="tag.name"
                                ></span>
                                <span v-if="conv.tags.length > 2" class="text-[10px] text-[#b0aca8] dark:text-white/30">+{{ conv.tags.length - 2 }}</span>
                            </div>
                        </template>

                        <!-- Move to project / Delete actions -->
                        <div class="relative shrink-0 pt-2" :ref="el => { if (moveMenuOpen === conv.ulid) moveMenuRef = (el as HTMLElement) }">
                            <button
                                class="flex h-5 w-5 items-center justify-center rounded-full opacity-0 transition-colors"
                                style="color: #9ca3af;"
                                @click.stop="moveMenuOpen = moveMenuOpen === conv.ulid ? null : conv.ulid"
                            >
                                <svg style="width: 9px; height: 9px; color: inherit;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
                            </button>
                            <div v-if="moveMenuOpen === conv.ulid" class="absolute right-0 top-full mt-0.5 min-w-[160px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1 z-50">
                                <div class="px-3 py-1.5 text-[11px] font-medium text-[#b0aca8] dark:text-white/30">{{ t('Move to Project') }}</div>
                                <button v-for="proj in otherProjects" :key="proj.id" class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="onMoveToProject(conv.ulid, proj.id)">
                                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: proj.color_hex || '#6b7280' }"></span>
                                    <span>{{ proj.name }}</span>
                                </button>
                                <button v-if="conv.project_id" class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="onMoveToProject(conv.ulid, null)">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    <span>{{ t('Remove from project') }}</span>
                                </button>
                                <div class="border-t border-black/5 dark:border-white/10 my-0.5"></div>
                                <div class="relative group/export">
                                    <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        <span>{{ t('Export') }}</span>
                                        <svg class="ml-auto" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                    </button>
                                    <div class="absolute left-full top-0 ml-1 min-w-[120px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1 z-50 opacity-0 invisible group-hover/export:opacity-100 group-hover/export:visible transition-all">
                                        <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="chat.exportConversation(conv.ulid, 'md'); moveMenuOpen = null">
                                            <span>Markdown</span>
                                        </button>
                                        <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="chat.exportConversation(conv.ulid, 'json'); moveMenuOpen = null">
                                            <span>JSON</span>
                                        </button>
                                        <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="chat.exportConversation(conv.ulid, 'pdf'); moveMenuOpen = null">
                                            <span>PDF</span>
                                        </button>
                                    </div>
                                </div>
                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="chat.togglePin(conv.ulid); moveMenuOpen = null">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z" /></svg>
                                    <span>{{ t('Pin') }}</span>
                                </button>
                                <button 
                                    v-if="conv.share_token"
                                    class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" 
                                    @click.stop="copyShareLink(conv); moveMenuOpen = null"
                                >
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                    <span>{{ t('Copy Share Link') }}</span>
                                </button>
                                <button 
                                    v-if="conv.share_token"
                                    class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-500/5 transition-colors" 
                                    @click.stop="chat.unshareConversation(conv.ulid); moveMenuOpen = null"
                                >
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                    <span>{{ t('Unshare') }}</span>
                                </button>
                                <button 
                                    v-else
                                    class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" 
                                    @click.stop="shareConversation(conv); moveMenuOpen = null"
                                >
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                    <span>{{ t('Share') }}</span>
                                </button>
                                <div class="relative group/tags">
                                    <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                        <span>{{ t('Tags') }}</span>
                                        <svg class="ml-auto" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                    </button>
                                    <div class="absolute left-full top-0 ml-1 min-w-[160px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1 z-50 opacity-0 invisible group-hover/tags:opacity-100 group-hover/tags:visible transition-all max-h-60 overflow-y-auto">
                                        <div v-if="chat.tags.value.length === 0" class="px-3.5 py-2 text-xs text-[#b0aca8] dark:text-white/30">
                                            {{ t('No tags yet') }}
                                        </div>
                                        <button
                                            v-for="tag in chat.tags.value"
                                            :key="tag.id"
                                            class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                            @click.stop="toggleConversationTag(conv, tag); moveMenuOpen = null"
                                        >
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color }"></span>
                                            <span class="flex-1 truncate">{{ tag.name }}</span>
                                            <svg v-if="conv.tags?.some(t => t.id === tag.id)" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <div class="border-t border-black/5 dark:border-white/10 my-1"></div>
                                        <button
                                            class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-primary-500 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                            @click.stop="showTagManager = true; moveMenuOpen = null"
                                        >
                                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span>{{ t('Manage tags') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click.stop="startConversationRename(conv)">
                                    <i class="ti ti-pencil text-[12px]"></i>
                                    <span>{{ t('Rename') }}</span>
                                </button>
                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-500/5 transition-colors" @click.stop="deletingConversation = conv; moveMenuOpen = null">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    <span>{{ t('Delete') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="!isCompactSidebar && !Object.values(grouped).some(g => g.length)" class="px-3 py-8 text-center text-sm text-[#b0aca8] dark:text-white/20">
                {{ chat.selectedProject.value ? t('No conversations in this project yet') : t('No conversations yet') }}
            </div>
        </div>

        <!-- User Menu (dropup) -->
        <div ref="menuRef" class="relative border-t border-black/5 dark:border-white/5" :class="{ 'p-1': isCompactSidebar }">
            <button
                class="flex items-center gap-2.5 w-full py-2.5 text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                :class="isCompactSidebar ? 'justify-center px-0' : 'px-3'"
                @click="menuOpen = !menuOpen"
            >
                <div class="w-8 h-8 rounded-full bg-[#d9cec7] dark:bg-white/10 text-[#1a1a1a] dark:text-white/80 flex items-center justify-center text-xs font-semibold shrink-0">
                    {{ user?.name?.charAt(0) || 'U' }}
                </div>
                <span v-show="!isCompactSidebar" class="flex-1 text-sm font-medium truncate text-left">{{ user?.name || 'User' }}</span>
                <svg v-show="!isCompactSidebar" class="w-4 h-4 shrink-0 transition-transform" :class="{ 'rotate-180': menuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
            </button>

            <div v-if="menuOpen" class="absolute bottom-full left-3 right-3 mb-1 bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1.5 z-50">
                <button class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click="openSettings">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>{{ t('Settings') }}</span>
                </button>

                <Link v-if="!isPro" :href="route('pricing')" class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                    <span>{{ t('Upgrade to Pro') }}</span>
                </Link>

                <Link v-if="isPro" :href="route('user.dashboard')" class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                    <span>{{ t('Billing') }}</span>
                </Link>

                <button class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click="router.visit('/contact'); menuOpen = false">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ t('Get Help') }}</span>
                </button>

                <button
                    class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                    @click="toggleDark(); menuOpen = false"
                >
                    <svg v-if="isDark" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.485-8.485h1M3.515 12.515h1M18.364 5.636l.707-.707M5.636 18.364l-.707.707M18.364 18.364l.707.707M5.636 5.636l-.707-.707M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg v-else width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0 1 12.002 21C6.477 21 2 16.523 2 10.998A9.718 9.718 0 0 1 8.998 2.248a.75.75 0 0 1 .836.919A7.5 7.5 0 0 0 18.833 13.17a.75.75 0 0 1 .919.836Z" />
                    </svg>
                    <span>{{ isDark ? t('Light mode') : t('Dark mode') }}</span>
                </button>

                <div ref="learnMoreRef" class="relative learn-more-wrapper">
                    <button
                        class="flex items-center justify-between w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                        @click="learnMoreOpen = !learnMoreOpen"
                    >
                        <span class="flex items-center gap-2.5">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ t('Learn More') }}
                        </span>
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="transition-transform" :class="{ 'rotate-180': learnMoreOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div v-if="learnMoreOpen" class="learn-more-flyout min-w-[180px] bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1.5">
                        <Link href="/privacy-policy" class="flex items-center gap-2 px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">{{ t('Privacy Policy') }}</Link>
                        <Link href="/terms" class="flex items-center gap-2 px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">{{ t('Terms of Use') }}</Link>
                        <Link href="/refund-policy" class="flex items-center gap-2 px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">{{ t('Usage Policy') }}</Link>
                        <Link href="/ai-tools" class="flex items-center gap-2 px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">{{ t('AI Tools') }}</Link>
                        <div class="px-3.5 py-1.5 text-[11px] text-[#b0aca8] dark:text-white/20">
                            <div class="font-medium text-[#6e6a65] dark:text-white/40 mb-1">{{ t('Keyboard Shortcuts') }}</div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] text-[#252525] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Ctrl+Shift+O</kbd><span>{{ t('New Chat') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] text-[#252525] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Ctrl+K</kbd><span>{{ t('Command palette') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] text-[#252525] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Ctrl+B</kbd><span>{{ t('Toggle sidebar') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] text-[#252525] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Esc</kbd><span>{{ t('Close menus') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] text-[#252525] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Enter</kbd><span>{{ t('Send') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] text-[#252525] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Shift+Enter</kbd><span>{{ t('Newline') }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-black/5 dark:border-white/10 my-1"></div>

                <form v-if="user" @submit.prevent="signOut">
                    <button type="submit" class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-500/5 transition-colors">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                        <span>{{ t('Sign Out') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Delete Confirmation Modal -->
    <ActionConfirmModal
        :open="Boolean(deletingConversation) || Boolean(deletingProject)"
        :title="deletingConversation ? t('Delete conversation?') : t('Delete project?')"
        :message="deletingConversation
            ? (t('This will permanently delete this conversation and its messages.'))
            : (t('Deleting this project will unlink all conversations. Conversations themselves will not be deleted.'))"
        :confirm-label="deletingConversation ? t('Delete conversation') : t('Delete project')"
        processing-label="Deleting..."
        :processing="deleteProcessing"
        variant="danger"
        @cancel="deletingConversation = null; deletingProject = null"
        @confirm="confirmDelete"
    />

    <!-- Tag Manager Modal -->
    <TagManager v-if="showTagManager" @close="showTagManager = false" />

    <!-- Settings Modal -->
    <Teleport v-if="showSettings" to="body">
        <div ref="settingsRef" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showSettings = false"></div>
            <div class="relative w-full max-w-md bg-white dark:bg-[#1c1c1c] rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-black/5 dark:border-white/5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-[#1a1a1a] dark:text-white/90">{{ t('Account Settings') }}</h2>
                    <button class="w-8 h-8 rounded-full hover:bg-black/5 dark:hover:bg-white/5 flex items-center justify-center text-[#b0aca8] dark:text-white/30 transition-colors" @click="showSettings = false">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="px-4 py-5 space-y-5 max-h-[70vh] overflow-y-auto sm:px-6">
                    <div class="grid gap-5 lg:grid-cols-[96px_minmax(0,1fr)]">
                        <div class="flex flex-col items-center gap-3 lg:items-start">
                            <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1">{{ t('Avatar') }}</label>
                            <div class="h-24 w-24 overflow-hidden rounded-full border border-black/5 bg-black/[0.02] dark:border-white/10 dark:bg-white/5">
                                <img v-if="avatarPreview" :src="avatarPreview" :alt="user?.name || t('Avatar')" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full w-full items-center justify-center text-xl font-semibold text-[#6e6a65] dark:text-white/40">
                                    {{ user?.name?.charAt(0) || 'U' }}
                                </div>
                            </div>
                            <label class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-black/5 px-3 py-2 text-xs font-medium text-[#1a1a1a] transition-colors hover:bg-black/5 dark:border-white/10 dark:text-white/80 dark:hover:bg-white/5">
                                {{ t('Change') }}
                                <input type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                            </label>
                            <button
                                v-if="avatarForm.avatar"
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-[#d9cec7] px-3 py-2 text-xs font-medium text-[#1a1a1a] transition-colors hover:bg-[#cfc3bb] dark:bg-white/10 dark:text-white/80 dark:hover:bg-white/15"
                                :disabled="avatarForm.processing"
                                @click="saveAvatar"
                            >
                                {{ avatarForm.processing ? t('Uploading...') : t('Save avatar') }}
                            </button>
                            <p v-if="avatarForm.errors.avatar" class="text-xs text-red-500">{{ avatarForm.errors.avatar }}</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Full Name') }}</label>
                                <input v-model="profileForm.name" type="text" class="w-full rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#1a1a1a] dark:text-white/80 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10" />
                                <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">{{ profileForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Email') }}</label>
                                <input v-model="profileForm.email" type="email" class="w-full rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#1a1a1a] dark:text-white/80 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10" />
                                <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">{{ profileForm.errors.email }}</p>
                            </div>
                            <button type="button" class="inline-flex w-full items-center justify-center rounded-lg bg-primary-100 px-4 py-2 text-sm font-medium text-primary-500 transition-colors hover:bg-primary-200 dark:bg-white/10 dark:text-white/80 dark:hover:bg-white/15" :disabled="profileForm.processing" @click="saveProfile">
                                {{ profileForm.processing ? t('Saving...') : t('Save changes') }}
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-black/5 dark:border-white/5 pt-5">
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Custom Instructions') }}</label>
                        <p class="text-xs text-[#b0aca8] dark:text-white/30 mb-2">{{ t('Tell the AI how you want it to respond. These instructions apply to all your conversations.') }}</p>
                        <textarea
                            v-model="customInstructions"
                            rows="4"
                            maxlength="2000"
                            class="w-full rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#1a1a1a] dark:text-white/80 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10 resize-none"
                            :placeholder="t('e.g., Always respond in a professional tone. Use bullet points for lists.')"
                        ></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-[#b0aca8] dark:text-white/30">{{ customInstructions.length }}/2000</span>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-primary-100 px-4 py-2 text-sm font-medium text-primary-500 transition-colors hover:bg-primary-200 dark:bg-white/10 dark:text-white/80 dark:hover:bg-white/15 disabled:opacity-50"
                                :disabled="savingCustomInstructions"
                                @click="saveCustomInstructions"
                            >
                                {{ savingCustomInstructions ? t('Saving...') : t('Save Instructions') }}
                            </button>
                        </div>
                        <p v-if="customInstructionsSaved" class="mt-2 text-xs text-success-500">{{ t('Saved successfully!') }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Password') }}</label>
                        <Link :href="route('password.request')" class="inline-flex w-full items-center justify-center gap-2 px-4 py-2 rounded-lg border border-black/5 dark:border-white/10 text-sm font-medium text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline sm:w-auto">
                            {{ t('Change Password') }}
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        </Link>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Two-Factor Authentication') }}</label>
                        <Link :href="route('user.dashboard.security')" class="inline-flex w-full items-center justify-center gap-2 px-4 py-2 rounded-lg border border-black/5 dark:border-white/10 text-sm font-medium text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline sm:w-auto">
                            {{ t('Manage 2FA') }}
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                        </Link>
                    </div>
                     <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Dashboard') }}</label>
                        <Link :href="userDashboardHref" class="inline-flex w-full items-center justify-center gap-2 px-4 py-2 rounded-lg border border-black/5 dark:border-white/10 text-sm font-medium text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline sm:w-auto">
                            <i class="ti ti-layout-dashboard text-[14px]"></i>
                            {{ t('Open dashboard') }}
                        </Link>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Referral Link') }}</label>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input readonly :value="user?.referral_link || siteUrl + '/register?ref=' + (user?.ulid || user?.id || '')" class="flex-1 rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#1a1a1a] dark:text-white/80 font-mono select-all" @focus="selectReferralLink" />
                            <button
                                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors sm:self-auto"
                                :class="referralCopied ? 'bg-success-500 text-white hover:bg-success-600' : 'bg-[#d9cec7] text-[#1a1a1a] hover:bg-[#cfc3bb] dark:bg-white/10 dark:text-white/80 dark:hover:bg-white/15'"
                                @click="copyReferralLink"
                            >
                                <i v-if="referralCopied" class="ti ti-check text-[14px]"></i>
                                <span>{{ referralCopied ? t('Copied') : t('Copy') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.sidebar {
    width: 260px;
    min-width: 260px;
    block-size: 100vh;
    display: flex;
    flex-direction: column;
    background-color: #ffffff;
    background-image: none;
    overflow: hidden;
    transition: width 0.2s, min-width 0.2s;
    position: relative;
}

:global(.dark) .sidebar {
    background-color: var(--color-surface-900);
}

.sidebar.collapsed {
    width: 56px;
    min-width: 56px;
}

@media (max-width: 1023px) {
    .sidebar {
        width: min(320px, 100vw);
        min-width: min(320px, 100vw);
        block-size: 100vh;
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        z-index: 50;
        transform: translateX(0);
        background-color: #ffffff;
        background-image: none;
        border-inline-end: 1px solid var(--border-color);
        box-shadow: 18px 0 40px rgb(0 0 0 / 0.18);
    }

    :global(.dark) .sidebar {
        background-color: var(--color-surface-900);
    }

    .sidebar.mobileClosed {
        transform: translateX(-100%);
    }

    .sidebar.mobileOpen {
        transform: translateX(0);
    }

    .sidebar.collapsed {
        width: min(320px, 100vw);
        min-width: min(320px, 100vw);
        block-size: 100vh;
    }
}

.sidebar::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 1px;
    height: 100%;
    background: rgb(17 24 39 / 0.10);
    pointer-events: none;
}

.chat-sidebar-search:focus,
.chat-sidebar-search:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border-color: transparent !important;
}

:global(.dark) .sidebar::after {
    background: linear-gradient(
        to bottom,
        transparent,
        rgb(255 255 255 / 0.08),
        transparent
    );
}

.learn-more-wrapper {
    position: relative;
}

.learn-more-flyout {
    position: absolute;
    bottom: 100%;
    left: 0;
    margin-bottom: 4px;
    z-index: 9999;
}
</style>