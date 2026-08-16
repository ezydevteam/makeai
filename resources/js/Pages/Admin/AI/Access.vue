/<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
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
    access_level?: string | null
    search?: string | null
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

interface PageProps {
    tools: ToolsResponse
    categories: Category[]
    filters: Filters
    globalDefault: string
    accessLevels?: Array<{ value: string; label: string; description?: string | null }>
}

const { t } = useTranslate()

const props = defineProps<PageProps>()

const form = useForm({
    category: props.filters.category ? String(props.filters.category) : '',
    access_level: props.filters.access_level ? String(props.filters.access_level) : '',
})

const searchQuery = ref(props.filters.search ? String(props.filters.search) : '')
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
const selectedIds = ref<number[]>([])
const bulkAction = ref<string | number | null>('')

// Mirrors config/access-levels.php, which is the only vocabulary AccessLevelService
// understands. The previous fallback offered 'public', 'login_required', 'free_plan' and
// 'pro_plan' — four values that exist nowhere in that config, so anything saved with one
// resolved to requiresAuth=false and silently ungated the tool. The server normally sends
// the real list via the accessLevels prop; this is only the safety net behind it.
const accessLevels = computed(() => props.accessLevels ?? [
    { value: 'inherit', label: t('Inherit (Default)') },
    { value: 'guest', label: t('Guest (Free)') },
    { value: 'login', label: t('Login Required') },
    { value: 'premium', label: t('Premium (Any Plan)') },
])

const accessFilterOptions = computed(() => [
    { value: '', label: t('All Access Levels') },
    ...accessLevels.value,
])

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
    return props.tools.data.length > 0 && props.tools.data.every((tool) => selectedIds.value.includes(tool.id))
})

const hasActiveFilters = computed(() => searchQuery.value.trim().length > 0 || Boolean(form.category) || Boolean(form.access_level))

const toggleAll = (e: Event) => {
    const target = e.target as HTMLInputElement
    if (target.checked) {
        const visibleIds = props.tools.data.map((tool) => tool.id)
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds]))
    } else {
        const visibleIds = new Set(props.tools.data.map((tool) => tool.id))
        selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id))
    }
}

const getAccessLevelLabel = (level: string) => {
    return accessLevels.value.find((item) => item.value === level)?.label || level
}

const getAccessLevelBadgeClass = (level: string) => {
    switch (level) {
        case 'inherit':
            return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10'
        case 'public':
        case 'guest':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
        case 'login_required':
        case 'login':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20'
        case 'free_plan':
        case 'free':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'
        case 'premium':
            return 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20'
        default:
            if (level.startsWith('plan:')) {
                return 'bg-purple-50 text-purple-700 ring-1 ring-purple-200 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-500/20'
            }
            return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'
    }
}

const buildFilterPayload = () => {
    const payload: Record<string, string> = {}

    if (form.category) {
        payload.category = form.category
    }

    if (form.access_level) {
        payload.access_level = form.access_level
    }

    if (searchQuery.value.trim()) {
        payload.search = searchQuery.value.trim()
    }

    return payload
}

const applyFilters = () => {
    router.get(route('admin.ai.access.index'), buildFilterPayload(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const clearSearch = () => {
    searchQuery.value = ''
    applyFilters()
}

const resetFilters = () => {
    searchQuery.value = ''
    form.category = ''
    form.access_level = ''
    selectedIds.value = []
    applyFilters()
}

let searchTimeout: any = null
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
})

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
        searchQuery.value = ''

        if (form.category || form.access_level) {
            form.category = ''
            form.access_level = ''
            applyFilters()
        }
    }
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

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('AI Access Settings')" />

    <AdminLayout>
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('AI Access Settings') }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage visibility and access requirements for all AI tools.') }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[240px] sm:max-w-xs">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Search tools...')"
                                    @focus="searchFocused = true"
                                    @blur="searchFocused = false"
                                />
                                <span
                                    v-if="!searchQuery && !searchFocused"
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                                >
                                    /
                                </span>
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
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[200px] lg:w-56 lg:flex-none">
                                <AppSelect
                                    v-model="form.category"
                                    :options="categoryOptions"
                                    :placeholder="t('All Categories')"
                                    live-search
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-56 lg:flex-none">
                                <AppSelect
                                    v-model="form.access_level"
                                    :options="accessFilterOptions"
                                    :placeholder="t('All Access Levels')"
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 w-full sm:w-auto"
                                @click="resetFilters"
                            >
                                {{ t('Clear filters') }}
                            </button>

                            <template v-if="selectedIds.length">
                                <span class="text-sm text-gray-500 dark:text-gray-400 sm:whitespace-nowrap">
                                    {{ t(':count selected', { count: selectedIds.length }) }}
                                </span>

                                <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-52 lg:flex-none">
                                    <AppSelect
                                        v-model="bulkAction"
                                        :options="accessLevels"
                                        :placeholder="t('Access Level')"
                                    />
                                </div>

                                <button
                                    type="button"
                                    :disabled="!bulkAction || selectedIds.length === 0"
                                    class="inline-flex w-full sm:w-auto items-center justify-center btn-primary-admin px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50 rounded-xl"
                                    :title="selectedIds.length === 0 ? t('Select at least 1 tool to apply') : ''"
                                    @click="applyBulkUpdate"
                                >
                                    {{ t('Apply') }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-b-2xl">
                    <div class="overflow-x-auto">
                        <table class="min-w-[760px] w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-800/50">
                                    <th scope="col" class="w-4 px-4 py-3.5 text-left">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                            :checked="isAllSelected"
                                            @change="toggleAll"
                                        />
                                    </th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Category') }}</th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Current Access') }}</th>
                                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                                <tr
                                    v-for="tool in tools.data"
                                    :key="tool.id"
                                    class="transition-colors hover:bg-gray-50/50 dark:hover:bg-surface-800/30"
                                >
                                    <td class="w-4 px-4 py-4">
                                        <input
                                            v-model="selectedIds"
                                            type="checkbox"
                                            :value="tool.id"
                                            class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                        />
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ tool.name }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-400">
                                            {{ tool.category?.name || t('Uncategorized') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getAccessLevelBadgeClass(tool.access_level)">
                                            {{ getAccessLevelLabel(tool.access_level) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end">
                                            <Tooltip :content="t('Edit Tool')">
                                                <Link
                                                    :href="route('admin.ai.tools.edit', tool.id)"
                                                    class="flex h-8 w-8 items-center justify-center rounded-full text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                                >
                                                    <i class="ti ti-edit text-base"></i>
                                                </Link>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="tools.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                        <i class="ti ti-search-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="font-medium">{{ hasActiveFilters ? t('No tools match your filters') : t('No tools found') }}</p>
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

                    <div v-if="tools.links && tools.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
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
