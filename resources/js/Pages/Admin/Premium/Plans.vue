<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
    credits: string | number
    features: string[] | null
    trial_days: number | null
    trial_all_countries: boolean
    stripe_price_monthly_id: string | null
    stripe_price_yearly_id: string | null
    paypal_plan_monthly_id: string | null
    paypal_plan_yearly_id: string | null
    is_featured: boolean
    is_active: boolean
    country_prices: CountryPrice[]
}

interface PricingSettings {
    pricing_show_monthly: boolean
    pricing_show_yearly: boolean
    pricing_show_lifetime: boolean
    pricing_currency_code: string
    pricing_auto_localize: boolean
    pricing_trial_button_text: string
    pricing_featured_label_text: string
    pricing_checkout_button_text: string
    credit_price_per_unit?: number | string | null
    registration_default_plan: string
}

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{
    plans: Plan[]
    countries: Country[]
    currencies: string[]
    settings: PricingSettings
}>()

const { t } = useTranslate()
const adminSecondaryButtonClass = 'inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'
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
    pricing_auto_localize: props.settings.pricing_auto_localize ?? true,
    pricing_trial_button_text: props.settings.pricing_trial_button_text,
    pricing_featured_label_text: props.settings.pricing_featured_label_text,
    pricing_checkout_button_text: props.settings.pricing_checkout_button_text,
    registration_default_plan: props.settings.registration_default_plan ?? 'none',
})
const countryOptions = computed(() => props.countries.map((country) => ({
    value: country.code,
    label: country.name,
})))
const registrationPlanOptions = computed(() => [
    { value: 'none', label: t('No plan') },
    { value: 'custom', label: t('Custom credit limits') },
    ...props.plans.map((plan) => ({ value: String(plan.id), label: plan.name })),
])

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
    credits: '0',
    features: [] as string[],
    trial_days: 0,
    trial_all_countries: false,
    stripe_price_monthly_id: '',
    stripe_price_yearly_id: '',
    paypal_plan_monthly_id: '',
    paypal_plan_yearly_id: '',
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
        credits: String(selectedPlan.value.credits ?? 0),
        features: normalizeFeatures(selectedPlan.value.features),
        trial_days: selectedPlan.value.trial_days ?? 0,
        trial_all_countries: selectedPlan.value.trial_all_countries,
        stripe_price_monthly_id: selectedPlan.value.stripe_price_monthly_id ?? '',
        stripe_price_yearly_id: selectedPlan.value.stripe_price_yearly_id ?? '',
        paypal_plan_monthly_id: selectedPlan.value.paypal_plan_monthly_id ?? '',
        paypal_plan_yearly_id: selectedPlan.value.paypal_plan_yearly_id ?? '',
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
        currency_code: props.settings.pricing_currency_code || 'USD',
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



const formatMoney = (value: string | number | null | undefined, currencyCode?: string | null) => {
    const amount = Number(value ?? 0)

    if (!Number.isFinite(amount) || amount <= 0) {
        return t('Free')
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currencyCode || props.settings.pricing_currency_code || 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount)
}

const creditPriceCurrency = computed(() => props.settings.pricing_currency_code || 'USD')

const creditAnchorPrice = computed(() => {
    const value = Number(props.settings.credit_price_per_unit ?? 0.01)

    return Number.isFinite(value) && value > 0 ? value : 0.01
})

const impliedPricePerCredit = computed(() => {
    const credits = Number(form.credits)
    const priceMonthly = Number(form.price_monthly)

    if (!Number.isFinite(credits) || !Number.isFinite(priceMonthly) || credits <= 0 || priceMonthly <= 0) {
        return null
    }

    return priceMonthly / credits
})

const isBelowRetailCreditPrice = computed(() => impliedPricePerCredit.value !== null && impliedPricePerCredit.value < creditAnchorPrice.value)

