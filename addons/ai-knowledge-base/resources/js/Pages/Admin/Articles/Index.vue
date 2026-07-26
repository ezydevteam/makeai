<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppFilterDropdown from '@/Components/Admin/AppFilterDropdown.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type ArticleStatus = 'draft' | 'published'
type EmbedStatus = 'pending' | 'processing' | 'done' | 'failed'

interface ArticleCategory {
    id: number
    name: string
}

interface ArticleCreator {
    name: string
}

interface Article {
    id: number
    ulid: string
    title: string
    slug: string
    status: ArticleStatus
    embed_status: EmbedStatus
    embed_error: string | null
    views: number
    helpful_count: number
    not_helpful_count: number
    helpful_percent: number | null
    created_at: string
    updated_at: string
    category?: ArticleCategory | null
    creator?: ArticleCreator | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface ArticlePagination {
    data: Article[]
    links: PaginationLink[]
    total: number
    from: number | null
    to: number | null
}

interface Filters {
    search?: string
    status?: string
    category_id?: string
    embed_status?: string
}

const props = defineProps<{
    articles: ArticlePagination
    categories: ArticleCategory[]
    filters: Filters
}>()

const search = ref(props.filters.search ?? '')
const statusFilter = ref(props.filters.status ?? '')
const categoryFilter = ref(props.filters.category_id ?? '')
const embedFilter = ref(props.filters.embed_status ?? '')

const deletingArticle = ref<Article | null>(null)
const deleting = ref(false)
const reEmbeddingId = ref<number | null>(null)

const { t } = useTranslate()

const embedStatusLabel: Record<EmbedStatus, string> = {
    pending: t('Pending'),
    processing: t('Processing'),
    done: t('Done'),
    failed: t('Failed'),
}

const categoryOptions = computed(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((category) => ({ value: String(category.id), label: category.name })),
])

const statusOptions = [
    { value: '', label: t('All Status') },
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
]

const embedOptions = [
    { value: '', label: t('All Embed States') },
    { value: 'pending', label: t('Pending') },
    { value: 'processing', label: t('Processing') },
    { value: 'done', label: t('Done') },
    { value: 'failed', label: t('Failed') },
]

// Drives the badge on the filter button. Search is excluded: it filters the loaded page
// client-side and has its own clear affordance inside the input.
const activeFiltersCount = computed(
    () => [statusFilter.value, categoryFilter.value, embedFilter.value].filter(Boolean).length,
)

// The watcher below reloads on any change, so this only has to clear the values.
function resetFilters() {
    statusFilter.value = ''
    categoryFilter.value = ''
    embedFilter.value = ''
}

watch([statusFilter, categoryFilter, embedFilter], () => {
    applyFilters()
})

