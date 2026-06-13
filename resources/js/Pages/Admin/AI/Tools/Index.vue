<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: AdminLayout })

interface ToolCategory {
    id: number
    name: string
    slug: string
}

interface ToolItem {
    id: number
    name: string
    slug: string
    icon?: string | null
    color?: string | null
    is_active: boolean
    usage_count?: number | string | null
    category?: ToolCategory | string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    tools: {
        data: ToolItem[]
        links: PaginationLink[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    categories: ToolCategory[]
    filters: { category?: string; search?: string; status?: string }
}>()

const search = ref(props.filters.search || '')
const selectedCategory = ref(props.filters.category || '')
const selectedStatus = ref(props.filters.status || '')
const { t } = useTranslate()
const { formatNumber } = useNumberFormat()

const categoryOptions = computed(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((c) => ({ value: String(c.id), label: c.name })),
])

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
])

const applyFilters = () => {
    router.get(route('admin.ai.tools.index'), {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
        status: selectedStatus.value || undefined,
    }, { preserveScroll: true, preserveState: true, replace: true })
}

const toggleTool = (id: number) => {
    router.post(route('admin.ai.tools.toggle', id), {}, { preserveScroll: true })
}

const hasActiveFilters = computed(() => Boolean(search.value || selectedCategory.value || selectedStatus.value))

let filterTimer: ReturnType<typeof setTimeout> | null = null
const handleSearchInput = () => {
    if (filterTimer) clearTimeout(filterTimer)
    filterTimer = setTimeout(applyFilters, 350)
}

const clearSearch = () => {
    if (!search.value) return
    search.value = ''
    applyFilters()
}

const resetFilters = () => {
    search.value = ''
    selectedCategory.value = ''
    selectedStatus.value = ''
    applyFilters()
}

// ─── Delete confirmation ──────────────────────
const deleteModalOpen = ref(false)
const toolToDelete = ref<number | null>(null)
const deleteProcessing = ref(false)

const confirmDelete = (id: number) => {
    toolToDelete.value = id
    deleteModalOpen.value = true
}

const executeDelete = () => {
    if (toolToDelete.value === null) return
    deleteProcessing.value = true
    router.delete(route('admin.ai.tools.destroy', toolToDelete.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false
            deleteModalOpen.value = false
            toolToDelete.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('AI Tools — Admin')" />

    <div class="mx-auto max-w-7xl px-6 py-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Tools') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Manage AI tools, prompts, and configurations.') }}</p>
            </div>
            <Link :href="route('admin.ai.tools.create')" class="inline-flex items-center gap-2 btn-primary px-4 py-2 text-sm">
                <i class="ti ti-plus"></i>
                {{ t('New Tool') }}
            </Link>
        </div>

        <!-- Table -->
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-surface-800">
                <div class="relative min-w-[240px] flex-1">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input
                        v-model="search"
                        @input="handleSearchInput"
                        type="text"
                        :placeholder="t('Search tools...')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                    <button
                        v-if="search"
                        type="button"
                        class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                        :aria-label="t('Clear search')"
                        @click="clearSearch"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <AppSelect
                    v-model="selectedCategory"
                    :options="categoryOptions"
                    :placeholder="t('All Categories')"
                    live-search
                    class="w-full sm:w-56"
                    @update:model-value="applyFilters"
                />
                <AppSelect
                    v-model="selectedStatus"
                    :options="statusOptions"
                    :placeholder="t('All Status')"
                    class="w-full sm:w-44"
                    @update:model-value="applyFilters"
                />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[700px] w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-800/50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Category') }}</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Uses') }}</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                    <tr v-for="tool in tools.data" :key="tool.id" class="hover:bg-gray-50/50 dark:hover:bg-surface-800/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div :style="{ background: tool.color || '#6366f1' }" class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm shrink-0 shadow-sm overflow-hidden">
                                    <i v-if="tool.icon" :class="tool.icon"></i>
                                    <span v-else class="font-bold leading-none">{{ String(tool.name || '?').charAt(0).toUpperCase() }}</span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ tool.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ tool.slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span v-if="tool.category" class="text-xs font-medium px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-surface-800 text-gray-600 dark:text-gray-400">
                                {{ typeof tool.category === 'object' ? tool.category.name : tool.category }}
                            </span>
                            <span v-else class="text-xs text-gray-400">{{ t('—') }}</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <button
                                type="button"
                                :class="tool.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'"
                                class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                @click="toggleTool(tool.id)"
                            >
                                <span
                                    :class="tool.is_active ? 'translate-x-4' : 'translate-x-0'"
                                    class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                                />
                            </button>
                        </td>
                        <td class="px-4 py-4 text-center text-gray-600 dark:text-gray-400 font-medium">
                            {{ formatNumber(Number(tool.usage_count || 0)) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link :href="route('admin.ai.tools.edit', tool.id)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-surface-800" :title="t('Edit')">
                                    <i class="ti ti-edit text-base"></i>
                                </Link>
                                <button @click="confirmDelete(tool.id)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20" :title="t('Delete')">
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!tools.data?.length">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                            <i class="ti ti-table-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                            <p class="font-medium">{{ t('No tools found') }}</p>
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

            <div v-if="tools.links && tools.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination
                    :links="tools.links"
                    :from="tools.from"
                    :to="tools.to"
                    :total="tools.total"
                    :current-page="tools.current_page"
                    :last-page="tools.last_page"
                />
            </div>
        </section>
    </div>

    <!-- Delete Confirmation Modal -->
    <ActionConfirmModal
        :open="deleteModalOpen"
        :title="t('Delete Tool')"
        :message="t('Are you sure you want to delete this tool? This action cannot be undone. All associated reviews and usage data will be lost.')"
        :confirm-label="t('Delete')"
        :processing-label="t('Deleting...')"
        :processing="deleteProcessing"
        variant="danger"
        @cancel="deleteModalOpen = false"
        @confirm="executeDelete"
    />
</template>
