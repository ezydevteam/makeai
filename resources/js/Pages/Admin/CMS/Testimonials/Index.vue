<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'

declare const route: (name: string, params?: Record<string, string | number>) => string

defineOptions({ layout: AdminLayout })

interface Testimonial {
    id: number
    name: string
    role: string | null
    company: string | null
    avatar: string | null
    content: string
    rating: number
    is_active: boolean
    is_featured: boolean
    sort_order: number
    source: 'manual' | 'google' | 'trustpilot' | 'ai'
    show_source: boolean
}

interface TestimonialForm {
    name: string
    role: string
    company: string
    avatar: string
    avatar_file: File | null
    content: string
    rating: number
    is_active: boolean
    is_featured: boolean
    sort_order: number
    source: Testimonial['source']
    show_source: boolean
}

const props = defineProps<{ testimonials: Testimonial[] }>()

const { t } = useTranslate()

const showForm = ref(false)
const editingId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const selectedStatus = ref<'all' | 'active' | 'inactive' | 'featured'>('all')
const selectedSource = ref<'all' | Testimonial['source']>('all')
const avatarPreview = ref<string | null>(null)

const ratingOptions = [5, 4, 3, 2, 1].map((value) => ({
    value,
    label: t(':count Star', { count: String(value) }),
}))

const sourceOptions = [
    { value: 'manual', label: t('Manual') },
    { value: 'google', label: t('Google') },
    { value: 'trustpilot', label: t('Trustpilot') },
    { value: 'ai', label: t('AI') },
]

const statusOptions = [
    { value: 'all', label: t('All Status') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
    { value: 'featured', label: t('Featured') },
]

const filterSourceOptions = [
    { value: 'all', label: t('All Sources') },
    ...sourceOptions,
]

const sourceClasses: Record<Testimonial['source'], string> = {
    manual: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
    google: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300',
    trustpilot: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300',
    ai: 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300',
}

const blank = (): TestimonialForm => ({
    name: '',
    role: '',
    company: '',
    avatar: '',
    avatar_file: null,
    content: '',
    rating: 5,
    is_active: true,
    is_featured: false,
    sort_order: props.testimonials.length,
    source: 'manual',
    show_source: false,
})

const form = useForm(blank())

const sortedTestimonials = computed(() =>
    [...props.testimonials].sort((left, right) => {
        if (left.sort_order !== right.sort_order) {
            return left.sort_order - right.sort_order
        }

        return right.id - left.id
    }),
)

const filteredTestimonials = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return sortedTestimonials.value.filter((testimonial) => {
        const matchesSearch = !query || [
            testimonial.name,
            testimonial.role ?? '',
            testimonial.company ?? '',
            testimonial.content,
            sourceLabel(testimonial.source),
        ].join(' ').toLowerCase().includes(query)

        const matchesStatus = selectedStatus.value === 'all'
            || (selectedStatus.value === 'active' && testimonial.is_active)
            || (selectedStatus.value === 'inactive' && !testimonial.is_active)
            || (selectedStatus.value === 'featured' && testimonial.is_featured)

        const matchesSource = selectedSource.value === 'all' || testimonial.source === selectedSource.value

        return matchesSearch && matchesStatus && matchesSource
    })
})

const hasActiveFilters = computed(() => Boolean(searchQuery.value || selectedStatus.value !== 'all' || selectedSource.value !== 'all'))

const openCreate = () => {
    form.reset()
    Object.assign(form, blank())
    avatarPreview.value = null
    editingId.value = null
    showForm.value = true
}

const openEdit = (testimonial: Testimonial) => {
    form.name = testimonial.name
    form.role = testimonial.role ?? ''
    form.company = testimonial.company ?? ''
    form.avatar = testimonial.avatar ?? ''
    form.avatar_file = null
    form.content = testimonial.content
    form.rating = testimonial.rating
    form.is_active = testimonial.is_active
    form.is_featured = testimonial.is_featured
    form.sort_order = testimonial.sort_order
    form.source = testimonial.source
    form.show_source = testimonial.show_source
    avatarPreview.value = resolveAvatar(testimonial.avatar)
    editingId.value = testimonial.id
    showForm.value = true
}

