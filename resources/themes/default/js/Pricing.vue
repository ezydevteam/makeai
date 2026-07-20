<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch, onMounted } from 'vue'
import Layout from '@themes/default/js/Layouts/AppLayout.vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

type BillingCycle = 'monthly' | 'yearly' | 'lifetime'

interface ResolvedCycle {
    amount: number
    subtotal_amount: number
    /** The cycle's configured list price. 0 means the admin never priced this cycle. */
    list_amount: number
    original_amount: number | null
    vat_percentage: number
    vat_amount: number
    formatted: string
    subtotal_formatted: string
    original_formatted: string | null
    vat_formatted: string
    renewal_formatted: string
    display_renewal_formatted?: string
    display_amount?: number
    display_formatted?: string
    display_subtotal_formatted?: string
    display_vat_formatted?: string
    display_original_formatted?: string | null
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
    features: string[] | string
    is_featured: boolean
    is_free: boolean
    sort_order: number
    yearly_savings: number
    pricing: {
        country_code: string | null
        country_name: string | null
        currency_code: string
        display_currency_code?: string
        is_localized?: boolean
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

interface CurrentSubscription {
    plan_id: number | null
    is_pro: boolean
    billing_cycle: string | null
    is_recurring_in_place: boolean
    scheduled_plan_slug: string | null
    scheduled_plan_name: string | null
    scheduled_change_at: string | null
}

const props = defineProps<{
    plans: Plan[]
    pricingCountry: string | null
    settings: PricingSettings
    currentSubscription?: CurrentSubscription | null
    // Only title_page is read here; the rest is consumed by the server-rendered head.
    seo?: { title_page?: string }
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

const CYCLE_RANK: Record<BillingCycle, number> = { monthly: 1, yearly: 2, lifetime: 3 }

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

const isLocalized = (plan: Plan) => !!plan.pricing?.is_localized

const displayPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    if (plan.is_free && cycle.subtotal_amount === 0) {
        return t('Free')
    }

    // Both branches show the SAME figure, just in different currencies. This used to
    // return the VAT-INCLUSIVE total when localized and the ex-VAT subtotal otherwise, so
    // a localized visitor saw a higher headline price for the same plan.
    if (isLocalized(plan) && cycle.display_subtotal_formatted) {
        return cycle.display_subtotal_formatted
    }

    return cycle.subtotal_formatted
}

// The strikethrough "was" price, in whichever currency the card is showing.
const originalPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    return isLocalized(plan) && cycle.display_original_formatted
        ? cycle.display_original_formatted
        : cycle.original_formatted
}

const showOriginalPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    return !!cycle.original_formatted && Number(cycle.original_amount) > cycle.subtotal_amount
}

const vatPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    return isLocalized(plan) && cycle.display_vat_formatted ? cycle.display_vat_formatted : cycle.vat_formatted
}

// What the plan costs once the trial ends — NOT `formatted`, which is zeroed for a trial
// and made the card advertise "renews at $0.00".
const renewalPrice = (plan: Plan) => {
    const cycle = activeCycle(plan)

    return isLocalized(plan) && cycle.display_renewal_formatted
        ? cycle.display_renewal_formatted
        : cycle.renewal_formatted
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
            ? plan.features.split(/[\r\n,]+/).map((feature: string) => feature.trim()).filter(Boolean)
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
    const authUser = (page.props.auth as { user?: { plan_id?: number | null } } | undefined)?.user

    return `${authUser ? '/checkout' : '/register'}?${query.toString()}`
}

const authUser = computed(() => (page.props.auth as { user?: { plan_id?: number | null } } | undefined)?.user ?? null)
const isPro = computed(() => !!props.currentSubscription?.is_pro)
const currentPlanId = computed<number | null>(
    () => props.currentSubscription?.plan_id ?? authUser.value?.plan_id ?? null,
)
const currentPlan = computed<Plan | null>(
    () => props.plans.find((plan) => plan.id === currentPlanId.value) ?? null,
)

const isCurrentPlan = (plan: Plan): boolean => {
    if (isPro.value) {
        return plan.id === currentPlanId.value
    }

    // A free (non-pro) user's "current" card is the free plan.
    return plan.is_free
}

// The card the user is scheduled to move to at period end.
const scheduledTargetSlug = computed(() => props.currentSubscription?.scheduled_plan_slug ?? null)

const scheduledDateLabel = computed(() => {
    const iso = props.currentSubscription?.scheduled_change_at
    if (!iso) return ''
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
})

type PlanCta = { label: string; kind: 'link' | 'downgrade' | 'upgrade-now' | 'current' | 'unavailable'; href?: string }

