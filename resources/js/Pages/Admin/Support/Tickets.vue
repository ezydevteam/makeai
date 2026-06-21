<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { badgeClass } from '@/Composables/useBadge'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

interface Option {
    id: number
    name: string
}

interface Ticket {
    id: number
    ticket_number: string
    subject: string
    status: string
    priority: string
    last_reply_at: string | null
    first_response_at: string | null
    admin_last_read_at: string | null
    last_reply_by: string | null
    created_at: string
    user?: { name: string; email: string }
    department?: Option
    assigned_admin?: Option | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface SupportSettingsForm {
    tickets_enabled: boolean
    max_attachments_per_reply: number
    max_attachment_size_mb: number
    allowed_attachment_types: string
    auto_close_resolved_days: number
    sla_first_response_hours: number
    sla_resolution_hours: number
    notify_admin_new_ticket: boolean
    notify_user_reply: boolean
    satisfaction_rating_enabled: boolean
    ai_reply_suggestion: boolean
}

const props = defineProps<{
    tickets: { data: Ticket[]; links: PaginationLink[] }
    departments: Option[]
    admins: Option[]
    filters: { search?: string; status?: string; priority?: string; department?: string; assigned_to?: string }
    stats: { total: number; open: number; in_progress: number; waiting_user: number; resolved: number }
    sla: { first_response_hours: number; resolution_hours: number }
    settings: SupportSettingsForm
    openSettings: boolean
}>()

const { t } = useTranslate()

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const priority = ref(props.filters.priority ?? '')
const department = ref(props.filters.department ?? '')
const assignedTo = ref(props.filters.assigned_to ?? '')
const selectedIds = ref<number[]>([])
const bulkAction = ref('')
const bulkStatus = ref('open')
const bulkPriority = ref('medium')
const bulkAssignedTo = ref<number | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const showSettingsModal = ref(props.openSettings)
const filterDebounce = ref<number | null>(null)

const settingsForm = useForm<SupportSettingsForm>({
    ...props.settings,
})

const showBulkControls = computed(() => selectedIds.value.length > 0)
const allVisibleSelected = computed(() => props.tickets.data.length > 0 && props.tickets.data.every((ticket) => selectedIds.value.includes(ticket.id)))
const hasActiveFilters = computed(() => Boolean(search.value || status.value || priority.value || department.value || assignedTo.value))

const statusOptions = computed(() => [
    { value: '', label: t('All statuses') },
    { value: 'open', label: t('Open') },
    { value: 'in_progress', label: t('In Progress') },
    { value: 'waiting_user', label: t('Waiting User') },
    { value: 'resolved', label: t('Resolved') },
    { value: 'closed', label: t('Closed') },
])

const priorityOptions = computed(() => [
    { value: '', label: t('All priorities') },
    { value: 'low', label: t('Low') },
    { value: 'medium', label: t('Medium') },
    { value: 'high', label: t('High') },
    { value: 'urgent', label: t('Urgent') },
])

const departmentOptions = computed(() => [
    { value: '', label: t('All departments') },
    ...props.departments.map((item) => ({ value: String(item.id), label: item.name })),
])

const adminOptions = computed(() => [
    { value: '', label: t('All agents') },
    ...props.admins.map((item) => ({ value: String(item.id), label: item.name })),
])

const bulkActionOptions = computed(() => [
    { value: '', label: t('Choose action') },
    { value: 'assign', label: t('Assign to') },
    { value: 'status', label: t('Change status') },
    { value: 'priority', label: t('Change priority') },
    { value: 'delete', label: t('Delete') },
])

const bulkStatusOptions = computed(() => [
    { value: 'open', label: t('Open') },
    { value: 'in_progress', label: t('In Progress') },
    { value: 'waiting_user', label: t('Waiting User') },
    { value: 'resolved', label: t('Resolved') },
    { value: 'closed', label: t('Closed') },
])

const bulkPriorityOptions = computed(() => [
    { value: 'low', label: t('Low') },
    { value: 'medium', label: t('Medium') },
    { value: 'high', label: t('High') },
    { value: 'urgent', label: t('Urgent') },
])

const bulkAssignOptions = computed(() => [
    { value: null, label: t('Unassigned') },
    ...props.admins.map((item) => ({ value: item.id, label: item.name })),
])

const labels: Record<string, string> = {
    notify_admin_new_ticket: 'Notify admin on new ticket',
    notify_user_reply: 'Notify user on reply',
    satisfaction_rating_enabled: 'Enable satisfaction rating',
    ai_reply_suggestion: 'Enable AI reply suggestions',
    max_attachments_per_reply: 'Max attachments per reply',
    max_attachment_size_mb: 'Max attachment size (MB)',
    auto_close_resolved_days: 'Auto-close resolved tickets after days',
    sla_first_response_hours: 'First response SLA (hours)',
    sla_resolution_hours: 'Resolution SLA (hours)',
    allowed_attachment_types: 'Allowed attachment types',
}

const label = (key: string) => t(labels[key] ?? key)

const ticketStatusLabel = (value: string) => {
    const labels: Record<string, string> = {
        open: 'Open',
        in_progress: 'In Progress',
        waiting_user: 'Waiting User',
        resolved: 'Resolved',
        closed: 'Closed',
    }

    return t(labels[value] ?? value)
}

const ticketPriorityLabel = (value: string) => {
    const labels: Record<string, string> = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        urgent: 'Urgent',
    }

