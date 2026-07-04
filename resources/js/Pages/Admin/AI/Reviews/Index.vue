<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import Pagination from '@/Components/Pagination.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface User {
    id: number
    name: string
    email: string
    avatar?: string | null
}

interface Tool {
    id: number
    slug: string
    name: string
    icon?: string | null
    color?: string | null
}

interface ReviewItem {
    id: number
    tool_slug: string
    user_id: number
    rating: number
    comment?: string | null
    admin_reply?: string | null
    is_approved: boolean
    helpful_count: number
    created_at: string
    user?: User | null
    tool?: Tool | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    reviews: {
        data: ReviewItem[]
        links: PaginationLink[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    filters: { search?: string; status?: string; rating?: string }
}>()

const { t } = useTranslate()

const search = ref(props.filters.search || '')
const selectedStatus = ref(props.filters.status || '')
const selectedRating = ref(props.filters.rating || '')
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)

// Reply Modal State
const replyModalOpen = ref(false)
const activeReview = ref<ReviewItem | null>(null)
const adminReplyText = ref('')
const approveOnReply = ref(false)
const replySubmitting = ref(false)

// Delete Confirm Modal State
const deleteModalOpen = ref(false)
const reviewToDelete = ref<ReviewItem | null>(null)
const deleteProcessing = ref(false)

// Approve/Disapprove Confirm Modal State
const approveModalOpen = ref(false)
const reviewToApprove = ref<ReviewItem | null>(null)
const approveProcessing = ref(false)

const approveModalTitle = computed(() => {
    if (!reviewToApprove.value) return ''
    return reviewToApprove.value.is_approved ? t('Disapprove Review') : t('Approve Review')
})

const approveModalMessage = computed(() => {
    if (!reviewToApprove.value) return ''
    return reviewToApprove.value.is_approved
        ? t('Are you sure you want to mark this review as pending? It will be hidden from public view.')
        : t('Are you sure you want to approve this review? It will become visible on the public tool page.')
})

const approveModalConfirmLabel = computed(() => {
    if (!reviewToApprove.value) return ''
    return reviewToApprove.value.is_approved ? t('Disapprove') : t('Approve')
})

const approveModalVariant = computed(() => {
    if (!reviewToApprove.value) return 'primary'
    return reviewToApprove.value.is_approved ? 'danger' : 'primary'
})

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: 'approved', label: t('Approved') },
    { value: 'pending', label: t('Pending') },
])

const ratingOptions = computed(() => [
    { value: '', label: t('All Ratings') },
    { value: '5', label: t('5 Stars') },
    { value: '4', label: t('4 Stars') },
    { value: '3', label: t('3 Stars') },
    { value: '2', label: t('2 Stars') },
    { value: '1', label: t('1 Star') },
])

const hasActiveFilters = computed(() => Boolean(search.value || selectedStatus.value || selectedRating.value))

const applyFilters = () => {
    router.get(route('admin.ai.reviews.index'), {
        search: search.value || undefined,
        status: selectedStatus.value || undefined,
        rating: selectedRating.value || undefined,
    }, { preserveScroll: true, preserveState: true, replace: true })
}

let filterTimer: ReturnType<typeof setTimeout> | null = null

const handleSearchInput = () => {
    if (filterTimer) {
        clearTimeout(filterTimer)
    }
    filterTimer = setTimeout(applyFilters, 350)
}

const clearSearch = () => {
    if (!search.value) return
    search.value = ''
    applyFilters()
}

const resetFilters = () => {
    search.value = ''
    selectedStatus.value = ''
    selectedRating.value = ''
    applyFilters()
}

const toggleApprove = (review: ReviewItem) => {
    reviewToApprove.value = review
    approveModalOpen.value = true
}

const handleToggleApprove = () => {
    if (!reviewToApprove.value) return
    approveProcessing.value = true
    const review = reviewToApprove.value
    const routeName = review.is_approved ? 'admin.ai.reviews.disapprove' : 'admin.ai.reviews.approve'
    router.post(route(routeName, review.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            approveModalOpen.value = false
            reviewToApprove.value = null
        },
        onFinish: () => {
            approveProcessing.value = false
        }
    })
}

const openReplyModal = (review: ReviewItem) => {
    activeReview.value = review
    adminReplyText.value = review.admin_reply || ''
    approveOnReply.value = review.is_approved || true // Default to true for auto approval on reply submit
    replyModalOpen.value = true
}

const submitReply = () => {
    if (!activeReview.value) return
    replySubmitting.value = true
    router.post(route('admin.ai.reviews.reply', activeReview.value.id), {
        reply: adminReplyText.value,
        approve: approveOnReply.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            replyModalOpen.value = false
            activeReview.value = null
            adminReplyText.value = ''
        },
        onFinish: () => {
            replySubmitting.value = false
        }
    })
}

