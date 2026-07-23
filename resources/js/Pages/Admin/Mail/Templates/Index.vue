<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: Record<string, string | number> | string | number) => string

interface MailTemplate {
    id: number
    slug: string
    name: string
    subject: string
    category: string
    is_active: boolean
    is_system: boolean
    requires_pro: boolean
    updated_at: string
    editor?: { id: number; name: string } | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface SelectOption {
    value: string
    label: string
}

const props = defineProps<{
    templates: {
        data: MailTemplate[]
        links: PaginationLink[]
        from: number | null
        to: number | null
        total: number
        current_page: number
        last_page: number
    }
    filters: {
        search?: string
        category?: string
        type?: string
    }
    categories: string[]
    proAvailable: boolean
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()

const search = ref(props.filters.search ?? '')
const searchFocused = ref(false)
const category = ref(props.filters.category ?? '')
const type = ref(props.filters.type ?? '')
const searchInput = ref<HTMLInputElement | null>(null)
const deleteTarget = ref<MailTemplate | null>(null)

const categoryOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((item) => ({ value: item, label: item })),
])

const typeOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Types') },
    { value: 'system', label: t('System') },
    { value: 'custom', label: t('Custom') },
    { value: 'active', label: t('Active') },
    { value: 'disabled', label: t('Disabled') },
    // Pro templates are filtered out of the list entirely without an Extended
    // License + subscriptions on, so offering the filter would only ever return
    // an empty table. The server ignores type=pro in that state too.
    ...(props.proAvailable ? [{ value: 'pro', label: t('Pro') }] : []),
])

const hasActiveFilters = computed(() => Boolean(search.value || category.value || type.value))

/**
 * Filtering runs on the server so it spans every page, not just the rows
 * currently rendered. Search is debounced; the selects fire immediately.
 */
let filterTimer: ReturnType<typeof setTimeout> | undefined