const closeForm = () => {
    showForm.value = false
    editingId.value = null
    avatarPreview.value = null
}

const submit = () => {
    if (editingId.value) {
        form.post(route('admin.testimonials.update', { testimonial: editingId.value }), {
            forceFormData: true,
            onSuccess: () => {
                closeForm()
            },
        })

        return
    }

    form.post(route('admin.testimonials.store'), {
        forceFormData: true,
        onSuccess: () => {
            closeForm()
        },
    })
}

const remove = (id: number) => {
    deleteTargetId.value = id
}

const confirmDelete = () => {
    if (deleteTargetId.value === null) {
        return
    }

    router.delete(route('admin.testimonials.delete', { testimonial: deleteTargetId.value }), {
        preserveScroll: true,
        onFinish: () => {
            deleteTargetId.value = null
        },
    })
}

const toggleFeatured = (id: number) => {
    router.post(route('admin.testimonials.featured', { testimonial: id }), {}, { preserveScroll: true })
}

const toggleActive = (id: number) => {
    router.post(route('admin.testimonials.active', { testimonial: id }), {}, { preserveScroll: true })
}

const clearFilters = () => {
    searchQuery.value = ''
    selectedStatus.value = 'all'
    selectedSource.value = 'all'
}

const isTypingTarget = (target: EventTarget | null) => {
    if (!(target instanceof HTMLElement)) {
        return false
    }

    const tagName = target.tagName.toLowerCase()

    return tagName === 'input'
        || tagName === 'textarea'
        || tagName === 'select'
        || target.isContentEditable
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === '/') {
        if (event.metaKey || event.ctrlKey || event.altKey || isTypingTarget(event.target)) {
            return
        }

        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key !== 'Escape') {
        return
    }

    if (deleteTargetId.value !== null) {
        deleteTargetId.value = null
        return
    }

    if (showForm.value) {
        closeForm()
        return
    }

    if (isTypingTarget(event.target) && event.target !== searchInput.value) {
        return
    }

    if (hasActiveFilters.value) {
        clearFilters()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
})



const handleAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    form.avatar_file = file
    avatarPreview.value = file ? URL.createObjectURL(file) : resolveAvatar(form.avatar)
}

const resolveAvatar = (avatar: string | null): string => mediaUrl(avatar)

const initials = (name: string) => {
    return name.trim().charAt(0).toUpperCase() || 'U'
}

const sourceLabel = (source: Testimonial['source']) => {
    return ({
        manual: t('Manual'),
        google: t('Google'),
        trustpilot: t('Trustpilot'),
        import: t('Import'),
        ai: t('AI'),
    })[source]
}

const stars = (count: number) => Array.from({ length: 5 }, (_, index) => index < count)
</script>

