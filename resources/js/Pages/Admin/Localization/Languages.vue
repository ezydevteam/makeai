<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FlagIcon from '@/Components/FlagIcon.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type LanguageItem = {
    id: number
    name: string
    code: string
    flag: string | null
    is_rtl: boolean
    is_active: boolean
    is_default: boolean
    date_format: string
    time_format: string
    decimal_separator: string
    thousands_separator: string
    number_system: string
    currency_position: string
}

type SelectOption = {
    value: string
    label: string
}

const props = defineProps<{
    languages: LanguageItem[]
}>()

const { t } = useTranslate()

const showModal = ref(false)
const editingLanguage = ref<LanguageItem | null>(null)
const deleteTarget = ref<LanguageItem | null>(null)
const statusFilter = ref<'all' | 'active' | 'inactive' | 'rtl' | 'default'>('all')
const searchQuery = ref('')
const openActionMenuId = ref<number | null>(null)
const actionMenuPosition = ref({ top: 0, left: 0, openUpward: false })

const ACTION_MENU_WIDTH = 208
const ACTION_MENU_ESTIMATED_HEIGHT = 188
const ACTION_MENU_GAP = 8
const VIEWPORT_PADDING = 16

const dateFormatOptions = computed<SelectOption[]>(() => [
    { value: 'MMM D, YYYY', label: `${t('Jan 31, 2026')} - ${t('Month name, day, year')}` },
    { value: 'D MMM YYYY', label: `${t('31 Jan 2026')} - ${t('Day, month name, year')}` },
    { value: 'MM/DD/YYYY', label: `${t('01/31/2026')} - ${t('US numeric date')}` },
    { value: 'DD/MM/YYYY', label: `${t('31/01/2026')} - ${t('International numeric date')}` },
    { value: 'YYYY-MM-DD', label: `${t('2026-01-31')} - ${t('ISO date')}` },
])

const timeFormatOptions = computed<SelectOption[]>(() => [
    { value: 'h:mm A', label: `${t('9:30 PM')} - ${t('12-hour clock')}` },
    { value: 'HH:mm', label: `${t('21:30')} - ${t('24-hour clock')}` },
    { value: 'HH:mm:ss', label: `${t('21:30:45')} - ${t('24-hour clock with seconds')}` },
])

const numberSystemOptions = computed<SelectOption[]>(() => [
    { value: 'latn', label: `${t('English digits')} - 123,456.78` },
    { value: 'arab', label: `${t('Arabic-Indic digits')} - ١٢٣٬٤٥٦٫٧٨` },
    { value: 'arabext', label: `${t('Eastern Arabic digits')} - ۱۲۳٬۴۵۶٫۷۸` },
    { value: 'beng', label: `${t('Bengali digits')} - ১২৩,৪৫৬.৭৮` },
    { value: 'deva', label: `${t('Devanagari digits')} - १२३,४५६.७८` },
])

const currencyPositionOptions = computed<SelectOption[]>(() => [
    { value: 'before', label: `${t('Before amount')} - $99.00` },
    { value: 'before_with_space', label: `${t('Before amount with space')} - $ 99.00` },
    { value: 'after', label: `${t('After amount')} - 99.00$` },
    { value: 'after_with_space', label: `${t('After amount with space')} - 99.00 $` },
])

const statusOptions = computed<SelectOption[]>(() => [
    { value: 'all', label: t('All Languages') },
    { value: 'active', label: t('Active Only') },
    { value: 'inactive', label: t('Inactive Only') },
    { value: 'rtl', label: t('RTL Only') },
    { value: 'default', label: t('Default Locale') },
])

const form = useForm({
    name: '',
    code: '',
    flag_file: null as File | null,
    is_rtl: false,
    is_active: true,
    date_format: 'MMM D, YYYY',
    time_format: 'h:mm A',
    decimal_separator: '.',
    thousands_separator: ',',
    number_system: 'latn',
    currency_position: 'before',
})

const stats = computed(() => ({
    total: props.languages.length,
    active: props.languages.filter((language) => language.is_active).length,
    rtl: props.languages.filter((language) => language.is_rtl).length,
    defaultLanguage: props.languages.find((language) => language.is_default)?.name ?? t('Not set'),
}))

