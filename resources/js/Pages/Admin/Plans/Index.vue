<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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

const props = defineProps<{
    plans: Plan[]
    countries: Country[]
    currencies: string[]
    settings: PricingSettings
}>()

const selectedPlanId = ref<number | null>(props.plans[0]?.id ?? null)
const settingsOpen = ref(false)
const selectedPlan = computed(() => props.plans.find((plan) => plan.id === selectedPlanId.value) ?? props.plans[0])

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

const settingsForm = useForm({
    pricing_show_monthly: props.settings.pricing_show_monthly,
    pricing_show_yearly: props.settings.pricing_show_yearly,
    pricing_show_lifetime: props.settings.pricing_show_lifetime,
    pricing_currency_code: props.settings.pricing_currency_code,
    pricing_trial_button_text: props.settings.pricing_trial_button_text,
    pricing_featured_label_text: props.settings.pricing_featured_label_text,
    pricing_checkout_button_text: props.settings.pricing_checkout_button_text,
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
        currency_code: props.settings.pricing_currency_code ?? 'USD',
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

const addFeature = () => form.features.push('')

const removeFeature = (index: number) => form.features.splice(index, 1)

const addCountryPrice = () => {
    const used = new Set(visibleCountryPrices.value.map((row) => row.country_code))
    const country = props.countries.find((item) => !used.has(item.code))

    if (!country) return

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

const countryName = (code: string) => props.countries.find((country) => country.code === code)?.name ?? code

const submit = () => {
    if (!selectedPlan.value) return
    form.features = normalizeFeatures(form.features)

    form.post(route('admin.plans.update', selectedPlan.value.id), {
        preserveScroll: true,
    })
}

const submitSettings = () => {
    settingsForm.post(route('admin.plans.settings'), {
        preserveScroll: true,
        onSuccess: () => {
            settingsOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Plans & Pricing" />

    <AdminLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Plans & Pricing</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage plan prices, country overrides, labels, and display settings.</p>
                    </div>
                    <button type="button" @click="settingsOpen = true" class="rounded-lg border border-primary-500 px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-50 dark:text-primary-300">
                        Pricing settings
                    </button>
                </div>
                <div v-if="plans.length === 0" class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No plans found.</p>
                </div>

                <div v-else class="min-w-0 space-y-6">
                    <nav class="max-w-full overflow-x-auto rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Plan tabs">
                        <div class="flex min-w-max gap-2">
                        <button
                            v-for="plan in plans"
                            :key="plan.id"
                            type="button"
                            @click="selectedPlanId = plan.id"
                            :class="selectedPlanId === plan.id ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'"
                            class="flex items-center gap-3 rounded-lg border px-4 py-3 text-left transition"
                        >
                            <span>
                                <span class="whitespace-nowrap text-sm font-semibold">{{ plan.name }}</span>
                                <span class="mt-1 block whitespace-nowrap text-xs text-gray-500">{{ plan.country_prices.length }} country prices</span>
                            </span>
                            <span v-if="plan.is_featured" class="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-700">{{ settings.pricing_featured_label_text }}</span>
                        </button>
                        </div>
                    </nav>

                    <form class="min-w-0 space-y-6" @submit.prevent="submit">
                        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Plan controls</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Default prices apply when country pricing is blank.</p>
                                </div>
                                <button type="submit" :disabled="form.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60">
                                    {{ form.processing ? 'Saving...' : 'Save plan' }}
                                </button>
                            </div>

                            <div class="mb-6 grid gap-3 md:grid-cols-3">
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    Show plan
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    Featured
                                    <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    Trial all countries
                                    <input v-model="form.trial_all_countries" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan name</span>
                                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Default trial days</span>
                                    <input v-model="form.trial_days" type="number" min="0" step="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>

                                <label class="block md:col-span-2">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</span>
                                    <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>

                                <label class="block md:col-span-2">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan bottom info text</span>
                                    <input v-model="form.bottom_info_text" type="text" placeholder="e.g. Cancel anytime" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan monthly credits</span>
                                    <input v-model="form.credits" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">VAT %</span>
                                    <input v-model="form.vat_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Default original and discounted prices</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Monthly original</span>
                                    <input v-model="form.original_price_monthly" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Monthly discounted</span>
                                    <input v-model="form.price_monthly" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Yearly original</span>
                                    <input v-model="form.original_price_yearly" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Yearly discounted</span>
                                    <input v-model="form.price_yearly" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Lifetime original</span>
                                    <input v-model="form.original_price_lifetime" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Lifetime discounted</span>
                                    <input v-model="form.price_lifetime" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Plan features</h3>
                                <button type="button" @click="addFeature" class="rounded-lg border border-primary-500 px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-50 dark:text-primary-300">Add feature</button>
                            </div>
                            <div class="space-y-3">
                                <div v-if="form.features.length === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">No features added.</div>
                                <div v-for="(feature, index) in form.features" :key="index" class="flex gap-3">
                                    <input v-model="form.features[index]" type="text" class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                    <button type="button" @click="removeFeature(index)" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50">Remove</button>
                                </div>
                            </div>
                        </section>

                        <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Country pricing</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Blank fields inherit defaults. Use Start trial toggles to enable country trials.</p>
                                </div>
                                <button type="button" @click="addCountryPrice" class="rounded-lg border border-primary-500 px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-50 dark:text-primary-300">
                                    Add country
                                </button>
                            </div>

                            <div class="max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                <table class="min-w-[1180px] divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                    <thead class="bg-gray-50 dark:bg-gray-950">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Country</th>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Currency</th>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Monthly</th>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Yearly</th>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Lifetime</th>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">VAT %</th>
                                            <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Active</th>
                                            <th class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                        <tr v-if="visibleCountryPrices.length === 0">
                                            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">No country pricing yet.</td>
                                        </tr>
                                        <tr v-for="(row, index) in visibleCountryPrices" :key="row.id ?? index" class="align-top">
                                            <td class="px-3 py-3">
                                                <select v-model="row.country_code" class="w-44 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                    <option v-for="country in countries" :key="country.code" :value="country.code">{{ country.name }}</option>
                                                </select>
                                                <p class="mt-1 text-xs text-gray-400">{{ countryName(row.country_code) }}</p>
                                            </td>
                                            <td class="px-3 py-3">
                                                <select v-model="row.currency_code" class="w-24 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                    <option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-3">
                                                <input v-model="row.original_price_monthly" type="number" min="0" step="0.01" placeholder="Original" class="mb-2 w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                                <input v-model="row.price_monthly" type="number" min="0" step="0.01" placeholder="Discount" class="mb-2 w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                                <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                                    <input v-model="row.trial_monthly_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    Start trial
                                                </label>
                                                <input v-if="row.trial_monthly_enabled" v-model="row.trial_monthly_days" type="number" min="1" step="1" placeholder="30 days" class="w-28 rounded-lg border border-primary-200 px-3 py-2 text-xs dark:border-primary-800 dark:bg-gray-950 dark:text-white" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input v-model="row.original_price_yearly" type="number" min="0" step="0.01" placeholder="Original" class="mb-2 w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                                <input v-model="row.price_yearly" type="number" min="0" step="0.01" placeholder="Discount" class="mb-2 w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                                <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                                    <input v-model="row.trial_yearly_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    Start trial
                                                </label>
                                                <input v-if="row.trial_yearly_enabled" v-model="row.trial_yearly_days" type="number" min="1" step="1" placeholder="360 days" class="w-28 rounded-lg border border-primary-200 px-3 py-2 text-xs dark:border-primary-800 dark:bg-gray-950 dark:text-white" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input v-model="row.original_price_lifetime" type="number" min="0" step="0.01" placeholder="Original" class="mb-2 w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                                <input v-model="row.price_lifetime" type="number" min="0" step="0.01" placeholder="Discount" class="mb-2 w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                                <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                                    <input v-model="row.trial_lifetime_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    Start trial
                                                </label>
                                                <input v-if="row.trial_lifetime_enabled" v-model="row.trial_lifetime_days" type="number" min="1" step="1" placeholder="30 days" class="w-28 rounded-lg border border-primary-200 px-3 py-2 text-xs dark:border-primary-800 dark:bg-gray-950 dark:text-white" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input v-model="row.vat_percentage" type="number" min="0" max="100" step="0.01" placeholder="Default" class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input v-model="row.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <button type="button" @click="removeCountryPrice(index)" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="settingsOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 px-4 backdrop-blur-sm" @click.self="settingsOpen = false">
            <form class="w-full max-w-xl rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900" @submit.prevent="submitSettings">
                <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pricing display settings</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Control visible billing cycles and frontend button labels.</p>
                </div>
                <div class="space-y-5 px-6 py-5">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm dark:border-gray-700 dark:text-gray-300">
                            Monthly
                            <input v-model="settingsForm.pricing_show_monthly" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm dark:border-gray-700 dark:text-gray-300">
                            Yearly
                            <input v-model="settingsForm.pricing_show_yearly" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm dark:border-gray-700 dark:text-gray-300">
                            Lifetime
                            <input v-model="settingsForm.pricing_show_lifetime" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Default plan currency</span>
                        <select v-model="settingsForm.pricing_currency_code" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Trial button text</span>
                        <input v-model="settingsForm.pricing_trial_button_text" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Featured label text</span>
                        <input v-model="settingsForm.pricing_featured_label_text" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Checkout button text</span>
                        <input v-model="settingsForm.pricing_checkout_button_text" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                    </label>
                </div>
                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-950">
                    <button type="button" @click="settingsOpen = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                    <button type="submit" :disabled="settingsForm.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:opacity-60">
                        {{ settingsForm.processing ? 'Saving...' : 'Save settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
