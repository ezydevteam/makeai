<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { fromDateTimeLocalInput, toDateTimeLocalInput } from '@/lib/datetime'
import { mediaUrl } from '@/lib/media'

declare const route: (name: string, params?: Record<string, string | number>) => string

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Announcement {
    id: number
    type: 'topbar' | 'popup' | 'bottom_popup' | 'notification'
    title: string | null
    content: string | null
    bg_color: string | null
    text_color: string | null
    cta_text: string | null
    cta_url: string | null
    image: string | null
    target_audience: 'all' | 'guests' | 'auth' | 'free' | 'pro'
    trigger_type: string | null
    trigger_value: string | null
    show_frequency: 'always' | 'session' | 'once'
    is_active: boolean
    starts_at: string | null
    ends_at: string | null
    creator?: { id: number; name: string } | null
}

interface AnnouncementFormData {
    type: Announcement['type']
    title: string
    content: string
    bg_color: string
    text_color: string
    cta_text: string
    cta_url: string
    image: string
    image_file: File | null
    target_audience: Announcement['target_audience']
    trigger_type: string
    trigger_value: string
    show_frequency: Announcement['show_frequency']
    is_active: boolean
    starts_at: string
    ends_at: string
}

const props = defineProps<{
    announcements: { data: Announcement[]; links: PaginationLink[]; from?: number; to?: number; total?: number }
    filters: { search?: string; audience?: string; status?: string }
    totalCount: number
    activeCount: number
    topbarCount: number
    popupCount: number
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const createBlankAnnouncement = (): AnnouncementFormData => ({
    type: 'topbar',
    title: '',
    content: '',
    bg_color: '#4f46e5',
    text_color: '#ffffff',
    cta_text: '',
    cta_url: '',
    image: '',
    image_file: null,
    target_audience: 'all',
    trigger_type: '',
    trigger_value: '',
    show_frequency: 'session',
    is_active: true,
    starts_at: '',
    ends_at: '',
})

const form = useForm<AnnouncementFormData>(createBlankAnnouncement())
const imagePreviewUrl = ref<string | null>(null)
const showForm = ref(false)
const editingId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const searchQuery = ref(props.filters.search ?? '')
const audienceFilter = ref(props.filters.audience ?? 'all')
const statusFilter = ref(props.filters.status ?? 'all')
const searchFocused = ref(false)
const filterDebounce = ref<number | null>(null)

const fillForm = (values: AnnouncementFormData) => {
    form.type = values.type
    form.title = values.title
    form.content = values.content
    form.bg_color = values.bg_color
    form.text_color = values.text_color
    form.cta_text = values.cta_text
    form.cta_url = values.cta_url
    form.image = values.image
    form.image_file = null
    form.target_audience = values.target_audience
    form.trigger_type = values.trigger_type
    form.trigger_value = values.trigger_value
    form.show_frequency = values.show_frequency
    form.is_active = values.is_active
    form.starts_at = values.starts_at
    form.ends_at = values.ends_at
}

const handleImageUpload = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null

    form.image_file = file
    form.clearErrors('image_file')
    imagePreviewUrl.value = file ? URL.createObjectURL(file) : (form.image ? mediaUrl(form.image) : null)
}

const typeLabel: Record<Announcement['type'], string> = {
    topbar: t('Top Bar'),
    popup: t('Popup'),
    bottom_popup: t('Bottom Popup'),
    notification: t('Notification'),
}

const typeColor: Record<Announcement['type'], string> = {
    topbar: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
    popup: 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300',
    bottom_popup: 'bg-teal-50 text-teal-700 dark:bg-teal-900/20 dark:text-teal-300',
    notification: 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300',
}

const audienceLabel: Record<Announcement['target_audience'], string> = {
    all: t('Everyone'),
    guests: t('Guests Only'),
    auth: t('Logged In Users'),
    free: t('Free Users'),
    pro: t('Pro Users'),
}

const frequencyLabel: Record<Announcement['show_frequency'], string> = {
    always: t('Always Show'),
    session: t('Once per session'),
    once: t('Once per user'),
}

