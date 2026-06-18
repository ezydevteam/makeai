<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface AuditLog {
    id: number
    admin_id: number
    admin_name: string
    admin_email: string
    action: string
    ip_address: string
    user_agent: string | null
    payload: string | null
    created_at: string
}

interface Admin {
    id: number
    name: string
    email: string
}

interface Props {
    logs: {
        data: AuditLog[]
        current_page: number
        last_page: number
        total: number
        per_page: number
        next_page_url: string | null
        prev_page_url: string | null
    }
    admins: Admin[]
    filters: {
        admin_id?: string
        action?: string
        date_from?: string
        date_to?: string
    }
}

const props = defineProps<Props>()
const { t } = useTranslate()

const showPayloadModal = ref(false)
const selectedPayload = ref<string | null>(null)

const parsedPayload = computed(() => {
    if (!selectedPayload.value) return null
    try {
        return JSON.parse(selectedPayload.value)
    } catch {
        return null
    }
})

function viewPayload(payload: string | null) {
    selectedPayload.value = payload
    showPayloadModal.value = true
}

function formatAction(action: string): string {
    const [method, ...pathParts] = action.split(' ')
    const path = pathParts.join(' ')
    return `${method} ${path}`
}

function formatDate(date: string): string {
    return new Date(date).toLocaleString()
}

function getMethodColor(action: string): string {
    const method = action.split(' ')[0]
    switch (method) {
        case 'POST':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
        case 'PUT':
        case 'PATCH':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
        case 'DELETE':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
    }
}
</script>

<template>
    <AdminLayout :title="t('Audit Logs')">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ t('Audit Logs') }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Super admin actions are logged for security and compliance') }}
                </p>
            </div>

            <!-- Filters -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <form method="GET" :action="route('admin.audit-logs.index')" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Admin') }}
                        </label>
                        <select
                            name="admin_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        >
                            <option value="">{{ t('All Admins') }}</option>
                            <option
                                v-for="admin in admins"
                                :key="admin.id"
                                :value="admin.id"
                                :selected="filters.admin_id == admin.id"
                            >
                                {{ admin.name }} ({{ admin.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Action') }}
                        </label>
                        <input
                            type="text"
                            name="action"
                            :value="filters.action"
                            :placeholder="t('e.g., POST, DELETE, settings')"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('From Date') }}
                        </label>
                        <input
                            type="date"
                            name="date_from"
                            :value="filters.date_from"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('To Date') }}
                        </label>
                        <input
                            type="date"
                            name="date_to"
                            :value="filters.date_to"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>

                    <div class="md:col-span-4">
                        <button
                            type="submit"
                            class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                        >
                            {{ t('Apply Filters') }}
                        </button>
                        <a
                            :href="route('admin.audit-logs.index')"
                            class="ml-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            {{ t('Clear') }}
                        </a>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ t('Admin') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ t('Action') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ t('IP Address') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ t('User Agent') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ t('Date') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ t('Payload') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ t('No audit logs found') }}
                            </td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ log.admin_name }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ log.admin_email }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="getMethodColor(log.action)"
                                >
                                    {{ formatAction(log.action) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ log.ip_address }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="max-w-xs truncate" :title="log.user_agent || ''">
                                    {{ log.user_agent || '-' }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <button
                                    v-if="log.payload"
                                    @click="viewPayload(log.payload)"
                                    class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                >
                                    {{ t('View') }}
                                </button>
                                <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    {{ t('Showing') }} {{ logs.from }} {{ t('to') }} {{ logs.to }} {{ t('of') }} {{ logs.total }} {{ t('results') }}
                </div>
                <div class="flex space-x-2">
                    <a
                        v-if="logs.prev_page_url"
                        :href="logs.prev_page_url"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        {{ t('Previous') }}
                    </a>
                    <a
                        v-if="logs.next_page_url"
                        :href="logs.next_page_url"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        {{ t('Next') }}
                    </a>
                </div>
            </div>

            <!-- Payload Modal -->
            <div
                v-if="showPayloadModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                @click.self="showPayloadModal = false"
            >
                <div class="max-h-[80vh] w-full max-w-2xl overflow-auto rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ t('Request Payload') }}
                        </h3>
                        <button
                            @click="showPayloadModal = false"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <pre class="overflow-auto rounded-md bg-gray-50 p-4 text-sm dark:bg-gray-900"><code>{{ JSON.stringify(parsedPayload, null, 2) }}</code></pre>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