function applyFilters() {
    router.get(route('addon.kb.admin.articles.index'), {
        status: statusFilter.value,
        category_id: categoryFilter.value,
        embed_status: embedFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function clearSearch() {
    search.value = ''
}

function createArticle() {
    router.visit(route('addon.kb.admin.articles.create'))
}

function editArticle(article: Article) {
    router.visit(route('addon.kb.admin.articles.edit', { article: article.ulid }))
}

function openDelete(article: Article) {
    deletingArticle.value = article
}

function closeDelete() {
    if (deleting.value) {
        return
    }

    deletingArticle.value = null
}

function destroyArticle() {
    if (!deletingArticle.value) {
        return
    }

    deleting.value = true

    router.delete(route('addon.kb.admin.articles.destroy', { article: deletingArticle.value.ulid }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            deletingArticle.value = null
        },
    })
}

function reEmbed(article: Article) {
    reEmbeddingId.value = article.id

    router.post(route('addon.kb.admin.articles.re-embed', { article: article.ulid }), undefined, {
        preserveScroll: true,
        onFinish: () => {
            reEmbeddingId.value = null
        },
    })
}

const statusBadgeClass = (status: ArticleStatus) => status === 'published'
    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
    : 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300'

const embedBadgeClass = (status: EmbedStatus) => ({
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}[status] ?? 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300')

const filteredArticles = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (!term) {
        return props.articles.data
    }

    return props.articles.data.filter((article) => {
        const searchable = [
            article.title,
            article.slug,
            article.category?.name ?? '',
            article.status,
            article.embed_status,
        ].join(' ').toLowerCase()

        return searchable.includes(term)
    })
})
</script>

<template>
    <Head :title="t('KB Articles')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Knowledge Base Articles') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                    <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Browse, filter, and manage published help articles from the knowledge base.') }}
                    </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 btn-primary-admin disabled:opacity-60"
                @click="createArticle"
            >
                <i class="ti ti-plus text-base"></i>
                {{ t('New Article') }}
            </button>
        </div>

        <div class="mt-6 rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="relative flex-1 sm:max-w-xs">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="t('Search articles...')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-9 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
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

                    <div class="shrink-0">
                        <AppFilterDropdown :active-filters-count="activeFiltersCount">
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Status') }}</label>
                                    <AppSelect
                                        v-model="statusFilter"
                                        :options="statusOptions"
                                        option-label="label"
                                        option-value="value"
                                        :placeholder="t('All Status')"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Category') }}</label>
                                    <AppSelect
                                        v-model="categoryFilter"
                                        :options="categoryOptions"
                                        option-label="label"
                                        option-value="value"
                                        :placeholder="t('All Categories')"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Embed State') }}</label>
                                    <AppSelect
                                        v-model="embedFilter"
                                        :options="embedOptions"
                                        option-label="label"
                                        option-value="value"
                                        :placeholder="t('All Embed States')"
                                    />
                                </div>
                                <div v-if="activeFiltersCount > 0" class="flex justify-end border-t border-gray-100 pt-3 dark:border-surface-800">
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-red-600 transition-colors hover:text-red-500"
                                        @click="resetFilters"
                                    >
                                        {{ t('Clear Filters') }}
                                    </button>
                                </div>
                            </div>
                        </AppFilterDropdown>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-2xl">
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-950/60 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">{{ t('Title') }}</th>
                            <th class="px-6 py-3">{{ t('Category') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Status') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Embed') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Views') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Votes') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Updated') }}</th>
                            <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr
                            v-for="article in filteredArticles"
                            :key="article.id"
                            class="transition hover:bg-primary-50/40 dark:hover:bg-white/[0.03]"
                        >
                            <td class="px-6 py-4 align-top">
                                <div class="min-w-[240px]">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ article.title }}</p>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">/{{ article.slug }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-gray-700 dark:text-gray-300">
                                {{ article.category?.name ?? t('Uncategorized') }}
                            </td>
                            <td class="px-6 py-4 text-center align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(article.status)">
                                    {{ article.status === 'published' ? t('Published') : t('Draft') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="embedBadgeClass(article.embed_status)" :title="article.embed_error || undefined">
                                    {{ embedStatusLabel[article.embed_status] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center align-top text-gray-700 dark:text-gray-300">{{ article.views }}</td>
                            <td class="px-6 py-4 text-center align-top text-gray-700 dark:text-gray-300">
                                <span v-if="article.helpful_percent !== null">
                                    {{ article.helpful_percent }}% {{ t('helpful') }}
                                </span>
                                <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                            </td>
                            <td class="px-6 py-4 text-center align-top whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(article.updated_at)) }}
                            </td>
                            <td class="px-6 py-4 text-end align-top">
                                <TableActionMenu>
                                    <template #default="{ close }">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click="editArticle(article); close()"
                                        >
                                            <i class="ti ti-pencil text-base"></i>
                                            {{ t('Edit') }}
                                        </button>
                                        <button
                                            v-if="article.embed_status === 'failed' || article.embed_status === 'done'"
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 disabled:opacity-60 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                            :disabled="reEmbeddingId === article.id"
                                            @click="reEmbed(article); close()"
                                        >
                                            <i class="ti ti-refresh text-base"></i>
                                            {{ reEmbeddingId === article.id ? t('Re-Embedding...') : t('Re-Embed') }}
                                        </button>
                                        <div class="block h-px w-full bg-gray-100 dark:bg-gray-800"></div>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                            @click="openDelete(article); close()"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                            {{ t('Delete') }}
                                        </button>
                                    </template>
                                </TableActionMenu>
                            </td>
                        </tr>

                        <tr v-if="filteredArticles.length === 0">
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                        <i class="ti ti-book text-xl"></i>
                                    </div>
                                    <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">{{ t('No articles found') }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Try clearing the filters or create a new article.') }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <div
                    v-if="articles.links.length > 3"
                    class="border-t border-gray-100 px-5 py-4 dark:border-surface-800"
                >
                    <Pagination
                        :links="articles.links"
                        :from="articles.from"
                        :to="articles.to"
                        :total="articles.total"
                    />
                </div>
            </div>
        </div>
    </div>

    <ActionConfirmModal
        :open="deletingArticle !== null"
        :title="t('Delete article?')"
        :message="deletingArticle ? t('This will permanently delete :title.', { title: deletingArticle.title }) : t('This article will be permanently deleted.')"
        :confirm-label="t('Delete')"
        :processing-label="t('Deleting...')"
        :processing="deleting"
        variant="danger"
        @cancel="closeDelete"
        @confirm="destroyArticle"
    />
</template>