const typeOptions = computed(() => [
    { value: 'topbar', label: t('Top Bar (Banner)') },
    { value: 'popup', label: t('Popup Modal') },
    { value: 'bottom_popup', label: t('Bottom Popup (Corner Card)') },
    { value: 'notification', label: t('In-App Notification') },
])

const audienceOptions = computed(() => [
    { value: 'all', label: t('Everyone') },
    { value: 'guests', label: t('Guests Only') },
    { value: 'auth', label: t('Logged In Users') },
    // Free/Pro targeting only makes sense when a paid tier exists.
    ...(isProAvailable.value ? [
        { value: 'free', label: t('Free Users') },
        { value: 'pro', label: t('Premium Users') },
    ] : []),
])

const triggerTypeOptions = computed(() => [
    { value: '', label: t('On Load') },
    { value: 'delay', label: t('Delay') },
    { value: 'scroll', label: t('Scroll %') },
    { value: 'exit', label: t('Exit Intent') },
])

const frequencyOptions = computed(() => [
    { value: 'always', label: t('Always Show') },
    { value: 'session', label: t('Once per session') },
    { value: 'once', label: t('Once per user') },
])

const audienceFilterOptions = computed(() => [
    { value: 'all', label: t('All audiences') },
    ...audienceOptions.value.filter((option) => option.value !== 'all'),
])

const statusFilterOptions = computed(() => [
    { value: 'all', label: t('All statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
])

const buildFilterQuery = () => {
    const query: Record<string, string> = {}

    if (searchQuery.value.trim()) {
        query.search = searchQuery.value.trim()
    }

    if (audienceFilter.value !== 'all') {
        query.audience = audienceFilter.value
    }

    if (statusFilter.value !== 'all') {
        query.status = statusFilter.value
    }

    return query
}

const applyFilters = () => {
    router.get(route('admin.announcements.index'), buildFilterQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([searchQuery, audienceFilter, statusFilter], () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
    filterDebounce.value = window.setTimeout(() => {
        applyFilters()
    }, 250) as unknown as number
})

watch(() => props.filters, (filters) => {
    searchQuery.value = filters.search ?? ''
    audienceFilter.value = filters.audience ?? 'all'
    statusFilter.value = filters.status ?? 'all'
}, { deep: true })

const filteredAnnouncements = computed(() => props.announcements.data)

const hasActiveFilters = computed(() => {
    return searchQuery.value.trim().length > 0 || audienceFilter.value !== 'all' || statusFilter.value !== 'all'
})

const clearSearchAndFilters = () => {
    searchQuery.value = ''
    audienceFilter.value = 'all'
    statusFilter.value = 'all'
    searchInputRef.value?.blur()
}

const stripHtml = (value: string | null) => {
    const source = value ?? ''
    if (typeof window === 'undefined') {
        return source.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
    }

    const container = document.createElement('div')
    container.innerHTML = source
    return (container.textContent ?? container.innerText ?? '').replace(/\s+/g, ' ').trim()
}

const excerptContent = (value: string | null) => {
    const plain = stripHtml(value)
    if (plain.length <= 120) {
        return plain
    }

    return `${plain.slice(0, 117)}...`
}

const openCreate = () => {
    form.clearErrors()
    form.reset()
    fillForm(createBlankAnnouncement())
    imagePreviewUrl.value = null
    editingId.value = null
    showForm.value = true
}

const openEdit = (announcement: Announcement) => {
    form.clearErrors()
    fillForm({
        type: announcement.type,
        title: announcement.title ?? '',
        content: announcement.content ?? '',
        bg_color: announcement.bg_color ?? '#4f46e5',
        text_color: announcement.text_color ?? '#ffffff',
        cta_text: announcement.cta_text ?? '',
        cta_url: announcement.cta_url ?? '',
        image: announcement.image ?? '',
        image_file: null,
        target_audience: announcement.target_audience,
        trigger_type: announcement.trigger_type ?? '',
        trigger_value: announcement.trigger_value ?? '',
        show_frequency: announcement.show_frequency,
        is_active: announcement.is_active,
        starts_at: toDateTimeLocalInput(announcement.starts_at),
        ends_at: toDateTimeLocalInput(announcement.ends_at),
    })

    imagePreviewUrl.value = announcement.image ? mediaUrl(announcement.image) : null
    editingId.value = announcement.id
    showForm.value = true
}

const closeForm = () => {
    if (form.processing) {
        return
    }

    showForm.value = false
}

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false
        },
    }

    // The pickers hold local wall clock; the server stores instants. Without this the
    // moment shifts by the admin's UTC offset — a schedule set for today lands hours
    // out and the list renders it on the following day.
    form.transform((data) => ({
        ...data,
        starts_at: fromDateTimeLocalInput(data.starts_at),
        ends_at: fromDateTimeLocalInput(data.ends_at),
    }))

    if (editingId.value) {
        form.post(route('admin.announcements.update', { announcement: editingId.value }), options)
        return
    }

    form.post(route('admin.announcements.store'), options)
}

