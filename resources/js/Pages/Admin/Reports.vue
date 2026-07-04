<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

interface ExportFile {
    path: string
    filename: string
    size: number
    modified: number
    type: string
    format: string
}

const props = defineProps<{
    recentExports: ExportFile[]
    exportTypes: { value: string; label: string }[]
    isProAvailable: boolean
    plans: { value: string; label: string }[]
    gateways: { value: string; label: string }[]
    providers: { value: string; label: string }[]
    toolSlugs: { value: string; label: string }[]
}>()

const type = ref('users')
const format = ref('xlsx')
const datePreset = ref('30d')
const dateFrom = ref('')
const dateTo = ref('')
const customDateFrom = ref('')
const customDateTo = ref('')
const exporting = ref(false)
const builderOpen = ref(false)
const exportMessage = ref('')
const deletingPath = ref<string | null>(null)
const deleteTarget = ref<ExportFile | null>(null)
const exportSearch = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)

const statusFilter = ref('')
const planFilter = ref('')
const userFilter = ref('')
const providerFilter = ref<string[]>([])
const gatewayFilter = ref<string[]>([])
const toolFilter = ref<string[]>([])
const estimatedRows = ref<number | null>(null)
const estimateMessage = ref('')
const estimateMessageTone = ref<'success' | 'error' | ''>('')
const estimating = ref(false)

const formats = [
    { value: 'xlsx', label: 'XLSX' },
    { value: 'csv', label: 'CSV' },
    { value: 'pdf', label: 'PDF' },
]

const datePresets = [
    { value: '7d', label: t('Last 7 days') },
    { value: '30d', label: t('Last 30 days') },
    { value: 'month', label: t('This month') },
    { value: 'custom', label: t('Custom') },
]

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
}

function buildExportFilename(): string {
    let dateLabel = ''

    if (datePreset.value === '7d') {
        dateLabel = 'last-7-days'
    } else if (datePreset.value === '30d') {
        dateLabel = 'last-30-days'
    } else if (datePreset.value === 'month') {
        dateLabel = 'this-month'
    } else {
        const from = customDateFrom.value || 'custom'
        const to = customDateTo.value || 'range'
        dateLabel = `${from}-to-${to}`
    }

    return `${slugify(type.value)}-${slugify(dateLabel)}.${format.value}`
}

function setDatePreset(value: string) {
    datePreset.value = value
    const now = new Date()
    if (value === '7d') {
        dateFrom.value = daysAgo(6)
        dateTo.value = today()
    } else if (value === '30d') {
        dateFrom.value = daysAgo(29)
        dateTo.value = today()
    } else if (value === 'month') {
        dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
        dateTo.value = today()
    }
}

function today() { return new Date().toISOString().split('T')[0] }
function daysAgo(n: number) { return new Date(Date.now() - n * 86400000).toISOString().split('T')[0] }

setDatePreset('30d')

function clearExportSearch() {
    exportSearch.value = ''
}

async function doExport() {
    exporting.value = true
    exportMessage.value = ''

    const from = datePreset.value === 'custom' ? customDateFrom.value : dateFrom.value
    const to = datePreset.value === 'custom' ? customDateTo.value : dateTo.value

    try {
        const body: Record<string, string | string[]> = {
            type: type.value,
            format: format.value,
            date_from: from,
            date_to: to,
        }
        if (statusFilter.value) body.status = statusFilter.value
        if (planFilter.value) body.plan_id = planFilter.value
        if (userFilter.value) body.user_id = userFilter.value
        if (providerFilter.value.length) body.provider = providerFilter.value
        if (gatewayFilter.value.length) body.gateway = gatewayFilter.value
        if (toolFilter.value.length) body.tool_slug = toolFilter.value

        const res = await fetch(route('admin.reports.export'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(body),
        })

        if (res.headers.get('content-type')?.includes('application/json')) {
            const json = await res.json()
            if (json.queued) {
                exportMessage.value = json.message
            } else {
                exportMessage.value = json.message || t('Export failed')
            }
        } else {
            const blob = await res.blob()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = buildExportFilename()
            a.click()
            URL.revokeObjectURL(url)
            builderOpen.value = false
            // Refresh via Inertia instead of full page reload
            router.reload({ only: ['recentExports'] })
        }
    } catch {
        exportMessage.value = t('Export failed. Please try again.')
    } finally {
        exporting.value = false
    }
}

