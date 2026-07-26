<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { mediaUrl } from '@/lib/media'
import { fromDateTimeLocalInput, toDateTimeLocalInput } from '@/lib/datetime'

defineOptions({ layout: AdminLayout })

interface PlanOption { id: number; name: string }
interface AdItem {
    id: number
    title: string
    type: string
    zone: string
    show_to: string
    impressions: number | string | null
    clicks: number | string | null
    ctr: number | string
    is_active: boolean
    adsense_client?: string | null
    adsense_slot?: string | null
    adsense_format?: string | null
    custom_html?: string | null
    image_url?: string | null
    link_url?: string | null
    link_target?: string | null
    start_at?: string | null
    end_at?: string | null
    sort_order?: number | null
}

type AdEditorMode = 'create' | 'edit'

const props = defineProps<{
    ads: AdItem[]
    zones: Record<string, string>
    settings: {
        ads_enabled: boolean
        adsense_publisher_id: string
        ads_auto_ads_enabled: boolean
        ads_disable_for_subscribed_users: boolean
        ads_disabled_plan_ids: number[]
    }
    plans: PlanOption[]
    filters: {
        search?: string
        status?: string
    }
    editorAd?: AdItem | null
    editorMode?: AdEditorMode | null
}>()

const { t } = useTranslate()
const { formatNumber } = useNumberFormat()
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
const deleteTarget = ref<AdItem | null>(null)
const settingsModalOpen = ref(false)
const adModalOpen = ref(Boolean(props.editorMode))
const imagePreviewUrl = ref<string | null>(props.editorAd?.image_url ?? null)
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const filterDebounce = ref<number | null>(null)

const zoneOptions = computed<SelectOption[]>(() =>
    Object.entries(props.zones).map(([value, label]) => ({ value, label })),
)
const statusOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('All statuses') },
    { value: 'active', label: t('Active') },
    { value: 'paused', label: t('Paused') },
])
const adTypeOptions = computed<SelectOption[]>(() => [
    { value: 'image_link', label: t('Image link') },
    { value: 'custom_html', label: t('Custom HTML') },
    { value: 'adsense', label: t('AdSense') },
])
const audienceOptions = computed<SelectOption[]>(() => [
    { value: 'all', label: t('All visitors') },
    { value: 'guests', label: t('Guests only') },
    { value: 'logged_in', label: t('Logged in users') },
    // Free/Paid targeting only makes sense when a paid tier exists.
    ...(isProAvailable.value ? [
        { value: 'free_users', label: t('Free users') },
        { value: 'paid_users', label: t('Premium users') },
    ] : []),
])
const linkTargetOptions = computed<SelectOption[]>(() => [
    { value: '_blank', label: t('New tab') },
    { value: '_self', label: t('Same tab') },
])
const settingsForm = useForm({
    ads_enabled: props.settings.ads_enabled,
    adsense_publisher_id: props.settings.adsense_publisher_id,
    ads_auto_ads_enabled: props.settings.ads_auto_ads_enabled,
    ads_disable_for_subscribed_users: props.settings.ads_disable_for_subscribed_users,
    ads_disabled_plan_ids: props.settings.ads_disabled_plan_ids ?? [],
})
const adForm = useForm({
    title: props.editorAd?.title ?? '',
    type: props.editorAd?.type ?? 'image_link',
    zone: props.editorAd?.zone ?? 'header_banner',
    adsense_client: props.editorAd?.adsense_client ?? '',
    adsense_slot: props.editorAd?.adsense_slot ?? '',
    adsense_format: props.editorAd?.adsense_format ?? 'auto',
    custom_html: props.editorAd?.custom_html ?? '',
    image_file: null as File | null,
    link_url: props.editorAd?.link_url ?? '',
    link_target: props.editorAd?.link_target ?? '_blank',
    show_to: props.editorAd?.show_to ?? 'all',
    is_active: props.editorAd?.is_active ?? true,
    start_at: toDateTimeLocalInput(props.editorAd?.start_at),
    end_at: toDateTimeLocalInput(props.editorAd?.end_at),
    sort_order: props.editorAd?.sort_order ?? 0,
})

