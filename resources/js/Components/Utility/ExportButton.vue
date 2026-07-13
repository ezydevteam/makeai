<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

const props = defineProps<{
    type: string
    filters?: Record<string, any>
}>()

const open = ref(false)
const exporting = ref(false)
const el = ref<HTMLElement | null>(null)

const formats = [
    { value: 'xlsx', label: 'XLSX' },
    { value: 'csv', label: 'CSV' },
    { value: 'pdf', label: 'PDF' },
]

function toggle() { open.value = !open.value }

function handleClickOutside(e: MouseEvent) {
    if (el.value && !el.value.contains(e.target as Node)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

async function doExport(format: string) {
    exporting.value = true
    open.value = false

    try {
        const res = await fetch(route('admin.reports.export'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                type: props.type,
                format,
                date_from: props.filters?.dateFrom ?? null,
                date_to: props.filters?.dateTo ?? null,
                ...props.filters,
            }),
        })

        if (res.headers.get('content-type')?.includes('application/json')) {
            const json = await res.json()
            if (json.queued) {
                alert(json.message)
            }
        } else {
            const blob = await res.blob()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = 'export.' + format
            a.click()
            URL.revokeObjectURL(url)
        }
    } catch {
        alert(t('Export failed'))
    } finally {
        exporting.value = false
    }
}
</script>

<template>
    <div ref="el" class="relative inline-block">
        <button @click="toggle" :disabled="exporting"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ exporting ? t('Exporting...') : t('Export') }}
        </button>
        <div v-if="open"
            class="absolute right-0 top-full mt-1 z-50 w-32 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-1 shadow-lg">
            <button v-for="f in formats" :key="f.value" @click="doExport(f.value)"
                class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-surface-800 transition-colors">
                {{ f.label }}
            </button>
        </div>
    </div>
</template>
