<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useAdminCan } from '@/Composables/useAdminCan'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: string | number | Record<string, string | number | undefined>) => string

interface Category {
    id: number
    name: string
    slug?: string
}

interface Author {
    id: number
    name: string
}

interface BlogPost {
    ulid: string
    title: string
    slug: string
    status: string
    views_count: number
    published_at: string | null
    deleted_at: string | null
    updated_at: string
    author: Author | null
    categories: Category[]
}

interface Paginated<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
}

interface SelectOption {
    value: string
    label: string
}

interface ConfirmModalState {
    open: boolean
    title: string
    message: string
    confirmLabel: string
    processingLabel: string
    processing: boolean
    variant: 'primary' | 'danger'
    action: null | (() => void)
}

const props = defineProps<{
    posts: Paginated<BlogPost>
    categories: Category[]
    authors: Author[]
    filters: { search?: string; status?: string; category?: string; author?: string }
    hasTrashedPosts: boolean
    trashMode?: boolean
    stats?: {
        total: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        published: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        comments: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        views: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { isSuperAdmin } = useAdminCan()

const selected = ref<string[]>([])
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const category = ref(props.filters.category ?? '')
const author = ref(props.filters.author ?? '')
const bulkAction = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)

const confirmModal = ref<ConfirmModalState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    processingLabel: '',
    processing: false,
    variant: 'primary',
    action: null,
})

const isTrashed = computed(() => props.trashMode === true)
const hasActiveFilters = computed(() => Boolean(search.value.trim() || status.value || category.value || author.value))

const statusOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Statuses') },
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
    { value: 'scheduled', label: t('Scheduled') },
    { value: 'private', label: t('Private') },
])

const categoryOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((item) => ({
        value: String(item.id),
        label: item.name,
    })),
])

const authorOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Authors') },
    ...props.authors.map((item) => ({
        value: String(item.id),
        label: item.name,
    })),
])

const bulkActionOptions = computed<SelectOption[]>(() => {
    if (isTrashed.value) {
        return [
            { value: '', label: t('Bulk Actions') },
            { value: 'restore', label: t('Restore Selected') },
            // Permanent deletion is irreversible â€” Super Admins only.
            ...(isSuperAdmin.value ? [{ value: 'force-delete', label: t('Delete Permanently') }] : []),
        ]
    }

    return [
        { value: '', label: t('Bulk Actions') },
        { value: 'publish', label: t('Publish Selected') },
        { value: 'draft', label: t('Move to Draft') },
        { value: 'delete', label: t('Move to Trash') },
    ]
})

let searchTimeout: any = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
})

