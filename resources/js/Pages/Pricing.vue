<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Layout from '@/Layouts/AppLayout.vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

type BillingCycle = 'monthly' | 'yearly' | 'lifetime'

interface ResolvedCycle {
    amount: number
    subtotal_amount: number
    original_amount: number | null
    vat_percentage: number
    vat_amount: number
    formatted: string
    subtotal_formatted: string
    original_formatted: string | null
    vat_formatted: string
    uses_default: boolean
    is_trial: boolean
    trial_days: number | null
}

interface Plan {
    id: number
    name: string
    slug: string
    description: string
    bottom_info_text: string | null
    credits: number | string
    features: string[]
    is_featured: boolean
    is_free: boolean
    yearly_savings: number
    pricing: {
        country_code: string | null
        country_name: string | null
        currency_code: string
        source: 'country' | 'default'
        monthly: ResolvedCycle
        yearly: ResolvedCycle
        lifetime: ResolvedCycle
    }
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
    pricingCountry: string | null
    settings: PricingSettings
}>()

const page = usePage()
const { formatCurrency } = useNumberFormat()
const { t } = useTranslate()
const billing = ref<BillingCycle>('monthly')

const billingLabels: Record<BillingCycle, string> = {
    monthly: t('Monthly'),
    yearly: t('Yearly'),
    lifetime: t('Lifetime'),
}

const billingCycles = computed<BillingCycle[]>(() => {
    const cycles: BillingCycle[] = []

    if (props.settings.pricing_show_monthly) cycles.push('monthly')
    if (props.settings.pricing_show_yearly) cycles.push('yearly')
    if (props.settings.pricing_show_lifetime) cycles.push('lifetime')

    return cycles.length > 0 ? cycles : (['monthly'] as BillingCycle[])
})

watch(billingCycles, (cycles) => {
    if (!cycles.includes(billing.value)) {
        billing.value = cycles[0] ?? 'monthly'
    }
}, { immediate: true })

const activeCycle = (plan: Plan): ResolvedCycle => plan.pricing[billing.value]

const activeCycleLabel = computed(() => billingLabels[billing.value])

const displayPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    if (plan.is_free && cycle.subtotal_amount === 0) {
        return t('Free')
    }

    return cycle.subtotal_formatted
}

const priceSuffix = (plan: Plan) => {
    const cycle = activeCycle(plan)

    if (cycle.is_trial || (plan.is_free && cycle.subtotal_amount === 0)) {
        return ''
    }

    if (billing.value === 'monthly') {
        return t('/month')
    }

    if (billing.value === 'yearly') {
        return t('/year')
    }

    return ''
}

const savingsText = (plan: Plan) => {
    const currency = plan.pricing.currency_code
    const monthly = plan.pricing.monthly.subtotal_amount
    const yearly = plan.pricing.yearly.subtotal_amount
    const lifetime = plan.pricing.lifetime.subtotal_amount

    if (billing.value === 'yearly' && monthly > 0 && yearly > 0) {
        const savings = monthly * 12 - yearly

        return savings > 0 ? t('Save :amount', { amount: formatCurrency(savings, currency) }) : ''
    }

    if (billing.value === 'lifetime' && lifetime > 0) {
        const originalLifetime = plan.pricing.lifetime.original_amount ?? 0

        if (originalLifetime > lifetime) {
            return t('Save :amount', { amount: formatCurrency(originalLifetime - lifetime, currency) })
        }

        const yearlySavings = yearly > lifetime ? yearly - lifetime : 0
        const monthlySavings = monthly > 0 && monthly * 12 > lifetime ? monthly * 12 - lifetime : 0
        const savings = Math.max(yearlySavings, monthlySavings)

        return savings > 0 ? t('Save :amount', { amount: formatCurrency(savings, currency) }) : ''
    }

    return ''
}

const pricingCountryLabel = computed(() => {
    const countryName = props.plans.find((plan) => plan.pricing.country_name)?.pricing.country_name

    return countryName || props.pricingCountry || t('default')
})

const planFeatures = (plan: Plan) => {
    const features = Array.isArray(plan.features)
        ? [...plan.features]
        : typeof plan.features === 'string'
            ? plan.features.split(/[\r\n,]+/).map((feature) => feature.trim()).filter(Boolean)
            : []

    if (Number(plan.credits) > 0) {
        features.push(t(':count credits', { count: Number(plan.credits).toLocaleString() }))
    }

    return features
}

const planActionUrl = (plan: Plan) => {
    const query = new URLSearchParams({
        plan: plan.slug,
        billing: billing.value,
    })
    const authUser = (page.props.auth as { user?: unknown } | undefined)?.user

    return `${authUser ? '/checkout' : '/register'}?${query.toString()}`
}

const planCardClass = (plan: Plan) => [
    plan.is_featured
        ? 'border-primary-200 bg-white shadow-2xl shadow-primary-500/10 ring-1 ring-primary-100'
        : 'border-gray-100 bg-white hover:border-gray-200',
    'relative flex flex-col rounded-3xl border p-8 transition-all duration-300 hover:-translate-y-1',
]

