<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import RagSourceInput from '@themes/default/js/Components/Rag/RagSourceInput.vue'
import RagIngestProgress from '@themes/default/js/Components/Rag/RagIngestProgress.vue'
import RagChat from '@themes/default/js/Components/Rag/RagChat.vue'
import RagSessionList from '@themes/default/js/Components/Rag/RagSessionList.vue'
import SaveToKbModal from '@themes/default/js/Components/Rag/SaveToKbModal.vue'
import RagSharePanel from '@themes/default/js/Components/Rag/RagSharePanel.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

interface ToolData {
    name: string
    slug: string
    description: string
    icon: string
    color: string
    show_header?: boolean
    show_footer?: boolean
    fields: {
        source_type: 'file' | 'url' | 'youtube' | 'collection'
        accept?: string[]
        multi_file?: boolean
        min_files?: number
        max_files?: number
        mode?: string
    }
}

interface RagSettings {
    max_file_mb: number
    max_pages: number
    allowed_file_types: string[]
    multi_file: boolean
    min_files?: number
    max_files?: number
}

const props = defineProps<{
    tool: ToolData
    recentSessions: Array<{ id: string; title: string; status: string; source_meta: Record<string, unknown> | null; created_at: string }>
    ragSettings: RagSettings
}>()

const { t } = useTranslate()
const toastr = useToastr()

const state = ref<'input' | 'ingesting' | 'chat'>('input')
const sessionId = ref<string | null>(null)
const sessionTitle = ref<string | null>(null)
const sourceMeta = ref<Record<string, unknown> | null>(null)
const uploadProgress = ref(0)
const showSaveKbModal = ref(false)
const showSharePanel = ref(false)
const showMobilePreview = ref(false)
const showMobileSidebar = ref(false)
const isCreating = ref(false)
const sidebarOpen = ref(true)

const shareContainerRef = ref<HTMLElement | null>(null)
const mobileShareContainerRef = ref<HTMLElement | null>(null)
onClickOutside(shareContainerRef, () => {
    showSharePanel.value = false
})
onClickOutside(mobileShareContainerRef, () => {
    showSharePanel.value = false
})

const mobileActionsOpen = ref(false)
const mobileActionsContainerRef = ref<HTMLElement | null>(null)
onClickOutside(mobileActionsContainerRef, () => {
    mobileActionsOpen.value = false
})

const page = usePage()
const user = computed(() => page.props.auth?.user as any)
const profileOpen = ref(false)
const profileContainerRef = ref<HTMLElement | null>(null)
onClickOutside(profileContainerRef, () => {
    profileOpen.value = false
})
const logout = () => router.post(route('logout'))
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))

async function handleUpload(payload: FormData | Record<string, string>) {
    isCreating.value = true
    state.value = 'ingesting'
    uploadProgress.value = 0
    showMobilePreview.value = false
    showMobileSidebar.value = false

    try {
        // Use XMLHttpRequest for FormData to get real upload progress
        if (payload instanceof FormData) {
            const data = await uploadWithProgress(payload)
            sessionId.value = data.session.id
            sessionTitle.value = data.session.title || null
            sourceMeta.value = data.session.source_meta || null
        } else {
            // URL/YouTube — no file to upload, use fetch
            const res = await fetch(`/tools/rag/${props.tool.slug}/sessions`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Failed to create session' }))
                throw new Error(err.message || 'Failed to create session')
            }
            const data = await res.json()
            sessionId.value = data.session.id
            sessionTitle.value = data.session.title || null
            sourceMeta.value = data.session.source_meta || null
        }
        router.reload({ only: ['recentSessions'] })
    } catch (e: unknown) {
        state.value = 'input'
        const errMsg = e instanceof Error ? e.message : 'Failed to start processing'
        toastr.error(errMsg)
    } finally {
        isCreating.value = false
    }
}

