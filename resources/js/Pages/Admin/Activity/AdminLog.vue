<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppSelect, { type SelectOption } from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'

defineOptions({ layout: AdminLayout })

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

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Filters {
    admin_id?: string
    action?: string
    date_from?: string
    date_to?: string
}

const props = defineProps<{
    logs: {
        data: AuditLog[]
        current_page?: number
        last_page?: number
        from?: number
        to?: number
        total?: number
        per_page?: number
        next_page_url?: string | null
        prev_page_url?: string | null
        links: PaginationLink[]
    }
    admins: Admin[]
    filters: Filters
}>()

const { t } = useTranslate()

const showPayloadModal = ref(false)
const selectedPayload = ref<string | null>(null)
const searchQuery = ref(props.filters.action ?? '')
const adminFilter = ref(props.filters.admin_id ?? '')
const actionSearchInputRef = ref<HTMLInputElement | null>(null)
const filterDropdownOpen = ref(false)
const filterDropdownRef = ref<HTMLElement | null>(null)

const adminOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Admins') },
    ...props.admins.map((admin) => ({
        value: String(admin.id),
        label: `${admin.name} (${admin.email})`,
    })),
])

const formattedPayload = computed(() => {
    if (!selectedPayload.value) {
        return []
    }

    try {
        const data = JSON.parse(selectedPayload.value)
        if (!data || typeof data !== 'object') return []

        return Object.entries(data)
            .filter(([key]) => !['_token', '_method', 'password', 'password_confirmation'].includes(key))
            .map(([key, value]) => ({
                field: key
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (char: string) => char.toUpperCase()),
                value: typeof value === 'boolean'
                    ? (value ? t('Yes') : t('No'))
                    : typeof value === 'object' && value !== null
                        ? JSON.stringify(value)
                        : String(value ?? '-'),
            }))
    } catch {
        return []
    }
})

function viewPayload(payload: string | null) {
    selectedPayload.value = payload
    showPayloadModal.value = true
}

function formatAction(action: string): string {
    const [method, ...pathParts] = action.split(' ')
    const path = pathParts.join(' ')

    const actionLabels: Record<string, string> = {
        'admin/settings/general': t('General Settings'),
        'admin/settings/features': t('Feature Toggles'),
        'admin/settings/notifications': t('Notification Settings'),
        'admin/settings/oauth': t('OAuth Settings'),
        'admin/settings/security': t('Security Settings'),
        'admin/settings/seo': t('SEO Settings'),
        'admin/settings/email': t('Email Settings'),
        'admin/settings/maintenance': t('Maintenance Mode'),
    }

    const label = actionLabels[path]

    if (label) {
        switch (method) {
            case 'POST':
            case 'PUT':
            case 'PATCH':
                return t('Updated :label', { label })
            case 'DELETE':
                return t('Reset :label', { label })
        }
    }

    if (/^admin\/roles\/\d+$/.test(path)) {
        return method === 'DELETE' ? t('Deleted a Role') : t('Updated a Role')
    }
    if (path === 'admin/roles') {
        return t('Created a New Role')
    }
    if (/^admin\/permissions\/roles(\/\d+)?$/.test(path)) {
        return t('Updated Role Permissions')
    }
    if (/^admin\/users\/\d+$/.test(path)) {
        return method === 'DELETE' ? t('Deleted a User') : t('Updated a User')
    }
    if (path === 'admin/users') {
        return t('Created a New User')
    }
    if (/^admin\/blog\/posts\/\d+/.test(path)) {
        return method === 'DELETE' ? t('Deleted a Blog Post') : t('Updated a Blog Post')
    }
    if (path === 'admin/blog/posts' || /^admin\/blog\/posts$/.test(path)) {
        return t('Created a Blog Post')
    }
    if (/^admin\/blog\/categories\/\d+$/.test(path)) {
        return method === 'DELETE' ? t('Deleted a Category') : t('Updated a Category')
    }
    if (path === 'admin/blog/categories') {
        return t('Created a Category')
    }
    if (/^admin\/tickets\/\d+/.test(path)) {
        return t('Updated a Support Ticket')
    }
    if (/^admin\/pages\/\d+/.test(path)) {
        return method === 'DELETE' ? t('Deleted a Page') : t('Updated a Page')
    }
    if (path === 'admin/pages') {
        return t('Created a New Page')
    }
    if (/^admin\/menu\/\d+/.test(path)) {
        return t('Updated a Menu')
    }
    if (path === 'admin/system/cache') {
        return t('Cleared System Cache')
    }
    if (/^admin\/system\/maintenance/.test(path)) {
        return t('Updated Maintenance Settings')
    }
    if (/^admin\/mail/.test(path)) {
        return t('Updated Mail Settings')
    }
    if (/^admin\/currencies/.test(path)) {
        return t('Updated Currency Settings')
    }
    if (/^admin\/languages/.test(path)) {
        return t('Updated Language Settings')
    }
    if (/^admin\/gateways/.test(path)) {
        return t('Updated Payment Gateways')
    }
    if (/^admin\/coupons/.test(path)) {
        return method === 'DELETE' ? t('Deleted a Coupon') : t('Updated a Coupon')
    }
    if (/^admin\/ai/.test(path)) {
        return t('Updated AI Settings')
    }
    if (/^admin\/appearance/.test(path)) {
        return t('Updated Appearance Settings')
    }

    const cleaned = path
        .replace(/^admin\//, '')
        .replace(/\/\d+/g, '/{id}')
        .replace(/\//g, ' › ')
        .replace(/-/g, ' ')

    return t(':method :path', { method, path: cleaned })
}

function formatDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date))
}