async function estimateRows() {
    estimating.value = true
    estimatedRows.value = null
    estimateMessage.value = ''
    estimateMessageTone.value = ''

    const from = datePreset.value === 'custom' ? customDateFrom.value : dateFrom.value
    const to = datePreset.value === 'custom' ? customDateTo.value : dateTo.value

    try {
        const body: Record<string, string | string[]> = {
            type: type.value,
            date_from: from,
            date_to: to,
        }
        if (statusFilter.value) body.status = statusFilter.value
        if (planFilter.value) body.plan_id = planFilter.value
        if (providerFilter.value.length) body.provider = providerFilter.value
        if (gatewayFilter.value.length) body.gateway = gatewayFilter.value
        if (toolFilter.value.length) body.tool_slug = toolFilter.value

        const res = await fetch(route('admin.reports.export.estimate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(body),
        })

        const json = await res.json()

        if (!res.ok) {
            estimateMessage.value = json.message || t('Unable to estimate rows. Please review the filters and try again.')
            estimateMessageTone.value = 'error'
            return
        }

        const count = typeof json.count === 'number' ? json.count : 0

        estimatedRows.value = count
        estimateMessage.value = count > 0
            ? t('Estimated :count rows for the current filters.', { count: count.toLocaleString() })
            : t('No rows match the current filters.')
        estimateMessageTone.value = 'success'
    } catch {
        estimatedRows.value = null
        estimateMessage.value = t('Unable to estimate rows. Please try again.')
        estimateMessageTone.value = 'error'
    } finally {
        estimating.value = false
    }
}

async function deleteFile() {
    if (!deleteTarget.value) {
        return
    }

    deletingPath.value = deleteTarget.value.path
    try {
        const res = await fetch(route('admin.reports.export.delete', { file: deleteTarget.value.filename }), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        })
        if (res.ok) {
            router.reload()
        }
    } finally {
        deletingPath.value = null
        deleteTarget.value = null
    }
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
}

function timeAgo(ts: number): string {
    const diff = Math.floor((Date.now() - ts * 1000) / 1000)
    if (diff < 60) return t('just now')
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
    return Math.floor(diff / 86400) + 'd ago'
}

const exportTypes = computed(() => {
    const types = [...props.exportTypes]
    if (props.isProAvailable) {
        types.push({ value: 'revenue', label: 'Revenue' })
    }
    return types
})

const pdfLimited = computed(() => format.value === 'pdf')