const remove = (id: number) => {
    deleteTargetId.value = id
}

const confirmDelete = () => {
    if (deleteTargetId.value === null) {
        return
    }

    router.delete(route('admin.announcements.delete', { announcement: deleteTargetId.value }), {
        preserveScroll: true,
        onFinish: () => {
            deleteTargetId.value = null
        },
    })
}

const toggleActive = (id: number) => {
    router.post(route('admin.announcements.active', { announcement: id }), {}, {
        preserveScroll: true,
    })
}

const focusSearchOnSlash = (event: KeyboardEvent) => {
    if (event.key !== '/' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    const target = event.target as HTMLElement | null
    const tagName = target?.tagName ?? ''
    const isTypingContext = Boolean(target?.isContentEditable) || ['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)

    if (isTypingContext) {
        return
    }

    event.preventDefault()
    searchInputRef.value?.focus()
    searchInputRef.value?.select()
}

const clearFiltersOnEscape = (event: KeyboardEvent) => {
    if (event.key !== 'Escape' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    if (showForm.value || deleteTargetId.value !== null) {
        return
    }

    if (!hasActiveFilters.value) {
        return
    }

    event.preventDefault()
    clearSearchAndFilters()
}

onMounted(() => {
    document.addEventListener('keydown', focusSearchOnSlash)
    document.addEventListener('keydown', clearFiltersOnEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', focusSearchOnSlash)
    document.removeEventListener('keydown', clearFiltersOnEscape)
})
</script>

<template>
    <Head :title="t('Announcements')" />

    <AdminLayout>
        <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Announcements') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage sitewide banners, popup campaigns, and in-app notifications.') }}</p>
                </div>

                <button
                    type="button"
                    class="btn-primary-admin shrink-0 inline-flex items-center justify-center gap-2"
                    @click="openCreate"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Add Announcement') }}
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="w-full min-w-[220px] md:max-w-sm">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    :placeholder="t('Search announcements...')"
                                    @focus="searchFocused = true"
                                    @blur="searchFocused = false"
                                />
                                <span
                                    v-if="!searchQuery && !searchFocused"
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                                >
                                    /
                                </span>
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    :title="t('Clear search')"
                                    @click="searchQuery = ''"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end shrink-0">
                            <div class="w-full sm:w-48 sm:flex-none">
                                <AppSelect v-model="audienceFilter" :options="audienceFilterOptions" />
                            </div>

                            <div class="w-full sm:w-44 sm:flex-none">
                                <AppSelect v-model="statusFilter" :options="statusFilterOptions" />
                            </div>

                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 w-full sm:w-auto"
                                @click="clearSearchAndFilters"
                            >
                                <i class="ti ti-rotate-clockwise text-base"></i>
                                {{ t('Reset') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="filteredAnnouncements.length === 0" class="px-6 py-14 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                        <i class="ti ti-speakerphone text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">
                        {{ props.totalCount === 0 ? t('No announcements yet') : t('No matching announcements found') }}
                    </h3>
                    <p class="mx-auto mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                        {{
                            props.totalCount === 0
                                ? t('Create promotional banners, popup campaigns, or notices for your users.')
                                : t('Try another search term or clear the active audience and status filters.')
                        }}
                    </p>
                    <div class="mt-6 flex justify-center gap-3">
                        <button
                            v-if="props.totalCount === 0"
                            type="button"
                            class="rounded-xl px-5 py-2.5 text-sm font-medium text-white btn-primary-admin"
                            @click="openCreate"
                        >
                            <i class="ti ti-plus"></i>
                            {{ t('Add First Announcement') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="clearSearchAndFilters"
                        >
                            {{ t('ResetFilters') }}
                        </button>
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left">{{ t('Announcement') }}</th>
                                <th scope="col" class="px-6 py-3 text-center">{{ t('Type') }}</th>
                                <th scope="col" class="px-6 py-3 text-center">{{ t('Audience') }}</th>
                                <th scope="col" class="px-6 py-3 text-center">{{ t('Schedule') }}</th>
                                <th scope="col" class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ t('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="announcement in filteredAnnouncements"
                                :key="announcement.id"
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-primary-50/40 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-primary-900/5 align-top"
                            >
                                <td class="px-6 py-4">
                                    <div class="min-w-[260px]">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ announcement.title || t('Untitled') }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ excerptContent(announcement.content) || t('No content added yet.') }}
                                        </p>
                                        <p v-if="announcement.creator" class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                            {{ t('by :name', { name: announcement.creator.name }) }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="typeColor[announcement.type]" class="inline-flex items-center justify-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em]">
                                        {{ typeLabel[announcement.type] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center justify-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-surface-800 dark:text-gray-200">
                                        {{ audienceLabel[announcement.target_audience] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-xs text-gray-500 dark:text-gray-400">
                                    <div>{{ frequencyLabel[announcement.show_frequency] }}</div>
                                    <div v-if="announcement.starts_at" class="mt-1">{{ t('Starts') }}: {{ formatDate(announcement.starts_at) }}</div>
                                    <div v-if="announcement.ends_at">{{ t('Ends') }}: {{ formatDate(announcement.ends_at) }}</div>
                                </td>
                                <td class="px-6 py-4 text-center text-center">
                                    <AppSwitch :model-value="announcement.is_active" @update:model-value="toggleActive(announcement.id)" />
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Tooltip :content="t('Edit announcement')" placement="top">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-100 bg-white text-gray-500 shadow-sm transition-all hover:bg-gray-50 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-primary-400"
                                                @click="openEdit(announcement)"
                                            >
                                                <i class="ti ti-edit text-base"></i>
                                            </button>
                                        </Tooltip>
                                        <Tooltip :content="t('Delete announcement')" placement="top">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-100 bg-red-50 text-red-600 shadow-sm transition-all hover:bg-red-100 hover:text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400 dark:hover:bg-red-900/30"
                                                @click="remove(announcement.id)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </Tooltip>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="announcements.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <Pagination
                        :links="announcements.links"
                        :from="announcements.from"
                        :to="announcements.to"
                        :total="announcements.total"
                    />
                </div>
            </div>
        </div>


        <AppModal
            :open="showForm"
            max-width="max-w-5xl"
            :title="editingId ? t('Edit Announcement') : t('Add Announcement')"
            :subtitle="t('Configure placement, audience, timing, and call to action details.')"
            has-form
            :confirm-text="editingId ? t('Save Changes') : t('Add Announcement')"
            :confirm-loading="form.processing"
            confirm-loading-text="Saving..."
            @close="closeForm"
            @submit="submit"
        >
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="grid gap-4">
                        <AppSelect v-model="form.type" :options="typeOptions" :label="t('Type')" />
                        <AppSelect v-model="form.target_audience" :options="audienceOptions" :label="t('Target Audience')" />

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Title') }}</span>
                            <input
                                v-model="form.title"
                                type="text"
                                :placeholder="t('Announcement title')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                            <p v-if="form.errors.title" class="mt-1 text-xs text-danger-600">{{ form.errors.title }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Content (Supports HTML)') }}</span>
                            <textarea
                                v-model="form.content"
                                rows="6"
                                :placeholder="t('Write the announcement content')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            ></textarea>
                            <p v-if="form.errors.content" class="mt-1 text-xs text-danger-600">{{ form.errors.content }}</p>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="grid gap-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <AppColorPicker v-model="form.bg_color" :label="t('Background Color')" />
                            <AppColorPicker v-model="form.text_color" :label="t('Text Color')" />
                        </div>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Button Text') }}</span>
                            <input
                                v-model="form.cta_text"
                                type="text"
                                :placeholder="t('e.g. Learn More')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                            <p v-if="form.errors.cta_text" class="mt-1 text-xs text-danger-600">{{ form.errors.cta_text }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Button URL') }}</span>
                            <input
                                v-model="form.cta_url"
                                type="text"
                                :placeholder="t('https://example.com')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                            <p v-if="form.errors.cta_url" class="mt-1 text-xs text-danger-600">{{ form.errors.cta_url }}</p>
                        </label>

                        <AppSelect v-model="form.show_frequency" :options="frequencyOptions" :label="t('Show Frequency')" />

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="flex items-center gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        {{ form.is_active ? t('Announcement is active') : t('Announcement is inactive') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Inactive announcements stay saved but do not display on the site.') }}
                                    </p>
                                </div>

                                <AppSwitch v-model="form.is_active" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-if="form.type === 'popup' || form.type === 'bottom_popup'" class="mt-6 rounded-xl border border-violet-100 bg-violet-50 p-5 dark:border-violet-900/30 dark:bg-violet-900/10">
                <h4 class="text-sm font-bold text-violet-800 dark:text-violet-300">
                    {{ form.type === 'bottom_popup' ? t('Bottom Popup Specific Settings') : t('Popup Specific Settings') }}
                </h4>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <AppSelect v-model="form.trigger_type" :options="triggerTypeOptions" :label="t('Trigger Type')" />

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Trigger Value') }}</span>
                        <input
                            v-model="form.trigger_value"
                            type="text"
                            :placeholder="t('e.g. 5 or 50')"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <p v-if="form.errors.trigger_value" class="mt-1 text-xs text-danger-600">{{ form.errors.trigger_value }}</p>
                    </label>

                    <div class="md:col-span-2">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Image (Optional)') }}</span>
                        <input
                            type="file"
                            accept="image/*"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-100 file:px-3 file:py-1.5 file:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:file:bg-primary-900/20 dark:file:text-primary-300"
                            @change="handleImageUpload"
                        >
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Upload JPG, PNG, WEBP, or GIF up to 5 MB.') }}</p>
                        <div v-if="imagePreviewUrl" class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800">
                            <img :src="imagePreviewUrl" :alt="t('Announcement image preview')" class="h-40 w-full object-cover">
                        </div>
                        <p v-if="form.errors.image_file" class="mt-1 text-xs text-danger-600">{{ form.errors.image_file }}</p>
                        <p v-if="form.errors.image" class="mt-1 text-xs text-danger-600">{{ form.errors.image }}</p>
                    </div>
                </div>
            </div>

            <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Start Date (Optional)') }}</span>
                        <input
                            v-model="form.starts_at"
                            type="datetime-local"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <p v-if="form.errors.starts_at" class="mt-1 text-xs text-danger-600">{{ form.errors.starts_at }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('End Date (Optional)') }}</span>
                        <input
                            v-model="form.ends_at"
                            type="datetime-local"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <p v-if="form.errors.ends_at" class="mt-1 text-xs text-danger-600">{{ form.errors.ends_at }}</p>
                    </label>
                </div>
            </section>
        </AppModal>


        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete announcement?')"
            :message="t('This announcement will be deleted permanently.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
