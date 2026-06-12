<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
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
const exportMessage = ref('')
const deletingPath = ref<string | null>(null)
const deleteTarget = ref<ExportFile | null>(null)

const statusFilter = ref('')
const planFilter = ref('')
const userFilter = ref('')
const providerFilter = ref<string[]>([])
const gatewayFilter = ref<string[]>([])
const toolFilter = ref<string[]>([])
const estimatedRows = ref<number | null>(null)
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
        if (toolFilter.value) body.tool_slug = toolFilter.value

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
        estimatedRows.value = json.count
    } catch {
        estimatedRows.value = null
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
</script>

<template>
    <Head :title="t('Export Center')" />

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <h1 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Export Center') }}</h1>
        <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ t('Build downloadable reports for users, subscriptions, support, and operational data from one admin workspace.') }}</p>
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900 xl:col-span-2">
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Export Builder') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose the dataset, time range, and file format before generating a downloadable report.') }}</p>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Data Type') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="exportType in exportTypes"
                                :key="exportType.value"
                                type="button"
                                @click="type = exportType.value"
                                :class="type === exportType.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'bg-white text-gray-700 border-gray-200 hover:border-primary-300 dark:bg-surface-800 dark:border-surface-700 dark:text-gray-300'"
                                class="rounded-xl border px-4 py-2 text-sm font-medium transition-colors"
                            >
                                {{ t(exportType.label) }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Date Range') }}</label>
                        <div class="mb-3 flex flex-wrap gap-2">
                            <button
                                v-for="preset in datePresets"
                                :key="preset.value"
                                type="button"
                                @click="setDatePreset(preset.value)"
                                :class="datePreset === preset.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'bg-white text-gray-700 border-gray-200 hover:border-primary-300 dark:bg-surface-800 dark:border-surface-700 dark:text-gray-300'"
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
                    </div>

                    <div v-if="type === 'ai-usage'" class="mt-4 space-y-3 border-t border-gray-100 pt-4 dark:border-surface-800">
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
                    </div>

                    <div v-if="type === 'revenue'" class="mt-4 space-y-3 border-t border-gray-100 pt-4 dark:border-surface-800">
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
                    </div>

                    <div v-if="type === 'users'" class="mt-4 space-y-3 border-t border-gray-100 pt-4 dark:border-surface-800">
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
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Format') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="fileFormat in formats"
                                :key="fileFormat.value"
                                type="button"
                                @click="format = fileFormat.value"
                                :class="format === fileFormat.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'bg-white text-gray-700 border-gray-200 hover:border-primary-300 dark:bg-surface-800 dark:border-surface-700 dark:text-gray-300'"
                                class="rounded-xl border px-4 py-2 text-sm font-medium transition-colors"
                            >
                                {{ fileFormat.label }}
                            </button>
                        </div>
                        <p v-if="pdfLimited" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                            {{ t('PDF limited to 5,000 rows. Use XLSX/CSV for full data.') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-primary-100 bg-primary-50 p-4 dark:border-primary-900/40 dark:bg-primary-900/20">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-primary-900 dark:text-primary-100">{{ t('Ready to export') }}</h3>
                                <p v-if="exportMessage" class="mt-1 text-sm text-primary-700 dark:text-primary-200">{{ exportMessage }}</p>
                                <p v-else class="mt-1 text-sm text-primary-700 dark:text-primary-200">{{ t('Generate a fresh export file using the selected filters.') }}</p>
                                <p v-if="estimatedRows !== null" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    ~{{ estimatedRows.toLocaleString() }} {{ t('rows match current filters') }}
                                </p>
                            </div>
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
                                    class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary shadow-lg shadow-primary-500/20 transition-all disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="doExport"
                                >
                                    <svg v-if="exporting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    {{ exporting ? t('Exporting...') : t('Start Export') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Export Notes') }}</h2>
                    <div class="mt-4 space-y-4 text-sm text-gray-500 dark:text-gray-400">
                        <p>{{ t('Use XLSX or CSV for the largest exports and PDF for summary-ready files.') }}</p>
                        <p>{{ t('Custom date filters help keep support and transactional exports more focused.') }}</p>
                        <p>{{ t('Recent exports stay available below until they are manually deleted.') }}</p>
                    </div>
                </section>
                <section class="rounded-xl border border-violet-200 bg-violet-50 p-6 shadow-sm dark:border-violet-900/40 dark:bg-violet-900/20">
                    <h2 class="text-lg font-bold text-violet-900 dark:text-violet-100">{{ t('Pro Data') }}</h2>
                    <p class="mt-2 text-sm text-violet-700 dark:text-violet-200">{{ props.isProAvailable ? t('Revenue exports are available for this installation.') : t('Revenue exports stay hidden unless Pro subscriptions are enabled.') }}</p>
                </section>
            </aside>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-surface-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Recent Exports') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Download or remove previously generated export files.') }}</p>
            </div>
            <div v-if="recentExports.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-surface-800 dark:bg-surface-950/40 dark:text-gray-400">
                            <th class="px-6 py-3">{{ t('Filename') }}</th>
                            <th class="px-6 py-3">{{ t('Type') }}</th>
                            <th class="px-6 py-3">{{ t('Format') }}</th>
                            <th class="px-6 py-3">{{ t('Size') }}</th>
                            <th class="px-6 py-3">{{ t('Date') }}</th>
                            <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="file in recentExports" :key="file.path" class="hover:bg-primary-50/40 dark:hover:bg-primary-900/10">
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
                <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('No recent exports') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use the export builder above to generate your first report file.') }}</p>
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
