<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

defineOptions({ layout: AdminLayout })

interface CommentReportItem {
    id: number
    comment_id: number
    user_id: number | null
    reason: string | null
    created_at: string
    user: { id: number; name: string } | null
}

interface CommentItem {
    id: number
    content: string
    status: 'pending' | 'approved' | 'spam'
    guest_name: string | null
    guest_email: string | null
    likes_count: number
    reports_count: number
    created_at: string
    user: { name: string; email: string } | null
    commentable: { title?: string; name?: string; slug?: string } | null
    reports?: CommentReportItem[]
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

type CommentStatus = 'all' | 'pending' | 'approved' | 'spam'
type BulkAction = '' | 'approve' | 'spam'

const props = defineProps<{
    comments: {
        data: CommentItem[]
        links: PaginationLink[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    filters: { status?: string; search?: string }
    pendingCount: number
    settings: any
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()

const searchInput = ref<HTMLInputElement | null>(null)
const deleteCommentId = ref<number | null>(null)
const spamCommentId = ref<number | null>(null)
const bulkConfirmOpen = ref(false)
const searchQuery = ref(props.filters.search || '')
const selectedIds = ref<number[]>([])
const bulkAction = ref<BulkAction>('')
const processing = ref<Record<number, boolean>>({})

// Details Modal State
const detailsModalOpen = ref(false)
const commentDetails = ref<CommentItem | null>(null)
const openDetailsModal = (comment: CommentItem) => {
    commentDetails.value = comment
    detailsModalOpen.value = true
}

const normalizedStatus = computed<CommentStatus>(() => {
    const value = props.filters.status

    return value === 'pending' || value === 'approved' || value === 'spam' ? value : 'all'
})

const selectedStatus = ref<CommentStatus>(normalizedStatus.value)

const statusOptions = computed(() => [
    { value: 'all', label: t('All Status') },
    { value: 'pending', label: t('Pending') },
    { value: 'approved', label: t('Approved') },
    { value: 'spam', label: t('Spam') },
])

const bulkActionOptions = computed(() => [
    { value: 'approve', label: t('Approve Selected'), icon: 'ti ti-circle-check', tone: 'success' as const },
    { value: 'spam', label: t('Mark Selected as Spam'), icon: 'ti ti-alert-triangle', tone: 'warning' as const },
])

const authorName = (comment: CommentItem) => comment.user?.name || comment.guest_name || t('Guest')
const contentTitle = (comment: CommentItem) => comment.commentable?.title || comment.commentable?.name || t('Unknown content')

const statusLabel = (status: CommentItem['status']) => {
    if (status === 'approved') return t('Approved')
    if (status === 'spam') return t('Spam')
    return t('Pending')
}

const statusBadgeClass = (status: CommentItem['status']) => {
    if (status === 'approved') {
        return 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300'
    }

    if (status === 'spam') {
        return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
    }

    return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
}

const applyFilters = () => {
    const params: Record<string, string> = {}
    if (selectedStatus.value && selectedStatus.value !== 'all') {
        params.status = selectedStatus.value
    }
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }

    router.get(route('admin.comments.index'), params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

let searchTimeout: any = null
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
})


const visibleIds = computed(() => props.comments.data.map((comment) => comment.id))
const hasSelection = computed(() => selectedIds.value.length > 0)
const isAllVisibleSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)))

const toggleSelectAll = () => {
    if (isAllVisibleSelected.value) {
        const visibleSet = new Set(visibleIds.value)
        selectedIds.value = selectedIds.value.filter((id) => !visibleSet.has(id))
        return
    }

    selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds.value]))
}

const clearSelection = () => {
    selectedIds.value = []
    bulkAction.value = ''
}


const approve = (comment: CommentItem) => {
    processing.value[comment.id] = true

    router.post(route('admin.comments.approve', comment.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value[comment.id] = false
        },
    })
}

const requestMarkSpam = (comment: CommentItem) => {
    spamCommentId.value = comment.id
}

const confirmMarkSpam = () => {
    if (spamCommentId.value === null) {
        return
    }

    const commentId = spamCommentId.value
    processing.value[commentId] = true

    router.post(route('admin.comments.spam', commentId), {}, {
        preserveScroll: true,
        onFinish: () => {
            spamCommentId.value = null
            processing.value[commentId] = false
        },
    })
}

