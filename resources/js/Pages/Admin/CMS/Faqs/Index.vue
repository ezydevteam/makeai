<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

declare const route: (name: string, params?: Record<string, string | number>) => string

interface Faq {
    id: number
    question: string
    answer: string
    category_id: number | null
    is_active: boolean
    sort_order: number
}

interface FaqCategory {
    id: number
    name: string
    slug: string
    sort_order: number
    faqs: Faq[]
}

const props = defineProps<{ categories: FaqCategory[]; uncategorized: Faq[] }>()

const toast = useToastr()
const { t } = useTranslate()

const showFaqForm = ref(false)
const showCatForm = ref(false)
const showAiForm = ref(false)
const aiGenerating = ref(false)
const editingFaqId = ref<number | null>(null)
const editingCatId = ref<number | null>(null)
const deleteFaqId = ref<number | null>(null)
const deleteCategoryId = ref<number | null>(null)
const importInput = ref<HTMLInputElement | null>(null)
const importCategoryId = ref<number | null>(null)
const openCategoryMenuId = ref<number | null>(null)
const openFaqMenuKey = ref<string | null>(null)

const faqForm = useForm({
    question: '',
    answer: '',
    category_id: null as number | null,
    is_active: true,
    sort_order: 0,
})

const catForm = useForm({
    name: '',
    sort_order: 0,
})

const aiForm = ref({
    topic: 'AI SaaS platform',
    prompt: '',
    count: 10,
    category_id: null as number | null,
})

const totalFaqs = computed(() => props.categories.reduce((sum, category) => sum + category.faqs.length, 0) + props.uncategorized.length)
const categoryCount = computed(() => props.categories.length)
const uncategorizedCount = computed(() => props.uncategorized.length)
const activeFaqCount = computed(() => {
    const categoryFaqs = props.categories.flatMap((category) => category.faqs)
    return [...categoryFaqs, ...props.uncategorized].filter((faq) => faq.is_active).length
})

const categoryOptions = computed(() => [
    { value: null, label: t('Uncategorized') },
    ...props.categories.map((category) => ({
        value: category.id,
        label: category.name,
    })),
])

const aiCategoryOptions = computed(() => [
    { value: null, label: t('Uncategorized') },
    ...props.categories.map((category) => ({
        value: category.id,
        label: category.name,
    })),
])

const sortedCategories = computed(() =>
    [...props.categories].sort((left, right) => {
        if (left.sort_order !== right.sort_order) {
            return left.sort_order - right.sort_order
        }

        return left.name.localeCompare(right.name)
    }),
)

const sortFaqs = (faqs: Faq[]) =>
    [...faqs].sort((left, right) => {
        if (left.sort_order !== right.sort_order) {
            return left.sort_order - right.sort_order
        }

        return left.question.localeCompare(right.question)
    })

const openCreateFaq = (categoryId?: number) => {
    faqForm.reset()
    faqForm.question = ''
    faqForm.answer = ''
    faqForm.category_id = categoryId ?? null
    faqForm.is_active = true
    faqForm.sort_order = 0
    editingFaqId.value = null
    showFaqForm.value = true
}

const openEditFaq = (faq: Faq) => {
    faqForm.question = faq.question
    faqForm.answer = faq.answer
    faqForm.category_id = faq.category_id
    faqForm.is_active = faq.is_active
    faqForm.sort_order = faq.sort_order
    editingFaqId.value = faq.id
    showFaqForm.value = true
}

const closeFaqForm = () => {
    showFaqForm.value = false
    editingFaqId.value = null
}

const submitFaq = () => {
    if (editingFaqId.value) {
        faqForm.post(route('admin.faqs.update', { faq: editingFaqId.value }), {
            onSuccess: () => {
                closeFaqForm()
            },
        })

        return
    }

    faqForm.post(route('admin.faqs.store'), {
        onSuccess: () => {
            closeFaqForm()
        },
    })
}

const requestFaqDelete = (id: number) => {
    openFaqMenuKey.value = null
    deleteFaqId.value = id
}