const userStatusOptions = computed(() => [
    { value: '', label: t('All') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
])

const revenueStatusOptions = computed(() => [
    { value: '', label: t('All') },
    { value: 'completed', label: t('Completed') },
    { value: 'pending', label: t('Pending') },
    { value: 'refunded', label: t('Refunded') },
])

const planOptions = computed(() => [
    { value: '', label: t('All plans') },
    ...props.plans,
])

const gatewayOptions = computed(() => props.gateways)

const providerOptions = computed(() => props.providers)

const toolOptions = computed(() => props.toolSlugs)

const filteredRecentExports = computed(() => {
    const query = exportSearch.value.trim().toLowerCase()

    if (!query) {
        return props.recentExports
    }

    return props.recentExports.filter((file) => {
        return [
            file.filename,
            file.type,
            file.format,
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const hasActiveExportSearch = computed(() => exportSearch.value.trim().length > 0)

const focusSearchOnSlash = (event: KeyboardEvent) => {
    if (event.key !== '/' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    if (builderOpen.value || deleteTarget.value) {
        return
    }

    const target = event.target as HTMLElement | null
    const tagName = target?.tagName ?? ''
    const isTypingContext = Boolean(target?.isContentEditable) || ['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)

    if (isTypingContext) {
        return
    }

    event.preventDefault()
    searchInputRef.value?.focus()
    searchInputRef.value?.select()
}

const clearSearchOnEscape = (event: KeyboardEvent) => {
    if (event.key !== 'Escape' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    if (builderOpen.value || deleteTarget.value || !hasActiveExportSearch.value) {
        return
    }

    event.preventDefault()
    clearExportSearch()
}

onMounted(() => {
    document.addEventListener('keydown', focusSearchOnSlash)
    document.addEventListener('keydown', clearSearchOnEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', focusSearchOnSlash)
    document.removeEventListener('keydown', clearSearchOnEscape)
})
</script>

<template>
    <Head :title="t('Export Center')" />

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Export Center') }}</h1>
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ t('Build downloadable reports for users, subscriptions, support, and operational data from one admin workspace.') }}</p>
            </div>
            <button
                type="button"
                class="btn-primary inline-flex items-center justify-center gap-2 self-start rounded-xl px-4 py-2 text-sm font-medium"
                @click="builderOpen = true"
            >
                <i class="ti ti-file-export text-base"></i>
                {{ t('Start Export') }}
            </button>
        </div>

        <div
            v-if="builderOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 px-4 py-6 backdrop-blur-sm"
            @click.self="builderOpen = false"
        >
            <section class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Export Builder') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Choose the dataset, time range, and file format before generating a downloadable report.') }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                        :aria-label="t('Close modal')"
                        @click="builderOpen = false"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="space-y-6 overflow-y-auto p-6">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <label class="mb-3 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Data Type') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="exportType in exportTypes"
                                :key="exportType.value"
                                type="button"
                                @click="type = exportType.value"
                                :class="type === exportType.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                                class="rounded-xl border px-4 py-2 text-sm font-medium transition-colors"
                            >
                                {{ t(exportType.label) }}
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <label class="mb-3 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Date Range') }}</label>
                        <div class="mb-3 flex flex-wrap gap-2">
                            <button
                                v-for="preset in datePresets"
                                :key="preset.value"
                                type="button"
                                @click="setDatePreset(preset.value)"
                                :class="datePreset === preset.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                        <div v-if="datePreset === 'custom'" class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                            <input
                                v-model="customDateFrom"
                                type="date"
                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                            />
                            <span class="self-center text-center text-sm text-gray-400">{{ t('to') }}</span>
                            <input
                                v-model="customDateTo"
                                type="date"
                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                            />
                        </div>
                    </section>

                    <section
                        v-if="type === 'ai-usage' || type === 'revenue' || type === 'users'"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                    >
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Filters') }}</h3>
                        <div class="mt-4 space-y-3">
                            <template v-if="type === 'ai-usage'">
                                <AppSelect
                                    v-model="providerFilter"
                                    :options="providerOptions"
                                    :placeholder="t('All providers')"
                                    :label="t('Provider')"
                                    :live-search="true"
                                    :multiple="true"
                                    :compact-multiple="true"
                                />
                                <AppSelect
                                    v-model="toolFilter"
                                    :options="toolOptions"
                                    :placeholder="t('All tools')"
                                    :label="t('Tool')"
                                    :live-search="true"
                                    :multiple="true"
                                    :compact-multiple="true"
                                />
                            </template>

                            <template v-if="type === 'revenue'">
                                <AppSelect
                                    v-model="statusFilter"
                                    :options="revenueStatusOptions"
                                    :placeholder="t('All')"
                                    :label="t('Status')"
                                />
                                <AppSelect
                                    v-model="gatewayFilter"
                                    :options="gatewayOptions"
                                    :placeholder="t('All gateways')"
                                    :label="t('Gateway')"
                                    :live-search="true"
                                    :multiple="true"
                                    :compact-multiple="true"
                                />
                            </template>

                            <template v-if="type === 'users'">
                                <AppSelect
                                    v-model="statusFilter"
                                    :options="userStatusOptions"
                                    :placeholder="t('All')"
                                    :label="t('Status')"
                                />
                                <AppSelect
                                    v-model="planFilter"
                                    :options="planOptions"
                                    :placeholder="t('All plans')"
                                    :label="t('Plan')"
                                />
                            </template>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <label class="mb-3 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Format') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="fileFormat in formats"
                                :key="fileFormat.value"
                                type="button"
                                @click="format = fileFormat.value"
                                :class="format === fileFormat.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                                class="rounded-xl border px-4 py-2 text-sm font-medium transition-colors"
                            >
                                {{ fileFormat.label }}
                            </button>
                        </div>
                        <p v-if="pdfLimited" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                            {{ t('PDF limited to 5,000 rows. Use XLSX/CSV for full data.') }}
                        </p>
                    </section>

                    <section class="rounded-xl border border-primary-100 bg-primary-50 p-5 dark:border-primary-900/40 dark:bg-primary-900/20">
                        <h3 class="text-sm font-semibold text-primary-900 dark:text-primary-100">{{ t('Ready to export') }}</h3>
                        <p v-if="exportMessage" class="mt-1 text-sm text-primary-700 dark:text-primary-200">{{ exportMessage }}</p>
                        <p v-else class="mt-1 text-sm text-primary-700 dark:text-primary-200">{{ t('Generate a fresh export file using the selected filters.') }}</p>
                        <p
                            v-if="estimateMessage"
                            :class="estimateMessageTone === 'error' ? 'text-red-600 dark:text-red-400' : 'text-primary-700 dark:text-primary-200'"
                            class="mt-2 text-xs font-medium"
                        >
                            {{ estimateMessage }}
                        </p>
                        <p v-if="estimatedRows !== null" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            ~{{ estimatedRows.toLocaleString() }} {{ t('rows match current filters') }}
                        </p>
                    </section>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="builderOpen = false"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="estimating"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 disabled:opacity-50"
                            @click="estimateRows"
                        >
                            <svg v-if="estimating" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ estimating ? t('Estimating...') : t('Estimate rows') }}
                        </button>
                        <button
                            type="button"
                            :disabled="exporting"
                            class="btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                            @click="doExport"
                        >
                            <svg v-if="exporting" class="mr-2 inline h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ exporting ? t('Exporting...') : t('Start Export') }}
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 dark:border-surface-800 lg:flex-row lg:items-center lg:justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Recent Exports') }}</h2>
                <div class="w-full lg:max-w-sm">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                            <i class="ti ti-search text-base"></i>
                        </span>
                        <input
                            ref="searchInputRef"
                            v-model="exportSearch"
                            type="text"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            :placeholder="t('Search exports...')"
                        >
                        <span
                            v-if="!exportSearch"
                            class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                        >
                            /
                        </span>
                        <button
                            v-if="exportSearch"
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            :aria-label="t('Clear search')"
                            :title="t('Clear search')"
                            @click="clearExportSearch"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="filteredRecentExports.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800/80">
                        <tr>
                            <th class="px-6 py-3">{{ t('Filename') }}</th>
                            <th class="px-6 py-3">{{ t('Type') }}</th>
                            <th class="px-6 py-3">{{ t('Format') }}</th>
                            <th class="px-6 py-3">{{ t('Size') }}</th>
                            <th class="px-6 py-3">{{ t('Date') }}</th>
                            <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="file in filteredRecentExports" :key="file.path" class="hover:bg-primary-50/40 dark:hover:bg-primary-900/10">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ file.filename }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ t(file.type) }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold uppercase text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ file.format }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ formatSize(file.size) }}</td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ timeAgo(file.modified) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Tooltip :content="t('Download export')" placement="top">
                                        <a
                                            :href="route('admin.reports.export.download', { file: file.filename })"
                                            target="_blank"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-primary-200 bg-primary-50 text-primary-700 transition-colors hover:border-primary-300 hover:bg-primary-100 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300"
                                        >
                                            <i class="ti ti-download text-base"></i>
                                        </a>
                                    </Tooltip>
                                    <Tooltip :content="t('Delete export')" placement="top">
                                        <button
                                            type="button"
                                            :disabled="deletingPath === file.path"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 transition-colors hover:border-red-300 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300"
                                            @click="deleteTarget = file"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </Tooltip>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-6 py-14 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                    <i class="ti ti-file-export text-2xl"></i>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">{{ exportSearch ? t('No matching exports') : t('No recent exports') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ exportSearch ? t('Try a different search term.') : t('Open the export builder to generate your first report file.') }}
                </p>
            </div>
        </section>
    </div>


    <ActionConfirmModal
        :open="Boolean(deleteTarget)"
        :title="t('Delete export file?')"
        :message="t('Remove this export file from the server? This action cannot be undone.')"
        :confirm-label="t('Delete File')"
        :cancel-label="t('Cancel')"
        :processing="Boolean(deletingPath)"
        :processing-label="t('Deleting...')"
        variant="danger"
        @cancel="deleteTarget = null"
        @confirm="deleteFile"
    />
</template>
