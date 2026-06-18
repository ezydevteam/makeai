<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useHeaderHeight } from '@/Composables/useHeaderHeight'
import { useTranslate } from '@/Composables/useTranslate'

interface Country {
    code: string
    name: string
}

interface CountryPrice {
    id?: number | null
    country_code: string
    currency_code: string
    original_price_monthly: string | number | null
    original_price_yearly: string | number | null
    original_price_lifetime: string | number | null
    price_monthly: string | number | null
    price_yearly: string | number | null
    price_lifetime: string | number | null
    vat_percentage: string | number | null
    trial_monthly_enabled: boolean
    trial_yearly_enabled: boolean
    trial_lifetime_enabled: boolean
    trial_monthly_days: string | number | null
    trial_yearly_days: string | number | null
    trial_lifetime_days: string | number | null
    is_active: boolean
    _delete?: boolean
}

interface Plan {
    id: number
    name: string
    description: string | null
    bottom_info_text: string | null
    price_monthly: string | number
    price_yearly: string | number
    price_lifetime: string | number | null
    original_price_monthly: string | number | null
    original_price_yearly: string | number | null
    original_price_lifetime: string | number | null
    vat_percentage: string | number | null
    currency_code: string | null
    credits: string | number
    features: string[] | null
    trial_days: number | null
    trial_all_countries: boolean
    is_featured: boolean
    is_active: boolean
    country_prices: CountryPrice[]
}

interface PricingSettings {
    pricing_show_monthly: boolean
    pricing_show_yearly: boolean
    pricing_show_lifetime: boolean
    pricing_currency_code: string
    pricing_trial_button_text: string
    pricing_featured_label_text: string
    pricing_checkout_button_text: string
}

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{
    plans: Plan[]
    countries: Country[]
    currencies: string[]
    settings: PricingSettings
}>()

const { t } = useTranslate()
const { topOffset } = useHeaderHeight()
const adminSecondaryButtonClass = 'inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'
const sidebarMaxHeight = computed(() => `calc(100vh - ${topOffset.value} - 24px)`)
const countryPricingModalOpen = ref(false)
const pricingSettingsModalOpen = ref(false)
const collapsedCountryPrices = ref<Record<string, boolean>>({})
const pendingCountryRemovalIndex = ref<number | null>(null)
const pendingFeatureRemovalIndex = ref<number | null>(null)
const selectedPlanId = ref<number | null>(props.plans[0]?.id ?? null)
const selectedPlan = computed(() => props.plans.find((plan) => plan.id === selectedPlanId.value) ?? props.plans[0])
const currencyOptions = computed(() => props.currencies.map((currency) => ({
    value: currency,
    label: currency,
})))

const pricingSettingsForm = useForm({
    pricing_show_monthly: props.settings.pricing_show_monthly,
    pricing_show_yearly: props.settings.pricing_show_yearly,
    pricing_show_lifetime: props.settings.pricing_show_lifetime,
    pricing_currency_code: props.settings.pricing_currency_code,
    pricing_trial_button_text: props.settings.pricing_trial_button_text,
    pricing_featured_label_text: props.settings.pricing_featured_label_text,
    pricing_checkout_button_text: props.settings.pricing_checkout_button_text,
})
const countryOptions = computed(() => props.countries.map((country) => ({
    value: country.code,
    label: country.name,
})))

const form = useForm({
    name: '',
    description: '',
    bottom_info_text: '',
    price_monthly: '0',
    price_yearly: '0',
    price_lifetime: '',
    original_price_monthly: '',
    original_price_yearly: '',
    original_price_lifetime: '',
    vat_percentage: '0',
    currency_code: 'USD',
    credits: '0',
    features: [] as string[],
    trial_days: 0,
    trial_all_countries: false,
    is_featured: false,
    is_active: true,
    country_prices: [] as CountryPrice[],
})

const visibleCountryPrices = computed(() => form.country_prices.filter((row) => !row._delete))

const normalizeFeatures = (value: unknown): string[] => {
    const parseJson = (text: string): string[] | null => {
        try {
            const parsed = JSON.parse(text)

            if (Array.isArray(parsed)) {
                return parsed.map((item) => String(item).trim()).filter(Boolean)
            }
        } catch {
            return null
        }

        return null
    }

    if (typeof value === 'string') {
        const trimmed = value.trim()
        const parsed = parseJson(trimmed)

        return parsed ?? trimmed.split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
    }

    if (!Array.isArray(value)) {
        return []
    }

    const normalized = value.map((item) => String(item).trim()).filter(Boolean)
    const joined = normalized.join('')

    if (joined.startsWith('[') && joined.endsWith(']')) {
        return parseJson(joined) ?? normalized
    }

    if (normalized.length === 1) {
        return parseJson(normalized[0]) ?? normalized
    }

    return normalized
}