const planButtonClass = (plan: Plan) => [
    'inline-flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black leading-none transition-all duration-200 ease-out hover:-translate-y-0.5',
    plan.is_featured
        ? 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-xl shadow-primary-600/20 hover:from-primary-500 hover:to-primary-600 hover:shadow-primary-600/25'
        : 'bg-gray-100 text-gray-900 hover:bg-gray-200',
]
</script>

<template>
    <Head :title="t('Pricing')" />

    <Layout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
            <div class="text-center mb-16">
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 mb-4 tracking-tight">
                    {{ t('Simple, transparent') }} <span class="bg-gradient-to-r from-primary-600 to-accent-600 bg-clip-text text-transparent">{{ t('pricing') }}</span>
                </h1>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto font-medium">{{ t('Choose the plan that fits your needs. Prices are shown for :country when available.', { country: pricingCountryLabel }) }}</p>

                <div v-if="billingCycles.length > 1" class="inline-flex items-center justify-center gap-1 mt-10 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm">
                    <button
                        v-for="cycle in billingCycles"
                        :key="cycle"
                        type="button"
                        @click="billing = cycle"
                        :class="billing === cycle ? 'btn-primary shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
                        class="rounded-xl px-5 py-2 text-sm font-bold transition"
                    >
                        {{ billingLabels[cycle] }}
                    </button>
                </div>
                <div v-else class="mt-10 flex justify-center">
                    <span class="rounded-full border border-primary-200 bg-primary-50 px-5 py-2 text-sm font-bold text-primary-700">
                        {{ activeCycleLabel }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-24">
                <div v-for="plan in plans" :key="plan.id" :class="planCardClass(plan)">
                    <div v-if="plan.is_featured" class="absolute -top-4 left-1/2 -translate-x-1/2 px-5 py-1.5 bg-gradient-to-r from-primary-600 to-accent-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg">
                        {{ settings.pricing_featured_label_text }}
                    </div>

                    <h3 class="text-xl font-black text-gray-900 mb-1">{{ plan.name }}</h3>
                    <p class="text-sm text-gray-500 font-medium mb-6 leading-relaxed">{{ plan.description }}</p>

                    <div class="mb-6">
                        <div class="flex flex-wrap items-end gap-2">
                            <span v-if="activeCycle(plan).original_formatted && Number(activeCycle(plan).original_amount) > activeCycle(plan).subtotal_amount" class="mb-1 text-lg font-bold text-gray-400 line-through">
                                {{ activeCycle(plan).original_formatted }}
                            </span>
                            <span class="text-4xl font-black text-gray-900 tracking-tight">
                                {{ displayPrice(plan) }}
                            </span>
                            <span v-if="priceSuffix(plan)" class="text-sm text-gray-400 font-bold mb-1">{{ priceSuffix(plan) }}</span>
                        </div>
                        <p v-if="activeCycle(plan).is_trial" class="text-xs text-primary-600 font-bold mt-1">
                            {{ t(':days days trial, then renews at :price', { days: String(activeCycle(plan).trial_days ?? 0), price: activeCycle(plan).formatted }) }}
                        </p>
                        <p v-else-if="billing === 'yearly' && savingsText(plan)" class="text-xs font-bold mt-1 text-success-600">{{ savingsText(plan) }}</p>
                        <p v-else-if="billing === 'lifetime'" class="text-xs font-bold mt-1 text-success-600">{{ t('One-time lifetime access') }}</p>
                        <p v-if="billing === 'lifetime' && savingsText(plan)" class="text-xs font-bold mt-1 text-success-600">{{ savingsText(plan) }}</p>
                        <p v-if="activeCycle(plan).vat_percentage > 0" class="text-xs text-gray-500 font-semibold mt-1">
                            {{ t('Includes :percentage% VAT (:amount)', { percentage: String(activeCycle(plan).vat_percentage), amount: activeCycle(plan).vat_formatted }) }}
                        </p>
                        <p v-if="plan.pricing.source === 'country'" class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-2">{{ t('Country price') }}</p>
                    </div>

                    <ul class="space-y-3.5 flex-1 mb-8">
                            <li v-for="feature in planFeatures(plan)" :key="feature" class="flex items-start gap-3 text-sm text-gray-600 font-medium leading-tight">
                            <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 shadow-sm shadow-primary-500/20">
                                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            {{ feature }}
                        </li>
                    </ul>

                    <Link :href="planActionUrl(plan)" :class="planButtonClass(plan)">
                        {{ activeCycle(plan).is_trial ? settings.pricing_trial_button_text : settings.pricing_checkout_button_text }}
                    </Link>

                    <p v-if="plan.bottom_info_text" class="mt-4 text-center text-xs font-semibold text-gray-500">
                        {{ plan.bottom_info_text }}
                    </p>
                </div>
            </div>
        </div>
    </Layout>
</template>
ate>