const isDeepDiscountCreditPrice = computed(() => impliedPricePerCredit.value !== null && impliedPricePerCredit.value < creditAnchorPrice.value * 0.2)

const creditDiscountPercent = computed(() => {
    if (impliedPricePerCredit.value === null || creditAnchorPrice.value <= 0) {
        return 0
    }

    return Math.round((1 - impliedPricePerCredit.value / creditAnchorPrice.value) * 100)
})

const formatCreditPrice = (value: number) => `${value.toFixed(4)} ${creditPriceCurrency.value}`

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
        <div class="mx-auto w-full max-w-5xl space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
                <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex-1">
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
                        class="shrink-0 btn-primary-admin inline-flex items-center justify-center gap-2"
                    >
                        <i class="ti ti-settings text-base"></i>
                        {{ t('Settings') }}
                    </button>
                </div>

                <div v-if="plans.length === 0" class="rounded-2xl border border-gray-250 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No plans found.') }}
                    </div>
                </div>

                <div v-else class="space-y-6">
                    <!-- Top Grid Plan Selector -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <button
                            v-for="plan in plans"
                            :key="plan.id"
                            type="button"
                            class="relative flex flex-col justify-between overflow-hidden rounded-2xl border p-5 text-left transition-all hover:shadow-md"
                            :class="selectedPlanId === plan.id
                                ? 'bg-primary-50 border-primary-300 ring-2 ring-primary-500/10 dark:border-primary-900 dark:bg-primary-900/20'
                                : 'bg-white border-gray-200 hover:border-gray-300 dark:border-surface-800 dark:hover:border-surface-700 dark:bg-surface-900'"
                            @click="selectedPlanId = plan.id"
                        >
                            <div class="w-full">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <i class="ti ti-package text-lg" :class="selectedPlanId === plan.id ? 'text-primary-500' : 'text-gray-400 dark:text-gray-500'"></i>
                                        <span class="font-bold text-gray-900 dark:text-white">{{ plan.name }}</span>
                                    </div>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                                        :class="plan.is_active ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ plan.is_active ? t('Active') : t('Hidden') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2 min-h-[32px]">{{ plan.description || t('No description provided.') }}</p>
                                <div v-if="plan.is_featured" class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                    {{ settings.pricing_featured_label_text }}
                                </div>
                            </div>

                            <div class="mt-4 w-full border-t border-gray-100 pt-3 dark:border-surface-800">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ formatMoney(plan.price_monthly, settings.pricing_currency_code) }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">/{{ t('mo') }}</span>
                                    </div>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ t(':count country overrides', { count: plan.country_prices.length }) }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>

                    <form class="space-y-6" @submit.prevent="submit">
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="border-b border-gray-200 px-5 py-4 dark:border-surface-800">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedPlan?.name }}</h2>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Update plan information, visibility, and core pricing.') }}</p>
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="btn-primary-admin inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <i class="ti ti-device-floppy text-base"></i>
                                        {{ form.processing ? t('Saving...') : t('Save Plan') }}
                                    </button>
                                </div>
                            </div>

                            <div class="p-5">
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
                                        <div
                                            v-if="impliedPricePerCredit !== null"
                                            class="mt-1.5 space-y-1"
                                            :class="isDeepDiscountCreditPrice ? 'rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 dark:border-amber-900/40 dark:bg-amber-950/30' : ''"
                                        >
                                            <p
                                                class="text-[11px]"
                                                :class="isDeepDiscountCreditPrice ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400'"
                                            >
                                                {{ t('≈ :implied per credit (default credit cost : :anchor)', { implied: formatCreditPrice(impliedPricePerCredit), anchor: formatCreditPrice(creditAnchorPrice) }) }}
                                            </p>
                                            <p
                                                v-if="isBelowRetailCreditPrice"
                                                class="text-[11px]"
                                                :class="isDeepDiscountCreditPrice ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400'"
                                            >
                                                {{ t(':percent% below default cost', { percent: creditDiscountPercent }) }}
                                            </p>
                                            <p v-if="isDeepDiscountCreditPrice" class="flex items-center gap-1 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                                                <i class="ti ti-alert-triangle text-sm"></i>
                                                {{ t('Deep discount — verify this still covers your AI provider cost.') }}
                                            </p>
                                        </div>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Default trial days') }}</span>
                                        <input v-model="form.trial_days" type="number" min="0" step="1" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('VAT %') }}</span>
                                        <input v-model="form.vat_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Stripe monthly price ID') }}</span>
                                        <input v-model="form.stripe_price_monthly_id" type="text" placeholder="price_..." class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <span v-if="form.errors.stripe_price_monthly_id" class="mt-1 block text-xs text-red-500">{{ form.errors.stripe_price_monthly_id }}</span>
                                        <span v-else class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Optional. Enables automatic recurring billing via Stripe for the monthly cycle.') }}</span>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Stripe yearly price ID') }}</span>
                                        <input v-model="form.stripe_price_yearly_id" type="text" placeholder="price_..." class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <span v-if="form.errors.stripe_price_yearly_id" class="mt-1 block text-xs text-red-500">{{ form.errors.stripe_price_yearly_id }}</span>
                                        <span v-else class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Optional. Enables automatic recurring billing via Stripe for the yearly cycle.') }}</span>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('PayPal monthly plan ID') }}</span>
                                        <input v-model="form.paypal_plan_monthly_id" type="text" placeholder="P-..." class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <span v-if="form.errors.paypal_plan_monthly_id" class="mt-1 block text-xs text-red-500">{{ form.errors.paypal_plan_monthly_id }}</span>
                                        <span v-else class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Optional. Enables PayPal recurring billing for the monthly cycle.') }}</span>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('PayPal yearly plan ID') }}</span>
                                        <input v-model="form.paypal_plan_yearly_id" type="text" placeholder="P-..." class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                        <span v-if="form.errors.paypal_plan_yearly_id" class="mt-1 block text-xs text-red-500">{{ form.errors.paypal_plan_yearly_id }}</span>
                                        <span v-else class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Optional. Enables PayPal recurring billing for the yearly cycle.') }}</span>
                                    </label>

                                    <!-- Visibility Toggles (3 Columns) -->
                                    <div class="grid gap-4 md:grid-cols-3 md:col-span-2 mt-4">
                                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                                            <span>{{ t('Show plan') }}</span>
                                            <AppSwitch v-model="form.is_active" />
                                        </div>

                                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                                            <span>{{ t('Featured plan') }}</span>
                                            <AppSwitch
                                                :model-value="form.is_featured"
                                                @update:model-value="toggleFeatured"
                                            />
                                        </div>

                                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                                            <span>{{ t('Trial all countries') }}</span>
                                            <AppSwitch v-model="form.trial_all_countries" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="border-b border-gray-200 px-5 py-4 dark:border-surface-800">
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

                            <div class="grid gap-4 p-5 lg:grid-cols-3">
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
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

                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
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

                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
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

                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="border-b border-gray-200 px-5 py-4 dark:border-surface-800">
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

                            <div class="p-5">
                                <div v-if="form.features.length === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
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

        <AppModal
            :open="countryPricingModalOpen"
            max-width="max-w-7xl"
            @close="countryPricingModalOpen = false"
        >
            <template #header>
                <div class="flex items-start justify-between gap-4 rounded-t-2xl border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Country Pricing') }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Use country rows only where default pricing is not enough.') }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" :class="adminSecondaryButtonClass" @click="addCountryPrice">
                            <i class="ti ti-plus text-base"></i>
                            {{ t('Add Country') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 dark:text-gray-500 dark:hover:bg-surface-800 dark:hover:text-gray-400"
                            @click="countryPricingModalOpen = false"
                        >
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>
                </div>
            </template>

            <div v-if="visibleCountryPrices.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white px-5 py-12 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                {{ t('No country pricing yet.') }}
            </div>

            <div v-else class="space-y-4">
                <section
                    v-for="(row, index) in visibleCountryPrices"
                    :key="row.id ?? index"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="flex-1 min-w-0 space-y-3 w-full">
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

                                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                                    <label class="flex-1 min-w-0 block">
                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Country') }}</span>
                                        <AppSelect
                                            v-model="row.country_code"
                                            :options="countryOptions"
                                            :placeholder="t('Select country')"
                                            live-search
                                        />
                                    </label>

                                    <label class="flex-1 min-w-0 block">
                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Currency') }}</span>
                                        <AppSelect
                                            v-model="row.currency_code"
                                            :options="currencyOptions"
                                            :placeholder="t('Currency')"
                                            live-search
                                        />
                                    </label>

                                    <label class="flex-1 min-w-0 block">
                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('VAT %') }}</span>
                                        <input v-model="row.vat_percentage" type="number" min="0" max="100" step="0.01" :placeholder="t('Default')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>

                                    <div class="flex-1 min-w-0 flex flex-col">
                                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</span>
                                        <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200">
                                            <span class="pe-2 text-xs text-gray-600 dark:text-gray-400">{{ t('Active override') }}</span>
                                            <AppSwitch v-model="row.is_active" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 xl:self-start">
                                <Tooltip :content="isCountryPriceCollapsed(row, index) ? t('Expand') : t('Collapse')" placement="left">
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

                                <Tooltip :content="t('Remove')" placement="left">
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

                    <div v-show="!isCountryPriceCollapsed(row, index)" class="grid gap-4 p-4 xl:grid-cols-3">
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
        </AppModal>

        <AppModal
            :open="pricingSettingsModalOpen"
            max-width="max-w-3xl"
            :title="t('Plan Settings')"
            :subtitle="t('Control global pricing display, billing cycles, and frontend pricing copy.')"
            has-form
            :confirm-text="t('Save Settings')"
            :confirm-loading="pricingSettingsForm.processing"
            :confirm-loading-text="t('Saving...')"
            confirm-variant="admin"
            overflow-visible
            @close="pricingSettingsModalOpen = false"
            @submit="submitPricingSettings"
        >
            <div class="grid gap-4 md:grid-cols-3">
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    {{ t('Show Monthly') }}
                    <AppSwitch v-model="pricingSettingsForm.pricing_show_monthly" />
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    {{ t('Show Yearly') }}
                    <AppSwitch v-model="pricingSettingsForm.pricing_show_yearly" />
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    {{ t('Show Lifetime') }}
                    <AppSwitch v-model="pricingSettingsForm.pricing_show_lifetime" />
                </label>
            </div>

            <div class="mt-4">
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    {{ t('Auto-localize prices by visitor location') }}
                    <AppSwitch v-model="pricingSettingsForm.pricing_auto_localize" />
                </label>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Show visitors prices converted to their local currency (via GeoIP). Billing still happens in your store currency.') }}</p>
            </div>

            <div class="mt-4">
                <AppSelect
                    v-model="pricingSettingsForm.registration_default_plan"
                    :options="registrationPlanOptions"
                    :label="t('Assign new registered user to:')"
                />
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('What a newly-registered user is granted on signup.') }}</p>
                <p v-if="pricingSettingsForm.registration_default_plan === 'custom'" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('Usage is governed by the per-user daily/monthly limits in') }}
                    <Link :href="route('admin.ai.index')" class="underline hover:text-primary-600 dark:hover:text-primary-400">
                        {{ t('AI Settings → Spend Controls') }}
                    </Link>
                </p>
                <p v-if="pricingSettingsForm.errors.registration_default_plan" class="mt-1.5 text-xs text-danger-600">{{ pricingSettingsForm.errors.registration_default_plan }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3 mt-4">
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
        </AppModal>

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