const resetForm = () => {
    if (!selectedPlan.value) return

    form.clearErrors()
    collapsedCountryPrices.value = {}
    form.defaults({
        name: selectedPlan.value.name,
        description: selectedPlan.value.description ?? '',
        bottom_info_text: selectedPlan.value.bottom_info_text ?? '',
        price_monthly: String(selectedPlan.value.price_monthly ?? 0),
        price_yearly: String(selectedPlan.value.price_yearly ?? 0),
        price_lifetime: selectedPlan.value.price_lifetime === null ? '' : String(selectedPlan.value.price_lifetime),
        original_price_monthly: selectedPlan.value.original_price_monthly === null ? '' : String(selectedPlan.value.original_price_monthly),
        original_price_yearly: selectedPlan.value.original_price_yearly === null ? '' : String(selectedPlan.value.original_price_yearly),
        original_price_lifetime: selectedPlan.value.original_price_lifetime === null ? '' : String(selectedPlan.value.original_price_lifetime),
        vat_percentage: String(selectedPlan.value.vat_percentage ?? 0),
        currency_code: selectedPlan.value.currency_code ?? props.settings.pricing_currency_code ?? 'USD',
        credits: String(selectedPlan.value.credits ?? 0),
        features: normalizeFeatures(selectedPlan.value.features),
        trial_days: selectedPlan.value.trial_days ?? 0,
        trial_all_countries: selectedPlan.value.trial_all_countries,
        is_featured: selectedPlan.value.is_featured,
        is_active: selectedPlan.value.is_active,
        country_prices: selectedPlan.value.country_prices.map((row) => ({
            id: row.id,
            country_code: row.country_code,
            currency_code: row.currency_code,
            original_price_monthly: row.original_price_monthly,
            original_price_yearly: row.original_price_yearly,
            original_price_lifetime: row.original_price_lifetime,
            price_monthly: row.price_monthly,
            price_yearly: row.price_yearly,
            price_lifetime: row.price_lifetime,
            vat_percentage: row.vat_percentage,
            trial_monthly_enabled: row.trial_monthly_enabled,
            trial_yearly_enabled: row.trial_yearly_enabled,
            trial_lifetime_enabled: row.trial_lifetime_enabled,
            trial_monthly_days: row.trial_monthly_days,
            trial_yearly_days: row.trial_yearly_days,
            trial_lifetime_days: row.trial_lifetime_days,
            is_active: row.is_active,
            _delete: false,
        })),
    })
    form.reset()
}

watch(selectedPlanId, resetForm, { immediate: true })
watch(countryPricingModalOpen, (isOpen) => {
    if (typeof document === 'undefined') return
    document.body.classList.toggle('overflow-hidden', isOpen || pricingSettingsModalOpen.value)
})
watch(pricingSettingsModalOpen, (isOpen) => {
    if (typeof document === 'undefined') return
    document.body.classList.toggle('overflow-hidden', isOpen || countryPricingModalOpen.value)
})

const toggleFeatured = () => {
    if (!form.is_featured) {
        props.plans.forEach((plan) => {
            if (plan.id !== selectedPlanId.value) {
                plan.is_featured = false
            }
        })
    }
    form.is_featured = !form.is_featured
}

const addFeature = () => form.features.push('')

const removeFeature = (index: number) => form.features.splice(index, 1)

const addCountryPrice = () => {
    const used = new Set(visibleCountryPrices.value.map((row) => row.country_code))
    const country = props.countries.find((item) => !used.has(item.code))

    if (!country) return

    visibleCountryPrices.value.forEach((row, index) => {
        collapsedCountryPrices.value[getCountryPriceKey(row, index)] = true
    })

    form.country_prices.push({
        id: null,
        country_code: country.code,
        currency_code: form.currency_code || props.settings.pricing_currency_code || 'USD',
        original_price_monthly: null,
        original_price_yearly: null,
        original_price_lifetime: null,
        price_monthly: null,
        price_yearly: null,
        price_lifetime: null,
        vat_percentage: null,
        trial_monthly_enabled: false,
        trial_yearly_enabled: false,
        trial_lifetime_enabled: false,
        trial_monthly_days: null,
        trial_yearly_days: null,
        trial_lifetime_days: null,
        is_active: true,
        _delete: false,
    })
}