const requestDelete = (comment: CommentItem) => {
    deleteCommentId.value = comment.id
}

const confirmDelete = () => {
    if (deleteCommentId.value === null) {
        return
    }

    router.delete(route('admin.comments.delete', deleteCommentId.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteCommentId.value = null
        },
    })
}

const clearLocalFilters = () => {
    searchQuery.value = ''
}

const requestBulkApply = () => {
    if (!hasSelection.value || !bulkAction.value) {
        return
    }

    bulkConfirmOpen.value = true
}

const confirmBulkApply = () => {
    if (!hasSelection.value || !bulkAction.value) {
        bulkConfirmOpen.value = false
        return
    }

    router.post(route('admin.comments.bulk'), {
        ids: selectedIds.value,
        action: bulkAction.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            bulkConfirmOpen.value = false
        },
        onSuccess: () => {
            clearSelection()
        },
    })
}

const isTypingTarget = (target: EventTarget | null) => {
    if (!(target instanceof HTMLElement)) {
        return false
    }

    const tagName = target.tagName.toLowerCase()

    return tagName === 'input'
        || tagName === 'textarea'
        || tagName === 'select'
        || target.isContentEditable
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === '/') {
        if (event.metaKey || event.ctrlKey || event.altKey || isTypingTarget(event.target)) {
            return
        }

        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key !== 'Escape') {
        return
    }

    if (deleteCommentId.value !== null) {
        deleteCommentId.value = null
        return
    }

    if (spamCommentId.value !== null) {
        spamCommentId.value = null
        return
    }

    if (bulkConfirmOpen.value) {
        bulkConfirmOpen.value = false
        return
    }

    if (isTypingTarget(event.target) && event.target !== searchInput.value) {
        return
    }

    const hadSearch = searchQuery.value.length > 0
    const hadStatus = selectedStatus.value !== 'all'

    if (hadSearch) {
        clearLocalFilters()
    }

    if (hadStatus) {
        selectedStatus.value = 'all'
    }
}

watch(normalizedStatus, (value) => {
    if (selectedStatus.value !== value) {
        selectedStatus.value = value
    }
})

watch(() => props.comments.data, (comments) => {
    const visibleSet = new Set(comments.map((comment) => comment.id))
    selectedIds.value = selectedIds.value.filter((id) => visibleSet.has(id))
}, { deep: true })