function timeAgo(time: string): string {
    const diff = Date.now() - new Date(time).getTime()
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)

    if (minutes < 60) return t(':count m ago', { count: String(minutes) })
    if (hours < 24) return t(':count h ago', { count: String(hours) })

    return t(':count d ago', { count: String(days) })
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

function closePayloadModal() {
    showPayloadModal.value = false
}

function toggleFilterDropdown() {
    filterDropdownOpen.value = !filterDropdownOpen.value
}

function closeFilterDropdown() {
    filterDropdownOpen.value = false
}

function clearSearch() {
    searchQuery.value = ''

    router.get(route('admin.activity.admin-logs.index'), {
        admin_id: props.filters.admin_id,
        date_from: props.filters.date_from,
        date_to: props.filters.date_to,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function focusActionSearchOnSlash(event: KeyboardEvent) {
    if (event.key !== '/' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    const target = event.target as HTMLElement | null

    if (target) {
        const tagName = target.tagName
        const isTypingContext = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)

        if (isTypingContext) {
            return
        }
    }

    event.preventDefault()
    actionSearchInputRef.value?.focus()
    actionSearchInputRef.value?.select()
}

function handleEscape(event: KeyboardEvent) {
    if (event.key !== 'Escape' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    if (filterDropdownOpen.value) {
        event.preventDefault()
        closeFilterDropdown()
        return
    }

    if (showPayloadModal.value) {
        event.preventDefault()
        closePayloadModal()
    }
}

function handleClickOutside(event: MouseEvent) {
    if (!filterDropdownOpen.value) {
        return
    }

    const target = event.target as Node | null

    if (filterDropdownRef.value && target && !filterDropdownRef.value.contains(target)) {
        closeFilterDropdown()
    }
}

onMounted(() => {
    document.addEventListener('keydown', focusActionSearchOnSlash)
    document.addEventListener('keydown', handleEscape)
    document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', focusActionSearchOnSlash)
    document.removeEventListener('keydown', handleEscape)
    document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<template>
    <Head :title="t('Admin Activity Logs')" />

    <div class="py-6">
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Admin Activity Logs') }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Review administrator changes from the last 30 days, inspect request metadata, and trace sensitive system updates from one place.') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('admin.system.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back') }}
                    </Link>

                    <div class="inline-flex gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <Link
                            :href="route('admin.activity.admin-logs.index')"
                            class="inline-flex items-center justify-center rounded-lg bg-primary-50 px-4 py-2 text-sm font-medium text-primary-700 transition-colors dark:bg-primary-900/30 dark:text-primary-300"
                        >
                            {{ t('Admin') }}
                        </Link>
                        <Link
                            :href="route('admin.activity.user-logs.index')"
                            class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/60"
                        >
                            {{ t('User') }}
                        </Link>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <form method="GET" :action="route('admin.activity.admin-logs.index')" class="flex flex-col gap-4">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="w-full xl:max-w-md">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                        <i class="ti ti-search text-base"></i>
                                    </span>
                                    <input
                                        ref="actionSearchInputRef"
                                        v-model="searchQuery"
                                        type="text"
                                        name="action"
                                        :placeholder="t('Filter by method, path, or keyword...')"
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    >
                                    <button
                                        v-if="searchQuery"
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        :aria-label="t('Clear search')"
                                        :title="t('Clear search')"
                                        @click="clearSearch"
                                    >
                                        <i class="ti ti-x text-base"></i>
                                    </button>
                                    <span
                                        v-else
                                        class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                                    >
                                        /
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 xl:justify-end">
                                <div class="relative" ref="filterDropdownRef">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        :aria-expanded="filterDropdownOpen"
                                        @click="toggleFilterDropdown"
                                    >
                                        <i class="ti ti-adjustments-horizontal text-base"></i>
                                        {{ t('Filters') }}
                                        <span
                                            v-if="filters.admin_id || filters.date_from || filters.date_to"
                                            class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary-100 px-1.5 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                        >
                                            {{ [filters.admin_id, filters.date_from, filters.date_to].filter(Boolean).length }}
                                        </span>
                                        <i :class="filterDropdownOpen ? 'ti ti-chevron-up' : 'ti ti-chevron-down'" class="text-sm"></i>
                                    </button>

                                    <div
                                        v-if="filterDropdownOpen"
                                        class="absolute right-0 z-20 mt-2 w-[min(92vw,22rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                    >
                                        <div class="space-y-4">
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Administrator') }}</label>
                                                <input type="hidden" name="admin_id" :value="adminFilter">
                                                <AppSelect
                                                    v-model="adminFilter"
                                                    :options="adminOptions"
                                                    :placeholder="t('All Admins')"
                                                    live-search
                                                    dropdown-placement="bottom"
                                                />
                                            </div>

                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('From') }}</label>
                                                    <input
                                                        type="date"
                                                        name="date_from"
                                                        :value="filters.date_from"
                                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                                    >
                                                </div>

                                                <div>
                                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('To') }}</label>
                                                    <input
                                                        type="date"
                                                        name="date_to"
                                                        :value="filters.date_to"
                                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                                    >
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-surface-800">
                                                <Link
                                                    :href="route('admin.activity.admin-logs.index')"
                                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <i class="ti ti-x text-base"></i>
                                                    {{ t('Clear Filters') }}
                                                </Link>

                                                <button
                                                    type="submit"
                                                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                                                >
                                                    <i class="ti ti-filter text-base"></i>
                                                    {{ t('Apply Filters') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3">{{ t('Administrator') }}</th>
                                <th class="px-6 py-3">{{ t('Action') }}</th>
                                <th class="px-6 py-3">{{ t('IP Address') }}</th>
                                <th class="px-6 py-3">{{ t('User Agent') }}</th>
                                <th class="px-6 py-3">{{ t('Date') }}</th>
                                <th class="px-6 py-3 text-right">{{ t('Payload') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    {{ t('No admin activity logs found.') }}
                                </td>
                            </tr>

                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-6 py-4">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900 dark:text-white">{{ log.admin_name }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ log.admin_email }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getMethodColor(log.action)"
                                    >
                                        {{ formatAction(log.action) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ log.ip_address }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="max-w-xs truncate" :title="log.user_agent || ''">
                                        {{ log.user_agent || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ timeAgo(log.created_at) }}</div>
                                    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ formatDate(log.created_at) }}</div>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <button
                                        v-if="log.payload"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-900/20"
                                        @click="viewPayload(log.payload)"
                                    >
                                        <i class="ti ti-file-text text-base"></i>
                                        {{ t('View') }}
                                    </button>
                                    <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="logs.links.length > 3"
                    class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6"
                >
                    <Pagination
                        :links="logs.links"
                        :from="logs.from"
                        :to="logs.to"
                        :total="logs.total"
                    />
                </div>
            </section>

            <div
                v-if="showPayloadModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm"
                @click.self="closePayloadModal"
            >
                <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                    <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-100 px-6 py-3 dark:border-surface-800">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Request Payload') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Inspect the captured request payload for this audit log entry.') }}</p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                            :aria-label="t('Close modal')"
                            @click="closePayloadModal"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <div v-if="formattedPayload.length" class="overflow-auto p-6">
                        <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-surface-700 dark:border-surface-700">
                            <div
                                v-for="item in formattedPayload"
                                :key="item.field"
                                class="flex items-start gap-4 px-4 py-3"
                            >
                                <span class="min-w-[140px] text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.field }}</span>
                                <span class="break-all text-sm text-gray-600 dark:text-gray-400">{{ item.value }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="overflow-auto p-6">
                        <pre class="overflow-auto rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-200"><code>{{ selectedPayload }}</code></pre>
                    </div>

                    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-3 dark:border-surface-800 dark:bg-surface-800/50">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="closePayloadModal"
                        >
                            {{ t('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
