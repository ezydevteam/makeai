<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'

defineOptions({ layout: AdminLayout })

interface AuditLog {
    id: number
    admin_id: number
    admin_name: string
    admin_email: string
    action: string
    action_label: string
    category: string
    category_label: string
    category_icon: string
    category_color: string
    method: string
    target: string | null
    ip_address: string
    user_agent: string | null
    payload: Record<string, unknown> | null
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
const selectedPayload = ref<Record<string, unknown> | null>(null)
const searchQuery = ref(props.filters.action ?? '')
const searchFocused = ref(false)
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

// Payload arrives already redacted + humanised-ready from the server (secrets
// masked, noise stripped). We only turn snake_case keys into readable labels.
const formattedPayload = computed(() => {
    const data = selectedPayload.value
    if (!data) {
        return []
    }

    return Object.entries(data).map(([key, value]) => ({
        field: key
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char: string) => char.toUpperCase()),
        value: typeof value === 'boolean'
            ? (value ? t('Yes') : t('No'))
            : value === null || value === undefined
                ? '-'
                : typeof value === 'object'
                    ? JSON.stringify(value)
                    : String(value),
    }))
})

function viewPayload(payload: Record<string, unknown> | null) {
    selectedPayload.value = payload
    showPayloadModal.value = true
}

// Category chip colours. Full class strings (not interpolated) so Tailwind's
// content scanner keeps them.
const chipClasses: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    pink: 'bg-pink-100 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
    fuchsia: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300',
    cyan: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-700/40 dark:text-gray-300',
}

const chipClass = (color: string): string => chipClasses[color] ?? chipClasses.gray

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
    if (event.key === 'Escape' && document.activeElement === actionSearchInputRef.value) {
        event.preventDefault()
        searchQuery.value = ''
        actionSearchInputRef.value?.blur()
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

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Admin Activity Logs') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review administrator changes from the last 30 days, inspect request metadata, and trace sensitive system updates from one place.') }}
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-3">
                <Link
                    :href="route('admin.system.health')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>

                <div class="inline-flex gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <Link
                        :href="route('admin.activity.admin-logs.index')"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white transition-colors"
                    >
                        {{ t('Admin') }}
                    </Link>
                    <Link
                        :href="route('admin.activity.user-logs.index')"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/60"
                    >
                        {{ t('User') }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                <form method="GET" :action="route('admin.activity.admin-logs.index')" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[220px] md:max-w-sm">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="actionSearchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    name="action"
                                    :placeholder="t('Search admin, email, IP, details...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    @focus="searchFocused = true"
                                    @blur="searchFocused = false"
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
                                    v-if="!searchQuery && !searchFocused"
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                                >/</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                            <div class="relative" ref="filterDropdownRef">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
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
                                                class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                            >
                                                <i class="ti ti-x text-base"></i>
                                                {{ t('Clear Filters') }}
                                            </Link>

                                            <button
                                                type="submit"
                                                class="grow btn-primary-admin inline-flex items-center justify-center gap-2"
                                            >
                                                <i class="ti ti-filter text-base"></i>
                                                {{ t('Apply Filters') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <Link
                                v-if="filters.admin_id || filters.date_from || filters.date_to || filters.action"
                                :href="route('admin.activity.admin-logs.index')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                            >
                                <i class="ti ti-rotate-clockwise text-base"></i>
                                {{ t('Reset') }}
                            </Link>
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
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                        :class="chipClass(log.category_color)"
                                        :title="log.category_label"
                                    >
                                        <i :class="[log.category_icon, 'text-sm']"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ log.action_label }}</p>
                                        <p v-if="log.target" class="truncate text-xs text-gray-500 dark:text-gray-400">{{ log.target }}</p>
                                    </div>
                                </div>
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
                                <Tooltip v-if="log.payload" :content="t('View payload')" placement="top">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950/30"
                                        @click="viewPayload(log.payload)"
                                    >
                                        <i class="ti ti-file-text text-base"></i>
                                    </button>
                                </Tooltip>
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

        <AppModal
            :open="showPayloadModal"
            max-width="max-w-3xl"
            :title="t('Request Payload')"
            :subtitle="t('Inspect the captured request payload for this audit log entry.')"
            :cancel-text="t('Close')"
            @close="closePayloadModal"
        >
            <div v-if="formattedPayload.length" class="space-y-4">
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
            <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:text-gray-400">
                {{ t('No payload details were captured for this action.') }}
            </div>
        </AppModal>
    </div>

</template>
