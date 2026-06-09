<template>
    <Head :title="t('AI Access Settings')" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('AI Access Settings') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage visibility and access requirements for all AI tools.') }}
                    </p>
                </div>

                <!-- Filters -->
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-64">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Search tools...')"
                                />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    :title="t('Clear search')"
                                    @click="clearSearch"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>
                        <div class="w-48">
                            <AppSelect
                                v-model="form.category"
                                :options="categoryOptions"
                                :placeholder="t('All Categories')"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Bulk Action Dropdown -->
                        <div class="flex items-center gap-2">
                            <span v-if="selectedIds.length > 0" class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t(':count selected', { count: selectedIds.length }) }}
                            </span>
                            <AppSelect
                                v-model="bulkAction"
                                :options="accessLevels"
                                :placeholder="t('Select Access Level')"
                                class="w-56"
                            />
                            <div :title="selectedIds.length === 0 ? t('Select at 1 tool to apply') : ''">
                                <button @click="applyBulkUpdate" :disabled="!bulkAction || selectedIds.length === 0" class="px-4 py-2 btn-primary rounded-lg transition-colors text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50">
                                    {{ t('Apply') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="overflow-hidden border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="p-4 w-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                class="w-4 h-4 bg-gray-50 border-gray-300 rounded text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
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
                                    class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                                >
                                    <td class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                v-model="selectedIds"
                                                :value="tool.id"
                                                class="w-4 h-4 bg-gray-50 border-gray-300 rounded text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
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
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No tools found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-4" v-if="tools.links && tools.links.length > 3">
                    <Pagination :links="tools.links" />
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

const closeConfirmModal = () => {
    if (confirmModal.value.processing) {
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
                    closeConfirmModal()
                },
            })
        },
    })
}
</script>