const confirmFaqDelete = () => {
    if (deleteFaqId.value === null) {
        return
    }

    router.delete(route('admin.faqs.delete', { faq: deleteFaqId.value }), {
        preserveScroll: true,
        onFinish: () => {
            deleteFaqId.value = null
        },
    })
}

const toggleFaqActive = (id: number) => {
    openFaqMenuKey.value = null
    router.post(route('admin.faqs.active', { faq: id }), {}, { preserveScroll: true })
}

const openCreateCat = () => {
    catForm.reset()
    catForm.name = ''
    catForm.sort_order = 0
    editingCatId.value = null
    showCatForm.value = true
}

const openEditCat = (category: FaqCategory) => {
    catForm.name = category.name
    catForm.sort_order = category.sort_order
    editingCatId.value = category.id
    showCatForm.value = true
}

const closeCatForm = () => {
    showCatForm.value = false
    editingCatId.value = null
}

const submitCat = () => {
    if (editingCatId.value) {
        catForm.post(route('admin.faq-categories.update', { category: editingCatId.value }), {
            onSuccess: () => {
                closeCatForm()
            },
        })

        return
    }

    catForm.post(route('admin.faq-categories.store'), {
        onSuccess: () => {
            closeCatForm()
        },
    })
}

const requestCategoryDelete = (id: number) => {
    openCategoryMenuId.value = null
    deleteCategoryId.value = id
}

const confirmCategoryDelete = () => {
    if (deleteCategoryId.value === null) {
        return
    }

    router.delete(route('admin.faq-categories.delete', { category: deleteCategoryId.value }), {
        preserveScroll: true,
        onFinish: () => {
            deleteCategoryId.value = null
        },
    })
}

const handleImport = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0]

    if (!file) {
        return
    }

    const payload = new FormData()
    payload.append('csv', file)

    if (importCategoryId.value !== null) {
        payload.append('category_id', String(importCategoryId.value))
    }

    router.post(route('admin.faqs.import'), payload, {
        preserveScroll: true,
        onFinish: () => {
            if (importInput.value) {
                importInput.value.value = ''
            }
            importCategoryId.value = null
        },
    })
}

const triggerImport = (categoryId: number | null = null) => {
    importCategoryId.value = categoryId
    importInput.value?.click()
}

const generateFaqs = async () => {
    if (aiGenerating.value) {
        return
    }

    aiGenerating.value = true

    try {
        const response = await fetch(route('admin.faqs.generate'), {
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

        toast.success(payload.message || t('FAQs generated.'))
        showAiForm.value = false
        router.reload({ only: ['categories', 'uncategorized'] })
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('AI generation failed.'))
    } finally {
        aiGenerating.value = false
    }
}

const faqPreview = computed(() => faqForm.answer || `<p>${t('Answer preview will appear here as you write it.')}</p>`)

const toggleCategoryMenu = (id: number) => {
    openCategoryMenuId.value = openCategoryMenuId.value === id ? null : id
}

const toggleFaqMenu = (key: string) => {
    openFaqMenuKey.value = openFaqMenuKey.value === key ? null : key
}

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-faq-actions]')) {
        return
    }

    openCategoryMenuId.value = null
    openFaqMenuKey.value = null
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
})
</script>

