<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

declare const route: (name: string, params?: Record<string, string | number>) => string

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
    source: 'manual' | 'google' | 'trustpilot' | 'import'
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
}

const props = defineProps<{ testimonials: Testimonial[] }>()

const toast = useToastr()
const { t } = useTranslate()

const showForm = ref(false)
const showAiForm = ref(false)
const aiGenerating = ref(false)
const editingId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)
const openActionMenuId = ref<number | null>(null)
const actionMenuPosition = ref({ top: 0, left: 0 })
const importInput = ref<HTMLInputElement | null>(null)
const avatarPreview = ref<string | null>(null)

const aiForm = ref({
    company_type: 'AI SaaS platform',
    tone: 'professional and authentic',
    prompt: '',
    count: 5,
})

const toneOptions = [
    { value: 'professional and authentic', label: t('Professional') },
    { value: 'friendly and conversational', label: t('Friendly') },
    { value: 'confident and results-focused', label: t('Results-focused') },
    { value: 'warm and trustworthy', label: t('Warm') },
    { value: 'premium and polished', label: t('Premium') },
    { value: 'casual startup voice', label: t('Casual startup') },
]

const ratingOptions = [5, 4, 3, 2, 1].map((value) => ({
    value,
    label: t(':count Star', { count: String(value) }),
}))

const sourceOptions = [
    { value: 'manual', label: t('Manual') },
    { value: 'google', label: t('Google') },
    { value: 'trustpilot', label: t('Trustpilot') },
    { value: 'import', label: t('Import') },
]

const sourceClasses: Record<Testimonial['source'], string> = {
    manual: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
    google: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300',
    trustpilot: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300',
    import: 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300',
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
})

const form = useForm(blank())

const totalTestimonials = computed(() => props.testimonials.length)
const activeTestimonials = computed(() => props.testimonials.filter((item) => item.is_active).length)
const featuredTestimonials = computed(() => props.testimonials.filter((item) => item.is_featured).length)
const averageRating = computed(() => {
    if (props.testimonials.length === 0) {
        return '0.0'
    }

    const total = props.testimonials.reduce((sum, item) => sum + item.rating, 0)
    return (total / props.testimonials.length).toFixed(1)
})

const sortedTestimonials = computed(() =>
    [...props.testimonials].sort((left, right) => {
        if (left.sort_order !== right.sort_order) {
            return left.sort_order - right.sort_order
        }

        return right.id - left.id
    }),
)

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
    openActionMenuId.value = null
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
    openActionMenuId.value = null
    router.post(route('admin.testimonials.featured', { testimonial: id }), {}, { preserveScroll: true })
}

const toggleActive = (id: number) => {
    openActionMenuId.value = null
    router.post(route('admin.testimonials.active', { testimonial: id }), {}, { preserveScroll: true })
}

const toggleActionMenu = async (id: number, event: MouseEvent) => {
    if (openActionMenuId.value === id) {
        openActionMenuId.value = null
        return
    }

    const trigger = event.currentTarget

    if (!(trigger instanceof HTMLElement)) {
        return
    }

    const rect = trigger.getBoundingClientRect()

    openActionMenuId.value = id
    actionMenuPosition.value = {
        top: rect.bottom + window.scrollY + 8,
        left: rect.right + window.scrollX,
    }

    await nextTick()
}

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-testimonial-actions]')) {
        return
    }

    openActionMenuId.value = null
}

const handleViewportChange = () => {
    openActionMenuId.value = null
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    window.removeEventListener('resize', handleViewportChange)
    window.removeEventListener('scroll', handleViewportChange, true)
})

const importFile = () => {
    importInput.value?.click()
}

const handleImport = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0]

    if (!file) {
        return
    }

    const payload = new FormData()
    payload.append('csv', file)

    router.post(route('admin.testimonials.import'), payload, {
        preserveScroll: true,
        onFinish: () => {
            if (importInput.value) {
                importInput.value.value = ''
            }
        },
    })
}

const handleAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    form.avatar_file = file
    avatarPreview.value = file ? URL.createObjectURL(file) : resolveAvatar(form.avatar)
}

const resolveAvatar = (avatar: string | null): string => {
    if (!avatar) {
        return ''
    }

    if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) {
        return avatar
    }

    return `/storage/${avatar}`
}

const initials = (name: string) => {
    return name.trim().charAt(0).toUpperCase() || 'U'
}

const sourceLabel = (source: Testimonial['source']) => {
    return ({
        manual: t('Manual'),
        google: t('Google'),
        trustpilot: t('Trustpilot'),
        import: t('Import'),
    })[source]
}

const stars = (count: number) => Array.from({ length: 5 }, (_, index) => index < count)