const applyFilters = () => {
    router.get(route(isTrashed.value ? 'admin.blog.posts.trash' : 'admin.blog.posts.index'), {
        search: search.value.trim() || undefined,
        status: isTrashed.value ? undefined : (status.value || undefined),
        category: category.value || undefined,
        author: author.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const clearSearch = () => {
    search.value = ''
}

const resetFilters = () => {
    search.value = ''
    status.value = ''
    category.value = ''
    author.value = ''
    selected.value = []
    bulkAction.value = ''

    router.get(route(isTrashed.value ? 'admin.blog.posts.trash' : 'admin.blog.posts.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const openConfirmModal = (config: Omit<ConfirmModalState, 'open' | 'processing'>) => {
    confirmModal.value = {
        ...config,
        open: true,
        processing: false,
    }
}

const closeConfirmModal = (force = false) => {
    if (confirmModal.value.processing && !force) {
        return
    }

    confirmModal.value = {
        open: false,
        title: '',
        message: '',
        confirmLabel: '',
        processingLabel: '',
        processing: false,
        variant: 'primary',
        action: null,
    }
}

const runConfirmedAction = () => {
    confirmModal.value.processing = true
    confirmModal.value.action?.()
}

const confirmTrashPost = (post: BlogPost) => {
    openConfirmModal({
        title: t('Move post to trash?'),
        message: t('Move post :name to trash?', { name: post.title }),
        confirmLabel: t('Move to Trash'),
        processingLabel: t('Moving...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.blog.posts.destroy', post.ulid), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const confirmForceDeletePost = (post: BlogPost) => {
    openConfirmModal({
        title: t('Delete permanently?'),
        message: t('Delete post :name permanently? This action cannot be undone.', { name: post.title }),
        confirmLabel: t('Delete Permanently'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.blog.posts.force-delete', post.ulid), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const confirmRestorePost = (post: BlogPost) => {
    openConfirmModal({
        title: t('Restore post?'),
        message: t('Restore post :name and return it to the blog posts list?', { name: post.title }),
        confirmLabel: t('Restore'),
        processingLabel: t('Restoring...'),
        variant: 'primary',
        action: () => {
            router.post(route('admin.blog.posts.restore', post.ulid), {}, {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const duplicatePost = (post: BlogPost) => {
    router.post(route('admin.blog.posts.duplicate', post.ulid), {}, { preserveScroll: true })
}

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked
    selected.value = checked ? props.posts.data.map((post: BlogPost) => post.ulid) : []
}

const runBulkAction = () => {
    if (!selected.value.length || !bulkAction.value) {
        return
    }

    const action = bulkAction.value as 'publish' | 'draft' | 'delete' | 'restore' | 'force-delete'

    if (action === 'delete') {
        openConfirmModal({
            title: t('Move selected posts to trash?'),
            message: t('Move :count selected posts to trash?', { count: selected.value.length }),
            confirmLabel: t('Move to Trash'),
            processingLabel: t('Moving...'),
            variant: 'danger',
            action: () => {
                router.post(route('admin.blog.posts.bulk'), { ids: selected.value, action }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        selected.value = []
                        bulkAction.value = ''
                    },
                    onFinish: () => closeConfirmModal(true),
                })
            },
        })
        return
    }

    if (action === 'force-delete') {
        openConfirmModal({
            title: t('Delete selected posts permanently?'),
            message: t('Delete :count selected posts permanently? This action cannot be undone.', { count: selected.value.length }),
            confirmLabel: t('Delete Permanently'),
            processingLabel: t('Deleting...'),
            variant: 'danger',
            action: () => {
                router.post(route('admin.blog.posts.bulk'), { ids: selected.value, action }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        selected.value = []
                        bulkAction.value = ''
                    },
                    onFinish: () => closeConfirmModal(true),
                })
            },
        })
        return
    }

    router.post(route('admin.blog.posts.bulk'), { ids: selected.value, action }, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = []
            bulkAction.value = ''
        },
    })
}

const badgeClass = (value: string) => {
    if (value === 'published') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
    if (value === 'scheduled') return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
    if (value === 'private') return 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'
    if (value === 'trashed') return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
}

const formatDate = (value: string | null) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
    : t('Not published')

const postViewHref = (post: BlogPost) => post.status === 'published'
    ? route('blog.show', post.slug)
    : route('admin.blog.posts.preview', post.ulid)

const postViewTooltip = (post: BlogPost) => post.status === 'published'
    ? t('View post')
    : t('Preview post')

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName?.toLowerCase()
    const isTypingTarget = tagName === 'input' || tagName === 'textarea' || target?.isContentEditable

    if (event.key === '/' && !isTypingTarget && !confirmModal.value.open) {
        event.preventDefault()
        searchInput.value?.focus()
        return
    }

    if (event.key === 'Escape' && !confirmModal.value.open && hasActiveFilters.value) {
        resetFilters()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="isTrashed ? t('Blog Trash') : t('Blog Posts')" />
    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ isTrashed ? t('Blog Trash') : t('Blog Posts') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ isTrashed ? t('Restore deleted blog posts or permanently remove them from the blog.') : t('Create, review, schedule, and publish blog content from one editorial dashboard.') }}
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <template v-if="isTrashed">
                    <Link
                        :href="route('admin.blog.posts.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back to Posts') }}
                    </Link>
                </template>
                <template v-else>
                    <Link
                        v-if="hasTrashedPosts"
                        :href="route('admin.blog.posts.trash')"
                        class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:hover:!border-red-900/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                    >
                        <i class="ti ti-trash text-base"></i>
                        {{ t('Trash') }}
                    </Link>
                    <Link
                        :href="route('admin.blog.posts.create')"
                        class="grow btn-primary-admin inline-flex items-center justify-center gap-2"
                    >
                        <i class="ti ti-plus text-base"></i>
                        {{ t('Create Post') }}
                    </Link>
                </template>
            </div>
        </div>

        <!-- Stats Grid (Hidden in Trash Mode to focus on restoration) -->
        <div v-if="stats && !isTrashed" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                :title="t('Total Posts')"
                :value="stats.total.value"
                :comparison="stats.total.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-article text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Published')"
                :value="stats.published.value"
                :comparison="stats.published.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.published.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-checkbox text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Comments')"
                :value="stats.comments.value"
                :comparison="stats.comments.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.comments.comparison.type"
                color="warning"
            >
                <template #icon>
                    <i class="ti ti-messages text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Total Views')"
                :value="stats.views.value"
                color="accent"
            >
                <template #icon>
                    <i class="ti ti-eye text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex-1 min-w-[220px] md:max-w-sm">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInput"
                                v-model="search"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('Search posts by title or slug...')"
                                @keydown.enter="applyFilters"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            />
                            <span
                                v-if="!search && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                            >
                                /
                            </span>
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
                        <div v-if="!isTrashed" class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                            <AppSelect
                                v-model="status"
                                :options="statusOptions"
                                :placeholder="t('All Statuses')"
                                @update:model-value="applyFilters"
                            />
                        </div>

                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                            <AppSelect
                                v-model="category"
                                :options="categoryOptions"
                                :placeholder="t('All Categories')"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>

                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                            <AppSelect
                                v-model="author"
                                :options="authorOptions"
                                :placeholder="t('All Authors')"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>

                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800/80 w-full sm:w-auto"
                            @click="resetFilters"
                        >
                            {{ t('Clear filters') }}
                        </button>

                        <div v-if="selected.length" class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t(':count selected', { count: selected.length }) }}
                            </span>
                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                                <AppSelect
                                    v-model="bulkAction"
                                    :options="bulkActionOptions"
                                    :placeholder="t('Bulk Actions')"
                                />
                            </div>
                            <button
                                type="button"
                                :disabled="!bulkAction || selected.length === 0"
                                class="btn-primary-admin inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50 w-full sm:w-auto"
                                @click="runBulkAction"
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
                                <th scope="col" class="w-12 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-primary-600"
                                        :checked="posts.data.length > 0 && selected.length === posts.data.length"
                                        @change="toggleSelectAll"
                                    >
                                </th>
                                <th scope="col" class="px-4 py-3">{{ t('Post') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('Status') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('Author') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('Views') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('Date') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr
                                v-for="post in posts.data"
                                :key="post.ulid"
                                class="transition-colors hover:bg-primary-50/40 dark:hover:bg-gray-900/30"
                            >
                                <td class="px-4 py-4">
                                    <input v-model="selected" :value="post.ulid" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                </td>
                                <td class="px-4 py-4">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-gray-900 dark:text-white">{{ post.title }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">/blog/{{ post.slug }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <span
                                                v-for="item in post.categories"
                                                :key="item.id"
                                                class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900/20 dark:text-primary-300"
                                            >
                                                {{ item.name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="badgeClass(isTrashed ? 'trashed' : post.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize">
                                        {{ t(isTrashed ? 'trashed' : post.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ post.author?.name ?? t('Unknown') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ new Intl.NumberFormat().format(post.views_count) }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ isTrashed ? formatDate(post.deleted_at) : formatDate(post.published_at) }}</td>
                                <td class="px-4 py-4 text-end">
                                    <TableActionMenu v-if="!isTrashed">
                                        <template #default="{ close }">
                                            <a
                                                :href="postViewHref(post)"
                                                target="_blank"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="close"
                                            >
                                                <i :class="post.status === 'published' ? 'ti ti-external-link' : 'ti ti-eye'" class="text-base"></i>
                                                {{ post.status === 'published' ? t('View Live') : t('Preview') }}
                                            </a>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="duplicatePost(post); close()"
                                            >
                                                <i class="ti ti-copy text-base"></i>
                                                {{ t('Duplicate') }}
                                            </button>
                                            <Link
                                                :href="route('admin.blog.posts.edit', post.ulid)"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="close"
                                            >
                                                <i class="ti ti-edit text-base"></i>
                                                {{ t('Edit Details') }}
                                            </Link>
                                            <hr class="border-gray-200 dark:border-surface-700">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                @click="confirmTrashPost(post); close()"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Move to Trash') }}
                                            </button>
                                        </template>
                                    </TableActionMenu>

                                    <div v-else class="flex items-center justify-end gap-2">
                                        <Tooltip :content="t('Restore')">
                                            <button
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center rounded-full text-emerald-600 transition-colors hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                                @click="confirmRestorePost(post)"
                                            >
                                                <i class="ti ti-arrow-back-up text-base"></i>
                                            </button>
                                        </Tooltip>

                                        <Tooltip v-if="isSuperAdmin" :content="t('Delete Forever')">
                                            <button
                                                type="button"
                                                class="flex h-8 w-8 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                @click="confirmForceDeletePost(post)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </Tooltip>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!posts.data.length">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <i class="ti ti-file-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                    <p class="font-medium">{{ hasActiveFilters ? t('No blog posts match your filters') : t('No blog posts found') }}</p>
                                    <button
                                        v-if="hasActiveFilters"
                                        type="button"
                                        class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                                        @click="resetFilters"
                                    >
                                        {{ t('Clear filters') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="posts.links.length > 3" class="border-t border-gray-100 p-4 dark:border-surface-800">
                    <Pagination
                        :links="posts.links"
                        :from="posts.from"
                        :to="posts.to"
                        :total="posts.total"
                        :current-page="posts.current_page"
                        :last-page="posts.last_page"
                    />
                </div>
            </div>
        </div>
    </div>

    <ActionConfirmModal
        :open="confirmModal.open"
        :title="confirmModal.title"
        :message="confirmModal.message"
        :confirm-label="confirmModal.confirmLabel"
        :processing-label="confirmModal.processingLabel"
        :processing="confirmModal.processing"
        :variant="confirmModal.variant"
        @cancel="closeConfirmModal"
        @confirm="runConfirmedAction"
    />
</template>