function uploadWithProgress(formData: FormData): Promise<{ session: { id: string; title: string; source_meta: Record<string, unknown> | null } }> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest()
        xhr.open('POST', `/tools/rag/${props.tool.slug}/sessions`)
        xhr.setRequestHeader('Accept', 'application/json')
        xhr.setRequestHeader('X-XSRF-TOKEN', getCsrf())
        xhr.withCredentials = true

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100)
            }
        }

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText)
                    resolve(data)
                } catch {
                    reject(new Error('Invalid response from server'))
                }
            } else {
                try {
                    const err = JSON.parse(xhr.responseText)
                    reject(new Error(err.message || `Upload failed (HTTP ${xhr.status})`))
                } catch {
                    reject(new Error(`Upload failed (HTTP ${xhr.status})`))
                }
            }
        }

        xhr.onerror = () => reject(new Error('Network error during upload'))
        xhr.onabort = () => reject(new Error('Upload cancelled'))

        xhr.send(formData)
    })
}

function handleIngestComplete() {
    state.value = 'chat'
}

function handleReopen(session: { id: string; title: string | null; status: string; source_meta: Record<string, unknown> | null }) {
    sessionId.value = session.id
    sessionTitle.value = session.title
    sourceMeta.value = session.source_meta
    state.value = session.status === 'ingesting' ? 'ingesting' : 'chat'
    showMobilePreview.value = false
    showMobileSidebar.value = false
}

function handleNewSession() {
    state.value = 'input'
    sessionId.value = null
    sessionTitle.value = null
    sourceMeta.value = null
    showMobilePreview.value = false
    showMobileSidebar.value = false
}

function toggleSidebar() {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
        showMobileSidebar.value = !showMobileSidebar.value
    } else {
        sidebarOpen.value = !sidebarOpen.value
    }
}

function handleReopenMobile(session: any) {
    handleReopen(session)
    showMobileSidebar.value = false
}

function handleNewSessionMobile() {
    handleNewSession()
    showMobileSidebar.value = false
}

async function handleSaveToKb(name: string) {
    if (!sessionId.value) return
    const res = await fetch(`/tools/rag/sessions/${sessionId.value}/save-to-kb`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        credentials: 'same-origin',
        body: JSON.stringify({ name }),
    })
    if (res.ok) {
        toastr.success(t('Saved to Knowledge Base'))
        showSaveKbModal.value = false
    }
}

const sessionToDelete = ref<string | null>(null)
const isDeletingSession = ref(false)

function confirmDeleteSession(id: string) {
    sessionToDelete.value = id
}