    return t(labels[value] ?? value)
}

const buildFilterQuery = (includeSettings = false) => {
    const query: Record<string, string | number> = {}

    if (search.value.trim()) {
        query.search = search.value.trim()
    }

    if (status.value) {
        query.status = status.value
    }

    if (priority.value) {
        query.priority = priority.value
    }

    if (department.value) {
        query.department = department.value
    }

    if (assignedTo.value) {
        query.assigned_to = assignedTo.value
    }

    if (includeSettings) {
        query.settings = 1
    }

    return query
}

const applyFilters = () => {
    router.get(route('admin.support.tickets.index'), buildFilterQuery(showSettingsModal.value && props.openSettings), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['tickets', 'departments', 'admins', 'filters', 'stats', 'sla', 'settings', 'openSettings'],
    })
}

const clearFilters = () => {
    search.value = ''
    status.value = ''
    priority.value = ''
    department.value = ''
    assignedTo.value = ''
    applyFilters()
}

const toggleAll = () => {
    selectedIds.value = allVisibleSelected.value ? [] : props.tickets.data.map((ticket) => ticket.id)
}

const toggleTicket = (id: number) => {
    const index = selectedIds.value.indexOf(id)
    if (index === -1) {
        selectedIds.value.push(id)
        return
    }

    selectedIds.value.splice(index, 1)
}

const executeBulk = () => {
    if (!selectedIds.value.length || !bulkAction.value) {
        return
    }

    router.post(route('admin.support.tickets.bulk'), {
        ids: selectedIds.value,
        action: bulkAction.value,
        status: bulkAction.value === 'status' ? bulkStatus.value : undefined,
        priority: bulkAction.value === 'priority' ? bulkPriority.value : undefined,
        assigned_to: bulkAction.value === 'assign' ? bulkAssignedTo.value : undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = []
            bulkAction.value = ''
        },
    })
}

const openSettingsModal = () => {
    settingsForm.defaults({ ...props.settings })
    settingsForm.reset()
    showSettingsModal.value = true
}

const closeSettingsModal = () => {
    if (settingsForm.processing) {
        return
    }

    showSettingsModal.value = false
    settingsForm.reset()
    settingsForm.clearErrors()

    if (props.openSettings) {
        router.get(route('admin.support.tickets.index'), buildFilterQuery(false), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tickets', 'departments', 'admins', 'filters', 'stats', 'sla', 'settings', 'openSettings'],
        })
    }
}

const submitSettings = () => {
    settingsForm.post(route('admin.support.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            closeSettingsModal()
        },
    })
}

const slaIndicator = (ticket: Ticket): { label: string; color: string } | null => {
    if (!ticket.first_response_at && ticket.status !== 'closed' && ticket.status !== 'resolved') {
        const createdAt = new Date(ticket.created_at).getTime()
        const deadline = createdAt + props.sla.first_response_hours * 3600000
        const remaining = deadline - Date.now()

        if (remaining <= 0) return { label: t('SLA breached'), color: 'text-red-600 dark:text-red-400' }
        if (remaining < 3600000) return { label: t('SLA soon'), color: 'text-amber-600 dark:text-amber-400' }
    }

    return null
}

const hasUnread = (ticket: Ticket): boolean => {
    if (!ticket.last_reply_at || !ticket.admin_last_read_at) return false
    if (ticket.last_reply_by === 'admin') return false
    return new Date(ticket.last_reply_at) > new Date(ticket.admin_last_read_at)
}

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName
    const isTypingTarget = tagName === 'INPUT' || tagName === 'TEXTAREA' || target?.isContentEditable

    if (event.key === '/' && !showSettingsModal.value && !isTypingTarget) {
        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && !showSettingsModal.value && hasActiveFilters.value) {
        event.preventDefault()
        clearFilters()
    }
}