const resetAdForm = (ad: AdItem | null = null) => {
    adForm.defaults({
        title: ad?.title ?? '',
        type: ad?.type ?? 'image_link',
        zone: ad?.zone ?? 'header_banner',
        adsense_client: ad?.adsense_client ?? '',
        adsense_slot: ad?.adsense_slot ?? '',
        adsense_format: ad?.adsense_format ?? 'auto',
        custom_html: ad?.custom_html ?? '',
        image_file: null,
        link_url: ad?.link_url ?? '',
        link_target: ad?.link_target ?? '_blank',
        show_to: ad?.show_to ?? 'all',
        is_active: ad?.is_active ?? true,
        start_at: toDateTimeLocalInput(ad?.start_at),
        end_at: toDateTimeLocalInput(ad?.end_at),
        sort_order: ad?.sort_order ?? 0,
    })
    adForm.reset()
    adForm.clearErrors()
    imagePreviewUrl.value = ad?.image_url ?? null
}

const buildFilterQuery = () => {
    const query: Record<string, string> = {}

    if (search.value.trim()) {
        query.search = search.value.trim()
    }

    if (status.value) {
        query.status = status.value
    }

    return query
}

const applyFilters = () => {
    router.get(route('admin.ads.index'), buildFilterQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['ads', 'filters', 'editorAd', 'editorMode'],
    })
}

const clearFilters = () => {
    search.value = ''
    status.value = ''
    searchInput.value?.blur()
}

const hasActiveFilters = computed(() => search.value.trim().length > 0 || status.value !== '')

const toggleAd = (id: number) => {
    router.post(route('admin.ads.toggle', id), {}, { preserveScroll: true })
}

const deleteAd = () => {
    if (!deleteTarget.value) {
        return
    }

    router.delete(route('admin.ads.delete', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null
        },
    })
}

const togglePlan = (planId: number) => {
    settingsForm.ads_disabled_plan_ids = settingsForm.ads_disabled_plan_ids.includes(planId)
        ? settingsForm.ads_disabled_plan_ids.filter((id) => id !== planId)
        : [...settingsForm.ads_disabled_plan_ids, planId]
}

const openCreateModal = () => {
    resetAdForm()
    adModalOpen.value = true
    router.get(route('admin.ads.create'), buildFilterQuery(), { preserveState: true, preserveScroll: true, replace: true })
}

const openEditModal = (ad: AdItem) => {
    resetAdForm(ad)
    adModalOpen.value = true
    router.get(route('admin.ads.edit', ad.id), buildFilterQuery(), { preserveState: true, preserveScroll: true, replace: true })
}

const closeAdModal = () => {
    adModalOpen.value = false
    adForm.clearErrors()
    router.get(route('admin.ads.index'), buildFilterQuery(), { preserveState: true, preserveScroll: true, replace: true })
}

const submitAdForm = () => {
    const url = props.editorMode === 'edit' && props.editorAd
        ? route('admin.ads.update', props.editorAd.id)
        : route('admin.ads.store')

    adForm.transform((data) => ({
        ...data,
        start_at: fromDateTimeLocalInput(data.start_at),
        end_at: fromDateTimeLocalInput(data.end_at),
        adsense_client: data.adsense_client || null,
        adsense_format: data.adsense_format || 'auto',
    })).post(url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            adModalOpen.value = false
        },
    })
}

const handleImageUpload = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null

    adForm.image_file = file
    adForm.clearErrors('image_file')
    imagePreviewUrl.value = file ? URL.createObjectURL(file) : (props.editorAd?.image_url ?? null)
}

