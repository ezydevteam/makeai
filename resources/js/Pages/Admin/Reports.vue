<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
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

interface ExportColumnMeta {
    key: string
    label: string
}

interface ExportTypeMeta {
    value: string
    label: string
    filters?: string[]
    columns?: ExportColumnMeta[]
}

interface ExportPreset {
    id: number
    name: string
    dataset: string
    format: string
    filters: Record<string, string | string[]>
    columns: string[]
}

interface ScheduledExport {
    id: number
    name: string
    dataset: string
    format: string
    frequency: string
    is_active: boolean
    last_run_at: string | null
    next_run_at: string | null
    available: boolean
}

const props = defineProps<{
    recentExports: ExportFile[]
    exportRetentionDays: number
    exportTypes: ExportTypeMeta[]
    isProAvailable: boolean
    plans: { value: string; label: string }[]
    gateways: { value: string; label: string }[]
    providers: { value: string; label: string }[]
    toolSlugs: { value: string; label: string }[]
    presets: ExportPreset[]
    schedules: ScheduledExport[]
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

// Column selection — driven by the dataset's column metadata from the registry.
const selectedColumns = ref<string[]>([])
const selectedTypeMeta = computed<ExportTypeMeta | undefined>(() =>
    props.exportTypes.find((et) => et.value === type.value),
)
const availableColumns = computed<ExportColumnMeta[]>(() => selectedTypeMeta.value?.columns ?? [])

// Default every column on, and re-sync whenever the dataset changes.
watch(type, () => {
    selectedColumns.value = availableColumns.value.map((c) => c.key)
}, { immediate: true })

function toggleColumn(key: string) {
    const idx = selectedColumns.value.indexOf(key)
    if (idx === -1) {
        selectedColumns.value.push(key)
    } else {
        selectedColumns.value.splice(idx, 1)
    }
}
function allColumnsSelected(): boolean {
    return availableColumns.value.length > 0 && selectedColumns.value.length === availableColumns.value.length
}
function toggleAllColumns() {
    selectedColumns.value = allColumnsSelected() ? [] : availableColumns.value.map((c) => c.key)
}

// --- Saved presets ---------------------------------------------------------
const localPresets = ref<ExportPreset[]>([...props.presets])
const presetName = ref('')
const savingPreset = ref(false)
const deletingPresetId = ref<number | null>(null)

function jsonHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
    }
}

function currentFilters(): Record<string, string | string[]> {
    const f: Record<string, string | string[]> = {}
    const from = datePreset.value === 'custom' ? customDateFrom.value : dateFrom.value
    const to = datePreset.value === 'custom' ? customDateTo.value : dateTo.value
    if (from) f.date_from = from
    if (to) f.date_to = to
    if (statusFilter.value) f.status = statusFilter.value
    if (planFilter.value) f.plan_id = planFilter.value
    if (userFilter.value) f.user_id = userFilter.value
    if (providerFilter.value.length) f.provider = providerFilter.value
    if (gatewayFilter.value.length) f.gateway = gatewayFilter.value
    if (toolFilter.value.length) f.tool_slug = toolFilter.value
    return f
}

const retentionDays = ref(props.exportRetentionDays)
const savingRetention = ref(false)
const retentionSaved = ref(false)

async function saveRetention() {
    let days = Math.round(Number(retentionDays.value))
    if (!Number.isFinite(days) || days < 0) days = 0
    if (days > 3650) days = 3650
    retentionDays.value = days
    if (savingRetention.value) return
    savingRetention.value = true
    retentionSaved.value = false
    try {
        const res = await fetch(route('admin.reports.export.retention'), {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ days }),
        })
        if (res.ok) {
            retentionSaved.value = true
            window.setTimeout(() => { retentionSaved.value = false }, 2000)
        }
    } finally {
        savingRetention.value = false
    }
}

async function saveCurrentPreset() {
    if (!presetName.value.trim() || savingPreset.value) return
    savingPreset.value = true
    try {
        const columns = (selectedColumns.value.length && selectedColumns.value.length < availableColumns.value.length)
            ? selectedColumns.value
            : []
        const res = await fetch(route('admin.reports.export.presets.store'), {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ name: presetName.value.trim(), dataset: type.value, format: format.value, filters: currentFilters(), columns }),
        })
        const json = await res.json()
        if (res.ok && json.preset) {
            localPresets.value.unshift(json.preset)
            presetName.value = ''
        }
    } finally {
        savingPreset.value = false
    }
}

