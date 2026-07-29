<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { asZonedDisplayDate, siteTimeZone } from '@/lib/timezone'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

interface Batch {
    id: number
    type: string
    label: string
    target_label: string | null
    requested_count: number
    inserted_count: number
    status: string
    error: string | null
    tokens: number
    created_by: string | null
    created_at: string | null
}

interface PageLink {
    url: string | null
    label: string
    active: boolean
}

defineProps<{
    batches: {
        data: Batch[]
        links: PageLink[]
        // The paginator's own counters, so the control can show "Showing 1 to 20 of 47"
        // rather than page numbers with no sense of scale.
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number
        last_page?: number
    }
}>()

const deletingId = ref<number | null>(null)
const deletingBatch = ref<Batch | null>(null)

const statusTone: Record<string, string> = {
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
}

const destroy = (batch: Batch) => {
    deletingBatch.value = batch
}

const confirmDelete = () => {
    if (!deletingBatch.value) return

    const id = deletingBatch.value.id
    deletingId.value = id
    router.delete(route('addon.faker-ai.admin.batches.destroy', { batch: id }), {
        preserveScroll: true,
        onSuccess: () => {
            deletingBatch.value = null
        },
        onFinish: () => {
            deletingId.value = null
        },
    })
}

const formatBatchDate = (dateStr: string | null) => {
    if (!dateStr) return '—'
    const cleanStr = dateStr.includes(' ') ? dateStr.replace(' ', 'T') + 'Z' : dateStr
    const parsedDate = new Date(cleanStr)
    if (isNaN(parsedDate.getTime())) return '—'
    const date = asZonedDisplayDate(parsedDate, siteTimeZone())
    const day = date.getDate()
    const month = date.toLocaleString('en-US', { month: 'short' })
    const year = date.getFullYear()
    let hours = date.getHours()
    const minutes = String(date.getMinutes()).padStart(2, '0')
    const ampm = hours >= 12 ? 'pm' : 'am'
    hours = hours % 12
    hours = hours ? hours : 12
    return `${day} ${month} ${year} ${hours}:${minutes}${ampm}`
}
</script>

<template>
    <Head title="FakerAI History" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('FakerAI History') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Every generation run. Deleting a batch removes exactly the data it created.') }}
                </p>
            </div>
            <Link
                :href="route('addon.faker-ai.admin.index')"
                class="inline-flex shrink-0 items-center justify-center gap-1 rounded-xl border border-primary-100 px-3 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:border-primary-900/30 dark:bg-primary-900/30 dark:text-primary-500 dark:hover:bg-primary-900/40"
            >
                <i class="ti ti-wand" />
                {{ t('Generate') }}
            </Link>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">{{ t('Type') }}</th>
                        <th class="px-4 py-3">{{ t('Target') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Inserted') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Tokens') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('By') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Date') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="b in batches.data" :key="b.id" class="text-gray-700 dark:text-gray-300">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ b.label }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ b.target_label ?? '—' }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">
                            {{ b.inserted_count }}
                            <span class="text-gray-400">/ {{ b.requested_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums text-gray-500">{{ b.tokens.toLocaleString() }}</td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusTone[b.status] ?? 'bg-gray-100 text-gray-600'"
                                :title="b.error ?? ''"
                            >
                                {{ b.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ b.created_by ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-500">{{ formatBatchDate(b.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                :disabled="deletingId === b.id"
                                class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-900/20"
                                @click="destroy(b)"
                            >
                                <i class="ti ti-trash" />
                                {{ t('Delete') }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!batches.data.length">
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                            {{ t('No runs yet.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="batches.links.length > 3">
            <Pagination
                :links="batches.links"
                :from="batches.from"
                :to="batches.to"
                :total="batches.total"
                :current-page="batches.current_page"
                :last-page="batches.last_page"
            />
        </div>

        <ActionConfirmModal
            :open="!!deletingBatch"
            :title="t('Delete generation batch')"
            :message="t('Are you sure you want to delete this batch and roll back all data it created?') + (deletingBatch ? ' (' + (deletingBatch.target_label ? deletingBatch.label + ' · ' + deletingBatch.target_label : deletingBatch.label) + ')' : '')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            :processing="deletingId !== null"
            variant="danger"
            @confirm="confirmDelete"
            @cancel="deletingBatch = null"
        />
    </div>
</template>
