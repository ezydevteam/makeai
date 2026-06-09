<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, nextTick } from 'vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

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
}>()

const { t } = useTranslate()

const selected = ref<string[]>([])
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const category = ref(props.filters.category ?? '')
const author = ref(props.filters.author ?? '')
const bulkAction = ref('')
const openActionMenuId = ref<string | null>(null)
const actionMenuPosition = ref({ top: 0, left: 0 })

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
            { value: 'force-delete', label: t('Delete Permanently') },
        ]
    }

    return [
        { value: '', label: t('Bulk Actions') },
        { value: 'publish', label: t('Publish Selected') },
        { value: 'draft', label: t('Move to Draft') },
        { value: 'delete', label: t('Move to Trash') },
    ]
})

const filteredPosts = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (!term) {
        return props.posts.data
    }

    return props.posts.data.filter((post) => {
        const title = post.title.toLowerCase()
        const slug = post.slug.toLowerCase()

        return title.includes(term) || slug.includes(term)
    })
})

const postStats = computed(() => ({
    total: filteredPosts.value.length,
    published: filteredPosts.value.filter((post) => !post.deleted_at && post.status === 'published').length,
    drafts: filteredPosts.value.filter((post) => !post.deleted_at && post.status === 'draft').length,
    scheduled: filteredPosts.value.filter((post) => !post.deleted_at && post.status === 'scheduled').length,
}))

const applyFilters = () => {
    router.get(route(isTrashed.value ? 'admin.blog.posts.trash' : 'admin.blog.posts.index'), {
        status: isTrashed.value ? undefined : (status.value || undefined),
        category: category.value || undefined,
        author: author.value || undefined,
    }, {
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

const toggleActionMenu = async (postUlid: string, event: MouseEvent) => {
    if (openActionMenuId.value === postUlid) {
        openActionMenuId.value = null
        return
    }

    const trigger = event.currentTarget

    if (!(trigger instanceof HTMLElement)) {
        return
    }

    const rect = trigger.getBoundingClientRect()

    openActionMenuId.value = postUlid
    actionMenuPosition.value = {
        top: rect.bottom + window.scrollY + 8,
        left: rect.right + window.scrollX,
    }

    await nextTick()
}

const closeActionMenu = () => {
    openActionMenuId.value = null
}

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-post-actions-menu]')) {
        return
    }

    openActionMenuId.value = null
}

const handleViewportChange = () => {
    openActionMenuId.value = null
}

const confirmTrashPost = (post: BlogPost) => {
    closeActionMenu()
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
    closeActionMenu()
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
    closeActionMenu()
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
    closeActionMenu()
    router.post(route('admin.blog.posts.duplicate', post.ulid), {}, { preserveScroll: true })
}

const toggleSelectAll = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked
    selected.value = checked ? filteredPosts.value.map((post) => post.ulid) : []
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

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    window.removeEventListener('resize', handleViewportChange)
    window.removeEventListener('scroll', handleViewportChange, true)
})
</script>

