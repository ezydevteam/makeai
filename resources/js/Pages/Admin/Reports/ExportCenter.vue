<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const page = usePage()

interface ExportFile {
    path: string
    filename: string
    size: number
    modified: number
}

const props = defineProps<{
    recentExports: ExportFile[]
    exportTypes: { value: string; label: string }[]
    isProAvailable: boolean
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
        const res = await fetch(route('admin.reports.export'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({ type: type.value, format: format.value, date_from: from, date_to: to }),
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
            a.download = 'export.' + format.value
            a.click()
            URL.revokeObjectURL(url)
            window.location.reload()
        }
    } catch {
        exportMessage.value = t('Export failed. Please try again.')
    } finally {
        exporting.value = false
    }
}

async function deleteFile(path: string) {
    deletingPath.value = path
    try {
        const res = await fetch(route('admin.reports.export.delete', { file: path.split('/').pop() }), {
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
</script>

<template>
    <Head :title="t('Export Center')" />

    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ t('Export Center') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">{{ t('Export data as Excel, CSV, or PDF reports.') }}</p>

        <!-- Export Builder -->
        <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-6 shadow-sm mb-8">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-5">{{ t('Export Builder') }}</h2>

            <div class="space-y-5">
                <!-- Data Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ t('Data Type') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="t in exportTypes" :key="t.value" @click="type = t.value"
                            :class="type === t.value ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-surface-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-surface-700 hover:border-primary-300'"
                            class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors">
                            {{ t.label }}
                        </button>
                    </div>
                </div>

                <!-- Date Range -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ t('Date Range') }}</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button v-for="p in datePresets" :key="p.value" @click="setDatePreset(p.value)"
                            :class="datePreset === p.value ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-surface-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-surface-700 hover:border-primary-300'"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors">
                            {{ p.label }}
                        </button>
                    </div>
                    <div v-if="datePreset === 'custom'" class="flex gap-3">
                        <input v-model="customDateFrom" type="date"
                            class="flex-1 rounded-lg border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-300" />
                        <span class="self-center text-gray-400 text-sm">{{ t('to') }}</span>
                        <input v-model="customDateTo" type="date"
                            class="flex-1 rounded-lg border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-300" />
                    </div>
                </div>

                <!-- Format -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ t('Format') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="f in formats" :key="f.value" @click="format = f.value"
                            :class="format === f.value ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-surface-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-surface-700 hover:border-primary-300'"
                            class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors">
                            {{ f.label }}
                        </button>
                    </div>
                    <p v-if="pdfLimited" class="text-xs text-amber-600 dark:text-amber-400 mt-2">
                        {{ t('PDF limited to 5,000 rows. Use XLSX/CSV for full data.') }}
                    </p>
                </div>

                <!-- Export Button -->
                <div class="flex items-center gap-4">
                    <button @click="doExport" :disabled="exporting"
                        class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2">
                        <svg v-if="exporting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ exporting ? t('Exporting...') : t('Export Now') }}
                    </button>
                    <p v-if="exportMessage" class="text-sm text-green-600 dark:text-green-400">{{ exportMessage }}</p>
                </div>
            </div>
        </div>

        <!-- Recent Exports -->
        <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">{{ t('Recent Exports') }}</h2>
            <div v-if="recentExports.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-100 dark:border-surface-800">
                            <th class="pb-3 font-semibold">{{ t('Filename') }}</th>
                            <th class="pb-3 font-semibold">{{ t('Size') }}</th>
                            <th class="pb-3 font-semibold">{{ t('Date') }}</th>
                            <th class="pb-3 font-semibold text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr v-for="file in recentExports" :key="file.path" class="hover:bg-gray-50 dark:hover:bg-surface-800">
                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white truncate max-w-xs">{{ file.filename }}</td>
                            <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">{{ formatSize(file.size) }}</td>
                            <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">{{ timeAgo(file.modified) }}</td>
                            <td class="py-3 text-right whitespace-nowrap">
                                <a :href="route('admin.reports.export.download', { file: file.filename })"
                                    class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-500 text-xs font-medium mr-3"
                                    target="_blank">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    {{ t('Download') }}
                                </a>
                                <button @click="deleteFile(file.path)" :disabled="deletingPath === file.path"
                                    class="text-red-500 hover:text-red-600 text-xs font-medium disabled:opacity-50">
                                    {{ t('Delete') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                <p>{{ t('No recent exports. Use the Export Builder above to create one.') }}</p>
            </div>
        </div>
    </div>
</template>