const removeCountryPrice = (index: number) => {
    const row = visibleCountryPrices.value[index]
    const realIndex = form.country_prices.indexOf(row)

    if (realIndex === -1) return

    if (form.country_prices[realIndex].id) {
        form.country_prices[realIndex]._delete = true
        return
    }

    form.country_prices.splice(realIndex, 1)
}

const confirmRemoveFeature = () => {
    if (pendingFeatureRemovalIndex.value === null) return
    removeFeature(pendingFeatureRemovalIndex.value)
    pendingFeatureRemovalIndex.value = null
}

const confirmRemoveCountryPrice = () => {
    if (pendingCountryRemovalIndex.value === null) return
    removeCountryPrice(pendingCountryRemovalIndex.value)
    pendingCountryRemovalIndex.value = null
}

const normalizePriceValue = (value: string | number | null | undefined, fallback = '0') => {
    if (value === null || value === undefined || value === '') {
        return fallback
    }

    return String(value)
}

const normalizeCountryPriceValue = (value: string | number | null | undefined) => {
    if (value === null || value === undefined || value === '') {
        return null
    }

    return value
}

const countryName = (code: string) => props.countries.find((country) => country.code === code)?.name ?? code

const getCountryPriceKey = (row: CountryPrice, index: number) => row.id ? `saved-${row.id}` : `draft-${index}`

const isCountryPriceCollapsed = (row: CountryPrice, index: number) => collapsedCountryPrices.value[getCountryPriceKey(row, index)] === true

const toggleCountryPriceCollapsed = (row: CountryPrice, index: number) => {
    const key = getCountryPriceKey(row, index)
    collapsedCountryPrices.value[key] = !isCountryPriceCollapsed(row, index)
}

const closeCountryPricingOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        countryPricingModalOpen.value = false
        pricingSettingsModalOpen.value = false
    }
}

onMounted(() => window.addEventListener('keydown', closeCountryPricingOnEscape))
onUnmounted(() => {
    window.removeEventListener('keydown', closeCountryPricingOnEscape)
    document.body.classList.remove('overflow-hidden')
})

const formatMoney = (value: string | number | null | undefined, currencyCode?: string | null) => {
    const amount = Number(value ?? 0)

    if (!Number.isFinite(amount) || amount <= 0) {
        return t('Not set')
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currencyCode || props.settings.pricing_currency_code || 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount)
}

const submitPricingSettings = () => {
    pricingSettingsForm.post(route('admin.plans.settings'), {
        preserveScroll: true,
        onSuccess: () => {
            pricingSettingsModalOpen.value = false
        },
    })
}