async function handleExecuteDeleteSession() {
    if (!sessionToDelete.value) return
    isDeletingSession.value = true
    try {
        const res = await fetch(`/tools/rag/sessions/${sessionToDelete.value}`, {
            method: 'DELETE',
            headers: { 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
            credentials: 'same-origin',
        })
        if (res.ok) {
            if (sessionToDelete.value === sessionId.value) {
                handleNewSession()
            }
            toastr.success(t('Session deleted'))
            router.reload({ only: ['recentSessions'] })
        } else {
            toastr.error(t('Failed to delete session'))
        }
    } catch {
        toastr.error(t('Failed to delete session'))
    } finally {
        isDeletingSession.value = false
        sessionToDelete.value = null
    }
}

async function handleRename(id: string, title: string) {
    try {
        const res = await fetch(`/tools/rag/sessions/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrf(),
                Accept: 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ title }),
        })
        if (res.ok) {
            const data = await res.json()
            if (id === sessionId.value) {
                sessionTitle.value = data.session.title
            }
            toastr.success(t('Session renamed'))
            router.reload({ only: ['recentSessions'] })
        } else {
            const err = await res.json().catch(() => ({ message: 'Failed to rename session' }))
            toastr.error(err.message || t('Failed to rename session'))
        }
    } catch {
        toastr.error(t('Failed to rename session'))
    }
}


function handleExport() {
    if (!sessionId.value) return
    window.open(`/tools/rag/sessions/${sessionId.value}/export`, '_blank')
}

function getCsrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

const sourceType = computed(() => props.tool.fields.source_type)
const youtubeVideoId = computed(() => (sourceMeta.value?.video_id as string) || null)
const isPdf = computed(() => {
    const mime = sourceMeta.value?.mime_type as string || ''
    const filename = sourceMeta.value?.filename as string || ''
    return mime === 'application/pdf' || filename.toLowerCase().endsWith('.pdf')
})

const previewWidth = ref(48)
const isResizing = ref(false)
const workspaceRef = ref<HTMLElement | null>(null)

function startResizing(e: MouseEvent) {
    e.preventDefault()
    isResizing.value = true
    document.addEventListener('mousemove', handleMouseMove)
    document.addEventListener('mouseup', stopResizing)
}

function handleMouseMove(e: MouseEvent) {
    if (!isResizing.value) return
    const container = workspaceRef.value
    if (!container) return
    const containerRect = container.getBoundingClientRect()
    const containerWidth = containerRect.width
    if (containerWidth === 0) return

    const relativeX = e.clientX - containerRect.left
    let percentage = (relativeX / containerWidth) * 100

    // Constrain width percentage between 20% and 80%
    if (percentage < 20) percentage = 20
    if (percentage > 80) percentage = 80

    previewWidth.value = percentage
}

function stopResizing() {
    isResizing.value = false
    document.removeEventListener('mousemove', handleMouseMove)
    document.removeEventListener('mouseup', stopResizing)
}

watch([state, sessionId, isPdf, sourceType, youtubeVideoId], ([newState, newSessionId, newIsPdf, newSourceType, newYoutubeId]) => {
    if (newState === 'chat' && newSessionId) {
        if ((newSourceType === 'file' && newIsPdf) || (newSourceType === 'youtube' && newYoutubeId)) {
            sidebarOpen.value = false
        }
    }
}, { immediate: true })

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(1) + ' KB'
    }
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

</script>

<template>
    <Head :title="tool.name" />

    <div
        class="flex bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-100 overflow-hidden"
        :class="tool.show_header ? 'h-[calc(100vh-4rem)]' : 'h-screen'"
    >
        <!-- Sidebar -->
        <div
            class="shrink-0 border-r border-surface-200/60 dark:border-surface-800/60 bg-surface-100 dark:bg-surface-900 hidden lg:flex flex-col transition-all duration-300 overflow-hidden"
            :class="sidebarOpen ? 'w-66 opacity-100 border-r' : 'w-0 opacity-0 border-r-0 pointer-events-none'"
        >
            <div class="p-3 border-b border-surface-200/60 dark:border-surface-800/60 bg-surface-50/50 dark:bg-surface-950/20">
                <div v-if="!tool.show_header" class="flex items-center justify-between gap-3">
                    <Link href="/" class="flex items-center gap-2 group min-w-0 flex-1">
                        <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-lg flex items-center justify-center text-white shrink-0 group-hover:scale-105 transition-transform shadow-md shadow-primary-500/10">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                        </div>
                        <span class="text-sm font-bold text-gray-900 dark:text-white truncate tracking-tight">{{ $page.props.appName }}</span>
                    </Link>
                    <Tooltip :content="t('New Session')" placement="left">
                        <button
                            @click="handleNewSession"
                            class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white shadow-sm hover:shadow transition-all duration-200"
                        >
                            <i class="ti ti-plus text-base"></i>
                        </button>
                    </Tooltip>
                </div>
                <button
                    v-else
                    @click="handleNewSession"
                    class="w-full inline-flex items-center justify-center py-2 px-4 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white font-semibold rounded-xl shadow-sm hover:shadow transition-all duration-200 text-sm gap-2"
                >
                    <i class="ti ti-plus"></i>
                    {{ t('New Session') }}
                </button>
            </div>
            <RagSessionList
                :sessions="recentSessions"
                :active-session-id="sessionId"
                @reopen="handleReopen"
                @delete="confirmDeleteSession"
                @rename="handleRename"
            />
        </div>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-surface-50 dark:bg-surface-950">
            <!-- Header -->
            <div class="flex items-center gap-3 px-5 py-3 border-b border-surface-200/60 dark:border-surface-800/60 bg-white/85 dark:bg-surface-950/85 backdrop-blur-md shrink-0 z-20 shadow-sm transition-colors duration-200">
                <!-- Sidebar toggle -->
                <Tooltip :content="t('Toggle sidebar')">
                    <button
                        class="inline-flex items-center justify-center w-8 h-8 text-surface-500 dark:text-surface-400 hover:text-primary-500 dark:hover:text-primary-400 hover:bg-surface-100 dark:hover:bg-surface-900 rounded-xl transition-all duration-200"
                        @click="toggleSidebar"
                    >
                        <i :class="sidebarOpen ? 'ti ti-layout-sidebar-left-collapse' : 'ti ti-layout-sidebar-left-expand'" class="text-xl"></i>
                    </button>
                </Tooltip>

                <div class="flex items-center gap-3 min-w-0">
                    <button
                        @click="handleNewSession"
                        class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 shadow-sm border border-surface-200/30 dark:border-surface-700/30 hover:scale-95 active:scale-90 transition-all duration-200 cursor-pointer"
                        :style="{ backgroundColor: tool.color + '12', color: tool.color }"
                        :title="t('New Session')"
                    >
                        <i :class="`${tool.icon} text-base`"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="text-sm font-bold text-surface-900 dark:text-surface-50 truncate">{{ tool.name }}</h1>
                        <p v-if="sessionTitle" class="text-[11px] font-semibold text-surface-500 dark:text-surface-400 truncate flex items-center gap-1.5 mt-0.5">
                            <span class="max-w-[300px] sm:max-w-[400px] truncate" :title="sessionTitle">{{ sessionTitle }}</span>
                            <span v-if="sourceMeta?.filesize" class="text-surface-300 dark:text-surface-700">&middot;</span>
                            <span v-if="sourceMeta?.filesize" class="text-surface-400 dark:text-surface-500 font-mono">{{ formatSize(Number(sourceMeta.filesize)) }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <!-- Mobile Preview Toggle -->
                    <button
                        v-if="sessionId && state === 'chat' && ((sourceType === 'file' && isPdf) || (sourceType === 'youtube' && youtubeVideoId))"
                        class="lg:hidden inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-surface-700 dark:text-surface-200 hover:text-primary-500 dark:hover:text-primary-400 bg-surface-100/50 dark:bg-surface-900/50 hover:bg-surface-200/60 dark:hover:bg-surface-800/60 border border-surface-200/40 dark:border-surface-800/40 rounded-xl transition-all duration-200 shadow-sm"
                        @click="showMobilePreview = true"
                    >
                        <i :class="sourceType === 'youtube' ? 'ti ti-brand-youtube text-red-500' : 'ti ti-file-text'" class="text-sm"></i>
                        <span>{{ sourceType === 'youtube' ? t('Watch') : t('Preview') }}</span>
                    </button>

                    <!-- Desktop Actions -->
                    <template v-if="sessionId && state === 'chat'">
                        <div class="hidden sm:block relative" ref="shareContainerRef">
                            <button
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-surface-700 dark:text-surface-200 hover:text-primary-500 dark:hover:text-primary-400 bg-surface-100/50 dark:bg-surface-900/50 hover:bg-surface-200/60 dark:hover:bg-surface-800/60 border border-surface-200/40 dark:border-surface-800/40 rounded-xl transition-all duration-200 shadow-sm"
                                @click="showSharePanel = !showSharePanel"
                            >
                                <i class="ti ti-share text-sm"></i>
                                <span>{{ t('Share') }}</span>
                            </button>
                            <RagSharePanel
                                v-if="showSharePanel && sessionId"
                                :session-id="sessionId"
                                @close="showSharePanel = false"
                                class="absolute right-0 top-full mt-2"
                            />
                        </div>
                        <button
                            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-surface-700 dark:text-surface-200 hover:text-primary-500 dark:hover:text-primary-400 bg-surface-100/50 dark:bg-surface-900/50 hover:bg-surface-200/60 dark:hover:bg-surface-800/60 border border-surface-200/40 dark:border-surface-800/40 rounded-xl transition-all duration-200 shadow-sm"
                            @click="handleExport"
                        >
                            <i class="ti ti-download text-sm"></i>
                            <span>{{ t('Export') }}</span>
                        </button>
                        <button
                            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-surface-700 dark:text-surface-200 hover:text-primary-500 dark:hover:text-primary-400 bg-surface-100/50 dark:bg-surface-900/50 hover:bg-surface-200/60 dark:hover:bg-surface-800/60 border border-surface-200/40 dark:border-surface-800/40 rounded-xl transition-all duration-200 shadow-sm"
                            @click="showSaveKbModal = true"
                        >
                            <i class="ti ti-device-floppy text-sm"></i>
                            <span>{{ t('Save') }}</span>
                        </button>
                        <div class="hidden sm:block w-px h-6 bg-surface-200 dark:bg-surface-800 mx-1"></div>
                    </template>

                    <Tooltip v-if="sessionId" :content="t('Delete session')" class="hidden sm:inline-flex">
                        <button
                            class="hidden sm:inline-flex items-center justify-center w-8 h-8 text-red-500/80 hover:text-red-500 bg-red-500/5 hover:bg-red-500/15 border border-red-500/10 rounded-xl transition-all duration-200 shadow-sm"
                            @click="confirmDeleteSession(sessionId)"
                        >
                            <i class="ti ti-trash text-base"></i>
                        </button>
                    </Tooltip>

                    <!-- Mobile Actions Dropdown -->
                    <div v-if="sessionId && state === 'chat'" class="sm:hidden relative" ref="mobileActionsContainerRef">
                        <button
                            class="inline-flex items-center justify-center w-8 h-8 text-surface-500 dark:text-surface-400 hover:text-primary-500 dark:hover:text-primary-400 hover:bg-surface-100 dark:hover:bg-surface-900 rounded-xl transition-all duration-200"
                            @click="mobileActionsOpen = !mobileActionsOpen"
                        >
                            <i class="ti ti-dots-vertical text-xl"></i>
                        </button>
                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="mobileActionsOpen" class="absolute right-0 top-full mt-2 w-48 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-xl shadow-2xl py-1.5 z-50">
                                <button
                                    @click="mobileActionsOpen = false; showSharePanel = true"
                                    class="w-full text-left px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors flex items-center gap-2 border-none bg-transparent"
                                >
                                    <i class="ti ti-share text-base"></i>
                                    {{ t('Share') }}
                                </button>
                                <button
                                    @click="mobileActionsOpen = false; handleExport()"
                                    class="w-full text-left px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors flex items-center gap-2 border-none bg-transparent"
                                >
                                    <i class="ti ti-download text-base"></i>
                                    {{ t('Export') }}
                                </button>
                                <button
                                    @click="mobileActionsOpen = false; showSaveKbModal = true"
                                    class="w-full text-left px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors flex items-center gap-2 border-none bg-transparent"
                                >
                                    <i class="ti ti-device-floppy text-base"></i>
                                    {{ t('Save') }}
                                </button>
                                <div class="h-px bg-surface-100 dark:bg-surface-800 my-1"></div>
                                <button
                                    @click="mobileActionsOpen = false; confirmDeleteSession(sessionId)"
                                    class="w-full text-left px-4 py-2.5 text-sm text-red-500 hover:bg-red-500/5 transition-colors flex items-center gap-2 border-none bg-transparent"
                                >
                                    <i class="ti ti-trash text-base"></i>
                                    {{ t('Delete Session') }}
                                </button>
                            </div>
                        </Transition>
                        <!-- Mobile Share Panel -->
                        <div v-if="showSharePanel && sessionId" ref="mobileShareContainerRef" class="absolute right-0 top-full mt-2 z-50">
                            <RagSharePanel
                                :session-id="sessionId"
                                @close="showSharePanel = false"
                            />
                        </div>
                    </div>

                    <!-- User menu dropdown / Sign In links (only when main header is disabled) -->
                    <template v-if="!tool.show_header">
                        <div class="w-px h-6 bg-surface-200 dark:bg-surface-800 mx-1"></div>
                        <div v-if="user" class="relative flex items-center" ref="profileContainerRef">
                            <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-900 transition-colors">
                                <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-lg flex items-center justify-center text-white text-sm font-bold shrink-0">{{ user.name?.charAt(0) ?? 'U' }}</div>
                                <svg class="w-4 h-4 text-surface-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="profileOpen" class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-xl shadow-2xl py-1.5 z-50">
                                    <div class="px-4 py-2.5 border-b border-surface-100 dark:border-surface-800">
                                        <p class="text-sm font-medium text-surface-900 dark:text-white truncate">{{ user.name }}</p>
                                        <p class="text-xs text-surface-500 dark:text-surface-400 truncate">{{ user.email }}</p>
                                    </div>
                                    <Link :href="route('user.dashboard')" class="w-full text-left px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors flex items-center gap-2 no-underline">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                        {{ t('Dashboard') }}
                                    </Link>
                                    <Link v-if="affiliateEnabled" :href="route('user.dashboard.affiliate')" class="w-full text-left px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors flex items-center gap-2 no-underline">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m0-12l-3 3m3-3l-3-3m0 15l3-3m-3 3l3 3M15.75 9h3A2.25 2.25 0 0121 11.25v1.5A2.25 2.25 0 0118.75 15h-3" /></svg>
                                        {{ t('Affiliate') }}
                                    </Link>
                                    <button @click="logout" class="w-full text-left px-4 py-2.5 text-sm text-red-500 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors flex items-center gap-2 border-none bg-transparent">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                        {{ t('Sign Out') }}
                                    </button>
                                </div>
                            </Transition>
                        </div>
                        <div v-else class="flex items-center gap-2">
                            <Link href="/login" class="text-xs font-bold text-surface-500 hover:text-primary-500 dark:text-surface-400 dark:hover:text-primary-400 transition-colors px-3 py-1.5 no-underline">{{ t('Sign In') }}</Link>
                            <Link href="/register" class="btn-primary px-3.5 py-1.5 rounded-xl font-bold text-xs shadow-sm hover:shadow transition-all duration-200 no-underline">{{ t('Get Started') }}</Link>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Content area -->
            <div class="flex-1 overflow-hidden relative bg-surface-50 dark:bg-surface-950">
                <RagSourceInput
                    v-if="state === 'input'"
                    :source-type="sourceType"
                    :settings="ragSettings"
                    :loading="isCreating"
                    @submit="handleUpload"
                />

                <RagIngestProgress
                    v-else-if="state === 'ingesting'"
                    :session-id="sessionId"
                    :source-type="sourceType"
                    :upload-progress="uploadProgress"
                    @complete="handleIngestComplete"
                />

                <div v-else-if="state === 'chat' && sessionId" ref="workspaceRef" class="flex h-full w-full overflow-hidden bg-surface-50 dark:bg-surface-950">
                    <!-- PDF Preview Pane (desktop only) -->
                    <div
                        v-if="sourceType === 'file' && isPdf"
                        class="hidden lg:flex border-r border-surface-200/60 dark:border-surface-800/60 bg-surface-50 dark:bg-surface-900 flex-col h-full overflow-hidden shrink-0"
                        :style="{ width: `${previewWidth}%` }"
                    >
                        <div class="px-4 py-3 bg-surface-100/80 dark:bg-surface-900/80 border-b border-surface-200/60 dark:border-surface-800/60 flex items-center justify-between shrink-0">
                            <span class="text-xs font-bold text-surface-800 dark:text-surface-100 flex items-center gap-2">
                                <i class="ti ti-file-text text-primary-500 text-sm"></i>
                                {{ t('Document Preview') }}
                            </span>
                            <a
                                :href="`/tools/rag/sessions/${sessionId}/file`"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-gray-500 hover:text-gray-600 bg-gray-500/10 dark:bg-gray-500/20 rounded-lg transition-colors duration-200 no-underline"
                            >
                                <i class="ti ti-external-link"></i>
                                {{ t('Open in tab') }}
                            </a>
                        </div>
                        <!-- Added #navpanes=0 to collapse the PDF viewer sidebar and save screen width -->
                        <!-- pointer-events-none added during resize so events register properly -->
                        <iframe :src="`/tools/rag/sessions/${sessionId}/file#navpanes=0`" class="w-full h-full border-none bg-surface-150 dark:bg-surface-950" :class="{ 'pointer-events-none': isResizing }" />
                    </div>

                    <!-- YouTube Video Preview Pane (desktop only) -->
                    <div
                        v-if="sourceType === 'youtube' && youtubeVideoId"
                        class="hidden lg:flex border-r border-surface-200/60 dark:border-surface-800/60 bg-surface-50 dark:bg-surface-900 flex-col h-full overflow-hidden shrink-0"
                        :style="{ width: `${previewWidth}%` }"
                    >
                        <div class="px-4 py-3 bg-surface-100/80 dark:bg-surface-900/80 border-b border-surface-200/60 dark:border-surface-800/60 flex items-center justify-between shrink-0">
                            <span class="text-xs font-bold text-surface-800 dark:text-surface-100 flex items-center gap-2">
                                <i class="ti ti-brand-youtube text-red-500 text-sm"></i>
                                {{ t('Video Preview') }}
                            </span>
                            <a
                                :href="`https://www.youtube.com/watch?v=${youtubeVideoId}`"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-gray-500 hover:text-gray-600 bg-gray-500/10 dark:bg-gray-500/20 rounded-lg transition-colors duration-200 no-underline"
                            >
                                <i class="ti ti-external-link"></i>
                                {{ t('Open on YouTube') }}
                            </a>
                        </div>
                        <div class="flex-1 bg-black flex items-center justify-center p-4">
                            <div class="relative w-full aspect-video rounded-xl overflow-hidden shadow-lg border border-surface-250/10 dark:border-surface-800/20">
                                <iframe
                                    :src="`https://www.youtube.com/embed/${youtubeVideoId}`"
                                    class="w-full h-full border-none"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                    :class="{ 'pointer-events-none': isResizing }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Drag Handle / Resizer (desktop only) -->
                    <div
                        v-if="(sourceType === 'file' && isPdf) || (sourceType === 'youtube' && youtubeVideoId)"
                        class="hidden lg:flex w-1.5 hover:w-2 hover:bg-primary-500/40 active:bg-primary-500 cursor-col-resize h-full items-center justify-center transition-all shrink-0 z-30 group"
                        @mousedown="startResizing"
                    >
                        <div class="w-0.5 h-8 bg-surface-300 dark:bg-surface-700 group-hover:bg-primary-500 rounded-full transition-colors"></div>
                    </div>

                    <!-- Chat Pane -->
                    <div class="flex-1 h-full min-w-0 bg-surface-50 dark:bg-surface-950">
                        <RagChat
                            :session-id="sessionId"
                            :tool-slug="tool.slug"
                            :source-type="sourceType"
                            :mode="tool.fields.mode || 'chat'"
                        />
                    </div>
                </div>

            </div>
        </div>
    </div>

    <SaveToKbModal
        v-if="showSaveKbModal"
        @save="handleSaveToKb"
        @close="showSaveKbModal = false"
    />

    <!-- Mobile Document Preview Offcanvas -->
    <Transition name="fade">
        <div v-if="showMobilePreview" class="lg:hidden fixed inset-0 z-50 flex justify-end">
            <!-- Backdrop overlay -->
            <div
                class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity duration-300"
                @click="showMobilePreview = false"
            ></div>

            <!-- Offcanvas Panel -->
            <Transition name="slide-rtl" appear>
                <div
                    v-if="showMobilePreview"
                    class="relative w-[85vw] max-w-md h-full bg-white dark:bg-surface-900 shadow-2xl flex flex-col z-10"
                >
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-surface-200/60 dark:border-surface-800/60 flex items-center justify-between bg-surface-50 dark:bg-surface-950">
                        <span class="text-xs font-bold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i :class="sourceType === 'youtube' ? 'ti ti-brand-youtube text-red-500' : 'ti ti-file-text text-primary-500'" class="text-sm"></i>
                            {{ sourceType === 'youtube' ? t('Video Preview') : t('Document Preview') }}
                        </span>
                        <div class="flex items-center gap-2">
                            <Tooltip :content="sourceType === 'youtube' ? t('Open on YouTube') : t('Open in tab')">
                                <a
                                    :href="sourceType === 'youtube' ? `https://www.youtube.com/watch?v=${youtubeVideoId}` : `/tools/rag/sessions/${sessionId}/file`"
                                    target="_blank"
                                    class="inline-flex items-center justify-center w-7 h-7 text-surface-500 hover:text-primary-500 dark:text-surface-400 dark:hover:text-primary-400 hover:bg-surface-100 dark:hover:bg-surface-900 rounded-lg transition-all"
                                >
                                    <i class="ti ti-external-link"></i>
                                </a>
                            </Tooltip>
                            <Tooltip :content="t('Close')">
                                <button
                                    class="w-7 h-7 flex items-center justify-center text-surface-500 hover:text-surface-700 dark:text-surface-400 dark:hover:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-900 rounded-lg transition-all"
                                    @click="showMobilePreview = false"
                                >
                                    <i class="ti ti-x"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>

                    <!-- Document / Video Preview Content -->
                    <div class="flex-1 min-h-0 bg-surface-150 dark:bg-surface-950 flex flex-col justify-center">
                        <div v-if="sourceType === 'youtube' && youtubeVideoId" class="w-full aspect-video bg-black">
                            <iframe
                                :src="`https://www.youtube.com/embed/${youtubeVideoId}`"
                                class="w-full h-full border-none"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            />
                        </div>
                        <iframe
                            v-else
                            :src="`/tools/rag/sessions/${sessionId}/file#navpanes=0`"
                            class="w-full h-full border-none"
                        ></iframe>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>

    <!-- Mobile Sessions Sidebar Offcanvas -->
    <Transition name="fade">
        <div v-if="showMobileSidebar" class="lg:hidden fixed inset-0 z-50 flex">
            <!-- Backdrop overlay -->
            <div
                class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity duration-300"
                @click="showMobileSidebar = false"
            ></div>

            <!-- Offcanvas Panel -->
            <Transition name="slide-ltr" appear>
                <div
                    v-if="showMobileSidebar"
                    class="relative w-[75vw] max-w-xs h-full bg-surface-100 dark:bg-surface-900 shadow-2xl flex flex-col z-10 border-r border-surface-200/60 dark:border-surface-800/60"
                >
                    <!-- Header -->
                    <div class="p-3 border-b border-surface-200/60 dark:border-surface-800/60 flex items-center justify-between bg-surface-50 dark:bg-surface-950/40">
                        <span class="text-xs font-bold uppercase tracking-wider text-surface-400 dark:text-surface-500">{{ t('Sessions') }}</span>
                        <button
                            class="w-7 h-7 flex items-center justify-center text-surface-500 hover:text-surface-700 dark:text-surface-400 dark:hover:text-surface-200 hover:bg-surface-200/55 dark:hover:bg-surface-800 rounded-lg transition-all"
                            @click="showMobileSidebar = false"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <!-- New Session Button -->
                    <div class="p-3 border-b border-surface-200/60 dark:border-surface-800/60 bg-surface-50/50 dark:bg-surface-950/20">
                        <div v-if="!tool.show_header" class="flex items-center justify-between gap-3">
                            <Link href="/" class="flex items-center gap-2 group min-w-0 flex-1">
                                <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-lg flex items-center justify-center text-white shrink-0 group-hover:scale-105 transition-transform shadow-md shadow-primary-500/10">
                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white truncate tracking-tight">{{ $page.props.appName }}</span>
                            </Link>
                            <button
                                @click="handleNewSessionMobile"
                                class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white shadow-sm hover:shadow transition-all duration-200"
                                :title="t('New Session')"
                            >
                                <i class="ti ti-plus text-base"></i>
                            </button>
                        </div>
                        <button
                            v-else
                            @click="handleNewSessionMobile"
                            class="w-full inline-flex items-center justify-center py-2 px-4 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white font-semibold rounded-xl shadow-sm hover:shadow transition-all duration-200 text-sm gap-2"
                        >
                            <i class="ti ti-plus"></i>
                            {{ t('New Session') }}
                        </button>
                    </div>

                    <!-- Sessions List -->
                    <div class="flex-1 overflow-y-auto">
                        <RagSessionList
                            :sessions="recentSessions"
                            :active-session-id="sessionId"
                            @reopen="handleReopenMobile"
                            @delete="confirmDeleteSession"
                        />
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>

    <!-- Confirm Session Delete Modal -->
    <ActionConfirmModal
        :open="!!sessionToDelete"
        :title="t('Delete Session')"
        :message="t('Are you sure you want to delete this session? This action cannot be undone.')"
        :confirm-label="t('Delete')"
        :cancel-label="t('Cancel')"
        :processing="isDeletingSession"
        @confirm="handleExecuteDeleteSession"
        @cancel="sessionToDelete = null"
    />
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-rtl-enter-active,
.slide-rtl-leave-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-rtl-enter-from,
.slide-rtl-leave-to {
    transform: translateX(100%);
}

.slide-ltr-enter-active,
.slide-ltr-leave-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-ltr-enter-from,
.slide-ltr-leave-to {
    transform: translateX(-100%);
}
</style>
