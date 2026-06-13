<template>
    <Head :title="t('AI Usage Logs')" />

    <div class="px-4 py-8 sm:px-6">
        <div class="mx-auto w-full space-y-6 sm:max-w-7xl">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('AI Usage Logs') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor AI token consumption and generations across all tools.') }}
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
                                :placeholder="t('Search tool, user, model...')"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                                :aria-label="t('Clear search')"
                                @click="searchQuery = ''"
                            >
                                <i class="ti ti-x text-sm"></i>
                            </button>
                        </div>

                        <AppSelect
                            v-model="selectedProvider"
                            :options="providerOptions"
                            :placeholder="t('All Providers')"
                            class="w-full sm:w-56"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                            <i class="ti ti-list-details text-sm"></i>
                            {{ t(':count log(s)', { count: filteredLogs.length }) }}
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                            <i class="ti ti-binary-tree-2 text-sm"></i>
                            {{ t(':count token(s)', { count: visibleTokenTotal }) }}
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-800 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-6 py-3">{{ t('User') }}</th>
                                <th scope="col" class="px-6 py-3">{{ t('Provider') }}</th>
                                <th scope="col" class="px-6 py-3">{{ t('Tool') }}</th>
                                <th scope="col" class="px-6 py-3">{{ t('Tokens') }}</th>
                                <th scope="col" class="px-6 py-3">{{ t('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in filteredLogs"
                                :key="log.id"
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50/70 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-surface-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ log.user?.name || t('Guest') }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ log.user?.email || '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="providerBadgeClass(log.provider)">
                                        {{ log.provider || t('Unknown') }}
                                    </span>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ log.model || '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                        {{ log.tool_slug || t('Unknown') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    {{ formatNumber(normalizeTokenCount(log.total_tokens)) }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ formatDateTime(log.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="filteredLogs.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="ti ti-database-off mb-3 text-4xl opacity-40"></i>
                                        <p>{{ t('No usage logs found.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.links && logs.links.length > 3 && !searchQuery && !selectedProvider" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                    <Pagination
                        :links="logs.links"
                        :from="logs.from"
                        :to="logs.to"
                        :total="logs.total"
                        :current-page="logs.current_page"
                        :last-page="logs.last_page"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
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
    }
}>()

const searchQuery = ref(props.filters.tool_slug || '')
const selectedProvider = ref(props.filters.provider || '')

const providerOptions = computed(() => {
    const providers = Array.from(
        new Set(
            props.logs.data
                .map((log) => log.provider?.trim().toLowerCase())
                .filter((provider): provider is string => Boolean(provider)),
        ),
    )

    return [
        { value: '', label: t('All Providers') },
        ...providers.map((provider) => ({
            value: provider,
            label: provider.charAt(0).toUpperCase() + provider.slice(1),
        })),
    ]
})

const filteredLogs = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.logs.data.filter((log) => {
        const matchesProvider = !selectedProvider.value || (log.provider?.toLowerCase() ?? '') === selectedProvider.value

        if (!matchesProvider) {
            return false
        }

        if (!query) {
            return true
        }

        const haystack = [
            log.tool_slug ?? '',
            log.provider ?? '',
            log.model ?? '',
            log.user?.name ?? '',
            log.user?.email ?? '',
        ].join(' ').toLowerCase()

        return haystack.includes(query)
    })
})

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

const visibleTokenTotal = computed(() => {
    return formatNumber(
        filteredLogs.value.reduce((sum, log) => sum + normalizeTokenCount(log.total_tokens), 0),
    )
})

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

</script>