<template>
    <Head :title="t('Testimonials')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Testimonials') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage customer proof for your homepage and marketing sections from one consistent admin workspace.') }}
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <button
                    type="button"
                    class="btn-primary-admin grow inline-flex items-center justify-center gap-2"
                    @click="openCreate"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Add Testimonial') }}
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                    <div class="flex-1 w-full lg:max-w-xs lg:min-w-[280px] relative">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInput"
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('Search testimonials, company, role, or review')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                            <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                <span v-if="!searchQuery" class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500">/</span>
                            </div>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                @click="searchQuery = ''"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto justify-start lg:justify-end shrink-0">
                        <div class="w-[calc(50%-6px)] sm:w-44">
                            <AppSelect
                                v-model="selectedStatus"
                                :options="statusOptions"
                                :placeholder="t('All Status')"
                            />
                        </div>
                        <div class="w-[calc(50%-6px)] sm:w-44">
                            <AppSelect
                                v-model="selectedSource"
                                :options="filterSourceOptions"
                                :placeholder="t('All Sources')"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="filteredTestimonials.length === 0" class="px-6 py-16 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl dark:bg-emerald-900/20">
                        <i class="ti ti-message-2 text-2xl text-emerald-600 dark:text-emerald-300"></i>
                    </div>
                    <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">
                        {{ hasActiveFilters ? t('No testimonials match these filters') : t('No testimonials yet') }}
                    </h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                        {{ hasActiveFilters
                            ? t('Try clearing the current search or filters to see more testimonials.')
                            : t('Create your first testimonial to start building social proof across the homepage and landing sections.') }}
                    </p>
                    <button
                        type="button"
                        class="btn-primary-admin mt-6 inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
                        @click="hasActiveFilters ? clearFilters() : openCreate()"
                    >
                        <i :class="hasActiveFilters ? 'ti ti-x' : 'ti ti-plus'"></i>
                        <span>{{ hasActiveFilters ? t('Clear Filters') : t('Add First Testimonial') }}</span>
                    </button>
            </div>

            <div v-else class="overflow-hidden rounded-b-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Customer') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Review') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Source') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Order') }}</th>
                                <th class="px-6 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr
                                v-for="testimonial in filteredTestimonials"
                                :key="testimonial.id"
                                class="transition hover:bg-primary-50/60 dark:hover:bg-primary-900/10"
                            >
                                <td class="px-6 py-5 align-top">
                                    <div class="flex items-start gap-3">
                                        <div
                                            v-if="testimonial.avatar"
                                            class="h-11 w-11 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800"
                                        >
                                            <img
                                                :src="resolveAvatar(testimonial.avatar)"
                                                :alt="testimonial.name"
                                                class="h-full w-full object-cover"
                                            >
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                        >
                                            {{ initials(testimonial.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ testimonial.name }}</div>
                                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ [testimonial.role, testimonial.company].filter(Boolean).join(' · ') || t('No role or company') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <div class="max-w-xl">
                                        <div class="mb-2 flex items-center gap-1">
                                            <svg
                                                v-for="(filled, index) in stars(testimonial.rating)"
                                                :key="index"
                                                class="h-4 w-4"
                                                :class="filled ? 'text-amber-400' : 'text-gray-200 dark:text-surface-700'"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        </div>
                                        <p class="line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ testimonial.content }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <span :class="sourceClasses[testimonial.source]" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                        {{ sourceLabel(testimonial.source) }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <div class="flex flex-col items-start gap-2">
                                        <span
                                            :class="testimonial.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'"
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        >
                                            {{ testimonial.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                        <span
                                            v-if="testimonial.is_featured"
                                            class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
                                        >
                                            {{ t('Featured') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 align-top text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ testimonial.sort_order }}
                                </td>
                                    <td class="overflow-visible px-6 py-5 align-top text-end">
                                        <TableActionMenu>
                                            <template #default="{ close }">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                                    @click="openEdit(testimonial); close()"
                                                >
                                                    <i class="ti ti-edit text-base"></i>
                                                    {{ t('Edit') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-amber-50 hover:text-amber-700 dark:text-gray-200 dark:hover:bg-amber-900/20 dark:hover:text-amber-300"
                                                    @click="toggleFeatured(testimonial.id); close()"
                                                >
                                                    <i class="ti ti-star text-base"></i>
                                                    {{ testimonial.is_featured ? t('Unfeature') : t('Feature') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                                                    @click="toggleActive(testimonial.id); close()"
                                                >
                                                    <i class="ti ti-toggle-right text-base"></i>
                                                    {{ testimonial.is_active ? t('Deactivate') : t('Activate') }}
                                                </button>
                                                <hr class="border-gray-200 dark:border-surface-700">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                    @click="remove(testimonial.id); close()"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                    {{ t('Delete') }}
                                                </button>
                                            </template>
                                        </TableActionMenu>
                                    </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <AppModal
            :open="showForm"
            max-width="max-w-5xl"
            :title="editingId ? t('Edit Testimonial') : t('Create Testimonial')"
            :subtitle="t('Shape customer proof with clean content, trusted identity details, and clear publishing controls.')"
            has-form
            @close="closeForm"
        >
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,1fr)]">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-5">
                            <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Reviewer Details') }}</h4>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Name') }} *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="t(' Sarah Khan')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-xs text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Role') }}</label>
                                <input
                                    v-model="form.role"
                                    type="text"
                                    :placeholder="t('CEO, Developer, Founder...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Company') }}</label>
                                <input
                                    v-model="form.company"
                                    type="text"
                                    :placeholder="t('e.g. PixelForge Studio')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Sort Order') }}</label>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    :placeholder="t('e.g. 1')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                        </div>

                        <div class="mt-5 border-t border-gray-100 px-5 py-3 dark:border-surface-800">
                            <label class="mb-3 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Photo') }}</label>
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-100 text-lg font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover">
                                    <span v-else>{{ initials(form.name) }}</span>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="cursor-pointer inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300">
                                        <input type="file" accept="image/*" class="hidden" @change="handleAvatarChange">
                                        <i class="ti ti-upload text-base"></i>
                                        {{ t('Upload Photo') }}
                                    </label>
                                    <p v-if="form.errors.avatar_file" class="text-xs text-red-500">{{ form.errors.avatar_file }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-5">
                            <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Review Content') }}</h4>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Review') }} *</label>
                            <textarea
                                v-model="form.content"
                                rows="6"
                                :placeholder="t('Write the testimonial in a natural, specific tone that feels believable and useful.')"
                                class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm leading-6 text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                            <p v-if="form.errors.content" class="mt-2 text-xs text-red-500">{{ form.errors.content }}</p>
                        </div>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-5">
                            <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Publishing Settings') }}</h4>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Rating') }}</label>
                                <AppSelect
                                    v-model.number="form.rating"
                                    :options="ratingOptions"
                                    :placeholder="t('Select rating')"
                                />
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Source') }}</label>
                                <AppSelect
                                    v-model="form.source"
                                    :options="sourceOptions"
                                    :placeholder="t('Select source')"
                                />
                            </div>

                            <button
                                type="button"
                                class="flex w-full items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-primary-200 hover:bg-primary-50/70 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10"
                                @click="form.show_source = !form.show_source"
                            >
                                <div>
                                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Show Source Publicly') }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Display the source (e.g. “via Google”) on public testimonial sections.') }}</div>
                                </div>
                                <AppSwitch :model-value="form.show_source" />
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-primary-200 hover:bg-primary-50/70 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10"
                                @click="form.is_active = !form.is_active"
                            >
                                <div>
                                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Active') }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show this testimonial on public-facing sections that consume active items.') }}</div>
                                </div>
                                <AppSwitch :model-value="form.is_active" />
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-amber-200 hover:bg-amber-50/70 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-amber-900/40 dark:hover:bg-amber-900/10"
                                @click="form.is_featured = !form.is_featured"
                            >
                                <div>
                                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Featured') }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Use this for priority placement in hero, spotlight, or premium testimonial sections.') }}</div>
                                </div>
                                <AppSwitch :model-value="form.is_featured" />
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between gap-3 w-full">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Required fields are marked with *') }}
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="closeForm"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary-admin inline-flex items-center gap-2 !rounded-full disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ form.processing ? t('Saving...') : editingId ? t('Save Changes') : t('Create Testimonial') }}
                        </button>
                    </div>
                </div>
            </template>
        </AppModal>

        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete testimonial?')"
            :message="t('This testimonial will be deleted permanently.')"
            :confirm-label="t('Delete')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
