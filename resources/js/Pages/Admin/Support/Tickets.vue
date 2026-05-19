<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

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
    created_at: string
    user?: { name: string; email: string }
    department?: Option
    assigned_admin?: Option | null
}

const props = defineProps<{
    tickets: { data: Ticket[]; links: { url: string | null; label: string; active: boolean }[] }
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

const applyFilters = () => {
    router.get(route('admin.support.tickets.index'), {
        search: search.value,
        status: status.value,
        priority: priority.value,
        department: department.value,
        assigned_to: assignedTo.value,
    }, { preserveState: true, replace: true })
}

const statCards = [
    { key: 'total', label: 'Total tickets', value: props.stats.total, color: 'text-gray-900 dark:text-white' },
    { key: 'open', label: 'Open', value: props.stats.open, color: 'text-blue-600' },
    { key: 'in_progress', label: 'In Progress', value: props.stats.in_progress, color: 'text-violet-600' },
    { key: 'waiting_user', label: 'Waiting User', value: props.stats.waiting_user, color: 'text-amber-600' },
    { key: 'resolved', label: 'Resolved', value: props.stats.resolved, color: 'text-primary-600' },
]

const badgeClass = (value: string) => ({
    open: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-violet-100 text-violet-700',
    waiting_user: 'bg-amber-100 text-amber-700',
    resolved: 'bg-primary-100 text-primary-700',
    closed: 'bg-gray-100 text-gray-600',
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-700',
    high: 'bg-amber-100 text-amber-700',
    urgent: 'bg-red-100 text-red-700',
}[value] ?? 'bg-gray-100 text-gray-600')
</script>

<template>
    <Head :title="t('Support Tickets')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support Tickets') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage customer tickets, replies, assignments, and SLA status.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.support.departments.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300">{{ t('Departments') }}</Link>
                <Link :href="route('admin.support.canned-responses.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300">{{ t('Canned Responses') }}</Link>
                <Link :href="route('admin.support.settings.edit')" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Settings') }}</Link>
            </div>
        </div>

        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div v-for="card in statCards" :key="card.key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ t(card.label) }}</div>
                <div class="mt-2 text-3xl font-bold" :class="card.color">{{ card.value }}</div>
            </div>
        </section>

        <section class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="grid gap-3 md:grid-cols-6">
                <input v-model="search" @keyup.enter="applyFilters" type="search" :placeholder="t('Search tickets')" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm md:col-span-2 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                <select v-model="status" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <option value="">{{ t('All statuses') }}</option>
                    <option v-for="item in ['open', 'in_progress', 'waiting_user', 'resolved', 'closed']" :key="item" :value="item">{{ t(item) }}</option>
                </select>
                <select v-model="priority" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <option value="">{{ t('All priorities') }}</option>
                    <option v-for="item in ['low', 'medium', 'high', 'urgent']" :key="item" :value="item">{{ t(item) }}</option>
                </select>
                <select v-model="department" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <option value="">{{ t('All departments') }}</option>
                    <option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
                <select v-model="assignedTo" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <option value="">{{ t('All agents') }}</option>
                    <option v-for="item in admins" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" class="rounded border-gray-300"></th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Ticket') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Department') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Priority') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Assigned') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase text-gray-500">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-primary-50/40 dark:hover:bg-surface-800">
                            <td class="px-4 py-4"><input type="checkbox" class="rounded border-gray-300"></td>
                            <td class="px-4 py-4">
                                <Link :href="route('admin.support.tickets.show', ticket.ticket_number)" class="font-semibold text-gray-900 hover:text-primary-600 dark:text-white">{{ ticket.subject }}</Link>
                                <div class="text-xs text-gray-500">{{ ticket.ticket_number }}</div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.user?.name }}<div class="text-xs text-gray-400">{{ ticket.user?.email }}</div></td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.department?.name }}</td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.priority)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.priority) }}</span></td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.status) }}</span></td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.assigned_admin?.name ?? t('Unassigned') }}</td>
                            <td class="px-4 py-4 text-right"><Link :href="route('admin.support.tickets.show', ticket.ticket_number)" class="rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50">{{ t('View') }}</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</template>
