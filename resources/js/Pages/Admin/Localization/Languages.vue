<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FlagIcon from '@/Components/Utility/FlagIcon.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
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
    number_system: string
}

type SelectOption = {
    value: string
    label: string
}

const props = defineProps<{
    manageLanguages: LanguageItem[]
    filters: {
        search?: string
        status?: 'all' | 'active' | 'inactive' | 'rtl' | 'default'
    }
}>()

const { t } = useTranslate()

const showModal = ref(false)
const editingLanguage = ref<LanguageItem | null>(null)
const deleteTarget = ref<LanguageItem | null>(null)
const statusFilter = ref<'all' | 'active' | 'inactive' | 'rtl' | 'default'>(props.filters?.status || 'all')
const searchQuery = ref(props.filters?.search || '')
const searchInputRef = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
const filterDebounce = ref<number | null>(null)

/* Below md these labels ("Jan 31, 2026 - Month name, day, year") are wider than the select,
   so the descriptive half is dropped and only the sample is shown. Desktop is untouched. */
const isNarrow = ref(false)
let narrowQuery: MediaQueryList | null = null
const syncIsNarrow = (e: MediaQueryList | MediaQueryListEvent) => { isNarrow.value = e.matches }

const optionLabel = (sample: string, description: string) => (isNarrow.value ? sample : `${sample} - ${description}`)

const dateFormatOptions = computed<SelectOption[]>(() => [
    { value: 'MMM D, YYYY', label: optionLabel(t('Jan 31, 2026'), t('Month name, day, year')) },
    { value: 'D MMM YYYY', label: optionLabel(t('31 Jan 2026'), t('Day, month name, year')) },
    { value: 'MM/DD/YYYY', label: optionLabel(t('01/31/2026'), t('US numeric date')) },
    { value: 'DD/MM/YYYY', label: optionLabel(t('31/01/2026'), t('International numeric date')) },
    { value: 'YYYY-MM-DD', label: optionLabel(t('2026-01-31'), t('ISO date')) },
])

const timeFormatOptions = computed<SelectOption[]>(() => [
    { value: 'h:mm A', label: optionLabel(t('9:30 PM'), t('12-hour clock')) },
    { value: 'HH:mm', label: optionLabel(t('21:30'), t('24-hour clock')) },
    { value: 'HH:mm:ss', label: optionLabel(t('21:30:45'), t('24-hour clock with seconds')) },
])

// Reversed here: the digit sample is the short half, so it is what gets dropped.
const numberSystemLabel = (name: string, sample: string) => (isNarrow.value ? name : `${name} - ${sample}`)

const numberSystemOptions = computed<SelectOption[]>(() => [
    { value: 'latn', label: numberSystemLabel(t('English digits'), '123,456.78') },
    { value: 'arab', label: numberSystemLabel(t('Arabic-Indic digits'), '١٢٣,٤٥٦.٧٨') },
    { value: 'arabext', label: numberSystemLabel(t('Eastern Arabic (Persian) digits'), '۱۲۳,۴۵۶.۷۸') },
    { value: 'beng', label: numberSystemLabel(t('Bengali digits'), '১২৩,৪৫৬.৭৮') },
    { value: 'deva', label: numberSystemLabel(t('Devanagari (Hindi) digits'), '१२३,४५६.७८') },
    { value: 'thai', label: numberSystemLabel(t('Thai digits'), '๑๒๓,๔๕๖.๗๘') },
    { value: 'tamldec', label: numberSystemLabel(t('Tamil digits'), '௧௨௩,௪௫௬.௭௮') },
    { value: 'telu', label: numberSystemLabel(t('Telugu digits'), '౧౨౩,౪౫౬.౭౮') },
    { value: 'knda', label: numberSystemLabel(t('Kannada digits'), '೧೨೩,೪೫೬.೭೮') },
    { value: 'mlym', label: numberSystemLabel(t('Malayalam digits'), '൧൨൩,൪൫൬.൭൮') },
    { value: 'gujr', label: numberSystemLabel(t('Gujarati digits'), '૧૨૩,૪૫૬.૭૮') },
    { value: 'guru', label: numberSystemLabel(t('Gurmukhi (Punjabi) digits'), '੧੨੩,੪੫੬.੭੮') },
    { value: 'mymr', label: numberSystemLabel(t('Myanmar digits'), '၁၂၃,၄၅၆.၇၈') },
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
    number_system: 'latn',
})