const generateTestimonials = async () => {
    if (aiGenerating.value) {
        return
    }

    aiGenerating.value = true

    try {
        const response = await fetch(route('admin.testimonials.generate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
            },
            body: JSON.stringify(aiForm.value),
        })

        const payload = await response.json()

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || t('AI generation failed.'))
        }

        toast.success(payload.message || t('Testimonials generated.'))
        showAiForm.value = false
        router.reload({ only: ['testimonials'] })
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('AI generation failed.'))
    } finally {
        aiGenerating.value = false
    }
}
</script>

<template>
    <Head :title="t('Testimonials - Admin')" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-2">
                    <div>
                        <h1 class="font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ t('Testimonials') }}</h1>
                        <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Manage customer proof for your homepage and marketing sections from one consistent admin workspace.') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <input
                        ref="importInput"
                        type="file"
                        accept=".csv,.txt"
                        class="hidden"
                        @change="handleImport"
                    >
                    <Tooltip :content="t('Homepage Builder')" placement="top">
                        <Link
                            :href="route('admin.homepage.index')"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                        >
                            <i class="ti ti-layout-dashboard text-lg"></i>
                        </Link>
                    </Tooltip>
                    <Tooltip :content="t('Import CSV')" placement="top">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                            @click="importFile"
                        >
                            <i class="ti ti-file-import text-lg"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('AI Generate')" placement="top">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-violet-200 bg-violet-50 text-violet-700 shadow-sm transition hover:bg-violet-100 dark:border-violet-900/40 dark:bg-violet-900/20 dark:text-violet-300 dark:hover:bg-violet-900/30"
                            @click="showAiForm = true"
                        >
                            <i class="ti ti-sparkles text-lg"></i>
                        </button>
                    </Tooltip>
                    <button
                        type="button"
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
                        @click="openCreate"
                    >
                        <i class="ti ti-plus text-base"></i>
                        {{ t('Add Testimonial') }}
                    </button>
                </div>
            </div>

            <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Total') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ totalTestimonials }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('All testimonials stored in the library.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Active') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-emerald-600">{{ activeTestimonials }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Currently visible across public sections.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Featured') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-amber-500">{{ featuredTestimonials }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Highlighted entries for premium placement.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Average Rating') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-blue-600">{{ averageRating }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Overall score based on saved testimonials.') }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div v-if="sortedTestimonials.length === 0" class="px-6 py-16 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl dark:bg-emerald-900/20">
                        <span>💬</span>
                    </div>
                    <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">{{ t('No testimonials yet') }}</h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Create your first testimonial to start building social proof across the homepage and landing sections.') }}
                    </p>
                    <button
                        type="button"
                        class="btn-primary mt-6 inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
                        @click="openCreate"
                    >
                        {{ t('Add First Testimonial') }}
                    </button>
                </div>

                <div v-else class="overflow-visible">
                    <div class="overflow-x-auto overflow-y-visible rounded-t-2xl">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                        <thead class="bg-gray-50 dark:bg-surface-800/70">
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
                                v-for="testimonial in sortedTestimonials"
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
                                <td class="overflow-visible px-6 py-5 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="relative" data-testimonial-actions>
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                                @click.stop="toggleActionMenu(testimonial.id, $event)"
                                            >
                                                <i class="ti ti-dots-vertical text-base"></i>
                                            </button>
                                        </div>
                                        <Teleport to="body">
                                            <div
                                                v-if="openActionMenuId === testimonial.id"
                                                data-testimonial-actions
                                                class="fixed z-[80] w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                                :style="{
                                                    top: `${actionMenuPosition.top}px`,
                                                    left: `${actionMenuPosition.left}px`,
                                                    transform: 'translateX(-100%)',
                                                }"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                    @click="openEdit(testimonial); openActionMenuId = null"
                                                >
                                                    <i class="ti ti-pencil text-base"></i>
                                                    {{ t('Edit') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-amber-50 hover:text-amber-700 dark:text-gray-200 dark:hover:bg-amber-900/20 dark:hover:text-amber-300"
                                                    @click="toggleFeatured(testimonial.id)"
                                                >
                                                    <i class="ti ti-star text-base"></i>
                                                    {{ testimonial.is_featured ? t('Unfeature') : t('Feature') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                                                    @click="toggleActive(testimonial.id)"
                                                >
                                                    <i class="ti ti-toggle-right text-base"></i>
                                                    {{ testimonial.is_active ? t('Deactivate') : t('Activate') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                    @click="remove(testimonial.id)"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                    {{ t('Delete') }}
                                                </button>
                                            </div>
                                        </Teleport>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeForm">
            <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-[16px] border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/80">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500 text-white shadow-sm">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 10.5h4.75m-4.75 3h8.75M6.75 19.5l-3-1.5V6.75A2.25 2.25 0 0 1 6 4.5h12A2.25 2.25 0 0 1 20.25 6.75v8.5A2.25 2.25 0 0 1 18 17.5H9.31L6.75 19.5Zm11.5-11.75.375.75.75.375-.75.375-.375.75-.375-.75-.75-.375.75-.375.375-.75Z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-heading text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ editingId ? t('Edit Testimonial') : t('Create Testimonial') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('Shape customer proof with clean content, trusted identity details, and clear publishing controls.') }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 text-gray-400 transition hover:bg-white/80 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                            @click="closeForm"
                        >
                            <span class="sr-only">{{ t('Close') }}</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,1fr)]">
                        <div class="space-y-6">
                            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="mb-5">
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Customer Details') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Define who said it and how that identity should appear on the site.') }}</p>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Name') }} *</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            :placeholder="t('e.g. Sarah Khan')"
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
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="mb-5 flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Review Content') }}</h4>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Keep the quote clear, believable, and easy to scan in cards or sliders.') }}</p>
                                    </div>
                                    <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500 dark:bg-surface-800 dark:text-gray-300">
                                        {{ t(':count chars', { count: String(form.content.length) }) }}
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Review') }} *</label>
                                    <textarea
                                        v-model="form.content"
                                        rows="8"
                                        :placeholder="t('Write the testimonial in a natural, specific tone that feels believable and useful.')"
                                        class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm leading-6 text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                    <p v-if="form.errors.content" class="mt-2 text-xs text-red-500">{{ form.errors.content }}</p>
                                </div>
                            </section>
                        </div>

                        <div class="space-y-6">
                            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="border-b border-gray-200 px-5 py-4 dark:border-surface-700">
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Preview') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Quick look at how the testimonial identity may feel in the UI.') }}</p>
                                </div>
                                <div class="space-y-5 p-5">
                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800">
                                        <div class="mb-4 flex items-center gap-3">
                                            <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-emerald-100 text-lg font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover">
                                                <span v-else>{{ initials(form.name) }}</span>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="truncate font-semibold text-gray-900 dark:text-white">{{ form.name || t('Customer Name') }}</div>
                                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ [form.role, form.company].filter(Boolean).join(' · ') || t('Role and company') }}
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                                            {{ form.content || t('Your testimonial preview will appear here as you type.') }}
                                        </p>
                                    </div>

                                    <label class="inline-flex cursor-pointer items-center rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300">
                                        <input type="file" accept="image/*" class="hidden" @change="handleAvatarChange">
                                        <i class="ti ti-upload text-base"></i>
                                        {{ t('Upload Photo') }}
                                    </label>
                                    <p v-if="form.errors.avatar_file" class="text-xs text-red-500">{{ form.errors.avatar_file }}</p>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="mb-5">
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Publishing Settings') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control rating, source, and whether this testimonial should be visible or featured.') }}</p>
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
                                        @click="form.is_active = !form.is_active"
                                    >
                                        <div>
                                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Active') }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show this testimonial on public-facing sections that consume active items.') }}</div>
                                        </div>
                                        <span
                                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full transition-colors"
                                            :class="form.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-surface-600'"
                                        >
                                            <span
                                                class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                            ></span>
                                        </span>
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
                                        <span
                                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full transition-colors"
                                            :class="form.is_featured ? 'bg-amber-500' : 'bg-gray-300 dark:bg-surface-600'"
                                        >
                                            <span
                                                class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                :class="form.is_featured ? 'translate-x-5' : 'translate-x-0.5'"
                                            ></span>
                                        </span>
                                    </button>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-surface-700 dark:bg-surface-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Required fields are marked with *') }}
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="closeForm"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ form.processing ? t('Saving...') : editingId ? t('Save Changes') : t('Create Testimonial') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showAiForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="showAiForm = false">
            <div class="w-full max-w-xl overflow-hidden rounded-[16px] border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5 dark:border-surface-700">
                    <div>
                        <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">{{ t('AI Generate Testimonials') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Generate demo-friendly testimonials with a consistent admin flow.') }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                        @click="showAiForm = false"
                    >
                        <span class="sr-only">{{ t('Close') }}</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Company Type') }}</label>
                        <input
                            v-model="aiForm.company_type"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Tone') }}</label>
                        <select
                            v-model="aiForm.tone"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                            <option v-for="option in toneOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Prompt') }}</label>
                        <textarea
                            v-model="aiForm.prompt"
                            rows="4"
                            :placeholder="t('Mention audience, positioning, product wins, or phrases to avoid...')"
                            class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        ></textarea>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Count') }}</label>
                        <input
                            v-model.number="aiForm.count"
                            type="number"
                            min="1"
                            max="10"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-surface-700 dark:bg-surface-800">
                    <button
                        type="button"
                        class="rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-700"
                        @click="showAiForm = false"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="btn-primary inline-flex items-center rounded-xl px-6 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="aiGenerating"
                        @click="generateTestimonials"
                    >
                        {{ aiGenerating ? t('Generating...') : t('Generate') }}
                    </button>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete testimonial?')"
            :message="t('This testimonial will be deleted permanently.')"
            :confirm-label="t('Delete')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