const submit = () => {
    if (!selectedPlan.value) return

    form.transform((data) => ({
        ...data,
        features: normalizeFeatures(data.features),
        price_monthly: normalizePriceValue(data.price_monthly),
        price_yearly: normalizePriceValue(data.price_yearly),
        price_lifetime: data.price_lifetime === '' ? null : data.price_lifetime,
        original_price_monthly: data.original_price_monthly === '' ? null : data.original_price_monthly,
        original_price_yearly: data.original_price_yearly === '' ? null : data.original_price_yearly,
        original_price_lifetime: data.original_price_lifetime === '' ? null : data.original_price_lifetime,
        country_prices: data.country_prices.map((row) => ({
            ...row,
            original_price_monthly: normalizeCountryPriceValue(row.original_price_monthly),
            original_price_yearly: normalizeCountryPriceValue(row.original_price_yearly),
            original_price_lifetime: normalizeCountryPriceValue(row.original_price_lifetime),
            price_monthly: normalizePriceValue(row.price_monthly),
            price_yearly: normalizePriceValue(row.price_yearly),
            price_lifetime: normalizeCountryPriceValue(row.price_lifetime),
            vat_percentage: row.vat_percentage === '' ? null : row.vat_percentage,
            trial_monthly_days: row.trial_monthly_days === '' ? null : row.trial_monthly_days,
            trial_yearly_days: row.trial_yearly_days === '' ? null : row.trial_yearly_days,
            trial_lifetime_days: row.trial_lifetime_days === '' ? null : row.trial_lifetime_days,
        })),
    })).put(route('admin.plans.update', selectedPlan.value.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Plans')" />

    <AdminLayout>
        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('Plans') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Manage plan content, default pricing, and country-specific pricing overrides.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="pricingSettingsModalOpen = true"
                        :class="adminSecondaryButtonClass"
                    >
                        <i class="ti ti-settings text-base"></i>
                        {{ t('Pricing Settings') }}
                    </button>
                </div>

                <div v-if="plans.length === 0" class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                    <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No plans found.') }}
                    </div>
                </div>

                <div v-else class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <section
                        class="self-start border border-gray-100 bg-white shadow-sm sm:rounded-lg lg:sticky lg:overflow-hidden dark:border-gray-800 dark:bg-gray-800"
                        :style="{ top: topOffset, maxHeight: sidebarMaxHeight }"
                    >
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Plans') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Select a plan to edit its pricing and details.') }}</p>
                        </div>

                        <div class="divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
                            <button
                                v-for="plan in plans"
                                :key="plan.id"
                                type="button"
                                class="flex w-full items-start justify-between gap-3 px-5 py-4 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                :class="selectedPlanId === plan.id ? 'bg-primary-50/70 dark:bg-primary-900/20' : ''"
                                @click="selectedPlanId = plan.id"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <i class="ti ti-package text-base text-gray-400 dark:text-gray-500"></i>
                                        <p class="truncate font-semibold text-gray-900 dark:text-white">{{ plan.name }}</p>
                                        <span
                                            v-if="plan.is_featured"
                                            class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                                        >
                                            {{ settings.pricing_featured_label_text }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatMoney(plan.price_monthly, plan.currency_code || settings.pricing_currency_code) }}
                                        · {{ t(':count country prices', { count: plan.country_prices.length }) }}
                                    </p>
                                </div>

                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                                    :class="plan.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                >
                                    {{ plan.is_active ? t('Active') : t('Hidden') }}
                                </span>
                            </button>
                        </div>
                    </section>

                    <form class="space-y-6" @submit.prevent="submit">
                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedPlan?.name }}</h2>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Update plan information, visibility, and core pricing.') }}</p>
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <i class="ti ti-device-floppy text-base"></i>
                                        {{ form.processing ? t('Saving...') : t('Save Plan') }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Plan name') }}</span>
                                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>

                                    <label class="block md:col-span-2">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Description') }}</span>
                                        <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                                    </label>

                                    <label class="block md:col-span-2">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Plan bottom info text') }}</span>
                                        <input v-model="form.bottom_info_text" type="text" :placeholder="t('e.g. Cancel anytime')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Monthly credits') }}</span>
                                        <input v-model="form.credits" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Default trial days') }}</span>
                                        <input v-model="form.trial_days" type="number" min="0" step="1" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Currency') }}</span>
                                        <AppSelect
                                            v-model="form.currency_code"
                                            :options="currencyOptions"
                                            :placeholder="t('Select currency')"
                                            live-search
                                        />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('VAT %') }}</span>
                                        <input v-model="form.vat_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>
                                </div>

                                <div class="space-y-4">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Visibility') }}</h3>
                                        <div class="mt-4 space-y-3">
                                            <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                                                <span>{{ t('Show plan') }}</span>
                                                <button type="button" role="switch" :aria-checked="form.is_active" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="form.is_active = !form.is_active">
                                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                                </button>
                                            </label>
                                            <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                                                <span>{{ t('Featured plan') }}</span>
                                                <button type="button" role="switch" :aria-checked="form.is_featured" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.is_featured ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="toggleFeatured">
                                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.is_featured ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                                </button>
                                            </label>
                                            <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                                                <span>{{ t('Trial all countries') }}</span>
                                                <button type="button" role="switch" :aria-checked="form.trial_all_countries" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.trial_all_countries ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="form.trial_all_countries = !form.trial_all_countries">
                                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.trial_all_countries ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                                </button>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Quick Summary') }}</h3>
                                        <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ t('Monthly') }}</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(form.price_monthly, form.currency_code) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ t('Yearly') }}</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(form.price_yearly, form.currency_code) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ t('Lifetime') }}</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(form.price_lifetime, form.currency_code) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ t('Country overrides') }}</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ visibleCountryPrices.length }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Default Pricing') }}</h2>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('These values are used unless a country-specific row overrides them.') }}</p>
                                    </div>

                                    <button
                                        type="button"
                                        :class="adminSecondaryButtonClass"
                                        @click="countryPricingModalOpen = true"
                                    >
                                        <i class="ti ti-map-pin-dollar text-base"></i>
                                        {{ t('Manage Country Pricing') }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-4 p-6 lg:grid-cols-3">
                                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Monthly') }}</h3>
                                    <div class="mt-4 space-y-3">
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Original') }}</span>
                                            <input v-model="form.original_price_monthly" type="number" min="0" step="0.01" :placeholder="t('Original price')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Discounted') }}</span>
                                            <input v-model="form.price_monthly" type="number" min="0" step="0.01" :placeholder="t('Discounted price')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        </label>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Yearly') }}</h3>
                                    <div class="mt-4 space-y-3">
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Original') }}</span>
                                            <input v-model="form.original_price_yearly" type="number" min="0" step="0.01" :placeholder="t('Original price')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Discounted') }}</span>
                                            <input v-model="form.price_yearly" type="number" min="0" step="0.01" :placeholder="t('Discounted price')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        </label>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Lifetime') }}</h3>
                                    <div class="mt-4 space-y-3">
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Original') }}</span>
                                            <input v-model="form.original_price_lifetime" type="number" min="0" step="0.01" :placeholder="t('Original price')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Discounted') }}</span>
                                            <input v-model="form.price_lifetime" type="number" min="0" step="0.01" :placeholder="t('Discounted price')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Plan Features') }}</h2>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('These lines appear in the pricing card and checkout experience.') }}</p>
                                    </div>

                                    <button type="button" :class="adminSecondaryButtonClass" @click="addFeature">
                                        <i class="ti ti-plus text-base"></i>
                                        {{ t('Add Feature') }}
                                    </button>
                                </div>
                            </div>

                            <div class="p-6">
                                <div v-if="form.features.length === 0" class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    {{ t('No features added.') }}
                                </div>

                                    <div v-else class="space-y-3">
                                        <div v-for="(feature, index) in form.features" :key="index" class="flex gap-3">
                                            <input v-model="form.features[index]" type="text" :placeholder="t('Feature name')" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <button type="button" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20" @click="pendingFeatureRemovalIndex = index">
                                            {{ t('Remove') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                    </form>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 scale-95 opacity-0"
                enter-to-class="translate-y-0 scale-100 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 scale-100 opacity-100"
                leave-to-class="translate-y-2 scale-95 opacity-0"
            >
                <div v-if="countryPricingModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="countryPricingModalOpen = false">
                    <div class="flex max-h-[90vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900">
                        <div class="rounded-t-2xl border-b border-gray-100 px-6 py-3 dark:border-gray-800">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Country Pricing') }}</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use country rows only where default pricing is not enough.') }}</p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="button" :class="adminSecondaryButtonClass" @click="addCountryPrice">
                                        <i class="ti ti-plus text-base"></i>
                                        {{ t('Add Country') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                                        :aria-label="t('Close modal')"
                                        @click="countryPricingModalOpen = false"
                                    >
                                        <i class="ti ti-x text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto bg-gray-50/70 p-6 dark:bg-gray-950/30">
                            <div v-if="visibleCountryPrices.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                {{ t('No country pricing yet.') }}
                            </div>

                            <div v-else class="space-y-5">
                                <section
                                    v-for="(row, index) in visibleCountryPrices"
                                    :key="row.id ?? index"
                                    class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                            <div class="space-y-3">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                                        <i class="ti ti-world text-base"></i>
                                                    </span>
                                                    <div>
                                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                            {{ countryName(row.country_code) }}
                                                        </h3>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Country-specific pricing override') }}</p>
                                                    </div>
                                                </div>

                                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[220px_160px_140px_180px]">
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Country') }}</span>
                                                        <AppSelect
                                                            v-model="row.country_code"
                                                            :options="countryOptions"
                                                            :placeholder="t('Select country')"
                                                            live-search
                                                        />
                                                    </label>

                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Currency') }}</span>
                                                        <AppSelect
                                                            v-model="row.currency_code"
                                                            :options="currencyOptions"
                                                            :placeholder="t('Currency')"
                                                            live-search
                                                        />
                                                    </label>

                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('VAT %') }}</span>
                                                        <input v-model="row.vat_percentage" type="number" min="0" max="100" step="0.01" :placeholder="t('Default')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                                    </label>

                                                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200 xl:self-end">
                                                        <span class="pe-2">{{ t('Active override') }}</span>
                                                        <button type="button" role="switch" :aria-checked="row.is_active" class="relative inline-flex h-6 w-11 rounded-full transition" :class="row.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="row.is_active = !row.is_active">
                                                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="row.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                                        </button>
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 xl:self-start">
                                                <Tooltip :content="isCountryPriceCollapsed(row, index) ? t('Expand country pricing') : t('Collapse country pricing')" placement="left">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                                        @click="toggleCountryPriceCollapsed(row, index)"
                                                    >
                                                        <i
                                                            class="ti text-base transition-transform"
                                                            :class="isCountryPriceCollapsed(row, index) ? 'ti-chevron-down' : 'ti-chevron-up'"
                                                        ></i>
                                                    </button>
                                                </Tooltip>

                                                <Tooltip :content="t('Remove country pricing')" placement="left">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/40 dark:bg-gray-800 dark:hover:bg-red-900/20"
                                                        @click="pendingCountryRemovalIndex = index"
                                                    >
                                                        <i class="ti ti-trash text-base"></i>
                                                    </button>
                                                </Tooltip>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-show="!isCountryPriceCollapsed(row, index)" class="grid gap-4 p-5 xl:grid-cols-3">
                                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                                            <div class="mb-4">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Monthly pricing') }}</h4>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Use this when monthly pricing differs for this country.') }}</p>
                                            </div>

                                            <div class="space-y-3">
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Original price') }}</span>
                                                        <input v-model="row.original_price_monthly" type="number" min="0" step="0.01" :placeholder="t('Original')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Discounted price') }}</span>
                                                        <input v-model="row.price_monthly" type="number" min="0" step="0.01" :placeholder="t('Discount')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                    </label>
                                                </div>
                                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    <input v-model="row.trial_monthly_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    {{ t('Enable monthly trial') }}
                                                </label>
                                                <label v-if="row.trial_monthly_enabled" class="block">
                                                    <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Trial days') }}</span>
                                                    <input v-model="row.trial_monthly_days" type="number" min="1" step="1" :placeholder="t('30 days')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                </label>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                                            <div class="mb-4">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Yearly pricing') }}</h4>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Set a yearly offer only if this country needs its own pricing.') }}</p>
                                            </div>

                                            <div class="space-y-3">
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Original price') }}</span>
                                                        <input v-model="row.original_price_yearly" type="number" min="0" step="0.01" :placeholder="t('Original')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Discounted price') }}</span>
                                                        <input v-model="row.price_yearly" type="number" min="0" step="0.01" :placeholder="t('Discount')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                    </label>
                                                </div>
                                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    <input v-model="row.trial_yearly_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    {{ t('Enable yearly trial') }}
                                                </label>
                                                <label v-if="row.trial_yearly_enabled" class="block">
                                                    <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Trial days') }}</span>
                                                    <input v-model="row.trial_yearly_days" type="number" min="1" step="1" :placeholder="t('360 days')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                </label>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                                            <div class="mb-4">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Lifetime pricing') }}</h4>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Use this block only when lifetime pricing is available in this country.') }}</p>
                                            </div>

                                            <div class="space-y-3">
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Original price') }}</span>
                                                        <input v-model="row.original_price_lifetime" type="number" min="0" step="0.01" :placeholder="t('Original')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Discounted price') }}</span>
                                                        <input v-model="row.price_lifetime" type="number" min="0" step="0.01" :placeholder="t('Discount')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                    </label>
                                                </div>
                                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    <input v-model="row.trial_lifetime_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    {{ t('Enable lifetime trial') }}
                                                </label>
                                                <label v-if="row.trial_lifetime_enabled" class="block">
                                                    <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Trial days') }}</span>
                                                    <input v-model="row.trial_lifetime_days" type="number" min="1" step="1" :placeholder="t('30 days')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transform transition duration-200 ease-out"
                enter-from-class="translate-y-2 scale-95 opacity-0"
                enter-to-class="translate-y-0 scale-100 opacity-100"
                leave-active-class="transform transition duration-150 ease-in"
                leave-from-class="translate-y-0 scale-100 opacity-100"
                leave-to-class="translate-y-2 scale-95 opacity-0"
            >
                <div v-if="pricingSettingsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="pricingSettingsModalOpen = false">
                    <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-800 dark:bg-surface-900">
                        <div class="rounded-t-2xl border-b border-gray-100 px-6 py-3 dark:border-surface-800">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Pricing Settings') }}</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control global pricing display, billing cycles, and frontend pricing copy.') }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                                    :aria-label="t('Close pricing settings')"
                                    @click="pricingSettingsModalOpen = false"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="max-h-[calc(90vh-140px)] overflow-y-auto p-6">
                            <form class="space-y-6" @submit.prevent="submitPricingSettings">
                                <div class="grid gap-4 md:grid-cols-3">
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {{ t('Show Monthly') }}
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="pricingSettingsForm.pricing_show_monthly"
                                            class="relative inline-flex h-6 w-11 rounded-full transition"
                                            :class="pricingSettingsForm.pricing_show_monthly ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                            @click="pricingSettingsForm.pricing_show_monthly = !pricingSettingsForm.pricing_show_monthly"
                                        >
                                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="pricingSettingsForm.pricing_show_monthly ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                        </button>
                                    </label>
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {{ t('Show Yearly') }}
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="pricingSettingsForm.pricing_show_yearly"
                                            class="relative inline-flex h-6 w-11 rounded-full transition"
                                            :class="pricingSettingsForm.pricing_show_yearly ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                            @click="pricingSettingsForm.pricing_show_yearly = !pricingSettingsForm.pricing_show_yearly"
                                        >
                                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="pricingSettingsForm.pricing_show_yearly ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                        </button>
                                    </label>
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {{ t('Show Lifetime') }}
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="pricingSettingsForm.pricing_show_lifetime"
                                            class="relative inline-flex h-6 w-11 rounded-full transition"
                                            :class="pricingSettingsForm.pricing_show_lifetime ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                            @click="pricingSettingsForm.pricing_show_lifetime = !pricingSettingsForm.pricing_show_lifetime"
                                        >
                                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="pricingSettingsForm.pricing_show_lifetime ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                        </button>
                                    </label>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Default Currency') }}</span>
                                        <AppSelect
                                            v-model="pricingSettingsForm.pricing_currency_code"
                                            :options="currencyOptions"
                                            :placeholder="t('Select currency')"
                                            live-search
                                        />
                                        <p v-if="pricingSettingsForm.errors.pricing_currency_code" class="mt-1 text-xs text-danger-600">{{ pricingSettingsForm.errors.pricing_currency_code }}</p>
                                    </label>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Trial Button Text') }}</span>
                                        <input v-model="pricingSettingsForm.pricing_trial_button_text" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <p v-if="pricingSettingsForm.errors.pricing_trial_button_text" class="mt-1 text-xs text-danger-600">{{ pricingSettingsForm.errors.pricing_trial_button_text }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Featured Label Text') }}</span>
                                        <input v-model="pricingSettingsForm.pricing_featured_label_text" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <p v-if="pricingSettingsForm.errors.pricing_featured_label_text" class="mt-1 text-xs text-danger-600">{{ pricingSettingsForm.errors.pricing_featured_label_text }}</p>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Checkout Button Text') }}</span>
                                        <input v-model="pricingSettingsForm.pricing_checkout_button_text" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <p v-if="pricingSettingsForm.errors.pricing_checkout_button_text" class="mt-1 text-xs text-danger-600">{{ pricingSettingsForm.errors.pricing_checkout_button_text }}</p>
                                    </label>
                                </div>

                                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-3 dark:border-surface-800 dark:bg-gray-900/40">
                                    <button type="button" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700" @click="pricingSettingsModalOpen = false">
                                        {{ t('Cancel') }}
                                    </button>
                                    <button type="submit" :disabled="pricingSettingsForm.processing" class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
                                        {{ pricingSettingsForm.processing ? t('Saving...') : t('Save Settings') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ActionConfirmModal
            :open="pendingCountryRemovalIndex !== null"
            :title="t('Remove country pricing?')"
            :message="t('This country pricing override will be removed from the plan.')"
            :confirm-label="t('Remove')"
            :cancel-label="t('Cancel')"
            @confirm="confirmRemoveCountryPrice"
            @cancel="pendingCountryRemovalIndex = null"
        />

        <ActionConfirmModal
            :open="pendingFeatureRemovalIndex !== null"
            :title="t('Remove feature?')"
            :message="t('This feature line will be removed from the plan.')"
            :confirm-label="t('Remove')"
            :cancel-label="t('Cancel')"
            @confirm="confirmRemoveFeature"
            @cancel="pendingFeatureRemovalIndex = null"
        />
    </AdminLayout>
</template>