const planCta = (plan: Plan): PlanCta => {
    const cycle = activeCycle(plan)
    const defaultLabel = cycle.is_trial ? props.settings.pricing_trial_button_text : props.settings.pricing_checkout_button_text

    // A cycle the admin never priced is not sold — checkout rejects it server-side,
    // so don't offer a button that leads nowhere. Checked against the configured list
    // price, so a free trial or a 100%-off coupon still gets a working CTA.
    const cycleUnavailable = !plan.is_free && (cycle.list_amount ?? 0) <= 0
    const unavailableCta: PlanCta = { label: t('Not available'), kind: 'unavailable' }

    // Not logged in — original behaviour (register/checkout).
    if (!authUser.value) {
        return cycleUnavailable ? unavailableCta : { label: defaultLabel, kind: 'link', href: planActionUrl(plan) }
    }

    if (isCurrentPlan(plan)) {
        const recurring = !!props.currentSubscription?.is_recurring_in_place
        const currentCycle = props.currentSubscription?.billing_cycle
        const targetCycle = billing.value

        // Cycles are switchable only for a paid monthly/yearly plan (admin-granted
        // has no cycle; lifetime is owned forever; free has nothing to switch).
        const switchable = isPro.value && (currentCycle === 'monthly' || currentCycle === 'yearly')
        const targetHasPrice = (plan.pricing[targetCycle]?.subtotal_amount ?? 0) > 0

        // A different, priced cycle → offer a switch. Recurring subscribers switch
        // in place (monthly<->yearly); one-time and lifetime go through checkout.
        if (switchable && targetCycle !== currentCycle && targetHasPrice) {
            const cycleLabel = billingLabels[targetCycle]
            const isLonger = (CYCLE_RANK[targetCycle] ?? 1) > (CYCLE_RANK[currentCycle as BillingCycle] ?? 1)

            if (recurring && targetCycle !== 'lifetime') {
                return { label: t('Switch to :cycle', { cycle: cycleLabel }), kind: isLonger ? 'upgrade-now' : 'downgrade' }
            }

            return { label: t('Switch to :cycle', { cycle: cycleLabel }), kind: 'link', href: planActionUrl(plan) }
        }

        // On the current cycle: recurring subscribers get Manage (a subscription to
        // cancel/change); everyone else just sees that it's their current plan.
        if (recurring && currentCycle) {
            return { label: t('Manage'), kind: 'link', href: '/user/dashboard/billing' }
        }

        return { label: t('Current plan'), kind: 'current' }
    }

    // Checked after the current-plan branch so the user's own plan keeps its
    // Current/Manage state even on a cycle it isn't sold on.
    if (cycleUnavailable) {
        return unavailableCta
    }

    // On the Lifetime tab every plan change is a one-time purchase: there is no
    // recurring price to schedule a downgrade into, and lifetime upgrades are routed
    // to checkout server-side anyway. Send both directions straight to checkout.
    if (billing.value === 'lifetime') {
        return { label: defaultLabel, kind: 'link', href: planActionUrl(plan) }
    }

    // Free (non-pro) users only ever move up → normal checkout.
    if (!isPro.value || !currentPlan.value) {
        return { label: defaultLabel, kind: 'link', href: planActionUrl(plan) }
    }

    // Pro user comparing against their current plan by tier.
    if (plan.sort_order < currentPlan.value.sort_order) {
        return { label: t('Downgrade'), kind: 'downgrade' }
    }

    // Upgrade. A live recurring subscription (Stripe or PayPal) upgrades in place;
    // everything else goes through the normal checkout page.
    if (props.currentSubscription?.is_recurring_in_place) {
        return { label: t('Upgrade'), kind: 'upgrade-now' }
    }

    return { label: t('Upgrade'), kind: 'link', href: planActionUrl(plan) }
}

// True when the CTA on the current plan is a billing-cycle switch (not a plan change).
const isCycleSwitch = (plan: Plan): boolean =>
    isCurrentPlan(plan)
    && isPro.value
    && !!props.currentSubscription?.billing_cycle
    && billing.value !== props.currentSubscription?.billing_cycle

const confirmDowngrade = (plan: Plan) => {
    const message = isCycleSwitch(plan)
        ? t('Switch to :cycle billing at your next renewal? No charge now, and your current features stay active until then.', { cycle: billingLabels[billing.value] })
        : plan.is_free
            ? t('Move to the free plan at the end of your current period? Your current features stay active until then.')
            : t('Schedule a downgrade to :plan at the end of your current period? No charge, and your current features stay active until then.', { plan: plan.name })

    if (!window.confirm(message)) return

    router.post('/subscription/downgrade', { plan: plan.slug, billing: billing.value }, { preserveScroll: true })
}

