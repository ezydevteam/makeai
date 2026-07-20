<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
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
const openActionMenuId = ref<number | null>(null)
const actionMenuPosition = ref({ top: 0, left: 0, placement: 'bottom' as 'top' | 'bottom' })

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
    openActionMenuId.value = null
    router.visit(route('addon.kb.admin.articles.edit', { article: article.ulid }))
}

function openDelete(article: Article) {
    openActionMenuId.value = null
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
    openActionMenuId.value = null
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

async function toggleActionMenu(id: number, event: MouseEvent) {
    if (openActionMenuId.value === id) {
        openActionMenuId.value = null
        return
    }

    const trigger = event.currentTarget

    if (!(trigger instanceof HTMLElement)) {
        return
    }

    const rect = trigger.getBoundingClientRect()
    const menuWidth = 176
    const menuHeight = 164
    const menuOffset = 8
    const viewportPadding = 12
    const hasBottomSpace = window.innerHeight - rect.bottom >= menuHeight + viewportPadding

    openActionMenuId.value = id
    actionMenuPosition.value = {
        top: hasBottomSpace
            ? rect.bottom + menuOffset
            : Math.max(viewportPadding, rect.top - menuHeight - menuOffset),
        left: Math.max(viewportPadding, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - viewportPadding)),
        placement: hasBottomSpace ? 'bottom' : 'top',
    }
}

function handleDocumentClick(event: MouseEvent) {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-article-actions]')) {
        return
    }

    openActionMenuId.value = null
}

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    window.removeEventListener('resize', handleViewportChange)
    window.removeEventListener('scroll', handleViewportChange, true)
})

function handleViewportChange() {
    openActionMenuId.value = null
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
})
</script>

<template>
    <Head :title="t('KB Articles')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
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
                class="inline-flex items-center gap-2 rounded-lg btn-primary-admin disabled:opacity-60"
                @click="createArticle"
            >
                <i class="ti ti-plus text-base"></i>
                {{ t('New Article') }}
            </button>
        </div>



        <div class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="relative">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400 dark:text-gray-500"></i>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('Search articles...')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-2 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                            :aria-label="t('Clear search')"
                            @click="clearSearch"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                    <AppSelect v-model="statusFilter" :options="statusOptions" :placeholder="t('All Status')" />
                    <AppSelect v-model="categoryFilter" :options="categoryOptions" :placeholder="t('All Categories')" />
                    <AppSelect v-model="embedFilter" :options="embedOptions" :placeholder="t('All Embed States')" />
                </div>
            </div>

            <div class="max-h-[calc(100vh-22rem)] overflow-auto">
                <table class="min-w-full text-left">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] uppercase tracking-[0.08em] text-gray-500 dark:bg-surface-800/95 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ t('Title') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ t('Category') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ t('Status') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ t('Embed') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ t('Views') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ t('Votes') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ t('Updated') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="article in filteredArticles" :key="article.id" class="hover:bg-primary-50/60 dark:hover:bg-surface-800/70">
                            <td class="px-5 py-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ article.title }}</div>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">/{{ article.slug }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ article.category?.name ?? t('Uncategorized') }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(article.status)">
                                    {{ article.status === 'published' ? t('Published') : t('Draft') }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="embedBadgeClass(article.embed_status)" :title="article.embed_error || undefined">
                                    {{ embedStatusLabel[article.embed_status] }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ article.views }}</td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <span v-if="article.helpful_percent !== null">
                                    {{ article.helpful_percent }}% {{ t('helpful') }}
                                </span>
                                <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(article.updated_at)) }}
                            </td>
                            <td class="overflow-visible px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="relative" data-article-actions>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click.stop="toggleActionMenu(article.id, $event)"
                                        >
                                            <i class="ti ti-dots-vertical text-base"></i>
                                        </button>
                                    </div>

                                    <Teleport to="body">
                                        <div
                                            v-if="openActionMenuId === article.id"
                                            data-article-actions
                                            class="fixed z-[80] w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                            :style="{
                                                top: `${actionMenuPosition.top}px`,
                                                left: `${actionMenuPosition.left}px`,
                                                transformOrigin: actionMenuPosition.placement === 'bottom' ? 'top right' : 'bottom right',
                                            }"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="editArticle(article)"
                                            >
                                                <i class="ti ti-pencil text-base"></i>
                                                {{ t('Edit') }}
                                            </button>
                                            <button
                                                v-if="article.embed_status === 'failed' || article.embed_status === 'done'"
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 disabled:opacity-60 dark:text-gray-200 dark:hover:bg-surface-800"
                                                :disabled="reEmbeddingId === article.id"
                                                @click="reEmbed(article)"
                                            >
                                                <i class="ti ti-refresh text-base"></i>
                                                {{ reEmbeddingId === article.id ? t('Re-Embedding...') : t('Re-Embed') }}
                                            </button>
                                            <hr class="my-1 border-gray-200 dark:border-surface-700">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                @click="openDelete(article)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Delete') }}
                                            </button>
                                        </div>
                                    </Teleport>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredArticles.length === 0">
                            <td colspan="8" class="px-5 py-14 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                        <i class="ti ti-book text-2xl"></i>
                                    </div>
                                    <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('No articles found') }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Try clearing the filters or create a new article.') }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-5 py-4 dark:border-surface-800">
                <Pagination
                    :links="articles.links"
                    :from="articles.from"
                    :to="articles.to"
                    :total="articles.total"
                />
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