<template>
    <Head :title="isTrashed ? t('Blog Trash') : t('Blog Posts')" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ isTrashed ? t('Blog Trash') : t('Blog Posts') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ isTrashed ? t('Restore deleted blog posts or permanently remove them from the blog.') : t('Create, review, schedule, and publish blog content from one editorial dashboard.') }}</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <template v-if="isTrashed">
                        <Link :href="route('admin.blog.posts.index')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="ti ti-arrow-left text-base"></i>
                            {{ t('Back to Posts') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="hasTrashedPosts"
                            :href="route('admin.blog.posts.trash')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <i class="ti ti-trash text-base"></i>
                            {{ t('Trash') }}
                        </Link>
                        <Link :href="route('admin.blog.posts.create')" class="btn-primary">
                            <i class="ti ti-plus text-base"></i>
                            {{ t('Create Post') }}
                        </Link>
                    </template>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Visible Posts') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ postStats.total }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Published') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ postStats.published }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Drafts') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ postStats.drafts }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Scheduled') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ postStats.scheduled }}</p>
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-4">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="w-full xl:max-w-md">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                </svg>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('Search posts by title or slug...')"
                                @keyup.enter="applyFilters"
                            />
                            <button
                                v-if="search"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                @click="search = ''"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 xl:ml-auto xl:flex-row xl:items-center xl:justify-end">
                        <div v-if="!isTrashed" class="w-full md:w-52">
                            <AppSelect
                                v-model="status"
                                :options="statusOptions"
                                :placeholder="t('All Statuses')"
                                @update:model-value="applyFilters"
                            />
                        </div>
                        <div class="w-full md:w-56">
                            <AppSelect
                                v-model="category"
                                :options="categoryOptions"
                                :placeholder="t('All Categories')"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>
                        <div class="w-full md:w-56">
                            <AppSelect
                                v-model="author"
                                :options="authorOptions"
                                :placeholder="t('All Authors')"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>
                        <div v-if="selected.length" class="w-full md:w-56">
                            <AppSelect
                                v-model="bulkAction"
                                :options="bulkActionOptions"
                                :placeholder="t('Bulk Actions')"
                            />
                        </div>
                        <button
                            v-if="selected.length"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="!bulkAction"
                            @click="runBulkAction"
                        >
                            <i class="ti ti-check text-base"></i>
                            {{ t('Apply') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="w-12 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-primary-600"
                                        :checked="filteredPosts.length > 0 && selected.length === filteredPosts.length"
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
                        <tbody>
                            <tr
                                v-for="post in filteredPosts"
                                :key="post.ulid"
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-4">
                                    <input v-model="selected" :value="post.ulid" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                </td>
                                <td class="px-4 py-4">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900 dark:text-white">{{ post.title }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">/blog/{{ post.slug }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <span
                                                v-for="item in post.categories"
                                                :key="item.id"
                                                class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/20 dark:text-primary-300"
                                            >
                                                {{ item.name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="badgeClass(isTrashed ? 'trashed' : post.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                                        {{ t(isTrashed ? 'trashed' : post.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ post.author?.name ?? t('Unknown') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ new Intl.NumberFormat().format(post.views_count) }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ isTrashed ? formatDate(post.deleted_at) : formatDate(post.published_at) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <div class="relative inline-flex justify-end" data-post-actions-menu>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click.stop="toggleActionMenu(post.ulid, $event)"
                                        >
                                            <i class="ti ti-dots-vertical text-base"></i>
                                        </button>
                                    </div>
                                    <Teleport to="body">
                                        <div
                                            v-if="openActionMenuId === post.ulid"
                                            data-post-actions-menu
                                            class="fixed z-[80] w-52 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900"
                                            :style="{
                                                top: `${actionMenuPosition.top}px`,
                                                left: `${actionMenuPosition.left}px`,
                                                transform: 'translateX(-100%)',
                                            }"
                                        >
                                            <template v-if="!isTrashed">
                                                <a
                                                    :href="postViewHref(post)"
                                                    target="_blank"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800"
                                                    @click="closeActionMenu"
                                                >
                                                    <i class="ti ti-world text-base text-gray-500"></i>
                                                    <span>{{ postViewTooltip(post) }}</span>
                                                </a>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800"
                                                    @click="duplicatePost(post)"
                                                >
                                                    <i class="ti ti-copy text-base text-gray-500"></i>
                                                    <span>{{ t('Duplicate post') }}</span>
                                                </button>
                                                <Link
                                                    :href="route('admin.blog.posts.edit', post.ulid)"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800"
                                                    @click="closeActionMenu"
                                                >
                                                    <i class="ti ti-edit text-base text-gray-500"></i>
                                                    <span>{{ t('Edit post') }}</span>
                                                </Link>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40"
                                                    @click="confirmTrashPost(post)"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                    <span>{{ t('Move to trash') }}</span>
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-emerald-700 transition hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                                                    @click="confirmRestorePost(post)"
                                                >
                                                    <i class="ti ti-arrow-back-up text-base"></i>
                                                    <span>{{ t('Restore') }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40"
                                                    @click="confirmForceDeletePost(post)"
                                                >
                                                    <i class="ti ti-trash-x text-base"></i>
                                                    <span>{{ t('Delete Permanently') }}</span>
                                                </button>
                                            </template>
                                        </div>
                                    </Teleport>
                                </td>
                            </tr>

                            <tr v-if="!filteredPosts.length">
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No blog posts found.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="posts.links.length > 3" class="mt-4">
                <Pagination :links="posts.links" />
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
