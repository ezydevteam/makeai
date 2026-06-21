<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
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
}>()

const searchInput = ref(props.filters.search || '')
const searchField = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)

const currentProvider = computed(() => props.filters.provider || '')
const currentStatus = computed(() => props.filters.status || '')
const dateFrom = computed(() => props.filters.date_from || '')
const dateTo = computed(() => props.filters.date_to || '')

const hasActiveFilters = computed(() => Boolean(searchInput.value || currentProvider.value || currentStatus.value || dateFrom.value || dateTo.value))

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

const updateFilter = (key: string, value: string) => {
    const params: Record<string, string | undefined> = buildParams()
    params[key] = value || undefined
    
    router.get(route('admin.ai.logs.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const resetFilters = () => {
    searchInput.value = ''
    router.get(route('admin.ai.logs.index'), {}, {
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

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})

</script>

<template>
    <Head :title="t('AI Usage Logs')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('AI Usage Logs') }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor AI token consumption and generations across all tools.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 dark:border-surface-700 dark:bg-surface-900">
                    <i class="ti ti-list-details text-sm text-primary-600 dark:text-primary-400"></i>
                    {{ t(':count log(s)', { count: logs.total || 0 }) }}
                </span>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-surface-800">
                <div class="relative min-w-[240px] flex-1">
                    <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input
                        ref="searchField"
                        v-model="searchInput"
                        type="text"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        :placeholder="t('Search tool, user, model...')"
                        @keydown.enter="applyFilters"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                    />
                    <span
                        v-if="!searchInput && !searchFocused"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                    >
                        /
                    </span>
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                        :aria-label="t('Clear search')"
                        @click="searchInput = ''; applyFilters()"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <AppSelect
                    :model-value="currentProvider"
                    :options="providerOptions"
                    :placeholder="t('All Providers')"
                    class="w-full sm:w-56"
                    @update:model-value="updateFilter('provider', $event)"
                />

                <AppSelect
                    :model-value="currentStatus"
                    :options="statusOptions"
                    :placeholder="t('All Statuses')"
                    class="w-full sm:w-48"
                    @update:model-value="updateFilter('status', $event)"
                />

                <input
                    type="date"
                    :value="dateFrom"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white sm:w-auto"
                    :placeholder="t('From')"
                    @change="updateFilter('date_from', ($event.target as HTMLInputElement).value)"
                />

                <input
                    type="date"
                    :value="dateTo"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white sm:w-auto"
                    :placeholder="t('To')"
                    @change="updateFilter('date_to', ($event.target as HTMLInputElement).value)"
                />

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    @click="resetFilters"
                >
                    {{ t('Clear filters') }}
                </button>
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
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ log.user?.name || t('Guest') }}
                                </div>
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
    </div>
</template>