const applyFilters = () => {
    router.get(
        route('admin.mail.templates.index'),
        {
            search: search.value || undefined,
            category: category.value || undefined,
            type: type.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

// A single shared timer, so resetting every filter at once collapses into one
// request instead of a debounced search hit plus an immediate select hit.
const scheduleFilters = (delay: number) => {
    clearTimeout(filterTimer)
    filterTimer = setTimeout(applyFilters, delay)
}

watch(search, () => scheduleFilters(300))
watch([category, type], () => scheduleFilters(0))

onBeforeUnmount(() => clearTimeout(filterTimer))

const focusSearch = async () => {
    await nextTick()
    searchInput.value?.focus()
    searchInput.value?.select()
}

const clearFilters = () => {
    search.value = ''
    category.value = ''
    type.value = ''
}

const requestDelete = (template: MailTemplate) => {
    deleteTarget.value = template
}

const confirmDelete = () => {
    if (!deleteTarget.value) {
        return
    }

    router.delete(route('admin.mail.templates.delete', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null
        },
    })
}

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target
    const targetTag = target instanceof HTMLElement ? target.tagName : ''
    const isTypingTarget = target instanceof HTMLElement && (
        target.isContentEditable ||
        targetTag === 'INPUT' ||
        targetTag === 'TEXTAREA' ||
        targetTag === 'SELECT'
    )

    if (event.key === '/' && !isTypingTarget) {
        event.preventDefault()
        focusSearch()
        return
    }

    if (event.key === 'Escape' && document.activeElement === searchInput.value) {
        event.preventDefault()
        search.value = ''
        searchInput.value?.blur()
        return
    }

    if (event.key === 'Escape' && !deleteTarget.value && hasActiveFilters.value) {
        clearFilters()
    }
}

const typeBadgeClass = (template: MailTemplate) => template.is_system
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
    : 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'

const statusBadgeClass = (active: boolean) => active
    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
    : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Mail Templates')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Mail Templates') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage system notifications and custom email communications from one place.') }}</p>
                </div>

                <Link
                    :href="route('admin.mail.templates.create')"
                    class="shrink-0 btn-primary-admin inline-flex items-center justify-center gap-2"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('New Template') }}
                </Link>
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[220px] md:max-w-sm">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInput"
                                    v-model="search"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    :placeholder="t('Search mail templates...')"
                                    @focus="searchFocused = true"
                                    @blur="searchFocused = false"
                                >
                                <span
                                    v-if="!search && !searchFocused"
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                                >/</span>
                                <button
                                    v-if="search"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    :title="t('Clear search')"
                                    @click="search = ''"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-56 lg:flex-none">
                                <AppSelect v-model="category" :options="categoryOptions" :placeholder="t('All Categories')" />
                            </div>
                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-48 lg:flex-none">
                                <AppSelect v-model="type" :options="typeOptions" :placeholder="t('All Types')" />
                            </div>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 w-full sm:w-auto"
                                @click="clearFilters"
                            >
                                <i class="ti ti-rotate-clockwise text-base"></i>
                                {{ t('Reset') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-4">{{ t('Template') }}</th>
                                <th class="px-6 py-4">{{ t('Category') }}</th>
                                <th class="px-6 py-4">{{ t('Subject') }}</th>
                                <th class="px-6 py-4">{{ t('Status') }}</th>
                                <th class="px-6 py-4 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="template in templates.data"
                                :key="template.id"
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-6 py-5 align-top">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ template.name }}</p>
                                            <span :class="typeBadgeClass(template)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                                {{ template.is_system ? t('System') : t('Custom') }}
                                            </span>
                                            <span
                                                v-if="template.requires_pro"
                                                class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/20 dark:text-violet-300"
                                            >
                                                {{ t('Pro') }}
                                            </span>
                                        </div>
                                        <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ template.slug }}</p>
                                        <p v-if="template.editor" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            {{ t('Edited by :name', { name: template.editor.name }) }} · {{ formatDate(template.updated_at) }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-surface-800 dark:text-gray-300">
                                        {{ template.category }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <p class="max-w-md truncate text-sm text-gray-600 dark:text-gray-300">{{ template.subject }}</p>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <span :class="statusBadgeClass(template.is_active)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                        {{ template.is_active ? t('Active') : t('Disabled') }}
                                    </span>
                                </td>
                                <td class="overflow-visible px-6 py-5 align-top text-end">
                                    <TableActionMenu>
                                        <template #default="{ close }">
                                            <Link
                                                :href="route('admin.mail.templates.edit', template.id)"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                                @click="close"
                                            >
                                                <i class="ti ti-edit text-base"></i>
                                                {{ t('Edit Details') }}
                                            </Link>
                                            <hr v-if="!template.is_system" class="h-px bg-gray-100 dark:bg-surface-800" />
                                            <button
                                                v-if="!template.is_system"
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                @click="requestDelete(template); close()"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Delete') }}
                                            </button>
                                        </template>
                                    </TableActionMenu>
                                </td>
                            </tr>

                            <tr v-if="templates.data.length === 0">
                                <td colspan="5" class="px-6 py-14 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                            <i class="ti ti-mail-off text-2xl"></i>
                                        </div>
                                        <p class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-300">{{ t('No mail templates found.') }}</p>
                                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">{{ t('Try adjusting your search or filters.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="templates.links.length > 3" class="border-t border-gray-100 px-6 py-4 dark:border-surface-800">
                    <Pagination
                        :links="templates.links"
                        :from="templates.from"
                        :to="templates.to"
                        :total="templates.total"
                        :current-page="templates.current_page"
                        :last-page="templates.last_page"
                    />
                </div>
            </section>
    </div>

    <ActionConfirmModal
        :open="deleteTarget !== null"
        :title="t('Delete template?')"
        :message="t('This mail template will be deleted permanently.')"
        :confirm-label="t('Delete')"
        @cancel="deleteTarget = null"
        @confirm="confirmDelete"
    />
</template>