watch(
    () => props.settings,
    (settings) => {
        settingsForm.defaults({ ...settings })
        settingsForm.reset()
    },
)

watch(
    () => props.openSettings,
    (value) => {
        showSettingsModal.value = value
    },
)

watch([search, status, priority, department, assignedTo], () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }

    filterDebounce.value = window.setTimeout(() => {
        applyFilters()
    }, 250)
})

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)

    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
})
</script>

<template>
    <Head :title="t('Support Tickets')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support Tickets') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage customer tickets, replies, assignments, and SLA status from one support workspace.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="route('admin.support.departments.index')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                >
                    <i class="ti ti-building text-base"></i>
                    {{ t('Departments') }}
                </Link>
                <Link
                    :href="route('admin.support.canned-responses.index')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                >
                    <i class="ti ti-message-2-code text-base"></i>
                    {{ t('Canned Responses') }}
                </Link>
                <a
                    :href="route('admin.support.tickets.export')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                >
                    <i class="ti ti-file-export text-base"></i>
                    {{ t('Export CSV') }}
                </a>
                <button
                    type="button"
                    class="btn-primary inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold"
                    @click="openSettingsModal"
                >
                    <i class="ti ti-settings text-base"></i>
                    {{ t('Settings') }}
                </button>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-surface-800">
                <div class="relative w-full max-w-xl">
                    <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                    <input
                        ref="searchInput"
                        v-model="search"
                        :placeholder="t('Search tickets by subject, number, or user')"
                        type="search"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-700 transition focus:border-primary-300 focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    >
                    <span
                        v-if="!search"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                    >/</span>
                    <button
                        v-if="search"
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        @click="search = ''"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="flex w-full flex-col gap-3 lg:w-auto lg:min-w-[44rem] lg:flex-row lg:justify-end">
                    <div class="w-full lg:w-44">
                        <AppSelect v-model="status" :options="statusOptions" :placeholder="t('All statuses')" />
                    </div>
                    <div class="w-full lg:w-44">
                        <AppSelect v-model="priority" :options="priorityOptions" :placeholder="t('All priorities')" />
                    </div>
                    <div class="w-full lg:w-52">
                        <AppSelect v-model="department" :options="departmentOptions" :placeholder="t('All departments')" />
                    </div>
                    <div class="w-full lg:w-44">
                        <AppSelect v-model="assignedTo" :options="adminOptions" :placeholder="t('All agents')" />
                    </div>
                </div>
            </div>

            <div v-if="showBulkControls" class="flex flex-col gap-3 border-b border-primary-100 bg-primary-50/80 px-4 py-3 dark:border-primary-900/30 dark:bg-primary-900/10 lg:flex-row lg:items-center lg:justify-between">
                <div class="text-sm font-semibold text-primary-700 dark:text-primary-300">
                    {{ t(':count selected', { count: String(selectedIds.length) }) }}
                </div>

                <div class="flex w-full flex-col gap-3 lg:w-auto lg:min-w-[46rem] lg:flex-row lg:justify-end">
                    <div class="w-full lg:w-52">
                        <AppSelect v-model="bulkAction" :options="bulkActionOptions" :placeholder="t('Choose action')" />
                    </div>
                    <div v-if="bulkAction === 'assign'" class="w-full lg:w-52">
                        <AppSelect v-model="bulkAssignedTo" :options="bulkAssignOptions" :placeholder="t('Unassigned')" />
                    </div>
                    <div v-if="bulkAction === 'status'" class="w-full lg:w-52">
                        <AppSelect v-model="bulkStatus" :options="bulkStatusOptions" :placeholder="t('Choose status')" />
                    </div>
                    <div v-if="bulkAction === 'priority'" class="w-full lg:w-52">
                        <AppSelect v-model="bulkPriority" :options="bulkPriorityOptions" :placeholder="t('Choose priority')" />
                    </div>
                    <button
                        type="button"
                        class="btn-primary inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-50"
                        :disabled="!bulkAction"
                        @click="executeBulk"
                    >
                        {{ t('Apply') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50 dark:bg-surface-800/70">
                        <tr>
                            <th class="w-10 px-4 py-3.5">
                                <input type="checkbox" :checked="allVisibleSelected" :aria-label="t('Select all visible tickets')" @change="toggleAll">
                            </th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Ticket') }}</th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Customer') }}</th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Department') }}</th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Priority') }}</th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('SLA') }}</th>
                            <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Assigned') }}</th>
                            <th class="px-4 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="bg-white transition-colors hover:bg-gray-50/50 dark:bg-surface-900 dark:hover:bg-surface-800/30"
                        >
                            <td class="px-4 py-4 align-top">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(ticket.id)"
                                    :aria-label="t('Select ticket')"
                                    @change="toggleTicket(ticket.id)"
                                >
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="flex items-start gap-2">
                                    <span v-if="hasUnread(ticket)" class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-primary-500"></span>
                                    <div class="min-w-0">
                                        <Link :href="route('admin.support.tickets.show', ticket.ticket_number)" class="text-sm font-medium text-gray-800 transition hover:text-primary-600 dark:text-white">
                                            {{ ticket.subject }}
                                        </Link>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ ticket.ticket_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="text-sm text-gray-700 dark:text-gray-200">{{ ticket.user?.name || t('Unknown user') }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ ticket.user?.email || t('No email') }}</div>
                            </td>
                            <td class="px-4 py-4 align-top text-sm text-gray-600 dark:text-gray-300">{{ ticket.department?.name || t('No department') }}</td>
                            <td class="px-4 py-4 align-top">
                                <span :class="badgeClass(ticket.priority)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                    {{ ticketPriorityLabel(ticket.priority) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span :class="badgeClass(ticket.status)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                    {{ ticketStatusLabel(ticket.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span v-if="slaIndicator(ticket)" class="text-xs font-semibold" :class="slaIndicator(ticket)?.color">{{ slaIndicator(ticket)?.label }}</span>
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
                            </td>
                            <td class="px-4 py-4 align-top text-sm text-gray-600 dark:text-gray-300">{{ ticket.assigned_admin?.name ?? t('Unassigned') }}</td>
                            <td class="px-4 py-4 text-right align-top">
                                <Link
                                    :href="route('admin.support.tickets.show', ticket.ticket_number)"
                                    class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 transition hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-900/20"
                                >
                                    <i class="ti ti-arrow-right text-base"></i>
                                    {{ t('View') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                    <i class="ti ti-ticket-off text-2xl"></i>
                                </div>
                                <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                                    {{ hasActiveFilters ? t('No tickets match these filters') : t('No support tickets found') }}
                                </h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ hasActiveFilters ? t('Try another search term or change the current filters.') : t('New customer tickets will appear here once they are submitted.') }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="tickets.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination :links="tickets.links" />
            </div>
        </section>

        <div v-if="showSettingsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeSettingsModal">
            <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-200 px-6 py-3 dark:border-surface-700">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Support Settings') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Configure support ticket rules, notifications, and SLA thresholds.') }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800"
                            :aria-label="t('Close modal')"
                            @click="closeSettingsModal"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <div class="space-y-6">
                        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="mb-5">
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Automation & Notifications') }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Control whether tickets are enabled and how support notifications behave.') }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label
                                    v-for="key in ['notify_admin_new_ticket', 'notify_user_reply', 'satisfaction_rating_enabled', 'ai_reply_suggestion']"
                                    :key="key"
                                    class="flex items-start justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800"
                                >
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ label(key) }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="Boolean(settingsForm[key as keyof SupportSettingsForm])"
                                        class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                        :class="settingsForm[key as keyof SupportSettingsForm] ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                        @click="settingsForm[key as keyof SupportSettingsForm] = !settingsForm[key as keyof SupportSettingsForm] as never"
                                    >
                                        <span
                                            class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                            :class="settingsForm[key as keyof SupportSettingsForm] ? 'translate-x-5' : 'translate-x-0.5'"
                                        ></span>
                                    </button>
                                </label>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="mb-5">
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Limits & SLA') }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Set attachment limits, auto-close timing, and response expectations for your support team.') }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label
                                    v-for="key in ['max_attachments_per_reply', 'max_attachment_size_mb', 'auto_close_resolved_days', 'sla_first_response_hours', 'sla_resolution_hours']"
                                    :key="key"
                                    class="block"
                                >
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label(key) }}</span>
                                    <input
                                        v-model="settingsForm[key as keyof SupportSettingsForm]"
                                        type="number"
                                        min="1"
                                        class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </label>

                                <label class="block md:col-span-2">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label('allowed_attachment_types') }}</span>
                                    <input
                                        v-model="settingsForm.allowed_attachment_types"
                                        :placeholder="t('jpg,png,pdf,docx')"
                                        type="text"
                                        class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </label>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-3 dark:border-surface-700 dark:bg-surface-800/80">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('These changes affect the public support flow and admin ticket handling.') }}</div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="closeSettingsModal"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary inline-flex items-center gap-2 rounded-lg px-5 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="settingsForm.processing"
                            @click="submitSettings"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ settingsForm.processing ? t('Saving...') : t('Save Settings') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