const confirmDelete = (review: ReviewItem) => {
    reviewToDelete.value = review
    deleteModalOpen.value = true
}

const handleDeleteReview = () => {
    if (!reviewToDelete.value) return
    deleteProcessing.value = true
    router.delete(route('admin.ai.reviews.destroy', reviewToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false
            reviewToDelete.value = null
        },
        onFinish: () => {
            deleteProcessing.value = false
        }
    })
}

const formatDate = (dateStr: string) => {
    try {
        return new Date(dateStr).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    } catch (e) {
        return dateStr
    }
}

const handleKeydown = (event: KeyboardEvent) => {
    if (replyModalOpen.value || deleteModalOpen.value) return

    if (event.key === '/') {
        if (event.metaKey || event.ctrlKey || event.altKey || ['input', 'textarea'].includes((event.target as HTMLElement).tagName.toLowerCase())) {
            return
        }
        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && document.activeElement === searchInput.value) {
        event.preventDefault()
        search.value = ''
        applyFilters()
        searchInput.value?.blur()
        return
    }

    if (event.key === 'Escape' && hasActiveFilters.value) {
        event.preventDefault()
        resetFilters()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
    if (filterTimer) clearTimeout(filterTimer)
})
</script>

<template>
    <Head :title="t('Tool Reviews — Admin')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-8 xl:px-10">
        <!-- Title section -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Reviews') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Moderate, approve, delete, and reply to user tool reviews.') }}</p>
            </div>
        </div>

        <!-- Main section -->
        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <!-- Filter Bar -->
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex-1 min-w-[240px]">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInput"
                                v-model="search"
                                type="text"
                                :placeholder="t('Search comments, users or tools...')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                @input="handleSearchInput"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            />
                            <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                <span v-if="!search && !searchFocused" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500">/</span>
                            </div>
                            <button
                                v-if="search"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                @click="clearSearch"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                            <AppSelect
                                v-model="selectedStatus"
                                :options="statusOptions"
                                :placeholder="t('All Status')"
                                @update:model-value="applyFilters"
                            />
                        </div>
                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                            <AppSelect
                                v-model="selectedRating"
                                :options="ratingOptions"
                                :placeholder="t('All Ratings')"
                                @update:model-value="applyFilters"
                            />
                        </div>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="resetFilters"
                        >
                            <i class="ti ti-rotate-clockwise text-base"></i>
                            {{ t('Reset') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table or Empty state -->
            <div v-if="reviews.data.length === 0" class="px-6 py-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl dark:bg-emerald-900/20">
                    <i class="ti ti-star text-2xl text-emerald-600 dark:text-emerald-300"></i>
                </div>
                <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">
                    {{ hasActiveFilters ? t('No reviews match these filters') : t('No reviews yet') }}
                </h3>
                <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                    {{ hasActiveFilters
                        ? t('Try clearing the current search or filters to see more reviews.')
                        : t('Users have not left any reviews for the AI tools yet.') }}
                </p>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="btn-primary mt-6 inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
                    @click="resetFilters"
                >
                    {{ t('Clear Filters') }}
                </button>
            </div>

            <div v-else class="overflow-hidden rounded-b-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Rating') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Comment & Reply') }}</th>
                                <th class="px-6 py-4 text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr v-for="review in reviews.data" :key="review.id" class="transition hover:bg-primary-50/60 dark:hover:bg-primary-900/10">
                                <!-- User column -->
                                <td class="px-6 py-5 align-top">
                                    <div class="flex items-start gap-3">
                                        <div v-if="review.user?.avatar" class="h-11 w-11 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800">
                                            <img :src="review.user.avatar.startsWith('http') ? review.user.avatar : '/storage/' + review.user.avatar" class="h-full w-full object-cover" />
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                        >
                                            <span>{{ review.user?.name?.charAt(0) || 'U' }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ review.user?.name || t('Anonymous') }}</div>
                                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ review.user?.email || '—' }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Tool column -->
                                <td class="px-6 py-5 align-top">
                                    <div v-if="review.tool" class="flex items-center gap-2">
                                        <div :style="{ background: review.tool.color || '#6366f1' }" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-[10px] text-white">
                                            <i v-if="review.tool.icon" :class="review.tool.icon"></i>
                                            <span v-else>{{ review.tool.name.charAt(0) }}</span>
                                        </div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ review.tool.name }}</span>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 italic">{{ review.tool_slug }}</span>
                                </td>

                                <!-- Rating column -->
                                <td class="px-6 py-5 align-top whitespace-nowrap">
                                    <div class="flex items-center gap-0.5 text-warning-400">
                                        <i v-for="star in 5" :key="star" class="ti text-sm" :class="star <= review.rating ? 'ti-star-filled' : 'ti-star text-gray-200 dark:text-surface-700'"></i>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1">{{ formatDate(review.created_at) }}</p>
                                </td>

                                <!-- Comment & Reply column -->
                                <td class="px-6 py-5 align-top max-w-sm">
                                    <p class="text-gray-600 dark:text-gray-300 break-words leading-relaxed text-sm">
                                        {{ review.comment || t('No comment left.') }}
                                    </p>
                                    <div v-if="review.admin_reply" class="mt-2 rounded-lg bg-gray-50 p-2.5 dark:bg-surface-800">
                                        <div class="text-[11px] font-bold text-primary-600 dark:text-primary-400 mb-0.5">
                                            {{ t('Replied:') }}
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 break-words">{{ review.admin_reply }}</p>
                                    </div>
                                </td>

                                <!-- Status column -->
                                <td class="px-6 py-5 align-top text-center whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                        :class="review.is_approved
                                            ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400'
                                            : 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-400'"
                                        @click="toggleApprove(review)"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="review.is_approved ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                        {{ review.is_approved ? t('Approved') : t('Pending') }}
                                    </button>
                                </td>

                                <!-- Actions column -->
                                <td class="overflow-visible px-6 py-5 align-top text-end">
                                    <TableActionMenu>
                                        <template #default="{ close }">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="toggleApprove(review); close()"
                                            >
                                                <i class="ti text-base" :class="review.is_approved ? 'ti-circle-x' : 'ti-circle-check'"></i>
                                                {{ review.is_approved ? t('Disapprove') : t('Approve') }}
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="openReplyModal(review); close()"
                                            >
                                                <i class="ti ti-arrow-back-up text-base"></i>
                                                {{ t('Reply') }}
                                            </button>
                                            <hr class="border-gray-100 dark:border-surface-700">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                @click="confirmDelete(review); close()"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Delete') }}
                                            </button>
                                        </template>
                                    </TableActionMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Pagination -->
            <div v-if="reviews.data.length && reviews.total && reviews.total > reviews.data.length" class="border-t border-gray-100 p-4 dark:border-surface-800">
                <Pagination :links="reviews.links" />
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="replyModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="!replySubmitting && (replyModalOpen = false)"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <div v-if="replyModalOpen" class="w-full max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-surface-800 dark:bg-surface-900">
                        <form @submit.prevent="submitReply">
                            <div class="p-5 space-y-4">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ activeReview?.admin_reply ? t('Edit Reply') : t('Reply to Review') }}</h3>
                                <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <p class="font-bold text-gray-800 dark:text-white mb-1">{{ activeReview?.user?.name || t('Anonymous') }}</p>
                                    <p class="italic break-words">{{ activeReview?.comment || t('No comment left.') }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ t('Reply Content') }}</label>
                                    <textarea
                                        v-model="adminReplyText"
                                        rows="4"
                                        maxlength="2000"
                                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-955 transition placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-surface-800 dark:bg-surface-800 dark:text-white"
                                        :placeholder="t('Type your reply here...')"
                                    ></textarea>
                                </div>
                                <!-- Approve Review Switch -->
                                <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-surface-800">
                                    <span class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Approve Review') }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ t('Automatically approve this review upon submitting reply.') }}</span>
                                    </span>
                                    <button
                                        type="button"
                                        :class="approveOnReply ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        @click="approveOnReply = !approveOnReply"
                                    >
                                        <span
                                            :class="approveOnReply ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                        />
                                    </button>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-955">
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 disabled:opacity-60 dark:text-gray-300 dark:hover:bg-surface-800"
                                    :disabled="replySubmitting"
                                    @click="replyModalOpen = false"
                                >
                                    {{ t('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="btn-primary rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-60 transition"
                                    :disabled="replySubmitting"
                                >
                                    {{ replySubmitting ? t('Submitting...') : t('Submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <ActionConfirmModal
        :open="deleteModalOpen"
        :title="t('Delete Review')"
        :message="t('Are you sure you want to permanently delete this review? This action cannot be undone.')"
        :confirm-label="t('Delete Review')"
        :processing="deleteProcessing"
        variant="danger"
        @cancel="deleteModalOpen = false"
        @confirm="handleDeleteReview"
    />

    <!-- Approve/Disapprove Confirmation Modal -->
    <ActionConfirmModal
        :open="approveModalOpen"
        :title="approveModalTitle"
        :message="approveModalMessage"
        :confirm-label="approveModalConfirmLabel"
        :processing="approveProcessing"
        :variant="approveModalVariant"
        @cancel="approveModalOpen = false"
        @confirm="handleToggleApprove"
    />
</template>
