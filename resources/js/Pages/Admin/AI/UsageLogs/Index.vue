<template>
    <Head :title="t('AI Usage Logs')" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('AI Usage Logs') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor AI token consumption and generations across all tools.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-gray-500">
                        {{ t('Visible Logs') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ filteredLogs.length }}
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-gray-500">
                        {{ t('Visible Tokens') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ visibleTokenTotal }}
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-gray-500">
                        {{ t('Providers') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ providerOptions.length - 1 }}
                    </div>
                </div>
            </div>

            <div class="mb-4 flex items-center justify-between gap-4">
                <div class="relative w-64">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-9 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :placeholder="t('Search tool, user, model...')"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        :aria-label="t('Clear search')"
                        :title="t('Clear search')"
                        @click="searchQuery = ''"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <AppSelect
                            v-model="selectedProvider"
                            :options="providerOptions"
                            :placeholder="t('All Providers')"
                        />
                    </div>

                    <div v-if="searchQuery || selectedProvider" class="flex items-center justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="resetFilters"
                        >
                            {{ t('Reset Filters') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
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
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
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
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {{ t('No usage logs found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="logs.links && logs.links.length > 3">
                <Pagination :links="logs.links" />
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

const resetFilters = () => {
    searchQuery.value = ''
    selectedProvider.value = ''
}
</script>
