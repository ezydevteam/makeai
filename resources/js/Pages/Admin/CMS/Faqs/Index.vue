<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

declare const route: (name: string, params?: Record<string, string | number>) => string

defineOptions({ layout: AdminLayout })

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
const collapsedSections = ref<Record<string, boolean>>({})

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

// Categories are addressable from CMS pages via the [faqs] shortcode, so make the
// exact snippet one click away instead of making admins guess the id.
const copiedShortcodeId = ref<number | null>(null)
let copyResetTimer: ReturnType<typeof setTimeout> | null = null

const copyShortcode = async (categoryId: number) => {
    try {
        await navigator.clipboard.writeText(`[faqs category="${categoryId}"]`)
        copiedShortcodeId.value = categoryId
        if (copyResetTimer) clearTimeout(copyResetTimer)
        copyResetTimer = setTimeout(() => { copiedShortcodeId.value = null }, 2000)
    } catch {
        // Clipboard is unavailable over plain HTTP; the snippet is visible either way.
    }
}

const totalFaqs = computed(() => props.categories.reduce((sum, category) => sum + category.faqs.length, 0) + props.uncategorized.length)
const categoryCount = computed(() => props.categories.length)

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

const toggleSectionCollapse = (key: string) => {
    collapsedSections.value[key] = !collapsedSections.value[key]
}

const isSectionCollapsed = (key: string) => collapsedSections.value[key] === true
</script>