<template>
    <Head :title="t('FAQs - Admin')" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-2">
                    <div>
                        <h1 class="font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ t('FAQs') }}</h1>
                        <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Organize public-facing questions into clean categories and manage homepage-ready answers from one admin workspace.') }}
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
                            @click="triggerImport()"
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
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                        @click="openCreateCat"
                    >
                        <i class="ti ti-folder-plus text-base"></i>
                        {{ t('Category') }}
                    </button>
                    <button
                        type="button"
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
                        @click="openCreateFaq()"
                    >
                        <i class="ti ti-plus text-base"></i>
                        {{ t('Add FAQ') }}
                    </button>
                </div>
            </div>

            <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Total FAQs') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-gray-900 dark:text-white">{{ totalFaqs }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('All questions currently stored in the FAQ library.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Categories') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-blue-600">{{ categoryCount }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Content groups used across homepage and dedicated FAQ pages.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Active') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-emerald-600">{{ activeFaqCount }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Questions currently available to public-facing sections.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Uncategorized') }}</div>
                    <div class="mt-3 font-heading text-3xl font-bold text-amber-500">{{ uncategorizedCount }}</div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Entries that still need a category assignment.') }}</p>
                </div>
            </div>

            <div v-if="totalFaqs === 0 && categoryCount === 0" class="rounded-2xl border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl dark:bg-emerald-900/20">
                    <span>❓</span>
                </div>
                <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">{{ t('No FAQs yet') }}</h3>
                <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Create categories and questions to power the homepage FAQ section and your dedicated public FAQ page.') }}
                </p>
                <button
                    type="button"
                    class="btn-primary mt-6 inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
                    @click="openCreateFaq()"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Add First FAQ') }}
                </button>
            </div>

            <div v-else class="space-y-6">
                <div
                    v-for="category in sortedCategories"
                    :key="category.id"
                    class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
                >
                    <div class="rounded-t-2xl flex flex-col gap-4 border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/60 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ category.name }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t(':count FAQs in this category', { count: String(category.faqs.length) }) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3.5 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-900/30 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30"
                                @click="openCreateFaq(category.id)"
                            >
                                <i class="ti ti-plus text-base"></i>
                                {{ t('FAQ') }}
                            </button>
                            <Tooltip :content="t('Import CSV into this category')" placement="top">
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                                    @click="triggerImport(category.id)"
                                >
                                    <i class="ti ti-file-import text-base"></i>
                                </button>
                            </Tooltip>
                            <div class="relative" data-faq-actions>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                    @click.stop="toggleCategoryMenu(category.id)"
                                >
                                    <i class="ti ti-dots-vertical text-base"></i>
                                </button>

                                <div
                                    v-if="openCategoryMenuId === category.id"
                                    class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                        @click="openEditCat(category); openCategoryMenuId = null"
                                    >
                                        <i class="ti ti-pencil text-base"></i>
                                        {{ t('Edit') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                        @click="requestCategoryDelete(category.id)"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                        {{ t('Delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="category.faqs.length === 0" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ t('No FAQs in this category yet.') }}
                    </div>

                    <div v-else class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div
                            v-for="faq in sortFaqs(category.faqs)"
                            :key="faq.id"
                            class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-start lg:justify-between"
                            :class="!faq.is_active ? 'opacity-60' : ''"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ faq.question }}</h3>
                                    <span
                                        :class="faq.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'"
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    >
                                        {{ faq.is_active ? t('Active') : t('Inactive') }}
                                    </span>
                                </div>
                                <div class="mt-2 line-clamp-3 text-sm leading-6 text-gray-500 dark:text-gray-400" v-html="faq.answer"></div>
                            </div>

                            <div class="flex items-center gap-2 lg:shrink-0">
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 rounded-full transition-colors"
                                    :class="faq.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-surface-600'"
                                    @click="toggleFaqActive(faq.id)"
                                >
                                    <span
                                        class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                        :class="faq.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                    ></span>
                                </button>
                                <div class="relative" data-faq-actions>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                        @click.stop="toggleFaqMenu(`cat-${category.id}-faq-${faq.id}`)"
                                    >
                                        <i class="ti ti-dots-vertical text-base"></i>
                                    </button>

                                    <div
                                        v-if="openFaqMenuKey === `cat-${category.id}-faq-${faq.id}`"
                                        class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                            @click="openEditFaq(faq); openFaqMenuKey = null"
                                        >
                                            <i class="ti ti-pencil text-base"></i>
                                            {{ t('Edit') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                            @click="requestFaqDelete(faq.id)"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                            {{ t('Delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="uncategorized.length > 0"
                    class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
                >
                    <div class="rounded-t-2xl border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/60">
                        <h2 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Uncategorized') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('These questions still need a category assignment.') }}</p>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div
                            v-for="faq in sortFaqs(uncategorized)"
                            :key="faq.id"
                            class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-start lg:justify-between"
                            :class="!faq.is_active ? 'opacity-60' : ''"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ faq.question }}</h3>
                                    <span
                                        :class="faq.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'"
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    >
                                        {{ faq.is_active ? t('Active') : t('Inactive') }}
                                    </span>
                                </div>
                                <div class="mt-2 line-clamp-3 text-sm leading-6 text-gray-500 dark:text-gray-400" v-html="faq.answer"></div>
                            </div>

                            <div class="flex items-center gap-2 lg:shrink-0">
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 rounded-full transition-colors"
                                    :class="faq.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-surface-600'"
                                    @click="toggleFaqActive(faq.id)"
                                >
                                    <span
                                        class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                        :class="faq.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                    ></span>
                                </button>
                                <div class="relative" data-faq-actions>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                        @click.stop="toggleFaqMenu(`uncategorized-${faq.id}`)"
                                    >
                                        <i class="ti ti-dots-vertical text-base"></i>
                                    </button>

                                    <div
                                        v-if="openFaqMenuKey === `uncategorized-${faq.id}`"
                                        class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                            @click="openEditFaq(faq); openFaqMenuKey = null"
                                        >
                                            <i class="ti ti-pencil text-base"></i>
                                            {{ t('Edit') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                            @click="requestFaqDelete(faq.id)"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                            {{ t('Delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showFaqForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeFaqForm">
            <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-[16px] border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/80">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500 text-white shadow-sm">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75h6M7.5 10.5h9m-9 3.75h6m-7.5 6 1.35-2.7A2.25 2.25 0 0 0 9 15.75h9A2.25 2.25 0 0 0 20.25 13.5v-7.5A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v7.5A2.25 2.25 0 0 0 6 15.75h.75" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-heading text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ editingFaqId ? t('Edit FAQ') : t('Create FAQ') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('Write clear questions and polished answers that fit both homepage accordions and dedicated FAQ pages.') }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 text-gray-400 transition hover:bg-white/80 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                            @click="closeFaqForm"
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
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Question Setup') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Define the question, assign the right category, and control list order.') }}</p>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Question') }} *</label>
                                        <input
                                            v-model="faqForm.question"
                                            type="text"
                                            :placeholder="t('e.g. How do credits work in MakeAI?')"
                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        >
                                        <p v-if="faqForm.errors.question" class="mt-2 text-xs text-red-500">{{ faqForm.errors.question }}</p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Category') }}</label>
                                        <AppSelect
                                            v-model="faqForm.category_id"
                                            :options="categoryOptions"
                                            :placeholder="t('Select category')"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Sort Order') }}</label>
                                        <input
                                            v-model.number="faqForm.sort_order"
                                            type="number"
                                            min="0"
                                            :placeholder="t('e.g. 1')"
                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        >
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="mb-5">
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Answer Content') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use rich formatting when needed, but keep answers direct and readable.') }}</p>
                                </div>

                                <RichEditor v-model="faqForm.answer" variant="minimal" />
                                <p v-if="faqForm.errors.answer" class="mt-2 text-xs text-red-500">{{ faqForm.errors.answer }}</p>
                            </section>
                        </div>

                        <div class="space-y-6">
                            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="border-b border-gray-200 px-5 py-4 dark:border-surface-700">
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Preview') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Quick preview of how this entry reads in a public FAQ section.') }}</p>
                                </div>
                                <div class="p-5">
                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800">
                                        <div class="flex items-start justify-between gap-4">
                                            <h5 class="font-semibold text-gray-900 dark:text-white">
                                                {{ faqForm.question || t('FAQ question preview') }}
                                            </h5>
                                            <span
                                                :class="faqForm.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-surface-600'"
                                                class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full transition-colors"
                                            >
                                                <span
                                                    class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                    :class="faqForm.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                                ></span>
                                            </span>
                                        </div>
                                        <div class="prose prose-sm mt-4 max-w-none text-gray-600 dark:prose-invert dark:text-gray-300" v-html="faqPreview"></div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                                <div class="mb-5">
                                    <h4 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Publishing') }}</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose whether this FAQ is available to the frontend right now.') }}</p>
                                </div>

                                <button
                                    type="button"
                                    class="flex w-full items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-primary-200 hover:bg-primary-50/70 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10"
                                    @click="faqForm.is_active = !faqForm.is_active"
                                >
                                    <div>
                                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Active') }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show this FAQ on public pages and homepage sections that read active entries.') }}</div>
                                    </div>
                                    <span
                                        class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full transition-colors"
                                        :class="faqForm.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-surface-600'"
                                    >
                                        <span
                                            class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                            :class="faqForm.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                        ></span>
                                    </span>
                                </button>
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
                            @click="closeFaqForm"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="faqForm.processing"
                            @click="submitFaq"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ faqForm.processing ? t('Saving...') : editingFaqId ? t('Save Changes') : t('Create FAQ') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showCatForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeCatForm">
            <div class="w-full max-w-xl overflow-hidden rounded-[16px] border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/80">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg shadow-blue-500/20">
                                <i class="ti ti-folder text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-heading text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ editingCatId ? t('Edit Category') : t('Create Category') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('Group related questions together for cleaner public FAQ navigation.') }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 text-gray-400 transition hover:bg-white/80 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                            @click="closeCatForm"
                        >
                            <span class="sr-only">{{ t('Close') }}</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Name') }} *</label>
                        <input
                            v-model="catForm.name"
                            type="text"
                            :placeholder="t('e.g. Billing & Credits')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                        <p v-if="catForm.errors.name" class="mt-2 text-xs text-red-500">{{ catForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Sort Order') }}</label>
                        <input
                            v-model.number="catForm.sort_order"
                            type="number"
                            min="0"
                            :placeholder="t('e.g. 1')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-surface-700 dark:bg-surface-800">
                    <button
                        type="button"
                        class="rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-700"
                        @click="closeCatForm"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="catForm.processing"
                        @click="submitCat"
                    >
                        <i class="ti ti-device-floppy text-base"></i>
                        {{ catForm.processing ? t('Saving...') : editingCatId ? t('Save Category') : t('Create Category') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showAiForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="showAiForm = false">
            <div class="w-full max-w-2xl overflow-hidden rounded-[16px] border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-surface-700 dark:bg-surface-800/80">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-violet-600 text-white shadow-lg shadow-violet-500/20">
                                <i class="ti ti-sparkles text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-heading text-2xl font-semibold text-gray-900 dark:text-white">{{ t('AI Generate FAQs') }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create a first draft FAQ set for your product or category in one step.') }}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 text-gray-400 transition hover:bg-white/80 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                            @click="showAiForm = false"
                        >
                            <span class="sr-only">{{ t('Close') }}</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Topic or Product') }}</label>
                        <input
                            v-model="aiForm.topic"
                            type="text"
                            :placeholder="t('e.g. AI writing SaaS for marketers')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Count') }}</label>
                        <input
                            v-model.number="aiForm.count"
                            type="number"
                            min="1"
                            max="20"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Category') }}</label>
                        <AppSelect
                            v-model="aiForm.category_id"
                            :options="aiCategoryOptions"
                            :placeholder="t('Select category')"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Prompt') }}</label>
                        <textarea
                            v-model="aiForm.prompt"
                            rows="4"
                            :placeholder="t('Mention buyer concerns, answer style, topics to include, or wording to avoid...')"
                            class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        ></textarea>
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
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="aiGenerating"
                        @click="generateFaqs"
                    >
                        <i class="ti ti-sparkles text-base"></i>
                        {{ aiGenerating ? t('Generating...') : t('Generate FAQs') }}
                    </button>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="deleteFaqId !== null"
            :title="t('Delete FAQ?')"
            :message="t('This FAQ will be deleted permanently.')"
            :confirm-label="t('Delete')"
            @cancel="deleteFaqId = null"
            @confirm="confirmFaqDelete"
        />

        <ActionConfirmModal
            :open="deleteCategoryId !== null"
            :title="t('Delete category?')"
            :message="t('FAQs inside this category will become uncategorized.')"
            :confirm-label="t('Delete')"
            @cancel="deleteCategoryId = null"
            @confirm="confirmCategoryDelete"
        />
    </AdminLayout>
</template>