const saveSettings = () => settingsForm.post(route('admin.ads.settings'), {
    preserveScroll: true,
    onSuccess: () => {
        settingsModalOpen.value = false
    },
})
const adModalTitle = computed(() => props.editorMode === 'edit' ? t('Edit Advertisement') : t('Create Advertisement'))
const adSubmitLabel = computed(() => {
    if (adForm.processing) {
        return t('Saving...')
    }

    return props.editorMode === 'edit' ? t('Save Ad') : t('Create Ad')
})

watch(() => props.editorAd, (ad) => {
    resetAdForm(ad ?? null)
})

watch(() => props.editorMode, (mode) => {
    adModalOpen.value = Boolean(mode)
})

watch(() => props.filters, (filters) => {
    search.value = filters.search ?? ''
    status.value = filters.status ?? ''
}, { deep: true })

watch([search, status], () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
    filterDebounce.value = window.setTimeout(() => {
        applyFilters()
    }, 250) as unknown as number
})

const isTypingTarget = (target: EventTarget | null) =>
    target instanceof HTMLElement && (
        target.tagName === 'INPUT'
        || target.tagName === 'TEXTAREA'
        || target.tagName === 'SELECT'
        || target.isContentEditable
        || target.closest('[role="dialog"]') !== null
    )

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === '/' && !adModalOpen.value && !settingsModalOpen.value && !deleteTarget.value) {
        if (isTypingTarget(event.target) && event.target !== searchInput.value) {
            return
        }

        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && !adModalOpen.value && !settingsModalOpen.value && !deleteTarget.value && (search.value || status.value)) {
        clearFilters()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Ads')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Advertisements') }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ t('Manage ad zones, delivery rules, AdSense settings, and performance from one unified admin workspace.') }}</p>
            </div>
            <div class="shrink-0 flex gap-3">
                <button type="button" class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300" @click="settingsModalOpen = true">
                    <i class="ti ti-settings text-base"></i>
                    <span>{{ t('Settings') }}</span>
                </button>
                <button type="button" class="grow inline-flex items-center justify-center gap-2 btn-primary-admin" @click="openCreateModal">
                    <i class="ti ti-plus text-base"></i>
                    <span>{{ t('Create Ad') }}</span>
                </button>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="w-full min-w-[220px] md:max-w-sm">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base" />
                            </span>
                            <input
                                ref="searchInput"
                                v-model="search"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :placeholder="t('Search ads...')"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            />
                            <span
                                v-if="!search && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                            >
                                /
                            </span>
                            <button
                                v-if="search"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                @click="search = ''"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end shrink-0">
                        <div class="w-full sm:w-52">
                            <AppSelect v-model="status" :options="statusOptions" :placeholder="t('All statuses')" />
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

            <div class="overflow-visible">
                <div class="overflow-x-auto overflow-y-visible">
                    <div class="min-w-[980px]">
                        <table class="min-w-full table-auto text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3 text-left">{{ t('Ad') }}</th>
                                    <th class="px-6 py-3 text-center">{{ t('Zone') }}</th>
                                    <th class="px-6 py-3 text-center">{{ t('Audience') }}</th>
                                    <th class="px-6 py-3 text-center">{{ t('Stats') }}</th>
                                    <th class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                    <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="ad in props.ads"
                                    :key="ad.id"
                                    class="border-t border-gray-100 align-top transition hover:bg-primary-50/40 dark:border-surface-800 dark:hover:bg-primary-900/5"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ ad.title }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ ad.type.replace('_', ' ') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ zones[ad.zone] || ad.zone }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center capitalize text-gray-700 dark:text-gray-300">{{ t(String(ad.show_to).replace('_', ' ')) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center gap-4 text-center text-xs text-gray-500 dark:text-gray-400">
                                            <span><b class="text-gray-900 dark:text-white">{{ formatNumber(Number(ad.impressions || 0)) }}</b><br>{{ t('Impressions') }}</span>
                                            <span><b class="text-gray-900 dark:text-white">{{ formatNumber(Number(ad.clicks || 0)) }}</b><br>{{ t('Clicks') }}</span>
                                            <span><b class="text-gray-900 dark:text-white">{{ ad.ctr }}%</b><br>{{ t('CTR') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center">
                                            <AppSwitch :model-value="ad.is_active" @update:model-value="toggleAd(ad.id)" />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Tooltip :content="t('Edit ad')" placement="top">
                                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-100 bg-white text-gray-500 shadow-sm transition-all hover:bg-gray-50 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-primary-400" @click="openEditModal(ad)">
                                                    <i class="ti ti-edit text-base"></i>
                                                </button>
                                            </Tooltip>
                                            <Tooltip :content="t('Delete ad')" placement="top">
                                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-100 bg-red-50 text-red-600 shadow-sm transition-all hover:bg-red-100 hover:text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400 dark:hover:bg-red-900/30" @click="deleteTarget = ad">
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="props.ads.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                        {{ search || status ? t('No advertisements match these filters.') : t('No advertisements found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <ActionConfirmModal
        :open="Boolean(deleteTarget)"
        :title="t('Delete advertisement?')"
        :message="t('Delete this advertisement permanently? This action cannot be undone.')"
        :confirm-label="t('Delete Ad')"
        :cancel-label="t('Cancel')"
        variant="danger"
        @cancel="deleteTarget = null"
        @confirm="deleteAd"
    />

    <AppModal
        :open="adModalOpen"
        max-width="max-w-6xl"
        :title="adModalTitle"
        :subtitle="t('Configure zone, audience, creative, schedule, and tracking behavior.')"
        has-form
        :confirm-text="adSubmitLabel"
        :confirm-loading="adForm.processing"
        @close="closeAdModal"
        @submit="submitAdForm"
    >
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="space-y-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Admin title') }}</span>
                        <input v-model="adForm.title" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Enter internal ad title')" />
                        <p v-if="adForm.errors.title" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.title }}</p>
                    </label>
                    <div class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Ad type') }}</span>
                        <AppSelect v-model="adForm.type" :options="adTypeOptions" :placeholder="t('Select ad type')" :error="adForm.errors.type" />
                    </div>
                </div>

                <div class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Zone') }}</span>
                    <AppSelect v-model="adForm.zone" :options="zoneOptions" :placeholder="t('Select ad zone')" :error="adForm.errors.zone" />
                </div>

                <div v-if="adForm.type === 'image_link'" class="space-y-4">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Banner image') }}</span>
                        <input type="file" accept="image/*" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-100 file:px-3 file:py-1.5 file:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:file:bg-primary-900/20 dark:file:text-primary-300" @change="handleImageUpload" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Upload JPG, PNG, WEBP, or GIF up to 5 MB.') }}</p>
                        <div v-if="imagePreviewUrl" class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800">
                            <img :src="mediaUrl(imagePreviewUrl)" :alt="t('Ad preview')" class="h-40 w-full object-cover" />
                        </div>
                        <p v-if="adForm.errors.image_file" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.image_file }}</p>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Link URL') }}</span>
                        <input v-model="adForm.link_url" type="url" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('https://example.com/landing-page')" />
                        <p v-if="adForm.errors.link_url" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.link_url }}</p>
                    </label>
                </div>

                <div v-else-if="adForm.type === 'custom_html'">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom HTML or script') }}</span>
                        <textarea v-model="adForm.custom_html" rows="10" class="w-full rounded-lg border border-gray-200 bg-gray-950 px-3 py-2 font-mono text-sm text-white dark:border-surface-700" />
                        <p v-if="adForm.errors.custom_html" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.custom_html }}</p>
                    </label>
                </div>

                <div v-else class="grid gap-4 md:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Publisher ID') }}</span>
                        <input v-model="adForm.adsense_client" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('ca-pub-1234567890123456')" />
                        <p v-if="adForm.errors.adsense_client" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.adsense_client }}</p>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slot ID') }}</span>
                        <input v-model="adForm.adsense_slot" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Enter AdSense slot ID')" />
                        <p v-if="adForm.errors.adsense_slot" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.adsense_slot }}</p>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Format') }}</span>
                        <input v-model="adForm.adsense_format" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('auto')" />
                        <p v-if="adForm.errors.adsense_format" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.adsense_format }}</p>
                    </label>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show to') }}</span>
                        <AppSelect v-model="adForm.show_to" :options="audienceOptions" :placeholder="t('Select audience')" :error="adForm.errors.show_to" />
                    </div>
                    <div class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Link target') }}</span>
                        <AppSelect v-model="adForm.link_target" :options="linkTargetOptions" :placeholder="t('Select link target')" :error="adForm.errors.link_target" />
                    </div>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort order') }}</span>
                        <input v-model="adForm.sort_order" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('0')" />
                        <p v-if="adForm.errors.sort_order" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.sort_order }}</p>
                    </label>
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-surface-700">
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Active') }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ adForm.is_active ? t('Ad is available for delivery') : t('Ad is paused') }}</div>
                        </div>
                        <AppSwitch v-model="adForm.is_active" />
                    </div>
                </section>
            </aside>
        </div>
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Start at') }}</span>
                    <input v-model="adForm.start_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                    <p v-if="adForm.errors.start_at" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.start_at }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('End at') }}</span>
                    <input v-model="adForm.end_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                    <p v-if="adForm.errors.end_at" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ adForm.errors.end_at }}</p>
                </label>
            </div>
        </section>
    </AppModal>

    <AppModal
        :open="settingsModalOpen"
        max-width="max-w-3xl"
        :title="t('Global Ad Settings')"
        :subtitle="t('Set publisher ID, auto ads, and subscriber visibility rules.')"
        has-form
        :confirm-text="t('Save Settings')"
        :confirm-loading="settingsForm.processing"
        confirm-loading-text="Saving..."
        @close="settingsModalOpen = false"
        @submit="saveSettings"
    >
        <div class="space-y-6">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('AdSense Publisher ID') }}</span>
                <input
                    v-model="settingsForm.adsense_publisher_id"
                    type="text"
                    :class="settingsForm.errors.adsense_publisher_id ? 'border-red-300 focus:border-red-400 dark:border-red-800' : 'border-gray-200 dark:border-surface-700'"
                    class="w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 dark:bg-surface-800 dark:text-white"
                    :placeholder="t('ca-pub-1234567890123456')"
                />
                <p v-if="settingsForm.errors.adsense_publisher_id" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ settingsForm.errors.adsense_publisher_id }}
                </p>
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable ads') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ settingsForm.ads_enabled ? t('Global ad delivery is enabled') : t('Global ad delivery is disabled') }}</div>
                    </div>
                    <AppSwitch v-model="settingsForm.ads_enabled" />
                </div>
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto ads') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ settingsForm.ads_auto_ads_enabled ? t('Automatic AdSense placement is enabled') : t('Automatic AdSense placement is disabled') }}</div>
                    </div>
                    <AppSwitch v-model="settingsForm.ads_auto_ads_enabled" />
                </div>
                <div v-if="isProAvailable" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800 md:col-span-2">
                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Disable ads for subscribed users') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ settingsForm.ads_disable_for_subscribed_users ? t('Subscribed users will not see ads') : t('Subscribed users can still see ads') }}</div>
                    </div>
                    <AppSwitch v-model="settingsForm.ads_disable_for_subscribed_users" />
                </div>
            </div>

            <div v-if="isProAvailable && plans.length">
                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Disable ads for plans') }}</span>
                <div class="flex flex-wrap gap-2">
                    <button v-for="plan in plans" :key="plan.id" type="button" :class="settingsForm.ads_disabled_plan_ids.includes(plan.id) ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'" class="rounded-full px-3 py-1 text-xs font-bold" @click="togglePlan(plan.id)">
                        {{ plan.name }}
                    </button>
                </div>
            </div>
        </div>
    </AppModal>
</template>
