<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import type { useChat, ChatProject, Conversation, ChatProduct } from '@/Composables/useChat'

const siteUrl = typeof window !== 'undefined' ? window.location.origin : ''

const { t } = useTranslate()
const page = usePage()
const chat = inject<ReturnType<typeof useChat>>('chat')!
const isProAvailable = inject<ReturnType<typeof computed<boolean>>>('isProAvailable', computed(() => false))
const sidebarCollapsed = inject<ReturnType<typeof ref<boolean>>>('sidebarCollapsed', ref(false))

const showProjects = ref(false)
const showNewProject = ref(false)
const newProjectName = ref('')
const newProjectColor = ref('#6b7280')
const menuOpen = ref(false)
const learnMoreOpen = ref(false)
const collapsed = sidebarCollapsed
const showSettings = ref(false)

const deletingConversation = ref<Conversation | null>(null)
const deletingProject = ref<ChatProject | null>(null)
const deleteProcessing = ref(false)
const moveMenuOpen = ref<string | null>(null)
const moveMenuRef = ref<HTMLElement | null>(null)

const showProducts = ref(false)
const productRef = ref<HTMLElement | null>(null)

const renamingProjectId = ref<number | null>(null)
const renameValue = ref('')

const user = computed(() => page.props.auth?.user)
const isPro = computed(() => user.value?.is_pro === true || user.value?.plan?.type === 'pro')
const appName = computed(() => (page.props.appName as string) || t('Application'))

const menuRef = ref<HTMLElement | null>(null)
const learnMoreRef = ref<HTMLElement | null>(null)
const settingsRef = ref<HTMLElement | null>(null)

onClickOutside(menuRef, () => { menuOpen.value = false; learnMoreOpen.value = false })
onClickOutside(learnMoreRef, () => { learnMoreOpen.value = false })
onClickOutside(settingsRef, () => { if (showSettings.value) showSettings.value = false })
onClickOutside(moveMenuRef, () => { moveMenuOpen.value = null })
onClickOutside(productRef, () => { showProducts.value = false })

const topProducts = computed(() => chat.products.value.slice(0, 2))
const moreProducts = computed(() => chat.products.value.slice(2))
const hasActiveChat = computed(() => !!chat.activeConversation.value)

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