watch(selectedStatus, (value, oldValue) => {
    if (value === oldValue || value === normalizedStatus.value) {
        return
    }

    applyFilters()
})

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Comments')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Comments') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t(':count comments waiting for moderation.', { count: pendingCount }) }}
                </p>
            </div>

            <Link
                :href="route('admin.blog.settings.edit')"
                class="btn-primary-admin inline-flex items-center gap-2 shrink-0"
            >
                <i class="ti ti-settings text-base"></i>
                {{ t('Settings') }}
            </Link>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                    <div class="flex-1 w-full lg:max-w-xs lg:min-w-[280px] relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                            <i class="ti ti-search text-base"></i>
                        </span>
                        <input
                            ref="searchInput"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('Search comments, author, email, or content')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span
                            v-if="!searchQuery"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                        >/</span>
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            :aria-label="t('Clear search')"
                            @click="clearLocalFilters"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto justify-start lg:justify-end shrink-0">
                        <div class="w-full sm:w-44">
                            <AppSelect
                                v-model="selectedStatus"
                                :options="statusOptions"
                                :placeholder="t('Select status')"
                            />
                        </div>

                        <div v-if="hasSelection" class="flex flex-wrap items-center gap-3 w-full sm:w-auto sm:justify-end">
                            <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0">
                                {{ t(':count selected', { count: selectedIds.length }) }}
                            </span>
                            <div class="w-full sm:w-48 sm:flex-none">
                                <AppSelect
                                    v-model="bulkAction"
                                    :options="bulkActionOptions"
                                    :placeholder="t('Bulk Actions')"
                                />
                            </div>
                            <button
                                type="button"
                                class="btn-primary-admin inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50 w-full sm:w-auto shrink-0"
                                :disabled="!bulkAction || !hasSelection"
                                @click="requestBulkApply"
                            >
                                {{ t('Apply') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="isAllVisibleSelected"
                                        :aria-label="t('Select all visible comments')"
                                        @change="toggleSelectAll"
                                    >
                                </th>
                                <th class="px-4 py-3">{{ t('Comment') }}</th>
                                <th class="px-4 py-3">{{ t('Author') }}</th>
                                <th class="px-4 py-3">{{ t('Content') }}</th>
                                <th class="px-4 py-3">{{ t('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr
                                v-for="comment in comments.data"
                                :key="comment.id"
                                class="transition-colors hover:bg-primary-50/40 dark:hover:bg-gray-900/30"
                            >
                                <td class="px-4 py-4 align-top">
                                    <input
                                        v-model="selectedIds"
                                        type="checkbox"
                                        :value="comment.id"
                                        :aria-label="t('Select comment')"
                                    >
                                </td>
                                <td class="max-w-xl px-4 py-4 align-top">
                                    <p class="line-clamp-3 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ comment.content }}</p>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t(':likes likes, :reports reports', { likes: comment.likes_count, reports: comment.reports_count }) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ authorName(comment) }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ comment.user?.email || comment.guest_email || t('No email') }}</p>
                                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ formatDateTime(comment.created_at) }}</p>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-gray-600 dark:text-gray-300">
                                    {{ contentTitle(comment) }}
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(comment.status)">
                                            {{ statusLabel(comment.status) }}
                                        </span>
                                        <span v-if="comment.reports_count > 0" class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-950/20 dark:text-red-300 border border-red-200/50 dark:border-red-900/30">
                                            <i class="ti ti-alert-triangle text-[11px]"></i>
                                            {{ t('Reported') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top text-end">
                                     <TableActionMenu>
                                         <template #default="{ close }">
                                              <button
                                                  type="button"
                                                  class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                                  @click="openDetailsModal(comment); close()"
                                              >
                                                  <i class="ti ti-eye text-base"></i>
                                                  {{ t('View Details') }}
                                              </button>
                                              <button
                                                  v-if="comment.status !== 'approved'"
                                                  type="button"
                                                  :disabled="processing[comment.id]"
                                                  class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-emerald-600 transition hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/20 disabled:opacity-50"
                                                  @click="approve(comment); close()"
                                              >
                                                  <i class="ti ti-circle-check text-base"></i>
                                                  {{ t('Approve') }}
                                              </button>
                                             <button
                                                 v-if="comment.status !== 'spam'"
                                                 type="button"
                                                 :disabled="processing[comment.id]"
                                                 class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-amber-600 transition hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/20 disabled:opacity-50"
                                                 @click="requestMarkSpam(comment); close()"
                                             >
                                                 <i class="ti ti-alert-triangle text-base"></i>
                                                 {{ t('Mark as Spam') }}
                                             </button>
                                             <hr class="border-gray-200 dark:border-surface-700">
                                             <button
                                                 type="button"
                                                 class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                 @click="requestDelete(comment); close()"
                                             >
                                                 <i class="ti ti-trash text-base"></i>
                                                 {{ t('Delete') }}
                                             </button>
                                         </template>
                                     </TableActionMenu>
                                 </td>
                            </tr>
                            <tr v-if="!comments.data.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('No comments found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="comments.links.length > 3" class="border-t border-gray-100 p-4 dark:border-surface-800">
                    <Pagination
                        :links="comments.links"
                        :from="comments.from"
                        :to="comments.to"
                        :total="comments.total"
                        :current-page="comments.current_page"
                        :last-page="comments.last_page"
                    />
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <AppModal
            :open="detailsModalOpen && !!commentDetails"
            max-width="max-w-lg"
            :title="t('Comment Details')"
            @close="detailsModalOpen = false"
        >
            <div class="space-y-6">
                <!-- Author and Date info -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">{{ authorName(commentDetails!) }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ commentDetails?.user?.email || commentDetails?.guest_email || t('No email') }}</p>
                    </div>
                    <div class="text-right">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="statusBadgeClass(commentDetails!.status)"
                        >
                            {{ statusLabel(commentDetails!.status) }}
                        </span>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1.5">{{ formatDateTime(commentDetails!.created_at) }}</p>
                    </div>
                </div>

                <!-- Post & Stats Grid -->
                <div class="grid grid-cols-2 gap-4 rounded-xl bg-gray-50 dark:bg-surface-800 p-4">
                    <div>
                        <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">{{ t('Associated Post') }}</span>
                        <a
                            v-if="commentDetails?.commentable?.slug"
                            :href="route('blog.show', commentDetails.commentable.slug)"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                        >
                            <span>{{ contentTitle(commentDetails!) }}</span>
                            <i class="ti ti-external-link text-[10px]"></i>
                        </a>
                        <span v-else class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ contentTitle(commentDetails!) }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">{{ t('Reactions') }}</span>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t(':likes Likes · :reports Reports', { likes: commentDetails?.likes_count || 0, reports: commentDetails?.reports_count || 0 }) }}
                        </p>
                    </div>
                </div>

                <!-- Comment Content -->
                <div class="space-y-2">
                    <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider">{{ t('Comment Content') }}</span>
                    <div class="rounded-xl border border-gray-100 dark:border-surface-800 p-4 bg-white dark:bg-surface-900/50">
                        <p class="text-sm text-gray-700 dark:text-gray-300 break-words leading-relaxed whitespace-pre-wrap">
                            {{ commentDetails?.content }}
                        </p>
                    </div>
                </div>

                <!-- Reports and Reasons -->
                <div v-if="commentDetails?.reports && commentDetails.reports.length" class="space-y-2">
                    <span class="block text-[11px] font-semibold text-red-500 dark:text-red-400 uppercase tracking-wider">{{ t('Report Reasons') }}</span>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <div v-for="report in commentDetails.reports" :key="report.id" class="rounded-lg border border-red-100 dark:border-red-950/30 p-3 bg-red-50/30 dark:bg-red-950/10">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ report.user?.name || t('Guest User') }}</span>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ formatDateTime(report.created_at) }}</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 italic break-words">
                                {{ report.reason || t('No reason provided.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Actions Footer -->
            <template #footer>
                <div class="flex items-center justify-between w-full">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 hover:text-red-700 transition dark:text-red-400 dark:hover:text-red-300"
                        @click="detailsModalOpen = false; requestDelete(commentDetails!)"
                    >
                        <i class="ti ti-trash text-base"></i>
                        {{ t('Delete') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="commentDetails?.status !== 'spam'"
                            type="button"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-amber-600 transition hover:bg-amber-50 dark:border-surface-700 dark:bg-surface-800 dark:text-amber-400 dark:hover:bg-surface-700"
                            @click="detailsModalOpen = false; requestMarkSpam(commentDetails!)"
                        >
                            <i class="ti ti-alert-triangle text-base"></i>
                            {{ t('Mark as Spam') }}
                        </button>
                        <button
                            v-if="commentDetails?.status !== 'approved'"
                            type="button"
                            class="btn-primary-admin inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold"
                            @click="detailsModalOpen = false; approve(commentDetails!)"
                        >
                            <i class="ti ti-circle-check text-base"></i>
                            {{ t('Approve') }}
                        </button>
                    </div>
                </div>
            </template>
        </AppModal>

        <ActionConfirmModal
            :open="deleteCommentId !== null"
            :title="t('Delete comment?')"
            :message="t('This comment will be permanently removed from moderation and public views.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            @confirm="confirmDelete"
            @cancel="deleteCommentId = null"
        />

        <ActionConfirmModal
            :open="spamCommentId !== null"
            :title="t('Mark as spam?')"
            :message="t('Are you sure you want to mark this comment as spam? It will be hidden from public view.')"
            :confirm-label="t('Mark as spam')"
            :cancel-label="t('Cancel')"
            variant="danger"
            @confirm="confirmMarkSpam"
            @cancel="spamCommentId = null"
        />

        <ActionConfirmModal
            :open="bulkConfirmOpen"
            :title="bulkAction === 'approve' ? t('Approve selected comments?') : t('Mark selected comments as spam?')"
            :message="bulkAction === 'approve'
                ? t('This will approve :count selected comments.', { count: selectedIds.length })
                : t('This will mark :count selected comments as spam.', { count: selectedIds.length })"
            :confirm-label="bulkAction === 'approve' ? t('Approve Selected') : t('Mark as spam')"
            :cancel-label="t('Cancel')"
            :variant="bulkAction === 'approve' ? 'primary' : 'danger'"
            @confirm="confirmBulkApply"
            @cancel="bulkConfirmOpen = false"
        />
    </div>
</template>