const confirmUpgrade = (plan: Plan) => {
    const message = isCycleSwitch(plan)
        ? t('Switch to :cycle billing now? You will be charged the prorated difference (you may be redirected to PayPal to approve), and your renewal date stays the same.', { cycle: billingLabels[billing.value] })
        : t('Upgrade to :plan now? You will be charged the prorated difference (you may be redirected to PayPal to approve the new amount), and your renewal date stays the same.', { plan: plan.name })

    if (!window.confirm(message)) return

    router.post('/subscription/upgrade', { plan: plan.slug, billing: billing.value }, { preserveScroll: true })
}

const planCardClass = (plan: Plan) => [
    plan.is_featured
        ? 'border-primary-300 bg-gradient-to-b from-primary-500/10 via-white to-white shadow-2xl shadow-primary-500/10 ring-1 ring-primary-100 dark:border-primary-500/40 dark:from-primary-500/10 dark:via-surface-900 dark:to-surface-900 dark:ring-primary-500/20'
        : 'border-gray-100 bg-gradient-to-b from-primary-500/5 via-white to-white hover:border-primary-400 dark:border-surface-800 dark:from-primary-500/5 dark:via-surface-900 dark:to-surface-900 dark:hover:border-primary-500/30',
    'relative flex flex-col rounded-[2rem] border p-8 transition-all duration-300 hover:-translate-y-1',
]

const planButtonClass = (plan: Plan) => [
    'inline-flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black leading-none transition-all duration-200 ease-out hover:-translate-y-0.5',
    plan.is_featured
        ? 'bg-gradient-to-r from-primary-600 to-primary-500 !text-white shadow-xl shadow-primary-600/20 hover:from-primary-500 hover:to-primary-600 hover:shadow-primary-600/25'
        : 'border border-gray-200 bg-white text-gray-800 hover:border-primary-500 hover:text-primary-600 hover:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-400 dark:hover:text-primary-400 dark:hover:bg-primary-500/10',
]

const tabRefs = ref<Record<number, HTMLElement>>({})
const activeTabWidth = ref(0)
const activeTabOffset = ref(0)

const activeIndex = computed(() => billingCycles.value.indexOf(billing.value))

const updatePill = () => {
    const activeIdx = activeIndex.value
    const el = tabRefs.value[activeIdx]
    if (el) {
        activeTabWidth.value = el.offsetWidth
        activeTabOffset.value = el.offsetLeft
    }
}

watch(activeIndex, () => {
    setTimeout(updatePill, 0)
}, { flush: 'post' })

onMounted(() => {
    setTimeout(updatePill, 100)
})

const slidingPillStyle = computed(() => ({
    width: `${activeTabWidth.value}px`,
    transform: `translateX(${activeTabOffset.value - 4}px)`,
}))
</script>

