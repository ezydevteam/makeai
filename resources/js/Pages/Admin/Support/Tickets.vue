<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { badgeClass } from '@/Composables/useBadge'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface Option { id: number; name: string }
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

const props = defineProps<{
    tickets: { data: Ticket[]; links: PaginationLink[] }
    departments: Option[]
    admins: Option[]
    filters: { search?: string; status?: string; priority?: string; department?: string; assigned_to?: string }
    stats: { total: number; open: number; in_progress: number; waiting_user: number; resolved: number }
    sla: { first_response_hours: number; resolution_hours: number }
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

const showBulkControls = computed(() => selectedIds.value.length > 0)
const allVisibleSelected = computed(() => props.tickets.data.length > 0 && props.tickets.data.every((ticket) => selectedIds.value.includes(ticket.id)))

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

const applyFilters = () => {
    router.get(route('admin.support.tickets.index'), {
        search: search.value,
        status: status.value,
        priority: priority.value,
        department: department.value,
        assigned_to: assignedTo.value,
    }, { preserveState: true, replace: true })
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
    if (!selectedIds.value.length || !bulkAction.value) return

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

const slaIndicator = (ticket: Ticket): { label: string; color: string } | null => {
    if (!ticket.first_response_at && ticket.status !== 'closed' && ticket.status !== 'resolved') {
        const createdAt = new Date(ticket.created_at).getTime()
        const deadline = createdAt + props.sla.first_response_hours * 3600000
        const remaining = deadline - Date.now()

        if (remaining <= 0) return { label: t('SLA breached'), color: 'text-red-600' }
        if (remaining < 3600000) return { label: t('SLA soon'), color: 'text-amber-600' }
    }

    return null
}

const hasUnread = (ticket: Ticket): boolean => {
    if (!ticket.last_reply_at || !ticket.admin_last_read_at) return false
    if (ticket.last_reply_by === 'admin') return false
    return new Date(ticket.last_reply_at) > new Date(ticket.admin_last_read_at)
}

const statCards = [
    { key: 'total', label: 'Total tickets', value: props.stats.total, color: 'text-gray-900 dark:text-white', icon: 'ti ti-ticket', accent: 'bg-secondary-50 text-secondary-600 dark:bg-secondary-900/20 dark:text-secondary-300' },
    { key: 'open', label: 'Open', value: props.stats.open, color: 'text-blue-600', icon: 'ti ti-ticket', accent: 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-300' },
    { key: 'in_progress', label: 'In Progress', value: props.stats.in_progress, color: 'text-violet-600', icon: 'ti ti-progress-check', accent: 'bg-violet-50 text-violet-600 dark:bg-violet-900/20 dark:text-violet-300' },
    { key: 'waiting_user', label: 'Waiting User', value: props.stats.waiting_user, color: 'text-amber-600', icon: 'ti ti-clock-hour-4', accent: 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300' },
    { key: 'resolved', label: 'Resolved', value: props.stats.resolved, color: 'text-primary-600', icon: 'ti ti-circle-check', accent: 'bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300' },
]
</script>

<template>
    <Head :title="t('Support Tickets')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support Tickets') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage customer tickets, replies, assignments, and SLA status.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.support.departments.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"><i class="ti ti-building text-base"></i>{{ t('Departments') }}</Link>
                <Link :href="route('admin.support.canned-responses.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"><i class="ti ti-message-2-code text-base"></i>{{ t('Canned Responses') }}</Link>
                <a :href="route('admin.support.tickets.export')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"><i class="ti ti-file-export text-base"></i>{{ t('Export CSV') }}</a>
                <Link :href="route('admin.support.settings.edit')" class="btn-primary inline-flex items-center gap-2"><i class="ti ti-settings text-base"></i>{{ t('Settings') }}</Link>
            </div>
        </div>

        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article v-for="card in statCards" :key="card.key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ t(card.label) }}</div>
                        <div class="mt-2 text-3xl font-bold" :class="card.color">{{ card.value }}</div>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl" :class="card.accent">
                        <i :class="card.icon" class="text-xl"></i>
                    </span>
                </div>
            </article>
        </section>

        <section class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative flex-1">
                    <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                    <input v-model="search" @keyup.enter="applyFilters" type="search" :placeholder="t('Search tickets by subject, number, or user')" class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-44"><AppSelect v-model="status" :options="statusOptions" :placeholder="t('All statuses')" @update:model-value="applyFilters" /></div>
                    <div class="w-44"><AppSelect v-model="priority" :options="priorityOptions" :placeholder="t('All priorities')" @update:model-value="applyFilters" /></div>
                    <div class="w-48"><AppSelect v-model="department" :options="departmentOptions" :placeholder="t('All departments')" @update:model-value="applyFilters" /></div>
                    <div class="w-44"><AppSelect v-model="assignedTo" :options="adminOptions" :placeholder="t('All agents')" @update:model-value="applyFilters" /></div>
                </div>
            </div>
        </section>

        <section v-if="showBulkControls" class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
            <span class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ t(':count selected', { count: String(selectedIds.length) }) }}</span>
            <select v-model="bulkAction" class="rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                <option value="">{{ t('Choose action') }}</option>
                <option value="assign">{{ t('Assign to') }}</option>
                <option value="status">{{ t('Change status') }}</option>
                <option value="priority">{{ t('Change priority') }}</option>
                <option value="delete">{{ t('Delete') }}</option>
            </select>
            <select v-if="bulkAction === 'assign'" v-model="bulkAssignedTo" class="rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                <option :value="null">{{ t('Unassigned') }}</option>
                <option v-for="item in admins" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
            <select v-if="bulkAction === 'status'" v-model="bulkStatus" class="rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                <option v-for="item in ['open', 'in_progress', 'waiting_user', 'resolved', 'closed']" :key="item" :value="item">{{ t(item) }}</option>
            </select>
            <select v-if="bulkAction === 'priority'" v-model="bulkPriority" class="rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                <option v-for="item in ['low', 'medium', 'high', 'urgent']" :key="item" :value="item">{{ t(item) }}</option>
            </select>
            <button type="button" @click="executeBulk" :disabled="!bulkAction" class="btn-primary disabled:opacity-50">{{ t('Apply') }}</button>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" :checked="allVisibleSelected" @change="toggleAll" class="rounded border-gray-300"></th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Ticket') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Department') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Priority') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('SLA') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Assigned') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase text-gray-500">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-primary-50/40 dark:hover:bg-surface-800">
                            <td class="px-4 py-4"><input type="checkbox" :checked="selectedIds.includes(ticket.id)" @change="toggleTicket(ticket.id)" class="rounded border-gray-300"></td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <span v-if="hasUnread(ticket)" class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full bg-primary-500"></span>
                                    <div>
                                        <Link :href="route('admin.support.tickets.show', ticket.ticket_number)" class="font-semibold text-gray-900 hover:text-primary-600 dark:text-white">{{ ticket.subject }}</Link>
                                        <div class="text-xs text-gray-500">{{ ticket.ticket_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.user?.name }}<div class="text-xs text-gray-400">{{ ticket.user?.email }}</div></td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.department?.name }}</td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.priority)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.priority) }}</span></td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.status) }}</span></td>
                            <td class="px-4 py-4">
                                <span v-if="slaIndicator(ticket)" class="text-xs font-semibold" :class="slaIndicator(ticket)!.color">{{ slaIndicator(ticket)!.label }}</span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.assigned_admin?.name ?? t('Unassigned') }}</td>
                            <td class="px-4 py-4 text-right"><Link :href="route('admin.support.tickets.show', ticket.ticket_number)" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50"><i class="ti ti-arrow-right text-base"></i>{{ t('Open') }}</Link></td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No support tickets found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="tickets.links.length > 3" class="border-t border-gray-100 px-4 py-3 dark:border-surface-800">
                <Pagination :links="tickets.links" />
            </div>
        </section>
    </div>
</template>
