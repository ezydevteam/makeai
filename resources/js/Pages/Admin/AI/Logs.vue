<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppFilterDropdown from '@/Components/Admin/AppFilterDropdown.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface UsageUser {
    name?: string | null
    email?: string | null
}

interface UsageLog {
    id: number
    provider: string | null
    model: string | null
    tool_slug: string | null
    total_tokens: number | string | null
    input_tokens?: number
    output_tokens?: number
    cost_usd?: number | string
    credits_used?: number | string
    status?: string
    response_time_ms?: number | null
    created_at: string
    user?: UsageUser | null
    metadata?: Record<string, any> | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface LogsResponse {
    data: UsageLog[]
    links?: PaginationLink[]
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
}

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatNumber } = useNumberFormat()

const props = defineProps<{
    logs: LogsResponse
    filters: {
        tool_slug?: string
        provider?: string
        status?: string
        search?: string
        date_from?: string
        date_to?: string
    }
    providers: string[]
    stats: {
        total_requests: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        credits_used: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        estimated_cost: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        failed_requests: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const searchInput = ref(props.filters.search || '')
const searchField = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)

const currentProvider = computed(() => props.filters.provider || '')
const currentStatus = computed(() => props.filters.status || '')
const dateFrom = computed(() => props.filters.date_from || '')
const dateTo = computed(() => props.filters.date_to || '')

const hasActiveFilters = computed(() => Boolean(searchInput.value || currentProvider.value || currentStatus.value || dateFrom.value || dateTo.value))

const activeFiltersCount = computed(() => {
    return [currentProvider.value, currentStatus.value, dateFrom.value, dateTo.value].filter(Boolean).length
})

const providerOptions = computed(() => {
    return [
        { value: '', label: t('All Providers') },
        ...props.providers.map((provider) => ({
            value: provider,
            label: provider.charAt(0).toUpperCase() + provider.slice(1),
        })),
    ]
})

const statusOptions = computed(() => {
    return [
        { value: '', label: t('All Statuses') },
        { value: 'completed', label: t('Completed') },
        { value: 'failed', label: t('Failed') },
        { value: 'cancelled', label: t('Cancelled') },
    ]
})

const buildParams = () => ({
    search: searchInput.value || undefined,
    provider: currentProvider.value || undefined,
    status: currentStatus.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
})

const applyFilters = () => {
    router.get(
        route('admin.ai.logs.index'),
        buildParams(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

const updateFilter = (key: string, value: any) => {
    const params: Record<string, string | undefined> = buildParams()
    params[key] = value ? String(value) : undefined

    router.get(route('admin.ai.logs.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const resetFilters = () => {
    router.get(route('admin.ai.logs.index'), {
        search: searchInput.value || undefined
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName?.toLowerCase()
    const isTypingTarget = tagName === 'input' || tagName === 'textarea' || target?.isContentEditable

    if (event.key === '/' && !isTypingTarget) {
        event.preventDefault()
        searchField.value?.focus()
        return
    }

    if (event.key === 'Escape' && hasActiveFilters.value) {
        resetFilters()
    }
}

const normalizeTokenCount = (value: number | string | null | undefined) => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0
    }

    if (typeof value === 'string') {
        const normalized = Number(value.replace(/[^0-9.-]/g, ''))
        return Number.isFinite(normalized) ? normalized : 0
    }

    return 0
}

const providerBadgeClass = (provider: string | null) => {
    switch ((provider || '').toLowerCase()) {
        case 'openai':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        case 'anthropic':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300'
        case 'gemini':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    }
}

const statusBadgeClass = (status: string | undefined) => {
    switch ((status || '').toLowerCase()) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
        case 'cancelled':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    }
}

const formatCost = (cost: number | string | undefined) => {
    if (!cost) return '$0.000000'
    const num = typeof cost === 'string' ? parseFloat(cost) : cost
    return '$' + num.toFixed(6)
}

const formatCredits = (credits: number | string | undefined) => {
    if (!credits) return '0.00'
    const num = typeof credits === 'string' ? parseFloat(credits) : credits
    return num.toFixed(2)
}

const formatResponseTime = (ms: number | null | undefined) => {
    if (!ms) return t('—')
    if (ms < 1000) return `${ms}ms`
    return `${(ms / 1000).toFixed(2)}s`
}

const formatToolSlug = (slug: string | null | undefined) => {
    if (!slug) return t('Direct')

    const toolNames: Record<string, string> = {
        'admin_blog_assist': t('Blog Editor'),
        'admin_faq_generate': t('FAQ Generator'),
        'admin_testimonial_generate': t('Testimonial Generator'),
        'admin_mail_template_assist': t('Mail Template Editor'),
        'admin_page_assist': t('Page Editor'),
        'admin_ticket_suggest': t('Support Ticket Assistant'),
        'document_summarizer': t('Document Summarizer'),
        'data_extractor': t('Data Extractor'),
        'image_generator': t('Image Generator'),
        'audio_generator': t('Audio Generator'),
        'transcriber': t('Transcriber'),
        'embedding': t('Embedding'),
        'embedding_batch': t('Embedding Batch'),
    }

    return toolNames[slug] || slug.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const formatMetadataKey = (key: string) => {
    return key
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())
}

const detailsModalOpen = ref(false)
const selectedLog = ref<UsageLog | null>(null)

const openDetailsModal = (log: UsageLog) => {
    selectedLog.value = log
    detailsModalOpen.value = true
}

const closeDetailsModal = () => {
    detailsModalOpen.value = false
    selectedLog.value = null
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})

</script>

<template>
    <Head :title="t('AI Usage Logs')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('AI Usage Logs') }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor AI token consumption and generations across all tools.') }}
                </p>
            </div>

            <div class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 dark:border-surface-700 dark:bg-surface-900">
                    <i class="ti ti-list-details text-sm text-primary-600 dark:text-primary-400"></i>
                    {{ t(':count log(s)', { count: logs.total || 0 }) }}
                </span>
            </div>
        </section>

        <!-- Stats Grid -->
        <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                :title="t('AI Requests')"
                :value="stats.total_requests.value"
                :comparison="stats.total_requests.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_requests.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-cpu text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Credits Spent')"
                :value="stats.credits_used.value"
                :comparison="stats.credits_used.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.credits_used.comparison.type"
                color="accent"
            >
                <template #icon>
                    <i class="ti ti-coins text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Estimated Cost')"
                :value="stats.estimated_cost.value"
                :comparison="stats.estimated_cost.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.estimated_cost.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-currency-dollar text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Failed Requests')"
                :value="stats.failed_requests.value"
                :comparison="stats.failed_requests.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.failed_requests.comparison.type"
                color="danger"
            >
                <template #icon>
                    <i class="ti ti-alert-triangle text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900">
            <div class="flex gap-4 items-center justify-between border-b border-gray-100 p-4 dark:border-surface-800">
                <div class="flex-1 relative md:max-w-sm">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                        <i class="ti ti-search text-base"></i>
                    </span>
                    <input
                        ref="searchField"
                        v-model="searchInput"
                        type="text"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :placeholder="t('Search tool, user, model...')"
                        @keydown.enter="applyFilters"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                    />
                    <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                        <span v-if="!searchInput && !searchFocused" class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-white text-[11px] font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500">/</span>
                    </div>
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        :aria-label="t('Clear search')"
                        @click="searchInput = ''; applyFilters()"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="shrink-0">
                    <AppFilterDropdown :active-filters-count="activeFiltersCount">
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('Provider') }}</label>
                                <AppSelect
                                    :model-value="currentProvider"
                                    :options="providerOptions"
                                    :placeholder="t('All Providers')"
                                    @update:model-value="updateFilter('provider', $event)"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('Status') }}</label>
                                <AppSelect
                                    :model-value="currentStatus"
                                    :options="statusOptions"
                                    :placeholder="t('All Statuses')"
                                    @update:model-value="updateFilter('status', $event)"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('From Date') }}</label>
                                <input
                                    type="date"
                                    :value="dateFrom"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    @change="updateFilter('date_from', ($event.target as HTMLInputElement).value)"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('To Date') }}</label>
                                <input
                                    type="date"
                                    :value="dateTo"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    @change="updateFilter('date_to', ($event.target as HTMLInputElement).value)"
                                />
                            </div>
                            <div v-if="activeFiltersCount > 0" class="border-t border-gray-100 pt-3 dark:border-surface-800 flex justify-end">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-red-600 hover:text-red-500 transition-colors"
                                    @click="resetFilters"
                                >
                                    {{ t('Clear Filters') }}
                                </button>
                            </div>
                        </div>
                    </AppFilterDropdown>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-800/50">
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Provider') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tokens') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Cost') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Credits') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Response Time') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="transition-colors hover:bg-gray-50/50 dark:hover:bg-surface-800/30"
                        >
                            <td class="px-6 py-4">
                                <button
                                    type="button"
                                    class="font-medium text-gray-900 hover:text-primary-600 transition-colors text-left dark:text-white dark:hover:text-primary-400 cursor-pointer focus:outline-none"
                                    @click="openDetailsModal(log)"
                                >
                                    {{ log.user?.name || t('Guest') }}
                                </button>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ log.user?.email || t('—') }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="providerBadgeClass(log.provider)">
                                    {{ log.provider || t('Unknown') }}
                                </span>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ log.model || t('—') }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ formatToolSlug(log.tool_slug) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <div>{{ formatNumber(normalizeTokenCount((log.input_tokens || 0) + (log.output_tokens || 0))) }}</div>
                                <div class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                                    {{ t('In: :count', { count: formatNumber(log.input_tokens || 0) }) }} / {{ t('Out: :count', { count: formatNumber(log.output_tokens || 0) }) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ formatCost(log.cost_usd as number | string | undefined) }}
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ formatCredits(log.credits_used as number | string | undefined) }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusBadgeClass(log.status)">
                                    {{ log.status || t('Unknown') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white">
                                {{ formatResponseTime(log.response_time_ms) }}
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                {{ formatDateTime(log.created_at) }}
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="9" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                <i class="ti ti-database-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                <p class="font-medium">{{ hasActiveFilters ? t('No usage logs match your filters') : t('No usage logs found') }}</p>
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

            <div v-if="logs.links && logs.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination
                    :links="logs.links"
                    :from="logs.from"
                    :to="logs.to"
                    :total="logs.total"
                    :current-page="logs.current_page"
                    :last-page="logs.last_page"
                />
            </div>
        </section>

        <!-- Usage Log Details Modal -->
        <AppModal
            :open="detailsModalOpen"
            maxWidth="max-w-xl"
            :title="t('AI Log Details')"
            :subtitle="selectedLog ? t('Request ID: #:id', { id: selectedLog.id }) : ''"
            :cancel-text="t('Close')"
            @close="closeDetailsModal"
        >
            <div v-if="selectedLog" class="space-y-6">
                <!-- User Information -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2.5">{{ t('User Information') }}</h4>
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-950">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Name') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedLog.user?.name || t('Guest') }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Email') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedLog.user?.email || t('—') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API & Model Info -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2.5">{{ t('AI Model Settings') }}</h4>
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-950">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Provider') }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize mt-1" :class="providerBadgeClass(selectedLog.provider)">
                                    {{ selectedLog.provider || t('Unknown') }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Model') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedLog.model || t('—') }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Tool Used') }}</span>
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300 mt-1">
                                    {{ formatToolSlug(selectedLog.tool_slug) }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Status') }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize mt-1" :class="statusBadgeClass(selectedLog.status)">
                                    {{ selectedLog.status || t('Unknown') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Metrics -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2.5">{{ t('Token & Cost Metrics') }}</h4>
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-950">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Credits Spent') }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCredits(selectedLog.credits_used) }} {{ t('credits') }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Calculated Cost') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCost(selectedLog.cost_usd) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Total Token Consumption') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(normalizeTokenCount((selectedLog.input_tokens || 0) + (selectedLog.output_tokens || 0))) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ t('Token Details') }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('Input: :count', { count: formatNumber(selectedLog.input_tokens || 0) }) }} / {{ t('Output: :count', { count: formatNumber(selectedLog.output_tokens || 0) }) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Execution Speed -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">{{ t('Response Time') }}</h4>
                        <span class="font-medium text-gray-900 dark:text-white">{{ formatResponseTime(selectedLog.response_time_ms) }}</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">{{ t('Timestamp') }}</h4>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime(selectedLog.created_at) }}</span>
                    </div>
                </div>

                <!-- Metadata/Context -->
                <div v-if="selectedLog.metadata && Object.keys(selectedLog.metadata).length > 0">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">{{ t('Additional Details') }}</h4>
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-950 max-h-48 overflow-y-auto space-y-2.5">
                        <div v-for="(val, key) in selectedLog.metadata" :key="key" class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-gray-100/50 pb-2 last:border-0 last:pb-0 dark:border-surface-800/50">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ formatMetadataKey(key) }}</span>
                            <span class="text-xs text-gray-800 dark:text-gray-200 break-all sm:text-right">
                                {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </AppModal>
    </div>
</template>