const applyFilters = () => {
    const params: Record<string, string> = {}
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }
    if (statusFilter.value && statusFilter.value !== 'all') {
        params.status = statusFilter.value
    }

    router.get(route('admin.languages.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([searchQuery, statusFilter], () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
    filterDebounce.value = window.setTimeout(() => {
        applyFilters()
    }, 250) as unknown as number
})

const hasActiveFilters = computed(() => searchQuery.value.trim().length > 0 || statusFilter.value !== 'all')

function resetForm() {
    form.reset()
    form.clearErrors()
    form.flag_file = null
    form.is_rtl = false
    form.is_active = true
    form.date_format = 'MMM D, YYYY'
    form.time_format = 'h:mm A'
    form.number_system = 'latn'
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
    form.number_system = language.number_system
    form.clearErrors()
    showModal.value = true
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
    useForm({}).post(route('admin.languages.default', id))
}

function confirmDelete(language: LanguageItem) {
    deleteTarget.value = language
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

function clearSearchAndFilters() {
    searchQuery.value = ''
    statusFilter.value = 'all'
}

function isTypingTarget(target: EventTarget | null) {
    if (!(target instanceof HTMLElement)) {
        return false
    }

    const tagName = target.tagName.toLowerCase()

    return tagName === 'input'
        || tagName === 'textarea'
        || tagName === 'select'
        || target.isContentEditable
}

function handleKeydown(event: KeyboardEvent) {
    if (showModal.value || deleteTarget.value) {
        return
    }

    if (event.key === '/') {
        if (event.metaKey || event.ctrlKey || event.altKey || isTypingTarget(event.target)) {
            return
        }

        event.preventDefault()
        searchInputRef.value?.focus()
        searchInputRef.value?.select()
        return
    }

    if (event.key === 'Escape') {
        if (document.activeElement === searchInputRef.value) {
            event.preventDefault()
            searchQuery.value = ''
            searchInputRef.value?.blur()
            return
        }

        if (hasActiveFilters.value) {
            if (isTypingTarget(event.target)) {
                return
            }

            clearSearchAndFilters()
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)

    narrowQuery = window.matchMedia('(max-width: 767px)')
    syncIsNarrow(narrowQuery)
    narrowQuery.addEventListener('change', syncIsNarrow)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    narrowQuery?.removeEventListener('change', syncIsNarrow)
})
</script>

<template>
    <Head :title="t('Languages')" />

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Languages') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage available platform languages, locale formatting rules, and the default experience used across the application.') }}
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 btn-primary-admin shrink-0"
                @click="openCreateModal"
            >
                <i class="ti ti-plus text-base" />
                {{ t('Add Language') }}
            </button>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex-1 min-w-[220px] md:max-w-sm">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base" />
                            </span>
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :placeholder="t('Search languages by name, code, or format...')"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            >
                            <span
                                v-if="!searchQuery && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                            >/</span>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                :title="t('Clear search')"
                                @click="clearSearch"
                            >
                                <i class="ti ti-x text-base" />
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-56 lg:flex-none">
                            <AppSelect v-model="statusFilter" :options="statusOptions" :placeholder="t('Select filter')" />
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

            <div class="overflow-visible">
                <div class="overflow-x-auto overflow-y-visible">
                    <div class="min-w-[980px]">
                        <table class="min-w-full table-auto">
                            <thead class="bg-gray-50 dark:bg-surface-950/60">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Language') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Locale Rules') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Number Format') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="language in props.manageLanguages"
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
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Date') }}</span>
                                                <span>{{ language.date_format }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Time') }}</span>
                                                <span>{{ language.time_format }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('System') }}: <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ language.number_system }}</span></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <span v-if="language.is_default" class="inline-flex rounded-full bg-primary-100 px-2.5 py-1 text-xs font-medium text-primary-600 dark:bg-primary-900/30 dark:text-primary-300">{{ t('Default') }}</span>
                                            <span :class="language.is_active ? 'bg-green-100 text-green-600 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-300'" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                                {{ language.is_active ? t('Active') : t('Inactive') }}
                                            </span>
                                            <span v-if="language.is_rtl" class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-surface-800 dark:text-gray-300">{{ t('RTL') }}</span>
                                        </div>
                                    </td>
                                    <td class="overflow-visible px-6 py-4 text-right">
                                        <TableActionMenu>
                                            <template #default="{ close }">
                                                <Link
                                                    :href="route('admin.translations.index', language.id)"
                                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                    @click="close"
                                                >
                                                    <i class="ti ti-language text-base" />
                                                    <span>{{ t('Translations') }}</span>
                                                </Link>
                                                <button
                                                    v-if="!language.is_default"
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                    @click="setDefault(language.id); close()"
                                                >
                                                    <i class="ti ti-star text-base" />
                                                    <span>{{ t('Set Default') }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                    @click="openEditModal(language); close()"
                                                >
                                                    <i class="ti ti-pencil text-base" />
                                                    <span>{{ t('Edit Language') }}</span>
                                                </button>
                                                <hr v-if="!language.is_default" class="my-1 border-gray-200 dark:border-surface-700" />
                                                <button
                                                    v-if="!language.is_default"
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                    @click="confirmDelete(language); close()"
                                                >
                                                    <i class="ti ti-trash text-base" />
                                                    <span>{{ t('Delete') }}</span>
                                                </button>
                                            </template>
                                        </TableActionMenu>
                                    </td>
                                </tr>

                                <tr v-if="props.manageLanguages.length === 0">
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <div class="mx-auto max-w-sm space-y-3">
                                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                                <i class="ti ti-language text-2xl" />
                                            </div>
                                            <div>
                                                <p class="text-base font-semibold text-gray-900 dark:text-white">
                                                    {{ props.manageLanguages.length === 0 ? t('No languages yet') : t('No matching languages found') }}
                                                </p>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{
                                                        props.manageLanguages.length === 0
                                                            ? t('Add a language to start managing localization and locale formatting rules.')
                                                            : t('Try another search term or clear the active language filters.')
                                                    }}
                                                </p>
                                            </div>
                                            <div class="mt-6 flex justify-center gap-3">
                                                <button
                                                    v-if="props.manageLanguages.length === 0"
                                                    type="button"
                                                    class="rounded-lg px-5 py-2.5 text-sm font-medium text-white btn-primary-admin"
                                                    @click="openCreateModal"
                                                >
                                                    {{ t('Add First Language') }}
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                                    @click="clearSearchAndFilters"
                                                >
                                                    {{ t('Clear Filters') }}
                                                </button>
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


    <AppModal
        :open="showModal"
        max-width="max-w-4xl"
        :title="editingLanguage ? t('Edit Language') : t('Create Language')"
        :subtitle="t('Configure locale formatting, direction, and visibility for this language.')"
        has-form
        :confirm-text="editingLanguage ? t('Update Language') : t('Create Language')"
        :confirm-loading="form.processing"
        confirm-loading-text="Processing..."
        @close="closeModal"
        @submit="submit"
    >
        <div class="space-y-6">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Language Details') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Set the language name, ISO code, and optional flag asset.') }}</p>
                </div>

                <div class="grid gap-6 xl:grid-cols-12">
                    <div class="xl:col-span-7">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Language Name') }}</label>
                        <input
                            v-model="form.name"
                            type="text"
                            :placeholder="t('e.g. French')"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
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
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 disabled:opacity-60 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            required
                        >
                        <p v-if="form.errors.code" class="mt-2 text-xs text-red-600">{{ form.errors.code }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Flag Image') }}</label>
                    <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-950/40 md:flex-row md:items-center">
                        <div v-if="editingLanguage" class="shrink-0">
                            <FlagIcon :flag="editingLanguage.flag" :language-code="editingLanguage.code" :language-name="editingLanguage.name" size="lg" />
                        </div>
                        <input
                            type="file"
                            accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100"
                            @change="setFlagFile"
                        >
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Upload SVG, PNG, JPG, or WebP up to 512 KB.') }}</p>
                    <p v-if="form.errors.flag_file" class="mt-2 text-xs text-red-600">{{ form.errors.flag_file }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Locale Formatting') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control how dates, times, and numbers are displayed for this language.') }}</p>
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
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Availability') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose whether the language is active and whether it should use RTL layout rules.') }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('RTL Layout') }}</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Enable right-to-left reading direction.') }}</span>
                        </span>
                        <AppSwitch v-model="form.is_rtl" />
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Active') }}</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Show this language across the platform.') }}</span>
                        </span>
                        <AppSwitch v-model="form.is_active" />
                    </div>
                </div>
            </section>
        </div>
    </AppModal>

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