const filteredLanguages = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.languages.filter((language) => {
        const matchesStatus = (() => {
            switch (statusFilter.value) {
                case 'active':
                    return language.is_active
                case 'inactive':
                    return !language.is_active
                case 'rtl':
                    return language.is_rtl
                case 'default':
                    return language.is_default
                default:
                    return true
            }
        })()

        if (!matchesStatus) {
            return false
        }

        if (!query) {
            return true
        }

        return [
            language.name,
            language.code,
            language.date_format,
            language.time_format,
            language.number_system,
            language.currency_position,
        ].some((value) => value.toLowerCase().includes(query))
    })
})

function resetForm() {
    form.reset()
    form.clearErrors()
    form.flag_file = null
    form.is_rtl = false
    form.is_active = true
    form.date_format = 'MMM D, YYYY'
    form.time_format = 'h:mm A'
    form.decimal_separator = '.'
    form.thousands_separator = ','
    form.number_system = 'latn'
    form.currency_position = 'before'
}

function openCreateModal() {
    editingLanguage.value = null
    resetForm()
    showModal.value = true
}

function openEditModal(language: LanguageItem) {
    editingLanguage.value = language
    form.name = language.name
    form.code = language.code
    form.flag_file = null
    form.is_rtl = language.is_rtl
    form.is_active = language.is_active
    form.date_format = language.date_format
    form.time_format = language.time_format
    form.decimal_separator = language.decimal_separator
    form.thousands_separator = language.thousands_separator
    form.number_system = language.number_system
    form.currency_position = language.currency_position || 'before'
    form.clearErrors()
    showModal.value = true
    openActionMenuId.value = null
}

function closeModal() {
    if (form.processing) {
        return
    }

    showModal.value = false
    editingLanguage.value = null
}

function submit() {
    const options = {
        forceFormData: true,
        preserveState: false,
        onSuccess: () => {
            showModal.value = false
            editingLanguage.value = null
            resetForm()
        },
    }

    if (editingLanguage.value) {
        form.post(route('admin.languages.update', editingLanguage.value.id), options)
        return
    }

    form.post(route('admin.languages.store'), options)
}

function setDefault(id: number) {
    openActionMenuId.value = null
    useForm({}).post(route('admin.languages.default', id))
}

function confirmDelete(language: LanguageItem) {
    deleteTarget.value = language
    openActionMenuId.value = null
}

function deleteLanguage() {
    if (!deleteTarget.value) {
        return
    }

    useForm({}).delete(route('admin.languages.delete', deleteTarget.value.id), {
        onFinish: () => {
            deleteTarget.value = null
        },
    })
}

function setFlagFile(event: Event) {
    form.flag_file = (event.target as HTMLInputElement).files?.[0] ?? null
}

function clearSearch() {
    searchQuery.value = ''
}

async function toggleActionMenu(id: number, event: MouseEvent) {
    if (openActionMenuId.value === id) {
        openActionMenuId.value = null
        return
    }

    const trigger = event.currentTarget

    if (!(trigger instanceof HTMLElement)) {
        return
    }

    const rect = trigger.getBoundingClientRect()
    const spaceBelow = window.innerHeight - rect.bottom
    const spaceAbove = rect.top
    const openUpward = spaceBelow < ACTION_MENU_ESTIMATED_HEIGHT && spaceAbove > spaceBelow
    const top = openUpward
        ? Math.max(VIEWPORT_PADDING, rect.top - ACTION_MENU_GAP)
        : Math.min(window.innerHeight - VIEWPORT_PADDING, rect.bottom + ACTION_MENU_GAP)
    const left = Math.min(window.innerWidth - VIEWPORT_PADDING, rect.right)

    actionMenuPosition.value = {
        top,
        left,
        openUpward,
    }

    openActionMenuId.value = id
    await nextTick()
}

function handleWindowClick(event: MouseEvent) {
    const target = event.target as HTMLElement | null

    if (!target?.closest('[data-language-actions]')) {
        openActionMenuId.value = null
    }
}

onMounted(() => {
    window.addEventListener('click', handleWindowClick)
})

onUnmounted(() => {
    window.removeEventListener('click', handleWindowClick)
})
</script>

