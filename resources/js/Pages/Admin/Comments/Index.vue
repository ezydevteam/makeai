<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
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

const props = defineProps<{
    comments: {
        data: CommentItem[]
        links: PaginationLink[]
    }
    filters: { status?: string }
    pendingCount: number
}>()

const { t } = useTranslate()

const openActionMenuId = ref<number | null>(null)
const deleteCommentId = ref<number | null>(null)
const searchQuery = ref('')
const selectedStatus = ref<CommentStatus>('all')

const authorName = (comment: CommentItem) => comment.user?.name || comment.guest_name || t('Guest')
const contentTitle = (comment: CommentItem) => comment.commentable?.title || comment.commentable?.name || t('Unknown content')

const totalComments = computed(() => props.comments.data.length)
const approvedCount = computed(() => props.comments.data.filter(comment => comment.status === 'approved').length)
const spamCount = computed(() => props.comments.data.filter(comment => comment.status === 'spam').length)
const guestCount = computed(() => props.comments.data.filter(comment => !comment.user).length)
const statusOptions = computed(() => [
    { value: 'all', label: t('All') },
    { value: 'pending', label: t('Pending') },
    { value: 'approved', label: t('Approved') },
    { value: 'spam', label: t('Spam') },
])

const filteredComments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.comments.data.filter((comment) => {
        const matchesStatus = selectedStatus.value === 'all' || comment.status === selectedStatus.value

        if (!matchesStatus) {
            return false
        }

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

const approve = (comment: CommentItem) => {
    openActionMenuId.value = null
    router.post(route('admin.comments.approve', comment.id), {}, { preserveScroll: true })
}

const markSpam = (comment: CommentItem) => {
    openActionMenuId.value = null
    router.post(route('admin.comments.spam', comment.id), {}, { preserveScroll: true })
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

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-comment-actions]')) {
        return
    }

    openActionMenuId.value = null
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
})
</script>

<template>
    <Head :title="t('Comments')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                    {{ t('Moderation Center') }}
                </div>
                <h1 class="mt-4 font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ t('Comments') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t(':count comments waiting for moderation.', { count: pendingCount }) }}
                </p>
            </div>

            <Link
                :href="route('admin.blog.settings.edit')"
                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
            >
                <i class="ti ti-settings text-base"></i>
                {{ t('Blog Settings') }}
            </Link>
        </div>

        <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Visible Rows') }}</div>
                <div class="mt-3 font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ totalComments }}</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Comments currently loaded in this moderation view.') }}</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Pending') }}</div>
                <div class="mt-3 font-heading text-3xl font-bold text-amber-500">{{ pendingCount }}</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Items still waiting for a moderation decision.') }}</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Approved') }}</div>
                <div class="mt-3 font-heading text-3xl font-bold text-primary-600">{{ approvedCount }}</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Approved comments visible in the current result set.') }}</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Guests / Spam') }}</div>
                <div class="mt-3 flex items-end gap-2">
                    <span class="font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ guestCount }}</span>
                    <span class="pb-1 text-sm font-semibold text-red-500">{{ t(':count spam', { count: spamCount }) }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Quick signal for anonymous traffic and spam load.') }}</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/60 md:flex-row md:items-center md:justify-between">
                <div class="w-full md:max-w-sm">
                    <div class="relative">
                        <i class="ti ti-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('Search comments, author, email, or content')"
                            class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 transition focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                        >
                    </div>
                </div>

                <div class="w-full md:w-56">
                    <AppSelect
                        v-model="selectedStatus"
                        :options="statusOptions"
                        :placeholder="t('Select status')"
                    />
                </div>
            </div>

            <div v-if="filteredComments.length === 0" class="px-6 py-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                    <i class="ti ti-message-circle text-3xl"></i>
                </div>
                <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">{{ t('No comments found') }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Try a different search or status filter.') }}</p>
            </div>

            <div v-else class="overflow-visible">
                <div class="overflow-x-auto overflow-y-visible rounded-t-2xl">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-surface-700">
                        <thead class="bg-gray-50 dark:bg-surface-800/70">
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Comment') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Author') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Content') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr
                                v-for="comment in filteredComments"
                                :key="comment.id"
                                class="transition hover:bg-primary-50/60 dark:hover:bg-primary-900/10"
                            >
                                <td class="max-w-xl px-6 py-5 align-top">
                                    <p class="line-clamp-3 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ comment.content }}</p>
                                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ t(':likes likes, :reports reports', { likes: comment.likes_count, reports: comment.reports_count }) }}</p>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ authorName(comment) }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ comment.user?.email || comment.guest_email || t('No email') }}</div>
                                    <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ comment.created_at }}</div>
                                </td>

                                <td class="px-6 py-5 align-top text-sm text-gray-600 dark:text-gray-300">{{ contentTitle(comment) }}</td>

                                <td class="px-6 py-5 align-top">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="comment.status === 'approved'
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300'
                                            : comment.status === 'spam'
                                                ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'"
                                    >
                                        {{ t(comment.status.charAt(0).toUpperCase() + comment.status.slice(1)) }}
                                    </span>
                                </td>

                                <td class="overflow-visible px-6 py-5 align-top">
                                    <div class="flex items-center justify-end">
                                        <div class="relative" data-comment-actions>
                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
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
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                                                    @click="approve(comment)"
                                                >
                                                    <i class="ti ti-check text-base"></i>
                                                    {{ t('Approve') }}
                                                </button>

                                                <button
                                                    v-if="comment.status !== 'spam'"
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-red-50 hover:text-red-700 dark:text-gray-200 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                                    @click="markSpam(comment)"
                                                >
                                                    <i class="ti ti-alert-triangle text-base"></i>
                                                    {{ t('Mark as spam') }}
                                                </button>

                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-red-50 hover:text-red-700 dark:text-gray-200 dark:hover:bg-red-900/20 dark:hover:text-red-300"
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
                        </tbody>
                    </table>
                </div>

                <div v-if="comments.links.length > 3" class="flex flex-wrap gap-2 border-t border-gray-100 p-4 dark:border-surface-800">
                    <Link
                        v-for="link in comments.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        preserve-scroll
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                        :class="[link.active ? 'btn-primary' : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300', !link.url ? 'pointer-events-none opacity-50' : '']"
                        v-html="link.label"
                    />
                </div>
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
    </div>
</template>
