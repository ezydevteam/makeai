<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()

defineProps<{
    campaign: {
        id: number
        message: string
        action_url: string | null
        action_label: string | null
        status: string
        recipient_count: number
        sent_count: number
        failed_count: number
        preview: { body: string; length: number; segments: number; encoding: string }
        created_at: string | null
        finished_at: string | null
    }
    recipients: Array<{
        id: number
        name: string
        email: string | null
        phone: string
        status: string
        error_message: string | null
        sent_at: string | null
    }>
}>()

const statusClass = (status: string) => {
    switch (status) {
        case 'sent': return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
        case 'failed': return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
        default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/20 dark:text-gray-400'
    }
}
</script>

<template>
    <Head :title="t('SMS Campaign')" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('SMS Campaign') }} #{{ campaign.id }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ campaign.created_at ? formatDateTime(campaign.created_at) : '' }}
                </p>
            </div>
            <Link :href="route('admin.bulk-sms.index')" class="shrink-0 inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                <i class="ti ti-arrow-left"></i>
                {{ t('Back') }}
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <p class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500">{{ t('Recipients') }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ campaign.recipient_count }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <p class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500">{{ t('Sent') }}</p>
                <p class="mt-1 text-xl font-bold text-green-600 dark:text-green-400">{{ campaign.sent_count }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <p class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500">{{ t('Failed') }}</p>
                <p class="mt-1 text-xl font-bold" :class="campaign.failed_count ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ campaign.failed_count }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <p class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500">{{ t('Segments each') }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ campaign.preview.segments }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Message sent') }}</p>
            <pre class="mt-2 whitespace-pre-wrap break-words font-sans text-sm text-gray-900 dark:text-white">{{ campaign.preview.body }}</pre>
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                {{ campaign.preview.length }} {{ t('characters') }} · {{ campaign.preview.encoding }}
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-surface-700 dark:bg-surface-900">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-3 dark:border-surface-800 dark:bg-surface-800/50">
                <h2 class="font-bold text-gray-900 dark:text-white">{{ t('Delivery log') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-surface-800 dark:bg-surface-800/50">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Phone') }}</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Detail') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="r in recipients" :key="r.id" class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40">
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-900 dark:text-white">{{ r.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ r.email }}</p>
                            </td>
                            <td class="px-6 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ r.phone }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClass(r.status)">{{ r.status }}</span>
                            </td>
                            <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="r.error_message" class="text-red-600 dark:text-red-400">{{ r.error_message }}</span>
                                <span v-else-if="r.sent_at">{{ formatDateTime(r.sent_at) }}</span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