async function applyPreset(preset: ExportPreset) {
    // Set the dataset first, then let its change-watchers (reset filters +
    // default columns) run before we lay the preset's values on top.
    type.value = preset.dataset
    await nextTick()
    format.value = preset.format
    const f = preset.filters || {}
    statusFilter.value = (f.status as string) || ''
    planFilter.value = (f.plan_id as string) || ''
    userFilter.value = (f.user_id as string) || ''
    providerFilter.value = Array.isArray(f.provider) ? [...f.provider] : []
    gatewayFilter.value = Array.isArray(f.gateway) ? [...f.gateway] : []
    toolFilter.value = Array.isArray(f.tool_slug) ? [...f.tool_slug] : []
    if (f.date_from || f.date_to) {
        datePreset.value = 'custom'
        customDateFrom.value = (f.date_from as string) || ''
        customDateTo.value = (f.date_to as string) || ''
    }
    if (preset.columns && preset.columns.length) {
        selectedColumns.value = [...preset.columns]
    }
}

async function deletePreset(id: number) {
    if (deletingPresetId.value) return
    deletingPresetId.value = id
    try {
        const res = await fetch(route('admin.reports.export.presets.destroy', { preset: id }), {
            method: 'DELETE',
            headers: jsonHeaders(),
        })
        if (res.ok) {
            localPresets.value = localPresets.value.filter((p) => p.id !== id)
        }
    } finally {
        deletingPresetId.value = null
    }
}

// --- Scheduled exports ------------------------------------------------------
const localSchedules = ref<ScheduledExport[]>([...props.schedules])
const scheduleName = ref('')
const scheduleFrequency = ref('weekly')
const savingSchedule = ref(false)
const togglingScheduleId = ref<number | null>(null)
const deletingScheduleId = ref<number | null>(null)

const frequencyOptions = [
    { value: 'daily', label: t('Daily') },
    { value: 'weekly', label: t('Weekly') },
    { value: 'monthly', label: t('Monthly') },
]

function datasetLabel(key: string): string {
    return props.exportTypes.find((e) => e.value === key)?.label ?? key
}
function frequencyLabel(value: string): string {
    return frequencyOptions.find((f) => f.value === value)?.label ?? value
}
function formatDateTime(iso: string | null): string {
    if (!iso) return '—'
    return new Date(iso).toLocaleString()
}

async function createSchedule() {
    if (!scheduleName.value.trim() || savingSchedule.value) return
    savingSchedule.value = true
    try {
        const columns = (selectedColumns.value.length && selectedColumns.value.length < availableColumns.value.length)
            ? selectedColumns.value
            : []
        const res = await fetch(route('admin.reports.export.schedules.store'), {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({
                name: scheduleName.value.trim(),
                dataset: type.value,
                format: format.value,
                frequency: scheduleFrequency.value,
                filters: currentFilters(),
                columns,
            }),
        })
        const json = await res.json()
        if (res.ok && json.schedule) {
            localSchedules.value.unshift(json.schedule)
            scheduleName.value = ''
        }
    } finally {
        savingSchedule.value = false
    }
}

async function toggleSchedule(schedule: ScheduledExport) {
    if (togglingScheduleId.value) return
    togglingScheduleId.value = schedule.id
    try {
        const res = await fetch(route('admin.reports.export.schedules.toggle', { schedule: schedule.id }), {
            method: 'PATCH',
            headers: jsonHeaders(),
        })
        const json = await res.json()
        if (res.ok && json.schedule) {
            const idx = localSchedules.value.findIndex((s) => s.id === schedule.id)
            if (idx !== -1) localSchedules.value[idx] = json.schedule
        }
    } finally {
        togglingScheduleId.value = null
    }
}

async function deleteSchedule(id: number) {
    if (deletingScheduleId.value) return
    deletingScheduleId.value = id
    try {
        const res = await fetch(route('admin.reports.export.schedules.destroy', { schedule: id }), {
            method: 'DELETE',
            headers: jsonHeaders(),
        })
        if (res.ok) {
            localSchedules.value = localSchedules.value.filter((s) => s.id !== id)
        }
    } finally {
        deletingScheduleId.value = null
    }
}

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
        // Only send a column whitelist when it's a strict subset (empty/all = every column).
        if (selectedColumns.value.length && selectedColumns.value.length < availableColumns.value.length) {
            body.columns = selectedColumns.value
        }

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
    // The backend registry already filters datasets by availability (Revenue,
    // Affiliate, etc.), so the picker mirrors the server exactly.
    return props.exportTypes
})