<template>
    <Head :title="t('Languages')" />

    <div class="space-y-6">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('Languages') }}</h1>
                <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-300">
                    {{ t('Manage available platform languages, locale formatting rules, and the default experience used across the application.') }}
                </p>
            </div>

            <button
                type="button"
                class="btn-primary"
                @click="openCreateModal"
            >
                <i class="ti ti-plus text-base" />
               {{ t('Add Language') }}
            </button>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Total Languages') }}</p>
                <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats.total }}</p>
            </div>
            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Active') }}</p>
                <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats.active }}</p>
            </div>
            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('RTL Languages') }}</p>
                <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats.rtl }}</p>
            </div>
            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Default Locale') }}</p>
                <p class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">{{ stats.defaultLanguage }}</p>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 dark:border-surface-800 lg:flex-row lg:items-center lg:justify-between">
                <div class="w-full max-w-md">
                    <label class="sr-only">{{ t('Search languages') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                            <i class="ti ti-search text-base" />
                        </span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('Search by language, code, or format')"
                            class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-11 pr-11 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                        >
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                            :title="t('Clear search')"
                            @click="clearSearch"
                        >
                            <i class="ti ti-x text-base" />
                        </button>
                    </div>
                </div>

                <div class="w-full lg:w-64">
                    <AppSelect v-model="statusFilter" :options="statusOptions" :placeholder="t('Select filter')" />
                </div>
            </div>

            <div class="overflow-visible">
                <div class="overflow-x-auto overflow-y-visible">
                <div class="min-w-[980px]">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50 dark:bg-surface-950/60">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Language') }}</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Locale Rules') }}</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Number Format') }}</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="language in filteredLanguages"
                                :key="language.id"
                                class="border-t border-gray-100 align-top transition hover:bg-primary-50/40 dark:border-surface-800 dark:hover:bg-primary-900/5"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <FlagIcon :flag="language.flag" :language-code="language.code" :language-name="language.name" size="lg" />
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ language.name }}</p>
                                            <p class="mt-1 text-xs font-mono uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ language.code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ t('Date') }}</span>
                                            <span>{{ language.date_format }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ t('Time') }}</span>
                                            <span>{{ language.time_format }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ t('Currency') }}</span>
                                            <span>{{ language.currency_position }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                        <p>{{ t('System') }}: <span class="font-medium">{{ language.number_system }}</span></p>
                                        <p>{{ t('Decimal') }}: <span class="font-medium">{{ language.decimal_separator }}</span></p>
                                        <p>{{ t('Thousands') }}: <span class="font-medium">{{ language.thousands_separator }}</span></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <span v-if="language.is_default" class="inline-flex rounded-full bg-primary-100 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ t('Default') }}</span>
                                        <span :class="language.is_active ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                            {{ language.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                        <span v-if="language.is_rtl" class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-surface-800 dark:text-gray-300">{{ t('RTL') }}</span>
                                    </div>
                                </td>
                                <td class="overflow-visible px-6 py-4 text-right">
                                    <div class="relative inline-flex" data-language-actions>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-primary-200 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-700 dark:hover:text-primary-300"
                                            :title="t('Open actions')"
                                            @click.stop="toggleActionMenu(language.id, $event)"
                                        >
                                            <i class="ti ti-dots-vertical text-lg" />
                                        </button>
                                    </div>
                                    <Teleport to="body">
                                        <div
                                            v-if="openActionMenuId === language.id"
                                            data-language-actions
                                            class="fixed z-[80] w-52 overflow-hidden rounded-2xl border border-gray-200 bg-white p-2 text-left shadow-xl dark:border-surface-700 dark:bg-surface-900"
                                            :style="{
                                                top: `${actionMenuPosition.top}px`,
                                                left: `${actionMenuPosition.left}px`,
                                                transform: actionMenuPosition.openUpward ? `translate(-${ACTION_MENU_WIDTH}px, -100%)` : `translateX(-${ACTION_MENU_WIDTH}px)`,
                                            }"
                                        >
                                            <Link
                                                :href="route('admin.translations.index', language.id)"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                            >
                                                <i class="ti ti-language text-base" />
                                                <span>{{ t('Translations') }}</span>
                                            </Link>
                                            <button
                                                v-if="!language.is_default"
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                @click="setDefault(language.id)"
                                            >
                                                <i class="ti ti-star text-base" />
                                                <span>{{ t('Set Default') }}</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                @click="openEditModal(language)"
                                            >
                                                <i class="ti ti-pencil text-base" />
                                                <span>{{ t('Edit Language') }}</span>
                                            </button>
                                            <button
                                                v-if="!language.is_default"
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                @click="confirmDelete(language)"
                                            >
                                                <i class="ti ti-trash text-base" />
                                                <span>{{ t('Delete Language') }}</span>
                                            </button>
                                        </div>
                                    </Teleport>
                                </td>
                            </tr>

                            <tr v-if="filteredLanguages.length === 0">
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-sm space-y-3">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                            <i class="ti ti-language text-2xl" />
                                        </div>
                                        <div>
                                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ t('No languages found') }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ t('Try another search or filter, or add a new language to expand localization coverage.') }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </section>
    </div>

    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="w-full max-w-4xl overflow-hidden rounded-[1.25rem] border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                    <div class="space-y-1">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ editingLanguage ? t('Edit Language') : t('Create Language') }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Configure locale formatting, direction, and visibility for this language.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                        @click="closeModal"
                    >
                        <i class="ti ti-x text-lg" />
                    </button>
                </div>

                <form class="max-h-[calc(100vh-8rem)] overflow-y-auto" @submit.prevent="submit">
                    <div class="grid gap-6 p-6">
                        <div class="grid gap-6 xl:grid-cols-12">
                            <div class="xl:col-span-7">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Language Name') }}</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="t('e.g. French')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                    required
                                >
                                <p v-if="form.errors.name" class="mt-2 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div class="xl:col-span-5">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('ISO Code') }}</label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    :placeholder="t('e.g. fr')"
                                    :disabled="Boolean(editingLanguage)"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 disabled:opacity-60 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                    required
                                >
                                <p v-if="form.errors.code" class="mt-2 text-xs text-red-600">{{ form.errors.code }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Flag Image') }}</label>
                            <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-950/40 md:flex-row md:items-center">
                                <div v-if="editingLanguage" class="shrink-0">
                                    <FlagIcon :flag="editingLanguage.flag" :language-code="editingLanguage.code" :language-name="editingLanguage.name" size="lg" />
                                </div>
                                <input
                                    type="file"
                                    accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100"
                                    @change="setFlagFile"
                                >
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Upload SVG, PNG, JPG, or WebP up to 512 KB.') }}</p>
                            <p v-if="form.errors.flag_file" class="mt-2 text-xs text-red-600">{{ form.errors.flag_file }}</p>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Date Format') }}</label>
                                <AppSelect v-model="form.date_format" :options="dateFormatOptions" :placeholder="t('Select date format')" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Time Format') }}</label>
                                <AppSelect v-model="form.time_format" :options="timeFormatOptions" :placeholder="t('Select time format')" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Number System') }}</label>
                                <AppSelect v-model="form.number_system" :options="numberSystemOptions" :placeholder="t('Select number system')" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Currency Position') }}</label>
                                <AppSelect v-model="form.currency_position" :options="currencyPositionOptions" :placeholder="t('Select currency position')" />
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Decimal Separator') }}</label>
                                <input v-model="form.decimal_separator" type="text" maxlength="1" :placeholder="t('e.g. .')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30" required>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Thousands Separator') }}</label>
                                <input v-model="form.thousands_separator" type="text" maxlength="1" :placeholder="t('e.g. ,')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30" required>
                            </div>
                            <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('RTL Layout') }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Enable right-to-left reading direction.') }}</span>
                                </span>
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.is_rtl ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'"
                                    @click="form.is_rtl = !form.is_rtl"
                                >
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition" :class="form.is_rtl ? 'translate-x-5' : 'translate-x-1'" />
                                </button>
                            </label>
                            <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Active') }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Show this language across the platform.') }}</span>
                                </span>
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.is_active ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'"
                                    @click="form.is_active = !form.is_active"
                                >
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition" :class="form.is_active ? 'translate-x-5' : 'translate-x-1'" />
                                </button>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/80 px-6 py-4 dark:border-surface-800 dark:bg-surface-950/50">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-200 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                            @click="closeModal"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn-primary rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-60"
                        >
                            {{ form.processing ? t('Processing...') : (editingLanguage ? t('Update Language') : t('Create Language')) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <ActionConfirmModal
        :open="Boolean(deleteTarget)"
        :title="t('Delete language?')"
        :message="t('Delete this language and all of its translations? This action cannot be undone.')"
        :confirm-label="t('Delete Language')"
        :cancel-label="t('Cancel')"
        @cancel="deleteTarget = null"
        @confirm="deleteLanguage"
    />
</template>
