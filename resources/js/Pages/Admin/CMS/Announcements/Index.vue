<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

declare const route: (name: string, params?: Record<string, string | number>) => string

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Announcement {
    id: number
    type: 'topbar' | 'popup' | 'notification'
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
}

const props = defineProps<{
    announcements: { data: Announcement[]; links: PaginationLink[] }
    filters: { search?: string }
    totalCount: number
    activeCount: number
    topbarCount: number
    popupCount: number
}>()
const { t } = useTranslate()
const { formatDate } = useDateFormat()

const showForm = ref(false)
const editingId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)

const blank = (): Omit<Announcement, 'id'> => ({
    type: 'topbar',
    title: '',
    content: '',
    bg_color: '#4f46e5',
    text_color: '#ffffff',
    cta_text: '',
    cta_url: '',
    image: '',
    target_audience: 'all',
    trigger_type: '',
    trigger_value: '',
    show_frequency: 'session',
    is_active: true,
    starts_at: '',
    ends_at: '',
})

const form = useForm(blank())
const searchQuery = ref(props.filters.search || '')
const audienceFilter = ref('all')
const statusFilter = ref('all')
const filteredAnnouncements = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.announcements.data.filter((announcement) => {
        const matchesAudience = audienceFilter.value === 'all' || announcement.target_audience === audienceFilter.value
        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && announcement.is_active)
            || (statusFilter.value === 'inactive' && !announcement.is_active)

        const haystacks = [
            announcement.title ?? '',
            announcement.content ?? '',
            announcement.type ?? '',
            announcement.target_audience ?? '',
            announcement.show_frequency ?? '',
        ]

        const matchesSearch = !query || haystacks.some((value) => value.toLowerCase().includes(query))

        return matchesAudience && matchesStatus && matchesSearch
    })
})

const openCreate = () => {
    form.reset()
    Object.assign(form, blank())
    editingId.value = null
    showForm.value = true
}

const openEdit = (a: Announcement) => {
    form.type = a.type
    form.title = a.title ?? ''
    form.content = a.content ?? ''
    form.bg_color = a.bg_color ?? '#4f46e5'
    form.text_color = a.text_color ?? '#ffffff'
    form.cta_text = a.cta_text ?? ''
    form.cta_url = a.cta_url ?? ''
    form.image = a.image ?? ''
    form.target_audience = a.target_audience
    form.trigger_type = a.trigger_type ?? ''
    form.trigger_value = a.trigger_value ?? ''
    form.show_frequency = a.show_frequency
    form.is_active = a.is_active
    form.starts_at = a.starts_at ? new Date(a.starts_at).toISOString().slice(0, 16) : ''
    form.ends_at = a.ends_at ? new Date(a.ends_at).toISOString().slice(0, 16) : ''

    editingId.value = a.id
    showForm.value = true
}

const submit = () => {
    if (editingId.value) {
        form.post(route('admin.announcements.update', { announcement: editingId.value }), {
            onSuccess: () => { showForm.value = false },
        })
    } else {
        form.post(route('admin.announcements.store'), {
            onSuccess: () => { showForm.value = false },
        })
    }
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
    router.post(route('admin.announcements.active', { announcement: id }), {}, { preserveScroll: true })
}

const typeLabel: Record<string, string> = {
    topbar: t('Top Bar'),
    popup: t('Popup'),
    notification: t('Notification'),
}

const typeColor: Record<string, string> = {
    topbar: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    popup: 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
    notification: 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
}

const typeOptions = computed(() => [
    { value: 'topbar', label: t('Top Bar (Banner)') },
    { value: 'popup', label: t('Popup Modal') },
    { value: 'notification', label: t('In-App Notification') },
])

