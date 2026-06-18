<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

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
    filters: { status?: string }
    pendingCount: number
}>()

const { t } = useTranslate()

const searchInput = ref<HTMLInputElement | null>(null)
const openActionMenuId = ref<number | null>(null)
const deleteCommentId = ref<number | null>(null)
const spamCommentId = ref<number | null>(null)
const bulkConfirmOpen = ref(false)
const searchQuery = ref('')
const selectedIds = ref<number[]>([])
const bulkAction = ref<BulkAction>('')
const processing = ref<Record<number, boolean>>({})

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

const filteredComments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.comments.data.filter((comment) => {
        if (!query) {
            return true
        }

        const haystack = [
            comment.content,
            authorName(comment),
            comment.user?.email ?? '',
            comment.guest_email ?? '',
            contentTitle(comment),
            comment.created_at,
        ].join(' ').toLowerCase()

        return haystack.includes(query)
    })
})

const visibleIds = computed(() => filteredComments.value.map((comment) => comment.id))
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

const applyStatusFilter = (status: CommentStatus) => {
    router.get(
        route('admin.comments.index'),
        status === 'all' ? {} : { status },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

const approve = (comment: CommentItem) => {
    openActionMenuId.value = null
    processing.value[comment.id] = true

    router.post(route('admin.comments.approve', comment.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value[comment.id] = false
        },
    })
}

const requestMarkSpam = (comment: CommentItem) => {
    openActionMenuId.value = null
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
    openActionMenuId.value = null
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

const toggleActionMenu = (id: number) => {
    openActionMenuId.value = openActionMenuId.value === id ? null : id
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

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-comment-actions]')) {
        return
    }

    openActionMenuId.value = null
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

    if (openActionMenuId.value !== null) {
        openActionMenuId.value = null
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

watch(filteredComments, (comments) => {
    const visibleSet = new Set(comments.map((comment) => comment.id))
    selectedIds.value = selectedIds.value.filter((id) => visibleSet.has(id))
})

watch(selectedStatus, (value, oldValue) => {
    if (value === oldValue || value === normalizedStatus.value) {
        return
    }

    applyStatusFilter(value)
})

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Comments')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Comments') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t(':count comments waiting for moderation.', { count: pendingCount }) }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="route('admin.blog.settings.edit')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-settings text-base"></i>
                    {{ t('Blog Settings') }}
                </Link>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-6 py-4 dark:border-surface-800 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative w-full lg:max-w-md">
                    <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('Search comments, author, email, or content')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    >
                    <span
                        v-if="!searchQuery"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                    >/</span>
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        @click="clearLocalFilters"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="w-full lg:w-56">
                    <AppSelect
                        v-model="selectedStatus"
                        :options="statusOptions"
                        :placeholder="t('Select status')"
                    />
                </div>

                <div v-if="hasSelection" class="lg:ml-auto flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t(':count selected', { count: selectedIds.length }) }}
                    </span>
                    <AppSelect
                        v-model="bulkAction"
                        :options="bulkActionOptions"
                        :placeholder="t('Bulk Actions')"
                        class="w-full sm:w-56"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg btn-primary px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!bulkAction || !hasSelection"
                        @click="requestBulkApply"
                    >
                        {{ t('Apply') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-800/50 dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-6 py-3.5">
                                <input
                                    type="checkbox"
                                    :checked="isAllVisibleSelected"
                                    :aria-label="t('Select all visible comments')"
                                    @change="toggleSelectAll"
                                >
                            </th>
                            <th class="px-6 py-3.5">{{ t('Comment') }}</th>
                            <th class="px-6 py-3.5">{{ t('Author') }}</th>
                            <th class="px-6 py-3.5">{{ t('Content') }}</th>
                            <th class="px-6 py-3.5">{{ t('Status') }}</th>
                            <th class="px-6 py-3.5 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr
                            v-for="comment in filteredComments"
                            :key="comment.id"
                            class="bg-white transition-colors hover:bg-gray-50/50 dark:bg-surface-900 dark:hover:bg-surface-800/30"
                        >
                            <td class="px-6 py-4 align-top">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    :value="comment.id"
                                    :aria-label="t('Select comment')"
                                >
                            </td>
                            <td class="max-w-xl px-6 py-4 align-top">
                                <p class="line-clamp-3 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ comment.content }}</p>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t(':likes likes, :reports reports', { likes: comment.likes_count, reports: comment.reports_count }) }}
                                </p>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <p class="font-medium text-gray-900 dark:text-white">{{ authorName(comment) }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ comment.user?.email || comment.guest_email || t('No email') }}</p>
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ comment.created_at }}</p>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-600 dark:text-gray-300">
                                {{ contentTitle(comment) }}
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass(comment.status)">
                                    {{ statusLabel(comment.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right align-top">
                                <div class="inline-flex items-center justify-end">
                                    <div class="relative" data-comment-actions>
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click.stop="toggleActionMenu(comment.id)"
                                        >
                                            <i class="ti ti-dots-vertical text-base"></i>
                                        </button>

                                        <div
                                            v-if="openActionMenuId === comment.id"
                                            class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                        >
                                            <button
                                                v-if="comment.status !== 'approved'"
                                                type="button"
                                                :disabled="processing[comment.id]"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-green-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800 disabled:cursor-not-allowed disabled:opacity-60"
                                                @click="approve(comment)"
                                            >
                                                <i class="ti ti-check text-base"></i>
                                                {{ t('Approve') }}
                                            </button>

                                            <button
                                                v-if="comment.status !== 'spam'"
                                                type="button"
                                                :disabled="processing[comment.id]"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-warning-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40 disabled:cursor-not-allowed disabled:opacity-60"
                                                @click="requestMarkSpam(comment)"
                                            >
                                                <i class="ti ti-alert-triangle text-base"></i>
                                                {{ t('Mark as spam') }}
                                            </button>

                                            <hr class="border-gray-100 dark:border-surface-800">

                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40"
                                                @click="requestDelete(comment)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Delete') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredComments.length">
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ t('No comments found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="comments.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination
                    :links="comments.links"
                    :from="comments.from"
                    :to="comments.to"
                    :total="comments.total"
                    :current-page="comments.current_page"
                    :last-page="comments.last_page"
                />
            </div>
        </section>

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