const doRename = async () => {
    if (renamingProjectId.value === null || !renameValue.value.trim()) {
        renamingProjectId.value = null
        return
    }
    await chat.renameProject(renamingProjectId.value, renameValue.value.trim())
    renamingProjectId.value = null
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

const onSelectConversation = (conv: Conversation) => {
    chat.selectConversation(conv)
}

const onSelectProduct = (p: ChatProduct) => {
    chat.selectedProduct.value = p
    chat.selectedModel.value = p.default_model
    showProducts.value = false
}

const onMoveToProject = async (convUlid: string, projectId: number | null) => {
    await chat.moveToProject(convUlid, projectId)
    moveMenuOpen.value = null
    if (chat.activeConversation.value?.ulid === convUlid && projectId === null) {
        chat.activeConversation.value = { ...chat.activeConversation.value, project_id: null }
    }
}

const signOut = () => {
    router.post(route('logout'))
}

const grouped = computed(() => {
    const convs = chat.selectedProject.value
        ? chat.conversations.value.filter(c => c.project_id === chat.selectedProject.value!.id)
        : chat.conversations.value

    const groups: Record<string, Conversation[]> = { today: [], yesterday: [], last_7_days: [], older: [] }
    for (const c of convs) {
        const ts = new Date(c.last_message_at || '')
        const now = new Date()
        const diff = now.getTime() - ts.getTime()
        const days = Math.floor(diff / 86400000)
        if (days === 0) groups.today.push(c)
        else if (days === 1) groups.yesterday.push(c)
        else if (days < 7) groups.last_7_days.push(c)
        else groups.older.push(c)
    }
    return groups
})

const otherProjects = computed(() => {
    if (!chat.selectedProject.value) return chat.projects.value
    return chat.projects.value.filter(p => p.id !== chat.selectedProject.value!.id)
})
</script>

<template>
    <aside class="sidebar" :class="{ collapsed: collapsed }">
        <!-- Top bar: logo + collapse -->
        <div class="flex items-center justify-between p-3 border-b border-black/5 dark:border-white/5">
            <Link v-show="!collapsed" href="/" class="flex items-center gap-2.5 no-underline shrink-0">
                <div class="w-7 h-7 rounded-lg bg-[#d9cec7] dark:bg-white/10 flex items-center justify-center text-[#1a1a1a] dark:text-white/80 text-xs font-bold">
                    {{ appName.charAt(0) }}
                </div>
                <span class="text-sm font-semibold text-[#1a1a1a] dark:text-white/80">{{ appName }}</span>
            </Link>
            <button class="h-7 px-1.5 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 flex items-center justify-center text-[#b0aca8] dark:text-white/30 transition-colors shrink-0" :class="{ 'ml-auto w-full justify-end': collapsed }" @click="collapsed = !collapsed">
                <svg v-if="collapsed" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                <svg v-else width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </button>
        </div>

        <!-- Selected project header -->
        <div v-if="chat.selectedProject.value && !collapsed" class="px-3 pt-2 pb-1">
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-black/5 dark:bg-white/5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: chat.selectedProject.value.color_hex || '#6b7280' }"></span>
                <span class="flex-1 text-sm font-medium text-[#1a1a1a] dark:text-white/80 truncate">{{ chat.selectedProject.value.name }}</span>
                <button class="shrink-0 text-[#b0aca8] dark:text-white/30 hover:text-[#1a1a1a] dark:hover:text-white/70 transition-colors" title="Clear project filter" @click="onDeselectProject">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <!-- New Chat button -->
        <div :class="collapsed ? 'p-1' : 'p-3'">
            <button
                class="flex items-center justify-center gap-2 w-full py-2.5 rounded-full bg-[#d9cec7] hover:bg-[#cfc3bb] dark:bg-white/10 dark:hover:bg-white/15 text-[#1a1a1a] dark:text-white/80 text-sm font-medium transition-colors"
                @click="chat.newChat()"
            >
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span v-show="!collapsed">{{ t('New Chat') }}</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-2 pb-2 space-y-0.5" :class="{ 'px-1': collapsed }">
            <!-- Projects (only when no filter is active) -->
            <template v-if="isProAvailable && !collapsed && !chat.selectedProject.value">
                <div class="mb-2">
                    <button class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm font-medium text-[#6e6a65] dark:text-white/40 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click="showProjects = !showProjects">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                        <span>{{ t('Projects') }}</span>
                        <svg class="ml-auto transition-transform" :class="{ 'rotate-90': showProjects }" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
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
                                    autofocus
                                />
                            </template>
                            <template v-else>
                                <span class="flex-1 truncate">{{ proj.name }}</span>
                            </template>
                            <span class="text-[11px] opacity-50">{{ proj.conversations_count }}</span>
                            <button v-if="renamingProjectId !== proj.id" class="opacity-0 group-hover:opacity-50 hover:!opacity-100 shrink-0 text-[#b0aca8] dark:text-white/30 leading-none text-[11px] ml-1" @click.stop="startRename(proj)" title="Rename">
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM18 21.75H6.75" /></svg>
                            </button>
                            <button v-if="renamingProjectId !== proj.id" class="opacity-0 group-hover:opacity-50 hover:!opacity-100 shrink-0 text-[#b0aca8] dark:text-white/30 hover:text-red-500 leading-none text-[13px] ml-0.5" @click.stop="deletingProject = proj">&times;</button>
                        </div>
                        <div v-if="showNewProject" class="flex gap-1 px-3 py-1">
                            <input v-model="newProjectName" :placeholder="t('Project name')" class="flex-1 min-w-0 px-2 py-1 rounded-md border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-sm outline-none" @keyup.enter="doCreateProject" />
                            <input v-model="newProjectColor" type="color" class="w-7 h-7 rounded-md border-0 cursor-pointer p-0" />
                            <button class="px-2 py-1 rounded-md bg-[#d9cec7] dark:bg-white/10 text-[#1a1a1a] dark:text-white/80 text-[11px] font-medium hover:bg-[#cfc3bb] dark:hover:bg-white/15 transition-colors" @click="doCreateProject">{{ t('Create') }}</button>
                        </div>
                        <button v-else class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-[#b0aca8] dark:text-white/30 hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer transition-colors w-full" @click="showNewProject = true">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            <span>{{ t('New Project') }}</span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Products section -->
            <div v-if="hasActiveChat && !collapsed" ref="productRef" class="relative mb-1.5">
                <div class="px-3 py-1 cursor-pointer flex items-center gap-2 group" @click="showProducts = !showProducts">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-[#b0aca8] dark:text-white/30 group-hover:text-[#6e6a65] dark:group-hover:text-white/50 transition-colors shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#b0aca8] dark:text-white/30 group-hover:text-[#6e6a65] dark:group-hover:text-white/50 uppercase tracking-wider flex-1">{{ t('Products') }}</span>
                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="transition-transform text-[#b0aca8] dark:text-white/30 group-hover:text-[#6e6a65] dark:group-hover:text-white/50" :class="{ 'rotate-90': showProducts }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </div>
                <div v-if="showProducts" class="px-1 space-y-0.5">
                    <button
                        v-for="p in chat.products.value"
                        :key="p.slug"
                        class="flex items-center gap-2.5 w-full px-3 py-1.5 rounded-lg text-sm transition-colors text-left"
                        :class="chat.selectedProduct.value?.slug === p.slug
                            ? 'bg-black/5 dark:bg-white/10 font-medium'
                            : 'hover:bg-black/5 dark:hover:bg-white/5'"
                        :style="{ color: p.color_hex }"
                        @click="onSelectProduct(p)"
                    >
                        <i :class="['ti', p.icon]" style="font-size:14px;width:16px;text-align:center;opacity:0.8"></i>
                        <span class="flex-1 truncate capitalize">{{ p.name }}</span>
                    </button>
                </div>
            </div>

            <!-- Conversations -->
            <template v-for="(convs, group) in grouped" :key="group">
                <div v-if="convs.length && !collapsed">
                    <div class="px-3 py-1.5 text-[11px] font-medium text-[#b0aca8] dark:text-white/30 tracking-wide">
                        {{ t({ today: 'Today', yesterday: 'Yesterday', last_7_days: 'Last 7 Days', older: 'Older' }[group] || group) }}
                    </div>
                    <div
                        v-for="conv in convs"
                        :key="conv.ulid"
                        class="group flex items-center gap-2 pl-3 pr-1.5 py-1.5 rounded-lg text-sm cursor-pointer transition-colors"
                        :class="chat.activeConversation.value?.ulid === conv.ulid
                            ? 'bg-[#d9cec7]/50 dark:bg-white/10'
                            : 'text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5'"
                        @click="onSelectConversation(conv)"
                    >
                        <span class="flex-1 truncate">{{ conv.title || t('New conversation') }}</span>

                        <!-- Move to project / Delete actions -->
                        <div class="relative shrink-0" :ref="el => { if (moveMenuOpen === conv.ulid) moveMenuRef = (el as HTMLElement) }">
                            <button class="opacity-0 group-hover:opacity-50 hover:!opacity-100 text-[#b0aca8] dark:text-white/30 hover:text-[#1a1a1a] dark:hover:text-white/70 leading-none text-[13px] px-0.5" @click.stop="moveMenuOpen = moveMenuOpen === conv.ulid ? null : conv.ulid">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
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
                                <button class="flex items-center gap-2 w-full px-3.5 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-500/5 transition-colors" @click.stop="deletingConversation = conv; moveMenuOpen = null">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    <span>{{ t('Delete') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="!collapsed && !Object.values(grouped).some(g => g.length)" class="px-3 py-8 text-center text-sm text-[#b0aca8] dark:text-white/20">
                {{ chat.selectedProject.value ? t('No conversations in this project yet') : t('No conversations yet') }}
            </div>
        </div>

        <!-- User Menu (dropup) -->
        <div ref="menuRef" class="relative border-t border-black/5 dark:border-white/5" :class="{ 'p-1': collapsed }">
            <button
                class="flex items-center gap-2.5 w-full py-2.5 text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                :class="collapsed ? 'justify-center px-0' : 'px-3'"
                @click="menuOpen = !menuOpen"
            >
                <div class="w-8 h-8 rounded-full bg-[#d9cec7] dark:bg-white/10 text-[#1a1a1a] dark:text-white/80 flex items-center justify-center text-xs font-semibold shrink-0">
                    {{ user?.name?.charAt(0) || 'U' }}
                </div>
                <span v-show="!collapsed" class="flex-1 text-sm font-medium truncate text-left">{{ user?.name || 'User' }}</span>
                <svg v-show="!collapsed" class="w-4 h-4 shrink-0 transition-transform" :class="{ 'rotate-180': menuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
            </button>

            <div v-if="menuOpen" class="absolute bottom-full left-3 right-3 mb-1 bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1.5 z-50">
                <button class="flex items-center gap-2.5 w-full px-3.5 py-2 text-sm text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" @click="showSettings = true; menuOpen = false">
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
                        <Link href="/ai-tools" class="flex items-center gap-2 px-3.5 py-1.5 text-xs text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline" @click="menuOpen = false">{{ t('About Us') }}</Link>
                        <div class="px-3.5 py-1.5 text-[11px] text-[#b0aca8] dark:text-white/20">
                            <div class="font-medium text-[#6e6a65] dark:text-white/40 mb-1">{{ t('Keyboard Shortcuts') }}</div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Ctrl+Shift+O</kbd><span>{{ t('New Chat') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Ctrl+K</kbd><span>{{ t('Command palette') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Ctrl+B</kbd><span>{{ t('Toggle sidebar') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Esc</kbd><span>{{ t('Close menus') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Enter</kbd><span>{{ t('Send') }}</span></div>
                            <div class="flex items-center justify-between gap-3"><kbd class="text-[10px] bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">Shift+Enter</kbd><span>{{ t('Newline') }}</span></div>
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
                <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Display Name') }}</label>
                        <input :value="user?.name" disabled class="w-full rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#6e6a65] dark:text-white/50 cursor-not-allowed" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Email') }}</label>
                        <input :value="user?.email" disabled class="w-full rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#6e6a65] dark:text-white/50 cursor-not-allowed" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Password') }}</label>
                        <Link :href="route('password.request')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-black/5 dark:border-white/10 text-sm font-medium text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline">
                            {{ t('Change Password') }}
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        </Link>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Two-Factor Authentication') }}</label>
                        <Link :href="route('user.settings') + '#2fa'" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-black/5 dark:border-white/10 text-sm font-medium text-[#1a1a1a] dark:text-white/80 hover:bg-black/5 dark:hover:bg-white/5 transition-colors no-underline">
                            {{ t('Manage 2FA') }}
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                        </Link>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6e6a65] dark:text-white/40 mb-1.5">{{ t('Referral Link') }}</label>
                        <div class="flex items-center gap-2">
                            <input readonly :value="page.props.auth?.user?.referral_link || siteUrl + '/register?ref=' + (user?.id || '')" class="flex-1 rounded-lg border border-black/5 dark:border-white/10 bg-black/[0.02] dark:bg-white/5 px-3 py-2 text-sm text-[#1a1a1a] dark:text-white/80 font-mono select-all" @focus="$event.target.select()" />
                            <button class="shrink-0 px-3 py-2 rounded-lg bg-[#d9cec7] dark:bg-white/10 text-[#1a1a1a] dark:text-white/80 text-xs font-medium hover:bg-[#cfc3bb] dark:hover:bg-white/15 transition-colors" @click="navigator.clipboard.writeText((page.props.auth?.user?.referral_link || '') || siteUrl + '/register?ref=' + (user?.id || ''))">{{ t('Copy') }}</button>
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
    display: flex;
    flex-direction: column;
    background: #f0edeb;
    overflow: hidden;
    transition: width 0.2s, min-width 0.2s;
}

.sidebar.collapsed {
    width: 56px;
    min-width: 56px;
}

:global(.dark) .sidebar {
    background: #141414;
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