const pdfLimited = computed(() => format.value === 'pdf')

const revenueStatusOptions = computed(() => [
    { value: '', label: t('All') },
    { value: 'completed', label: t('Completed') },
    { value: 'pending', label: t('Pending') },
    { value: 'refunded', label: t('Refunded') },
])

// Which filter controls a dataset supports, from its registry metadata.
const datasetFilters = computed<string[]>(() => selectedTypeMeta.value?.filters ?? [])
function hasFilter(name: string): boolean {
    return datasetFilters.value.includes(name)
}

// Contextual status options per dataset (the `status` filter means different
// things for each). Falls back to a generic Active/Inactive pair.
const statusOptions = computed(() => {
    const all = { value: '', label: t('All') }
    const map: Record<string, { value: string; label: string }[]> = {
        'users': [all, { value: 'active', label: t('Active') }, { value: 'inactive', label: t('Inactive') }],
        'ai-tools-catalog': [all, { value: 'active', label: t('Active') }, { value: 'inactive', label: t('Inactive') }],
        'revenue': revenueStatusOptions.value,
        'subscriptions': [all, { value: 'active', label: t('Active') }, { value: 'cancelled', label: t('Cancelled') }, { value: 'past_due', label: t('Past due') }],
        'support-tickets': [all, { value: 'open', label: t('Open') }, { value: 'pending', label: t('Pending') }, { value: 'resolved', label: t('Resolved') }, { value: 'closed', label: t('Closed') }],
        'affiliates': [all, { value: 'pending', label: t('Pending') }, { value: 'approved', label: t('Approved') }, { value: 'paid', label: t('Paid') }, { value: 'rejected', label: t('Rejected') }],
        'affiliate-payouts': [all, { value: 'pending', label: t('Pending') }, { value: 'paid', label: t('Paid') }, { value: 'rejected', label: t('Rejected') }],
        'affiliate-referrals': [all, { value: 'converted', label: t('Converted') }, { value: 'pending', label: t('Pending') }],
        'newsletter-subscribers': [all, { value: 'subscribed', label: t('Subscribed') }, { value: 'unsubscribed', label: t('Unsubscribed') }, { value: 'pending', label: t('Pending') }],
        'contact-messages': [all, { value: 'read', label: t('Read') }, { value: 'unread', label: t('Unread') }],
        'login-history': [all, { value: 'success', label: t('Success') }, { value: 'failed', label: t('Failed') }],
    }
    return map[type.value] ?? [all, { value: 'active', label: t('Active') }, { value: 'inactive', label: t('Inactive') }]
})

// Reset filter selections when switching datasets so stale values don't leak.
watch(type, () => {
    statusFilter.value = ''
    planFilter.value = ''
    userFilter.value = ''
    providerFilter.value = []
    gatewayFilter.value = []
    toolFilter.value = []
})

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

// Client-side pagination for Recent Exports. The list is a bounded filesystem listing
// (one admin's export dir, capped by retention) already loaded and filtered in the browser,
// so paging here avoids a server round-trip while keeping long histories manageable.
const EXPORTS_PER_PAGE = 10
const exportPage = ref(1)

const exportTotalPages = computed(() =>
    Math.max(1, Math.ceil(filteredRecentExports.value.length / EXPORTS_PER_PAGE)),
)

const pagedRecentExports = computed(() => {
    const start = (exportPage.value - 1) * EXPORTS_PER_PAGE
    return filteredRecentExports.value.slice(start, start + EXPORTS_PER_PAGE)
})

// Keep the page in range: searching resets to page 1, and deleting the last row on a page
// (or a shrinking list after reload) pulls the cursor back rather than stranding it on an
// empty page.
watch(exportSearch, () => {
    exportPage.value = 1
})