<template>
    <Head :title="t('FAQs')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('FAQs') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Organize public-facing questions into clean categories and manage homepage-ready answers from one admin workspace.') }}
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <button
                    type="button"
                    class="grow inline-flex items-center justify-center gap-2 rounded-xl transition px-4 py-2 text-sm font-semibold border border-gray-200 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                    @click="openCreateCat"
                >
                    <i class="ti ti-folder-plus text-base"></i>
                    {{ t('Category') }}
                </button>
                <button
                    type="button"
                    class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 dark:border-violet-900/20 dark:bg-violet-900/20 dark:text-violet-300 dark:hover:bg-violet-900/30"
                    @click="showAiForm = true"
                >
                    <i class="ti ti-sparkles text-base"></i>
                    {{ t('AI Generate') }}
                </button>
                <button
                    type="button"
                    class="btn-primary-admin grow inline-flex items-center justify-center gap-2"
                    @click="openCreateFaq()"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Add FAQ') }}
                </button>
            </div>
        </div>

        <div v-if="totalFaqs === 0 && categoryCount === 0" class="rounded-2xl border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center shadow-sm dark:border-surface-700 dark:bg-surface-900">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl dark:bg-emerald-900/20">
                <i class="ti ti-help-circle"></i>
            </div>
            <h3 class="font-heading text-xl font-semibold text-gray-900 dark:text-white">{{ t('No FAQs yet') }}</h3>
            <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                {{ t('Create categories and questions to power the homepage FAQ section and your dedicated public FAQ page.') }}
            </p>
            <button
                type="button"
                class="btn-primary-admin mt-6 inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
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
                <div class="rounded-t-2xl flex flex-col gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 dark:border-surface-700 dark:bg-surface-800/60 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ category.name }}</h2>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ t(':count FAQs in this category', { count: String(category.faqs.length) }) }}
                        </p>
                        <!-- The id/name pair is what the [faqs] page shortcode filters on. -->
                        <button
                            type="button"
                            class="mt-1 inline-flex items-center gap-1.5 font-mono text-[11px] text-gray-400 transition hover:text-primary-600 dark:text-gray-500 dark:hover:text-primary-400"
                            :title="t('Copy shortcode')"
                            @click="copyShortcode(category.id)"
                        >
                            <i :class="copiedShortcodeId === category.id ? 'ti ti-check' : 'ti ti-code'"></i>
                            <span>[faqs category="{{ category.id }}"]</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-3 py-1 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-900/30 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30"
                            @click="openCreateFaq(category.id)"
                        >
                            <i class="ti ti-plus"></i>
                            {{ t('FAQ') }}
                        </button>

                        <button
                            v-if="category.faqs.length > 0"
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                            :aria-label="isSectionCollapsed(`category-${category.id}`) ? t('Expand section') : t('Collapse section')"
                            :title="isSectionCollapsed(`category-${category.id}`) ? t('Expand section') : t('Collapse section')"
                            @click="toggleSectionCollapse(`category-${category.id}`)"
                        >
                            <i
                                class="ti text-base transition-transform"
                                :class="isSectionCollapsed(`category-${category.id}`) ? 'ti-chevron-down' : 'ti-chevron-up'"
                            ></i>
                        </button>

                        <TableActionMenu>
                            <template #default="{ close }">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                    @click="openEditCat(category); close()"
                                >
                                    <i class="ti ti-edit text-base"></i>
                                    {{ t('Edit') }}
                                </button>
                                <hr class="border-gray-200 dark:border-surface-700">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                    @click="requestCategoryDelete(category.id); close()"
                                >
                                    <i class="ti ti-trash text-base"></i>
                                    {{ t('Delete') }}
                                </button>
                            </template>
                        </TableActionMenu>
                    </div>
                </div>

                <div v-if="!isSectionCollapsed(`category-${category.id}`) && category.faqs.length === 0" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ t('No FAQs in this category yet.') }}
                </div>

                <div v-else-if="!isSectionCollapsed(`category-${category.id}`)" class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div
                        v-for="faq in sortFaqs(category.faqs)"
                        :key="faq.id"
                        class="flex flex-col gap-4 px-6 py-3 lg:flex-row lg:items-start lg:justify-between"
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
                                <AppSwitch
                                    :model-value="faq.is_active"
                                    @update:model-value="toggleFaqActive(faq.id)"
                                />
                                <TableActionMenu>
                                    <template #default="{ close }">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click="openEditFaq(faq); close()"
                                        >
                                            <i class="ti ti-edit text-base"></i>
                                            {{ t('Edit') }}
                                        </button>
                                        <hr class="border-gray-200 dark:border-surface-700">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                            @click="requestFaqDelete(faq.id); close()"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                            {{ t('Delete') }}
                                        </button>
                                    </template>
                                </TableActionMenu>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="uncategorized.length > 0"
                    class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
                >
                    <div class="rounded-t-2xl border-b border-gray-200 bg-gray-50 px-6 py-3 dark:border-surface-700 dark:bg-surface-800/60">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-heading text-lg font-semibold text-gray-900 dark:text-white">{{ t('Uncategorized') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('These questions still need a category assignment.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                :aria-label="isSectionCollapsed('uncategorized') ? t('Expand section') : t('Collapse section')"
                                @click="toggleSectionCollapse('uncategorized')"
                            >
                                <i
                                    class="ti text-base transition-transform"
                                    :class="isSectionCollapsed('uncategorized') ? 'ti-chevron-down' : 'ti-chevron-up'"
                                ></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="!isSectionCollapsed('uncategorized')" class="divide-y divide-gray-100 dark:divide-surface-800">
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

                            <div class="flex items-center gap-3 lg:shrink-0">
                                <AppSwitch
                                    :model-value="faq.is_active"
                                    @update:model-value="toggleFaqActive(faq.id)"
                                />
                                <TableActionMenu>
                                    <template #default="{ close }">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click="openEditFaq(faq); close()"
                                        >
                                            <i class="ti ti-edit text-base"></i>
                                            {{ t('Edit') }}
                                        </button>
                                        <hr class="border-gray-200 dark:border-surface-700">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                            @click="requestFaqDelete(faq.id); close()"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                            {{ t('Delete') }}
                                        </button>
                                    </template>
                                </TableActionMenu>
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        <AppModal
            :open="showFaqForm"
            max-width="max-w-5xl"
            :title="editingFaqId ? t('Edit FAQ') : t('Create FAQ')"
            :subtitle="t('Write clear questions and polished answers that fit both homepage accordions and dedicated FAQ pages.')"
            has-form
            @close="closeFaqForm"
        >
            <div class="space-y-6">
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
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Answer Content') }}</label>
                        <RichEditor v-model="faqForm.answer" variant="minimal" />
                        <p v-if="faqForm.errors.answer" class="text-xs text-red-500">{{ faqForm.errors.answer }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Status') }}</label>
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-primary-200 hover:bg-primary-50/70 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10"
                            @click="faqForm.is_active = !faqForm.is_active"
                        >
                            <div>
                                <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Active') }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show this FAQ on public pages and homepage sections that read active entries.') }}</div>
                            </div>
                            <AppSwitch :model-value="faqForm.is_active" />
                        </button>
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
                            @click="closeFaqForm"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary-admin inline-flex items-center gap-2 !rounded-full disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="faqForm.processing"
                            @click="submitFaq"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ faqForm.processing ? t('Saving...') : editingFaqId ? t('Save Changes') : t('Create FAQ') }}
                        </button>
                    </div>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="showCatForm"
            max-width="max-w-xl"
            :title="editingCatId ? t('Edit Category') : t('Create Category')"
            :subtitle="t('Group related questions together for cleaner public FAQ navigation.')"
            has-form
            :confirm-text="catForm.processing ? t('Saving...') : editingCatId ? t('Save Category') : t('Create Category')"
            :confirm-loading="catForm.processing"
            @close="closeCatForm"
            @submit="submitCat"
        >
            <div class="space-y-5">
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
        </AppModal>

        <AppModal
            :open="showAiForm"
            max-width="max-w-2xl"
            :title="t('AI Generate FAQs')"
            :subtitle="t('Create a first draft FAQ set for your product or category in one step.')"
            has-form
            :confirm-text="t('Generate FAQs')"
            :confirm-loading="aiGenerating"
            confirm-loading-text="Generating..."
            @close="showAiForm = false"
            @submit="generateFaqs"
        >
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Topic or Product') }}</label>
                    <input
                        v-model="aiForm.topic"
                        type="text"
                        :placeholder="t('topic SaaS for marketers')"
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
        </AppModal>

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
    </div>
</template>
