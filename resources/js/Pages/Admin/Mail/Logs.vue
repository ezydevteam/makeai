<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

defineOptions({ layout: AdminLayout })

interface MailLogItem {
    id: number
    template_slug: string | null
    recipient_email: string
    subject: string
    status: string
    error_message: string | null
    sent_at: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    filters: {
        search?: string
        status?: string
    }
    logs: {
        data: MailLogItem[]
        links: PaginationLink[]
    }
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()

const filterForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
})

const openActionMenuId = ref<number | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)

const statusOptions = computed(() => [
    { value: '', label: t('All statuses') },
    { value: 'sent', label: t('Sent') },
    { value: 'failed', label: t('Failed') },
    { value: 'bounced', label: t('Bounced') },
])

const filteredLogs = computed(() => {
    const search = filterForm.value.search.trim().toLowerCase()
    const status = filterForm.value.status

    return props.logs.data.filter((log) => {
        const matchesStatus = !status || log.status === status

        if (!matchesStatus) {
            return false
        }

        if (!search) {
            return true
        }

        return [
            log.recipient_email,
            log.subject,
            log.template_slug || '',
            log.status,
            log.error_message || '',
        ].some((value) => value.toLowerCase().includes(search))
    })
})

function toggleActionMenu(id: number) {
    openActionMenuId.value = openActionMenuId.value === id ? null : id
}

function closeActionMenu() {
    openActionMenuId.value = null
}

function clearFilters() {
    filterForm.value.search = ''
    filterForm.value.status = ''
}

function handleKeydown(event: KeyboardEvent) {
    const target = event.target as HTMLElement | null
    const isTypingTarget = target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.tagName === 'SELECT' || target?.isContentEditable

    if (event.key === '/' && !openActionMenuId.value && !isTypingTarget) {
        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && !openActionMenuId.value && (filterForm.value.search || filterForm.value.status)) {
        event.preventDefault()
        clearFilters()
    }
}

function handleDocumentClick(event: MouseEvent) {
    const target = event.target as HTMLElement | null

    if (target?.closest('[data-mail-log-actions]')) {
        return
    }

    closeActionMenu()
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Mail Logs')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Mail Delivery Logs') }}</h1>
                <p class="max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review outgoing email activity, isolate failed deliveries, and resend templated messages when needed.') }}
                </p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-surface-700 dark:bg-surface-900">
            <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 dark:border-surface-700 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative w-full lg:max-w-md">
                    <i class="ti ti-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400" />
                    <input
                        ref="searchInput"
                        v-model="filterForm.search"
                        type="search"
                        :placeholder="t('Search recipient, subject, or template...')"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-11 pr-16 text-sm text-gray-700 transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                    >
                    <span
                        v-if="!filterForm.search"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                    >
                        /
                    </span>
                    <button
                        v-if="filterForm.search"
                        type="button"
                        class="absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                        :aria-label="t('Clear search')"
                        @click="filterForm.search = ''"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="sm:w-52">
                        <AppSelect
                            v-model="filterForm.status"
                            :options="statusOptions"
                            :placeholder="t('Filter by status')"
                        />
                    </div>
                    <button
                        v-if="filterForm.search || filterForm.status"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                        @click="clearFilters"
                    >
                        {{ t('Clear Filters') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="border-b border-gray-200 px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-surface-700 dark:text-gray-400">
                                {{ t('Recipient') }}
                            </th>
                            <th class="border-b border-gray-200 px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-surface-700 dark:text-gray-400">
                                {{ t('Subject') }}
                            </th>
                            <th class="border-b border-gray-200 px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-surface-700 dark:text-gray-400">
                                {{ t('Template') }}
                            </th>
                            <th class="border-b border-gray-200 px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-surface-700 dark:text-gray-400">
                                {{ t('Status') }}
                            </th>
                            <th class="border-b border-gray-200 px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-surface-700 dark:text-gray-400">
                                {{ t('Sent At') }}
                            </th>
                            <th class="border-b border-gray-200 px-6 py-3 text-right text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-surface-700 dark:text-gray-400">
                                {{ t('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in filteredLogs"
                            :key="log.id"
                            class="transition hover:bg-primary-50/50 dark:hover:bg-primary-900/10"
                        >
                            <td class="border-b border-gray-100 px-6 py-4 align-top dark:border-surface-800">
                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ log.recipient_email }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">#{{ log.id }}</p>
                                </div>
                            </td>
                            <td class="border-b border-gray-100 px-6 py-4 align-top dark:border-surface-800">
                                <p class="max-w-md text-sm text-gray-700 dark:text-gray-200">{{ log.subject }}</p>
                            </td>
                            <td class="border-b border-gray-100 px-6 py-4 align-top dark:border-surface-800">
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    {{ log.template_slug || t('Manual') }}
                                </span>
                            </td>
                            <td class="border-b border-gray-100 px-6 py-4 align-top dark:border-surface-800">
                                <div class="space-y-2">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300': log.status === 'sent',
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300': log.status === 'failed',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': log.status === 'bounced',
                                            'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300': !['sent', 'failed', 'bounced'].includes(log.status),
                                        }"
                                    >
                                        {{ log.status }}
                                    </span>
                                    <p v-if="log.error_message" class="max-w-xs text-xs text-red-600 dark:text-red-300">
                                        {{ log.error_message }}
                                    </p>
                                </div>
                            </td>
                            <td class="border-b border-gray-100 px-6 py-4 align-top text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                                {{ log.sent_at ? formatDateTime(log.sent_at) : t('Not recorded') }}
                            </td>
                            <td class="border-b border-gray-100 px-6 py-4 align-top text-right dark:border-surface-800">
                                <div class="relative inline-flex" data-mail-log-actions>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-primary-200 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-700 dark:hover:text-primary-300"
                                        @click.stop="toggleActionMenu(log.id)"
                                    >
                                        <i class="ti ti-dots-vertical text-base" />
                                    </button>

                                    <div
                                        v-if="openActionMenuId === log.id"
                                        class="absolute right-0 top-full z-20 mt-2 w-52 rounded-2xl border border-gray-200 bg-white p-2 text-left shadow-lg dark:border-surface-700 dark:bg-surface-900"
                                    >
                                        <Link
                                            v-if="log.template_slug"
                                            :href="route('admin.mail.logs.resend', log.id)"
                                            method="post"
                                            as="button"
                                            preserve-scroll
                                            class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                                            @click="closeActionMenu"
                                        >
                                            <i class="ti ti-refresh text-base" />
                                            {{ t('Resend Email') }}
                                        </Link>
                                        <div
                                            v-else
                                            class="rounded-xl px-3 py-2 text-sm text-gray-400 dark:text-gray-500"
                                        >
                                            {{ t('Manual emails cannot be resent') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredLogs.length === 0">
                            <td colspan="6" class="px-6 py-14 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                        <i class="ti ti-mail text-xl" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('No mail logs found') }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Try adjusting your table search or status filter.') }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-6 py-4 dark:border-surface-800">
                <Pagination :links="logs.links" />
            </div>
        </section>
    </div>
</template>