<template>
    <!-- Site-free base from the server, so the tab matches app.blade.php's <title inertia>
         (built from the same seo payload) instead of drifting from it. -->
    <Head :title="seo?.title_page || t('Pricing')" />

    <Layout>
        <div class="w-full pt-6 md:pt-10 pb-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-16">
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 dark:text-white mb-4 tracking-tight">
                    {{ t('Simple, transparent') }} <span class="bg-gradient-to-r from-primary-600 to-accent-600 bg-clip-text text-transparent">{{ t('pricing') }}</span>
                </h1>
                <p class="text-gray-500 dark:text-gray-400 text-lg max-w-2xl mx-auto font-medium">{{ t('Choose the plan that fits your needs. Prices are shown for :country when available.', { country: pricingCountryLabel }) }}</p>

                <div v-if="billingCycles.length > 1" class="relative mt-10 inline-flex items-center justify-center rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-surface-700 dark:bg-surface-800">
                    <!-- Sliding background pill -->
                    <div
                        class="absolute top-1 bottom-1 left-1 rounded-full bg-primary-600 transition-all duration-300 ease-out dark:bg-primary-500"
                        :style="slidingPillStyle"
                    ></div>
                    <button
                        v-for="(cycle, idx) in billingCycles"
                        :key="cycle"
                        :ref="(el) => { if (el) tabRefs[idx] = el as HTMLElement }"
                        type="button"
                        @click="billing = cycle"
                        :class="[
                            billing === cycle
                                ? 'text-white'
                                : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            'relative z-10 rounded-full px-5 py-2 text-sm font-bold transition-colors duration-200'
                        ]"
                    >
                        {{ billingLabels[cycle] }}
                    </button>
                </div>
                <div v-else class="mt-10 flex justify-center">
                    <span class="rounded-full border border-primary-200 bg-primary-50 px-5 py-2 text-sm font-bold text-primary-700 dark:border-primary-900/50 dark:bg-primary-500/10 dark:text-primary-400">
                        {{ activeCycleLabel }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-24">
                <div v-for="plan in plans" :key="plan.id" :class="planCardClass(plan)">
                    <div v-if="plan.is_featured" class="absolute -top-4 left-1/2 -translate-x-1/2 px-5 py-1.5 bg-gradient-to-r from-primary-600 to-accent-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg">
                        {{ settings.pricing_featured_label_text }}
                    </div>

                    <h3 class="text-xl font-black text-gray-900 mb-1 dark:text-white">{{ plan.name }}</h3>
                    <p class="text-sm text-gray-500 font-medium mb-6 leading-relaxed dark:text-gray-400">{{ plan.description }}</p>

                    <div class="mb-6">
                        <div class="flex flex-wrap items-end gap-2">
                            <span v-if="showOriginalPrice(plan)" class="mb-1 text-lg font-bold text-gray-400 dark:text-gray-500 line-through">
                                {{ originalPrice(plan) }}
                            </span>
                            <span class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">
                                {{ displayPrice(plan) }}
                            </span>
                            <span v-if="priceSuffix(plan)" class="text-sm text-gray-400 dark:text-gray-500 font-bold mb-1">{{ priceSuffix(plan) }}</span>
                        </div>
                        <p v-if="activeCycle(plan).is_trial" class="text-xs text-primary-600 dark:text-primary-400 font-bold mt-1">
                            {{ t(':days days trial, then renews at :price', { days: String(activeCycle(plan).trial_days ?? 0), price: renewalPrice(plan) }) }}
                        </p>
                        <p v-else-if="billing === 'yearly' && savingsText(plan)" class="text-xs font-bold mt-1 text-success-600 dark:text-success-400">{{ savingsText(plan) }}</p>
                        <p v-else-if="billing === 'lifetime'" class="text-xs font-bold mt-1 text-success-600 dark:text-success-400">{{ t('One-time lifetime access') }}</p>
                        <p v-if="billing === 'lifetime' && savingsText(plan)" class="text-xs font-bold mt-1 text-success-600 dark:text-success-400">{{ savingsText(plan) }}</p>
                        <p v-if="activeCycle(plan).vat_percentage > 0" class="text-xs text-gray-500 dark:text-gray-400 font-semibold mt-1">
                            {{ t('Includes :percentage% VAT (:amount)', { percentage: String(activeCycle(plan).vat_percentage), amount: vatPrice(plan) }) }}
                        </p>
                        <p v-if="plan.pricing.source === 'country'" class="text-[10px] text-gray-400 dark:text-gray-500 font-black uppercase tracking-widest mt-2">{{ t('Country price') }}</p>
                        <p v-if="plan.pricing?.is_localized" class="text-[11px] text-gray-400 dark:text-gray-500 font-semibold mt-1">{{ t('Billed in :currency', { currency: plan.pricing.currency_code }) }}</p>
                    </div>

                    <ul class="space-y-3.5 flex-1 mb-8">
                        <li v-for="feature in planFeatures(plan)" :key="feature" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300 font-medium leading-tight">
                            <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 shadow-sm shadow-primary-500/20">
                                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            {{ feature }}
                        </li>
                    </ul>

                    <template v-if="planCta(plan).kind === 'link'">
                        <Link :href="planCta(plan).href!" :class="planButtonClass(plan)">
                            {{ planCta(plan).label }}
                        </Link>
                    </template>
                    <button
                        v-else-if="planCta(plan).kind === 'downgrade'"
                        type="button"
                        @click="confirmDowngrade(plan)"
                        :class="planButtonClass(plan)"
                    >
                        {{ planCta(plan).label }}
                    </button>
                    <button
                        v-else-if="planCta(plan).kind === 'upgrade-now'"
                        type="button"
                        @click="confirmUpgrade(plan)"
                        :class="planButtonClass(plan)"
                    >
                        {{ planCta(plan).label }}
                    </button>
                    <span
                        v-else
                        :class="[planButtonClass(plan), 'opacity-60 cursor-default pointer-events-none']"
                    >
                        {{ planCta(plan).label }}
                    </span>

                    <p
                        v-if="scheduledTargetSlug === plan.slug"
                        class="mt-3 text-center text-xs font-bold text-primary-600 dark:text-primary-400"
                    >
                        {{ t('Scheduled to start on :date', { date: scheduledDateLabel }) }}
                    </p>
                    <p
                        v-else-if="isCurrentPlan(plan) && scheduledTargetSlug"
                        class="mt-3 text-center text-xs font-bold text-amber-600 dark:text-amber-400"
                    >
                        {{ t('Changing to :plan on :date', { plan: currentSubscription?.scheduled_plan_name ?? '', date: scheduledDateLabel }) }}
                    </p>

                    <p v-if="plan.bottom_info_text" class="mt-4 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">
                        {{ plan.bottom_info_text }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</Layout>
</template>
