<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

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
    access_level: string
}

interface SelectOption {
    value: string
    label: string
}

const props = defineProps<{
    templates: MailTemplate[]
}>()

const { t } = useTranslate()

const search = ref('')
const category = ref('')
const type = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const openActionMenuId = ref<number | null>(null)
const actionMenuPosition = ref({ top: 0, left: 0 })
const deleteTarget = ref<MailTemplate | null>(null)

const categoryOptions = computed<SelectOption[]>(() => {
    const categories = [...new Set(props.templates.map((template) => template.category).filter(Boolean))]

    return [
        { value: '', label: t('All Categories') },
        ...categories.map((item) => ({ value: item, label: item })),
    ]
})

const typeOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All Types') },
    { value: 'system', label: t('System') },
    { value: 'custom', label: t('Custom') },
    { value: 'active', label: t('Active') },
    { value: 'disabled', label: t('Disabled') },
    { value: 'pro', label: t('Pro') },
])

const filteredTemplates = computed(() => {
    const term = search.value.trim().toLowerCase()

    return props.templates.filter((template) => {
        const matchesSearch = !term || [
            template.name,
            template.slug,
            template.subject,
            template.category,
        ].some((value) => value.toLowerCase().includes(term))

        const matchesCategory = !category.value || template.category === category.value

        const matchesType = !type.value || (
            (type.value === 'system' && template.is_system) ||
            (type.value === 'custom' && !template.is_system) ||
            (type.value === 'active' && template.is_active) ||
            (type.value === 'disabled' && !template.is_active) ||
            (type.value === 'pro' && (template.access_level === 'premium' || template.access_level?.startsWith('plan:')))
        )

        return matchesSearch && matchesCategory && matchesType
    })
})

const hasActiveFilters = computed(() => Boolean(search.value || category.value || type.value))

const stats = computed(() => ({
    total: props.templates.length,
    active: props.templates.filter((template) => template.is_active).length,
    system: props.templates.filter((template) => template.is_system).length,
    custom: props.templates.filter((template) => !template.is_system).length,
}))

const toggleActionMenu = async (templateId: number, event: MouseEvent) => {
    if (openActionMenuId.value === templateId) {
        openActionMenuId.value = null
        return
    }

    const trigger = event.currentTarget

    if (!(trigger instanceof HTMLElement)) {
        return
    }

    const rect = trigger.getBoundingClientRect()

    openActionMenuId.value = templateId
    actionMenuPosition.value = {
        top: rect.bottom + 8,
        left: rect.right,
    }

    await nextTick()
}

const closeActionMenu = () => {
    openActionMenuId.value = null
}

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
    closeActionMenu()
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

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof HTMLElement) || target.closest('[data-mail-template-actions]')) {
        return
    }

    openActionMenuId.value = null
}

const handleViewportChange = () => {
    openActionMenuId.value = null
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

    if (event.key === 'Escape' && !deleteTarget.value && !openActionMenuId.value && hasActiveFilters.value) {
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
    document.addEventListener('click', handleDocumentClick)
    document.addEventListener('keydown', handleKeydown)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    document.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('resize', handleViewportChange)
    window.removeEventListener('scroll', handleViewportChange, true)
})
</script>

<template>
    <Head :title="t('Mail Templates')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Mail Templates') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage system notifications and custom email communications from one place.') }}</p>
                </div>

                <Link
                    :href="route('admin.mail.templates.create')"
                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('New Template') }}
                </Link>
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="w-full xl:max-w-md">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInput"
                                    v-model="search"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Filter this table by template, slug, subject, or category...')"
                                >
                                <span
                                    v-if="!search"
                                    class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                                >
                                    /
                                </span>
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

                        <div class="flex flex-col gap-4 xl:ml-auto xl:flex-row xl:items-center xl:justify-end">
                            <div class="w-full md:w-56">
                                <AppSelect v-model="category" :options="categoryOptions" :placeholder="t('All Categories')" />
                            </div>
                            <div class="w-full md:w-48">
                                <AppSelect v-model="type" :options="typeOptions" :placeholder="t('All Types')" />
                            </div>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="clearFilters"
                            >
                                <i class="ti ti-filter-off text-base"></i>
                                {{ t('Clear') }}
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
                                v-for="template in filteredTemplates"
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
                                                v-if="template.access_level === 'premium' || template.access_level?.startsWith('plan:')"
                                                class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/20 dark:text-violet-300"
                                            >
                                                {{ t('Pro') }}
                                            </span>
                                        </div>
                                        <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ template.slug }}</p>
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
                                <td class="px-6 py-5 text-right align-top">
                                    <div class="relative inline-flex justify-end" data-mail-template-actions>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                            @click.stop="toggleActionMenu(template.id, $event)"
                                        >
                                            <i class="ti ti-dots-vertical text-base"></i>
                                        </button>
                                    </div>

                                    <Teleport to="body">
                                        <div
                                            v-if="openActionMenuId === template.id"
                                            data-mail-template-actions
                                            class="fixed z-[80] w-48 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                            :style="{
                                                top: `${actionMenuPosition.top}px`,
                                                left: `${actionMenuPosition.left}px`,
                                                transform: 'translateX(-100%)',
                                            }"
                                        >
                                            <Link
                                                :href="route('admin.mail.templates.edit', template.id)"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                @click="closeActionMenu"
                                            >
                                                <i class="ti ti-pencil text-base"></i>
                                                {{ t('Edit') }}
                                            </Link>
                                            <button
                                                v-if="!template.is_system"
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                @click="requestDelete(template)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Delete') }}
                                            </button>
                                        </div>
                                    </Teleport>
                                </td>
                            </tr>

                            <tr v-if="filteredTemplates.length === 0">
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
