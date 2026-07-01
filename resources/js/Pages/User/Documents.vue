<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: UserDashboardLayout })

interface DocumentItem {
    id: number
    title: string
    tool_slug: string | null
    word_count: number
    created_at: string
    updated_at: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Pagination {
    current_page: number
    last_page: number
    total: number
    links: PaginationLink[]
}

const props = defineProps<{
    documents: DocumentItem[]
    filters: {
        search?: string | null
    }
    pagination: Pagination
}>()

const { t } = useTranslate()
const toast = useToastr()

const search = ref(props.filters.search || '')
const isDeleteModalOpen = ref(false)
const documentToDelete = ref<DocumentItem | null>(null)
const isDeleting = ref(false)

// Watch search query and update list with debounce
let searchTimeout: ReturnType<typeof setTimeout>
watch(search, (value) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(
            route('user.dashboard.documents.index'),
            { search: value || undefined },
            { preserveState: true, replace: true }
        )
    }, 300)
})

function confirmDelete(document: DocumentItem) {
    documentToDelete.value = document
    isDeleteModalOpen.value = true
}

function handleDeleteConfirm() {
    if (!documentToDelete.value) return
    isDeleting.value = true
    router.delete(route('documents.destroy', documentToDelete.value.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('Document deleted successfully'))
            isDeleteModalOpen.value = false
            documentToDelete.value = null
        },
        onFinish: () => {
            isDeleting.value = false
        },
    })
}

function handleDeleteCancel() {
    isDeleteModalOpen.value = false
    documentToDelete.value = null
}

const paginationLabel = (label: string) => {
    if (label.includes('Previous')) return t('Previous')
    if (label.includes('Next')) return t('Next')
    return label.replace('&laquo;', '').replace('&raquo;', '').trim()
}

function timeAgo(dateString: string): string {
    const diff = Date.now() - new Date(dateString).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return t('Just now')
    if (mins < 60) return t(':countm ago', { count: mins })
    const hours = Math.floor(mins / 60)
    if (hours < 24) return t(':counth ago', { count: hours })
    const days = Math.floor(hours / 24)
    if (days < 7) return t(':countd ago', { count: days })
    return new Date(dateString).toLocaleDateString(undefined, { dateStyle: 'medium' })
}

// Helper to determine gradient colors for AI tools to look premium
function getToolGradient(toolSlug: string | null): string {
    if (!toolSlug) return 'from-slate-500/10 to-slate-600/10 text-slate-700 dark:text-slate-300'

    // Select color based on string hash
    let hash = 0
    for (let i = 0; i < toolSlug.length; i++) {
        hash = toolSlug.charCodeAt(i) + ((hash << 5) - hash)
    }

    const colors = [
        'from-purple-500/10 to-indigo-600/10 text-indigo-700 dark:text-indigo-300 border-indigo-200/30 dark:border-indigo-800/30',
        'from-rose-500/10 to-orange-600/10 text-rose-700 dark:text-rose-300 border-rose-200/30 dark:border-rose-800/30',
        'from-emerald-500/10 to-teal-600/10 text-emerald-700 dark:text-emerald-300 border-emerald-200/30 dark:border-emerald-800/30',
        'from-blue-500/10 to-cyan-600/10 text-blue-700 dark:text-blue-300 border-blue-200/30 dark:border-blue-800/30',
        'from-amber-500/10 to-yellow-600/10 text-amber-700 dark:text-amber-300 border-amber-200/30 dark:border-amber-800/30'
    ]

    return colors[Math.abs(hash) % colors.length]
}

function formatToolName(slug: string | null): string {
    if (!slug) return t('Custom Document')
    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ')
}
</script>

<template>
    <Head :title="t('Saved Documents')" />

    <div>
        <!-- Header Section -->
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('Saved Documents') }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ t('Manage, edit, or delete documents you saved from AI tools.') }}
                </p>
            </div>

            <!-- Search bar -->
            <div class="relative w-full max-w-xs sm:w-72">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="ti ti-search text-gray-400 text-base"></i>
                </span>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('Search documents...')"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2.5 pl-10 pr-9 text-sm outline-none transition focus:border-primary-500 focus:bg-white dark:border-surface-700 dark:bg-surface-900 dark:text-white dark:focus:border-primary-500"
                />
                <span v-if="search" class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <button @click="search = ''" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </span>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="documents.length === 0"
            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900/50"
        >
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/30">
                <i class="ti ti-file-text text-2xl text-primary-600 dark:text-primary-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                {{ search ? t('No matching documents') : t('No documents saved yet') }}
            </h3>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                {{ search ? t('Try adjusting your search terms or clear the filter.') : t('Generate some content using our templates and save them to documents to see them here.') }}
            </p>
            <div class="mt-6">
                <Link
                    :href="route('ai.tools.index')"
                    class="inline-flex items-center gap-2 btn-primary rounded-full"
                >
                    <i class="ti ti-wand text-base"></i>
                    {{ t('Create Content') }}
                </Link>
            </div>
        </div>

        <!-- Documents Grid -->
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-surface-900/80 dark:hover:border-primary-900/50"
            >
                <div>
                    <!-- Top Category and Word Count Tags -->
                    <div
                        :class="getToolGradient(doc.tool_slug)"
                        class="mb-2 inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold tracking-wide bg-gray-200 dark:!bg-slate-800"
                    >
                        {{ formatToolName(doc.tool_slug) }}
                    </div>

                    <!-- Title -->
                    <h3 class="text-base font-bold text-gray-900 dark:text-white line-clamp-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                        {{ doc.title }}
                    </h3>

                    <!-- Updated Date -->
                    <div class="mt-3 flex items-center justify-between gap-2 text-xs text-gray-400 dark:text-gray-500">
                        <div class="flex items-center gap-1">
                            <i class="ti ti-clock text-sm"></i>
                            {{ t('Updated :time', { time: timeAgo(doc.updated_at) }) }}
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="ti ti-notes text-sm"></i>
                            <span>{{ t(':count words', { count: doc.word_count }) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Action Toolbar -->
                <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-surface-800">
                    <Link
                        :href="route('documents.edit', doc.id)"
                        class="text-xs font-semibold !text-primary-600 flex items-center gap-1 rounded-xl px-3 py-1.5 transition-colors hover:bg-gray-100 dark:hover:bg-slate-800"
                    >
                        <i class="ti ti-edit text-sm"></i>
                        {{ t('Edit Document') }}
                    </Link>

                    <Tooltip :content="t('Delete')">
                        <button
                            @click="confirmDelete(doc)"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
                        >
                            <i class="ti ti-trash text-base"></i>
                        </button>
                    </Tooltip>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="mt-8 flex justify-center">
            <nav class="flex gap-1.5">
                <Link
                    v-for="link in pagination.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-200',
                        link.active
                            ? 'bg-primary-600 text-white shadow-md shadow-primary-500/10'
                            : link.url
                                ? 'text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 dark:text-gray-400 dark:bg-surface-900 dark:border-surface-800 dark:hover:bg-surface-800'
                                : 'cursor-default text-gray-300 bg-gray-50 dark:text-gray-650 dark:bg-surface-950',
                    ]"
                    v-html="paginationLabel(link.label)"
                />
            </nav>
        </div>

        <!-- Deletion Confirmation Modal -->
        <ActionConfirmModal
            :open="isDeleteModalOpen"
            :title="t('Delete Document')"
            :message="t('Are you sure you want to permanently delete this document? This action cannot be undone.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            variant="danger"
            :processing="isDeleting"
            @confirm="handleDeleteConfirm"
            @cancel="handleDeleteCancel"
        />
    </div>
</template>
