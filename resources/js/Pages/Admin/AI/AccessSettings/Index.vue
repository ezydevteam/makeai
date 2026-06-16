<template>
    <Head :title="t('AI Access Settings')" />

    <AdminLayout>
        <div class="px-4 py-8 sm:px-6">
            <div class="mx-auto w-full sm:max-w-7xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('AI Access Settings') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage visibility and access requirements for all AI tools.') }}
                    </p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-surface-800 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                            <div class="relative w-full sm:max-w-xs">
                                <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    :placeholder="t('Search tools...')"
                                />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                                    :aria-label="t('Clear search')"
                                    @click="clearSearch"
                                >
                                    <i class="ti ti-x text-sm"></i>
                                </button>
                            </div>

                            <AppSelect
                                v-model="form.category"
                                :options="categoryOptions"
                                :placeholder="t('All Categories')"
                                class="w-full sm:w-52"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <span v-if="selectedIds.length > 0" class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t(':count selected', { count: selectedIds.length }) }}
                            </span>
                            <AppSelect
                                v-model="bulkAction"
                                :options="accessLevels"
                                :placeholder="t('Select Access Level')"
                                class="w-full sm:w-56"
                            />
                            <div :title="selectedIds.length === 0 ? t('Select at 1 tool to apply') : ''">
                                <button @click="applyBulkUpdate" :disabled="!bulkAction || selectedIds.length === 0" class="inline-flex items-center justify-center rounded-lg btn-primary px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50">
                                    {{ t('Apply') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[760px] w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-800 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="p-4 w-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                                :checked="isAllSelected"
                                                @change="toggleAll"
                                            />
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3">{{ t('Tool Name') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Category') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Current Access') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="tool in filteredTools"
                                    :key="tool.id"
                                    class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50/70 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-surface-800/50"
                                >
                                    <td class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                v-model="selectedIds"
                                                :value="tool.id"
                                                class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                            />
                                        </div>
                                    </td>
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {{ tool.name }}
                                    </th>
                                    <td class="px-6 py-4">
                                        {{ tool.category?.name || t('Uncategorized') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getAccessLevelBadgeClass(tool.access_level)">
                                            {{ getAccessLevelLabel(tool.access_level) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <Link
                                            :href="route('admin.ai.tools.edit', tool.id)"
                                            :title="t('Edit')"
                                            :aria-label="t('Edit')"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                        >
                                            <i class="ti ti-edit text-base"></i>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="filteredTools.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="ti ti-search-off mb-3 text-4xl opacity-40"></i>
                                            <p>{{ t('No tools found.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-100 px-4 py-4 dark:border-surface-800" v-if="tools.links && tools.links.length > 3 && !searchQuery">
                        <Pagination
                            :links="tools.links"
                            :from="tools.from"
                            :to="tools.to"
                            :total="tools.total"
                            :current-page="tools.current_page"
                            :last-page="tools.last_page"
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
            variant="primary"
            @cancel="closeConfirmModal"
            @confirm="runConfirmedAction"
        />
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface Category {
    id: number | string
    name: string
}

interface ToolCategory {
    name?: string | null
}

interface ToolItem {
    id: number
    name: string
    access_level: string
    category?: ToolCategory | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface ToolsResponse {
    data: ToolItem[]
    links?: PaginationLink[]
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
}

interface Filters {
    category?: string | number | null
}

interface ConfirmModalState {
    open: boolean
    title: string
    message: string
    confirmLabel: string
    processingLabel: string
    processing: boolean
    action: null | (() => void)
}

const { t } = useTranslate()

const props = defineProps({
    tools: Object as () => ToolsResponse,
    categories: Array as () => Category[],
    filters: Object as () => Filters,
    globalDefault: String,
})

const form = useForm({
    category: props.filters.category ? String(props.filters.category) : '',
})

const searchQuery = ref('')
const selectedIds = ref<number[]>([])
const bulkAction = ref<string | number | null>('')

const accessLevels = [
    { value: 'inherit', label: t('Inherit (Default)') },
    { value: 'public', label: t('Public (No Login)') },
    { value: 'login_required', label: t('Login Required') },
    { value: 'free_plan', label: t('Free Plan') },
    { value: 'pro_plan', label: t('Pro Plan') },
]

const categoryOptions = computed(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
])

const confirmModal = ref<ConfirmModalState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    processingLabel: '',
    processing: false,
    action: null,
})

const isAllSelected = computed(() => {
    return filteredTools.value.length > 0 && filteredTools.value.every((tool) => selectedIds.value.includes(tool.id))
})

const toggleAll = (e: Event) => {
    const target = e.target as HTMLInputElement
    if (target.checked) {
        const visibleIds = filteredTools.value.map((tool) => tool.id)
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds]))
    } else {
        const visibleIds = new Set(filteredTools.value.map((tool) => tool.id))
        selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id))
    }
}

const getAccessLevelLabel = (level: string) => {
    return accessLevels.find((item) => item.value === level)?.label || level
}

const getAccessLevelBadgeClass = (level: string) => {
    switch (level) {
        case 'public': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        case 'login_required': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
        case 'pro_plan': return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
        case 'free_plan': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    }
}

const applyFilters = () => {
    form.get(route('admin.ai.access.index'), {
        preserveState: true,
        preserveScroll: true,
    })
}

const clearSearch = () => {
    searchQuery.value = ''
}
const filteredTools = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()
    if (!query) {
        return props.tools.data
    }

    return props.tools.data.filter((tool) => {
        const categoryName = tool.category?.name?.toLowerCase() ?? ''
        return tool.name.toLowerCase().includes(query) || categoryName.includes(query)
    })
})

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
        action: null,
    }
}

const runConfirmedAction = () => {
    if (!confirmModal.value.action) {
        return
    }

    confirmModal.value.processing = true
    confirmModal.value.action()
}

const applyBulkUpdate = () => {
    if (selectedIds.value.length === 0 || !bulkAction.value) return

    const selectedLevel = String(bulkAction.value)

    openConfirmModal({
        title: t('Apply access level?'),
        message: t('Update access level to ":level" for :count tools?', {
            level: getAccessLevelLabel(selectedLevel),
            count: selectedIds.value.length,
        }),
        confirmLabel: t('Apply'),
        processingLabel: t('Applying...'),
        action: () => {
            router.post(route('admin.ai.access.bulk'), {
                tool_ids: selectedIds.value,
                access_level: selectedLevel,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = []
                    bulkAction.value = ''
                },
                onFinish: () => {
                    closeConfirmModal(true)
                },
            })
        },
    })
}
</script>