watch(exportTotalPages, (total) => {
    if (exportPage.value > total) {
        exportPage.value = total
    }
})

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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Export Center') }}</h1>
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ t('Build downloadable reports for users, subscriptions, support, and operational data from one admin workspace.') }}</p>
            </div>
            <button
                type="button"
                class="btn-primary-admin shrink-0 inline-flex items-center justify-center gap-2"
                @click="builderOpen = true"
            >
                <i class="ti ti-file-export text-base"></i>
                {{ t('Start Export') }}
            </button>
        </div>

        <AppModal
            :open="builderOpen"
            max-width="max-w-5xl"
            :title="t('Export Builder')"
            :subtitle="t('Choose the dataset, time range, and file format before generating a downloadable report.')"
            @close="builderOpen = false"
        >
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Saved Presets') }}</label>
                    </div>
                    <div v-if="localPresets.length" class="mb-3 flex flex-wrap gap-2">
                        <div
                            v-for="preset in localPresets"
                            :key="preset.id"
                            class="group inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white py-1.5 pl-3 pr-1.5 text-sm text-gray-700 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                        >
                            <button type="button" @click="applyPreset(preset)" class="font-medium">
                                {{ preset.name }}
                            </button>
                            <button
                                type="button"
                                @click="deletePreset(preset.id)"
                                :disabled="deletingPresetId === preset.id"
                                class="rounded-xl p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                :title="t('Delete preset')"
                            >
                                <i class="ti ti-x text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <p v-else class="mb-3 text-xs text-gray-400 dark:text-gray-500">{{ t('No saved presets yet. Configure an export below and save it for one-click reuse.') }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="presetName"
                            type="text"
                            :placeholder="t('Name this preset…')"
                            maxlength="80"
                            class="min-w-0 flex-1 border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                            @keyup.enter="saveCurrentPreset"
                        />
                        <button
                            type="button"
                            @click="saveCurrentPreset"
                            :disabled="!presetName.trim() || savingPreset"
                            class="inline-flex shrink-0 items-center gap-1.5 btn-primary-admin disabled:opacity-60"
                        >
                            <i class="ti ti-bookmark text-sm"></i>
                            {{ savingPreset ? t('Saving…') : t('Save') }}
                        </button>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <label class="mb-3 block text-sm font-semibold text-gray-700 dark:text-gray-400">{{ t('Data Type') }}</label>
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

                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <label class="mb-3 block text-sm font-semibold text-gray-700 dark:text-gray-400">{{ t('Date Range') }}</label>
                    <div class="mb-3 flex flex-wrap gap-2">
                        <button
                            v-for="preset in datePresets"
                            :key="preset.value"
                            type="button"
                            @click="setDatePreset(preset.value)"
                            :class="datePreset === preset.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                            class="rounded-xl border px-3 py-1.5 text-xs font-medium transition-colors"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    <div v-if="datePreset === 'custom'" class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                        <input
                            v-model="customDateFrom"
                            type="date"
                            class="border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                        />
                        <span class="self-center text-center text-sm text-gray-400">{{ t('to') }}</span>
                        <input
                            v-model="customDateTo"
                            type="date"
                            class="border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                        />
                    </div>
                </section>

                <section
                    v-if="hasFilter('status') || (hasFilter('plan_id') && isProAvailable) || hasFilter('provider') || hasFilter('gateway') || hasFilter('tool_slug')"
                    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900"
                >
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-white">{{ t('Filters') }}</h3>
                    <div class="mt-4 space-y-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <AppSelect
                            v-if="hasFilter('status')"
                            v-model="statusFilter"
                            :options="statusOptions"
                            :placeholder="t('All')"
                            :label="t('Status')"
                        />
                        <AppSelect
                            v-if="hasFilter('plan_id') && isProAvailable"
                            v-model="planFilter"
                            :options="planOptions"
                            :placeholder="t('All plans')"
                            :label="t('Plan')"
                        />
                        <AppSelect
                            v-if="hasFilter('provider')"
                            v-model="providerFilter"
                            :options="providerOptions"
                            :placeholder="t('All providers')"
                            :label="t('Provider')"
                            :live-search="true"
                            :multiple="true"
                            :compact-multiple="true"
                        />
                        <AppSelect
                            v-if="hasFilter('tool_slug')"
                            v-model="toolFilter"
                            :options="toolOptions"
                            :placeholder="t('All tools')"
                            :label="t('Tool')"
                            :live-search="true"
                            :multiple="true"
                            :compact-multiple="true"
                        />
                        <AppSelect
                            v-if="hasFilter('gateway')"
                            v-model="gatewayFilter"
                            :options="gatewayOptions"
                            :placeholder="t('All gateways')"
                            :label="t('Gateway')"
                            :live-search="true"
                            :multiple="true"
                            :compact-multiple="true"
                        />
                    </div>
                </section>

                <section v-if="availableColumns.length" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-400">{{ t('Columns') }}</label>
                        <button type="button" @click="toggleAllColumns" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                            {{ allColumnsSelected() ? t('Deselect all') : t('Select all') }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="col in availableColumns"
                            :key="col.key"
                            type="button"
                            @click="toggleColumn(col.key)"
                            :class="selectedColumns.includes(col.key) ? 'border-primary-200 bg-primary-100 text-primary-600 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400'"
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-medium transition-colors"
                        >
                            <i :class="selectedColumns.includes(col.key) ? 'ti ti-check' : 'ti ti-plus'" class="text-[0.7rem]"></i>
                            {{ t(col.label) }}
                        </button>
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                        {{ t(':selected of :total columns selected', { selected: selectedColumns.length || availableColumns.length, total: availableColumns.length }) }}
                    </p>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <label class="mb-3 block text-sm font-semibold text-gray-700 dark:text-gray-400">{{ t('Format') }}</label>
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

                <section v-if="isProAvailable" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <label class="mb-3 block text-sm font-semibold text-gray-700 dark:text-gray-400">{{ t('Recurring Schedule') }}</label>

                    <div v-if="localSchedules.length" class="mb-4 space-y-2">
                        <div
                            v-for="s in localSchedules"
                            :key="s.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 dark:border-surface-800 dark:bg-surface-800/50"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ s.name }}</span>
                                    <span class="rounded bg-gray-200 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase text-gray-600 dark:bg-surface-700 dark:text-gray-300">{{ s.format }}</span>
                                    <span v-if="!s.is_active" class="rounded bg-gray-200 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase text-gray-500 dark:bg-surface-700 dark:text-gray-400">{{ t('Paused') }}</span>
                                    <span v-if="!s.available" class="rounded bg-amber-100 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ t('Unavailable') }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    {{ t(datasetLabel(s.dataset)) }} · {{ frequencyLabel(s.frequency) }} · {{ t('Next') }}: {{ formatDateTime(s.next_run_at) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    @click="toggleSchedule(s)"
                                    :disabled="togglingScheduleId === s.id"
                                    class="rounded-xl border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:border-primary-300 disabled:opacity-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                                >
                                    {{ s.is_active ? t('Pause') : t('Resume') }}
                                </button>
                                <button
                                    type="button"
                                    @click="deleteSchedule(s.id)"
                                    :disabled="deletingScheduleId === s.id"
                                    class="rounded-xl p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 disabled:opacity-50 dark:hover:bg-red-900/20"
                                    :title="t('Delete schedule')"
                                >
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mb-4 text-xs text-gray-400 dark:text-gray-500">{{ t('No schedules yet. Automate the current export below — it runs on a rolling window and lands in Recent Exports.') }}</p>

                    <div class="space-y-3">
                        <input
                            v-model="scheduleName"
                            type="text"
                            :placeholder="t('Schedule name…')"
                            maxlength="80"
                            class="w-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300"
                            @keyup.enter="createSchedule"
                        />
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Frequency') }}:</span>
                                <button
                                    v-for="f in frequencyOptions"
                                    :key="f.value"
                                    type="button"
                                    @click="scheduleFrequency = f.value"
                                    :class="scheduleFrequency === f.value ? 'border-primary-200 bg-primary-100 text-primary-500 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                                    class="border rounded-xl px-3 py-1.5 text-xs font-medium transition-colors"
                                >
                                    {{ f.label }}
                                </button>
                            </div>
                            <button
                                type="button"
                                @click="createSchedule"
                                :disabled="!scheduleName.trim() || savingSchedule"
                                class="inline-flex shrink-0 items-center gap-1.5 btn-primary-admin disabled:opacity-60"
                            >
                                <i class="ti ti-calendar-plus text-sm"></i>
                                {{ savingSchedule ? t('Scheduling…') : t('Schedule') }}
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ t('Recurring exports use a rolling date range (e.g. weekly = last 7 days) and notify you in-app when ready.') }}</p>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <label class="mb-3 block text-sm font-semibold text-gray-700 dark:text-gray-400">{{ t('Export Retention') }}</label>
                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <span>{{ t('Auto-delete export files older than') }}</span>
                        <input
                            v-model.number="retentionDays"
                            type="number"
                            min="0"
                            max="3650"
                            class="w-20 rounded-xl border border-gray-200 bg-white px-3 py-2 text-center text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @change="saveRetention"
                            @keyup.enter="saveRetention"
                        >
                        <span>{{ t('days') }}</span>
                        <i v-if="retentionSaved" class="ti ti-check text-base text-emerald-500"></i>
                    </div>
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ t('Older files in Recent Exports are pruned daily. Set to 0 to keep them indefinitely.') }}</p>
                </section>

                <section v-if="estimateMessage || estimatedRows !== null" class="rounded-2xl border border-primary-100 bg-primary-50 p-5 dark:border-primary-900/40 dark:bg-primary-900/20">
                    <h3 class="text-sm font-semibold text-primary-700 dark:text-primary-100">{{ t('Ready to export') }}</h3>
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

            <template #footer>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                    <button
                        type="button"
                        :disabled="estimating"
                        class="inline-flex w-fit items-center gap-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 disabled:opacity-50"
                        @click="estimateRows"
                    >
                        <svg v-if="estimating" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ estimating ? t('Estimating...') : t('Estimate rows') }}
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="builderOpen = false"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="exporting"
                            class="btn-primary-admin"
                            @click="doExport"
                        >
                            <svg v-if="exporting" class="inline h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ exporting ? t('Exporting...') : t('Start Export') }}
                        </button>
                    </div>
                </div>
            </template>
        </AppModal>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 dark:border-surface-800 md:flex-row md:items-center md:justify-between">
                <h2 class="shrink-0 text-lg font-bold text-gray-900 dark:text-white">{{ t('Recent Exports') }}</h2>
                <div class="w-full max-w-sm">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                            <i class="ti ti-search text-base"></i>
                        </span>
                        <input
                            ref="searchInputRef"
                            v-model="exportSearch"
                            type="text"
                            class="w-full border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
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
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800/80 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 text-left">{{ t('Filename') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Type') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Format') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Size') }}</th>
                            <th class="px-6 py-3 text-center">{{ t('Date') }}</th>
                            <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="file in pagedRecentExports" :key="file.path" class="hover:bg-primary-50/40 dark:hover:bg-primary-900/10">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ file.filename }}</div>
                            </td>
                            <td class="px-6 py-4 text-center capitalize text-gray-500 dark:text-gray-400">{{ t(file.type) }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ file.format }}</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">{{ formatSize(file.size) }}</td>
                            <td class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">{{ timeAgo(file.modified) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Tooltip :content="t('Download export')" placement="top">
                                        <a
                                            :href="route('admin.reports.export.download', { file: file.filename })"
                                            target="_blank"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-primary-200 bg-primary-50 text-primary-700 transition-colors hover:border-primary-300 hover:bg-primary-100 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-300"
                                        >
                                            <i class="ti ti-download text-base"></i>
                                        </a>
                                    </Tooltip>
                                    <Tooltip :content="t('Delete export')" placement="top">
                                        <button
                                            type="button"
                                            :disabled="deletingPath === file.path"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-200 bg-red-50 text-red-600 transition-colors hover:border-red-300 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300"
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

                <!-- Pager: only shown once the history spills past a single page. -->
                <div
                    v-if="exportTotalPages > 1"
                    class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-3 dark:border-surface-800 sm:flex-row"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Showing :from–:to of :total', {
                            from: (exportPage - 1) * EXPORTS_PER_PAGE + 1,
                            to: Math.min(exportPage * EXPORTS_PER_PAGE, filteredRecentExports.length),
                            total: filteredRecentExports.length,
                        }) }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="exportPage <= 1"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="exportPage--"
                        >
                            <i class="ti ti-chevron-left text-sm"></i>
                            {{ t('Previous') }}
                        </button>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ t('Page :page of :total', { page: exportPage, total: exportTotalPages }) }}
                        </span>
                        <button
                            type="button"
                            :disabled="exportPage >= exportTotalPages"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="exportPage++"
                        >
                            {{ t('Next') }}
                            <i class="ti ti-chevron-right text-sm"></i>
                        </button>
                    </div>
                </div>
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