const audienceOptions = computed(() => [
    { value: 'all', label: t('Everyone') },
    { value: 'guests', label: t('Guests Only') },
    { value: 'auth', label: t('Logged In Users') },
    { value: 'free', label: t('Free Users') },
    { value: 'pro', label: t('Pro Users') },
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
</script>

<template>
    <Head :title="t('Announcements - Admin')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Announcements') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage sitewide banners, popups, and in-app notifications.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="openCreate" type="button" class="inline-flex items-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm">
                        <i class="ti ti-plus text-base"></i>
                        <span>{{ t('Add Announcement') }}</span>
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 border-b border-gray-100 p-5 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative w-full lg:max-w-sm">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                            </svg>
                        </span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            :placeholder="t('Search announcements...')"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                            @click="searchQuery = ''"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6 6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 lg:flex lg:w-auto lg:items-center">
                        <div class="min-w-[220px]">
                            <AppSelect v-model="audienceFilter" :options="audienceFilterOptions" />
                        </div>
                        <div class="min-w-[180px]">
                            <AppSelect v-model="statusFilter" :options="statusFilterOptions" />
                        </div>
                    </div>
                </div>

                <div v-if="filteredAnnouncements.length === 0" class="p-16 text-center">
                    <h3 class="mb-2 text-lg font-bold text-gray-900 dark:text-white">{{ t('No announcements yet') }}</h3>
                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">{{ t('Create promotional banners or important notices for your users.') }}</p>
                    <button @click="openCreate" type="button" class="rounded-lg btn-primary px-6 py-2.5 text-sm">{{ t('Add first announcement') }}</button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-4 py-3">{{ t('Announcement') }}</th>
                                <th class="px-4 py-3">{{ t('Type') }}</th>
                                <th class="px-4 py-3">{{ t('Audience') }}</th>
                                <th class="px-4 py-3">{{ t('Schedule') }}</th>
                                <th class="px-4 py-3">{{ t('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in filteredAnnouncements" :key="a.id" class="border-t border-gray-100 transition-colors hover:bg-primary-50/40 dark:border-gray-800 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-4">
                                    <div class="min-w-[240px]">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ a.title || t('Untitled') }}</div>
                                        <div class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400" v-html="a.content"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="typeColor[a.type]" class="inline-flex rounded-md px-2.5 py-1 text-[10px] font-bold uppercase">{{ typeLabel[a.type] }}</span>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ t(a.target_audience) }}</td>
                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                    <div>{{ t(a.show_frequency) }}</div>
                                    <div v-if="a.starts_at" class="mt-1">{{ t('Starts') }}: {{ formatDate(a.starts_at) }}</div>
                                    <div v-if="a.ends_at">{{ t('Ends') }}: {{ formatDate(a.ends_at) }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <button
                                        @click="toggleActive(a.id)"
                                        type="button"
                                        :class="a.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'"
                                        class="relative inline-flex h-5 w-9 rounded-full transition-colors"
                                    >
                                        <span :class="a.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none mt-0.5 ml-0.5 inline-block h-4 w-4 transform rounded-full bg-white shadow transition"></span>
                                    </button>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEdit(a)" type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:border-primary-300 hover:text-primary-600 dark:border-surface-700">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="remove(a.id)" type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-danger-500 transition-colors hover:bg-danger-50 dark:border-surface-700">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <Pagination :links="announcements.links" class="border-t border-gray-100 p-5 dark:border-gray-800" />
                </div>
            </div>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showForm = false">
            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingId ? t('Edit Announcement') : t('Add Announcement') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure the display, audience, and timing for this announcement.') }}</p>
                    </div>
                    <button @click="showForm = false" type="button" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-surface-700 dark:hover:bg-surface-800" :aria-label="t('Close')">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
                <div class="space-y-6 overflow-y-auto p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <section class="rounded-xl border border-gray-200 p-5 shadow-sm dark:border-surface-800">
                            <div class="grid gap-4">
                                <AppSelect v-model="form.type" :options="typeOptions" :label="t('Type')" />
                                <AppSelect v-model="form.target_audience" :options="audienceOptions" :label="t('Target audience')" />
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</span>
                                    <input v-model="form.title" type="text" :placeholder="t('Announcement title')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content (Supports HTML)') }}</span>
                                    <textarea v-model="form.content" rows="5" :placeholder="t('Write the announcement content')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                                </label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 p-5 shadow-sm dark:border-surface-800">
                            <div class="grid gap-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <AppColorPicker v-model="form.bg_color" :label="t('Background color')" />
                                    <AppColorPicker v-model="form.text_color" :label="t('Text color')" />
                                </div>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('CTA text') }}</span>
                                    <input v-model="form.cta_text" type="text" :placeholder="t('e.g. Learn More')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('CTA URL') }}</span>
                                    <input v-model="form.cta_url" type="text" :placeholder="t('https://example.com')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                </label>
                                <AppSelect v-model="form.show_frequency" :options="frequencyOptions" :label="t('Show frequency')" />
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium dark:border-surface-700">
                                    <span class="text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                                    <button
                                        type="button"
                                        :class="form.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'"
                                        class="relative inline-flex h-5 w-9 rounded-full transition-colors"
                                        @click="form.is_active = !form.is_active"
                                    >
                                        <span :class="form.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none mt-0.5 ml-0.5 inline-block h-4 w-4 transform rounded-full bg-white shadow transition"></span>
                                    </button>
                                </label>
                            </div>
                        </section>
                    </div>

                    <div v-if="form.type === 'popup'" class="rounded-xl border border-purple-100 bg-purple-50 p-5 dark:border-purple-900/30 dark:bg-purple-900/10">
                        <h4 class="mb-4 text-sm font-bold text-purple-800 dark:text-purple-300">{{ t('Popup Specific Settings') }}</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <AppSelect v-model="form.trigger_type" :options="triggerTypeOptions" :label="t('Trigger type')" />
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Trigger value') }}</span>
                                <input v-model="form.trigger_value" type="text" :placeholder="t('e.g. 5 or 50')" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Image URL (Optional banner)') }}</span>
                                <input v-model="form.image" type="text" :placeholder="t('https://example.com/image.jpg')" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                        </div>
                    </div>

                    <section class="rounded-xl border border-gray-200 p-5 shadow-sm dark:border-surface-800">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Start date (Optional)') }}</span>
                                <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('End date (Optional)') }}</span>
                                <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                        </div>
                    </section>
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 p-6 dark:border-surface-700 dark:bg-surface-800">
                    <button @click="showForm = false" type="button" class="rounded-lg px-5 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700">{{ t('Cancel') }}</button>
                    <button @click="submit" :disabled="form.processing" type="button" class="rounded-lg btn-primary px-6 py-2.5 text-sm disabled:opacity-50">
                        {{ form.processing ? t('Saving...') : editingId ? t('Save Changes') : t('Add Announcement') }}
                    </button>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete announcement?')"
            :message="t('This announcement will be deleted permanently.')"
            :confirm-label="t('Delete')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
