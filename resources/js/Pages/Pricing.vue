<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Layout from '@/Layouts/AppLayout.vue'

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

interface CreditPack {
    id: number
    name: string
    credits: number
    price: number
    formatted_price: string
    is_popular: boolean
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
    creditPacks: CreditPack[]
    pricingCountry: string | null
    settings: PricingSettings
}>()

const page = usePage()
const billing = ref<BillingCycle>('monthly')

const billingLabels: Record<BillingCycle, string> = {
    monthly: 'Monthly',
    yearly: 'Yearly',
    lifetime: 'Lifetime',
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

const formatMoney = (amount: number, currency: string) => {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(amount)
    } catch {
        return `${currency} ${amount.toFixed(2)}`
    }
}

const displayPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    if (plan.is_free && cycle.subtotal_amount === 0) {
        return 'Free'
    }

    return cycle.subtotal_formatted
}

const priceSuffix = (plan: Plan) => {
    const cycle = activeCycle(plan)

    if (cycle.is_trial || (plan.is_free && cycle.subtotal_amount === 0)) {
        return ''
    }

    if (billing.value === 'monthly') {
        return '/month'
    }

    if (billing.value === 'yearly') {
        return '/year'
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

        return savings > 0 ? `Save ${formatMoney(savings, currency)}` : ''
    }

    if (billing.value === 'lifetime' && lifetime > 0) {
        const originalLifetime = plan.pricing.lifetime.original_amount ?? 0

        if (originalLifetime > lifetime) {
            return `Save ${formatMoney(originalLifetime - lifetime, currency)}`
        }

        const yearlySavings = yearly > lifetime ? yearly - lifetime : 0
        const monthlySavings = monthly > 0 && monthly * 12 > lifetime ? monthly * 12 - lifetime : 0
        const savings = Math.max(yearlySavings, monthlySavings)

        return savings > 0 ? `Save ${formatMoney(savings, currency)}` : ''
    }

    return ''
}

const pricingCountryLabel = computed(() => {
    const countryName = props.plans.find((plan) => plan.pricing.country_name)?.pricing.country_name

    return countryName || props.pricingCountry || 'default'
})

const planFeatures = (plan: Plan) => {
    const features = [...plan.features]

    if (Number(plan.credits) > 0) {
        features.push(`${Number(plan.credits).toLocaleString()} credits`)
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
</script>

<template>
    <Head title="Pricing" />

    <Layout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
            <div class="text-center mb-16">
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 mb-4 tracking-tight">
                    Simple, transparent <span class="bg-gradient-to-r from-primary-600 to-accent-600 bg-clip-text text-transparent">pricing</span>
                </h1>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto font-medium">Choose the plan that fits your needs. Prices are shown for {{ pricingCountryLabel }} when available.</p>

                <div v-if="billingCycles.length > 1" class="inline-flex items-center justify-center gap-1 mt-10 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm">
                    <button
                        v-for="cycle in billingCycles"
                        :key="cycle"
                        type="button"
                        @click="billing = cycle"
                        :class="billing === cycle ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
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
                <div v-for="plan in plans" :key="plan.id" :class="[plan.is_featured ? 'border-primary-200 bg-white shadow-2xl shadow-primary-500/10 scale-105 z-10' : 'border-gray-100 bg-white hover:border-gray-200']" class="relative border rounded-3xl p-8 flex flex-col transition-all duration-300">
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
                            {{ activeCycle(plan).trial_days }} days trial, then renews at {{ activeCycle(plan).formatted }}
                        </p>
                        <p v-else-if="billing === 'yearly' && savingsText(plan)" class="text-xs text-primary-600 font-bold mt-1">{{ savingsText(plan) }}</p>
                        <p v-else-if="billing === 'lifetime'" class="text-xs text-primary-600 font-bold mt-1">One-time lifetime access</p>
                        <p v-if="billing === 'lifetime' && savingsText(plan)" class="text-xs text-primary-600 font-bold mt-1">{{ savingsText(plan) }}</p>
                        <p v-if="activeCycle(plan).vat_percentage > 0" class="text-xs text-gray-500 font-semibold mt-1">
                            Includes {{ activeCycle(plan).vat_percentage }}% VAT ({{ activeCycle(plan).vat_formatted }})
                        </p>
                        <p v-if="plan.pricing.source === 'country'" class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-2">Country price</p>
                    </div>

                    <ul class="space-y-3.5 flex-1 mb-8">
                        <li v-for="feature in planFeatures(plan)" :key="feature" class="flex items-start gap-3 text-sm text-gray-600 font-medium leading-tight">
                            <svg class="w-5 h-5 text-success-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            {{ feature }}
                        </li>
                    </ul>

                    <Link :href="planActionUrl(plan)" :class="[plan.is_featured ? 'bg-primary-600 text-white shadow-xl shadow-primary-600/20 hover:bg-primary-500' : 'bg-gray-100 text-gray-900 hover:bg-gray-200']" class="block text-center py-4 rounded-2xl font-black text-sm transition-all hover:-translate-y-1">
                        {{ activeCycle(plan).is_trial ? settings.pricing_trial_button_text : settings.pricing_checkout_button_text }}
                    </Link>

                    <p v-if="plan.bottom_info_text" class="mt-4 text-center text-xs font-semibold text-gray-500">
                        {{ plan.bottom_info_text }}
                    </p>
                </div>
            </div>

            <div class="text-center mb-12">
                <h2 class="text-3xl font-black text-gray-900 mb-3">Need more power?</h2>
                <p class="text-gray-500 font-medium">Top up your credits instantly with our one-time packs.</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
                <div v-for="pack in creditPacks" :key="pack.id" :class="[pack.is_popular ? 'border-primary-200 bg-primary-50/30' : 'border-gray-100 bg-white shadow-sm']" class="relative border rounded-3xl p-6 text-center hover:border-primary-300 transition-all group">
                    <div v-if="pack.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-primary-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full">Popular</div>
                    <p class="text-3xl font-black text-gray-900 mb-1 group-hover:scale-110 transition-transform">{{ pack.credits.toLocaleString() }}</p>
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-4">credits</p>
                    <p class="text-xl font-black text-primary-600">{{ pack.formatted_price }}</p>
                </div>
            </div>
        </div>
    </Layout>
</template>
