<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import Layout from '@themes/default/js/Layouts/AppLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

type BillingCycle = 'monthly' | 'yearly' | 'lifetime'

interface Plan {
    id: number
    name: string
    slug: string
    description: string | null
    credits: number | string
    features: string[]
}

interface CyclePrice {
    amount: number
    subtotal_amount: number
    original_amount: number | null
    vat_percentage: number
    vat_amount: number
    formatted: string
    subtotal_formatted: string
    original_formatted: string | null
    vat_formatted: string
    display_amount?: number
    display_formatted?: string
    is_trial: boolean
    trial_days: number | null
}

interface Gateway {
    id: number
    slug: string
    name: string
    description: string | null
    is_test_mode: boolean
    processing_fee_type: 'none' | 'percentage' | 'fixed'
    processing_fee_value: string | number
    fee_amount: number
    total_amount: number
    fee_formatted: string
    total_formatted: string
}

interface CouponPreview {
    coupon: { code: string; discount_formatted: string } | null
    summary: {
        subtotal_formatted: string
        discount_amount: number
        discount_formatted: string
        vat_amount: number
        vat_formatted: string
        proration_credit?: number
        proration_formatted?: string
        plan_total_formatted: string
    }
    gateways: Record<string, {
        fee_amount: number
        fee_formatted: string
        total_amount: number
        total_formatted: string
    }>
}

const props = defineProps<{
    plan: Plan
    billing: BillingCycle
    pricing: {
        country_code: string | null
        country_name: string | null
        currency_code: string
        display_currency_code?: string
        is_localized?: boolean
        source: 'country' | 'default'
        cycle: CyclePrice
    }
    gateways: Gateway[]
    proration?: {
        credit_amount: number
        credit_formatted: string
        from_plan: string | null
        net_amount: number
        net_formatted: string
    } | null
}>()

const { t } = useTranslate()
const page = usePage()
// Extended-License coupon system toggle (Admin → Settings → Features). Hides the
// coupon field when off; the server ignores any submitted code regardless.
const couponsEnabled = computed(() => Boolean(page.props.couponsEnabled))
const showCouponInput = ref(false)
const selectedGatewaySlug = ref(props.gateways[0]?.slug ?? '')
const submitting = ref(false)
const applyingCoupon = ref(false)
const coupon = ref('')
const couponError = ref('')
const couponPreview = ref<CouponPreview | null>(null)
const selectedGateway = computed(() => props.gateways.find((gateway) => gateway.slug === selectedGatewaySlug.value) ?? null)
const selectedGatewayTotals = computed(() => {
    if (!selectedGateway.value) return null

    return couponPreview.value?.gateways[selectedGateway.value.slug] ?? selectedGateway.value
})
const hasProration = computed(() => (props.proration?.credit_amount ?? 0) > 0)

const summary = computed(() => couponPreview.value?.summary ?? {
    subtotal_formatted: props.pricing.cycle.subtotal_formatted,
    discount_amount: 0,
    discount_formatted: '',
    vat_amount: props.pricing.cycle.vat_amount,
    vat_formatted: props.pricing.cycle.vat_formatted,
    proration_credit: props.proration?.credit_amount ?? 0,
    proration_formatted: props.proration?.credit_formatted ?? '',
    plan_total_formatted: hasProration.value ? (props.proration?.net_formatted ?? props.pricing.cycle.formatted) : props.pricing.cycle.formatted,
})

const billingLabel = computed(() => {
    if (props.billing === 'yearly') return t('Yearly')
    if (props.billing === 'lifetime') return t('Lifetime')

    return t('Monthly')
})

// Spreading a string would iterate it character by character, so never assume an array here:
// a legacy double-encoded plan row arrives as a JSON string.
const normalizeFeatures = (value: unknown): string[] => {
    let parsed: unknown = value

    for (let i = 0; i < 2 && typeof parsed === 'string'; i++) {
        try {
            parsed = JSON.parse(parsed)
        } catch {
            return []
        }
    }

    return Array.isArray(parsed)
        ? parsed.filter((feature): feature is string => typeof feature === 'string' && feature.trim() !== '')
        : []
}

const featureList = computed(() => {
    const features = normalizeFeatures(props.plan.features)

    if (Number(props.plan.credits) > 0) {
        features.push(t(':count credits', { count: Number(props.plan.credits).toLocaleString() }))
    }

    return features
})

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''

// Gateway sessions redirect to an EXTERNAL provider (PayPal/Stripe/…). An Inertia
// XHR can't follow a cross-origin redirect (CORS), so submit a real form — the
// browser then follows the redirect as a top-level navigation.
const createCheckoutSession = () => {
    if (!selectedGateway.value) return

    submitting.value = true

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = '/checkout/session'

    const fields: Record<string, string> = {
        _token: csrfToken(),
        plan: props.plan.slug,
        billing: props.billing,
        gateway: selectedGateway.value.slug,
        coupon: coupon.value || '',
    }

    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = name
        input.value = value
        form.appendChild(input)
    }

    document.body.appendChild(form)
    form.submit()
}

const applyCoupon = async () => {
    couponError.value = ''

    if (!coupon.value.trim()) {
        couponPreview.value = null
        return
    }

    applyingCoupon.value = true

    try {
        const response = await fetch('/checkout/coupon-preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                plan: props.plan.slug,
                billing: props.billing,
                coupon: coupon.value,
            }),
        })
        const data = await response.json()

        if (!response.ok) {
            couponPreview.value = null
            couponError.value = data.message ?? t('Coupon is invalid or expired.')
            return
        }

        couponPreview.value = data
        coupon.value = data.coupon?.code ?? coupon.value
    } catch {
        couponPreview.value = null
        couponError.value = t('Could not apply coupon. Please try again.')
    } finally {
        applyingCoupon.value = false
    }
}
</script>

<template>
    <Head :title="t('Checkout')" />

    <Layout>
        <div class="w-full pt-6 md:pt-10 pb-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="mt-2 text-3xl font-black text-gray-900">{{ t('Complete your payment') }}</h1>
                </div>
                <Link href="/pricing" class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-600">
                    {{ t('Back to pricing') }}
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <h2 class="text-2xl font-black text-gray-900">{{ plan.name }}</h2>
                            <p v-if="plan.description" class="mt-1 text-sm font-medium text-gray-500">{{ plan.description }}</p>
                        </div>
                        <span class="rounded-full bg-primary-50 px-4 py-2 text-sm font-bold text-primary-700 dark:!bg-primary-900/20">{{ billingLabel }}</span>
                    </div>

                    <div class="mb-6">
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-widest text-gray-400">{{ t('Payment method') }}</h3>
                        <div v-if="gateways.length" class="grid gap-3 md:grid-cols-2">
                            <button
                                v-for="gateway in gateways"
                                :key="gateway.id"
                                type="button"
                                @click="selectedGatewaySlug = gateway.slug"
                                :class="selectedGatewaySlug === gateway.slug ? 'border-primary-300 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-700 hover:border-primary-200 hover:bg-gray-50'"
                                class="rounded-xl border p-4 text-left transition"
                            >
                                <span class="flex items-center justify-between gap-3">
                                    <span class="text-base font-black">{{ gateway.name }}</span>
                                    <span v-if="gateway.is_test_mode" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                        {{ t('Test') }}
                                    </span>
                                </span>
                                <span v-if="gateway.description" class="mt-1 block text-sm font-medium text-gray-500">{{ gateway.description }}</span>
                                <span v-if="gateway.fee_amount > 0" class="mt-3 block text-xs font-semibold text-gray-500">
                                    {{ t('Processing fee') }}: {{ gateway.fee_formatted }}
                                </span>
                            </button>
                        </div>
                        <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:bg-amber-900/20 dark:border-amber-900/30 dark:text-amber-500">
                            {{ t('No payment gateway is enabled yet. Please contact support or enable a gateway from admin.') }}
                        </div>
                    </div>

                    <ul v-if="featureList.length" class="grid gap-3 border-t border-gray-100 pt-6 md:grid-cols-2">
                        <li v-for="feature in featureList" :key="feature" class="flex items-start gap-3 text-sm font-medium text-gray-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            {{ feature }}
                        </li>
                    </ul>
                </section>

                <aside class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-5 text-lg font-black text-gray-900">{{ t('Order summary') }}</h2>

                    <div class="space-y-4 text-sm font-medium text-gray-600">
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ plan.name }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Billing') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ billingLabel }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan price') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ pricing.cycle.subtotal_formatted }}</span>
                        </div>
                        <div v-if="couponsEnabled" class="block">
                            <button v-if="!showCouponInput" type="button" class="text-xs font-bold text-primary-600 hover:text-primary-700 hover:underline transition" @click="showCouponInput = true">
                                {{ t('Have coupon? Apply it') }}
                            </button>
                            <label v-else class="block">
                                <span class="mb-1 block text-sm font-bold text-gray-700">{{ t('Coupon code') }}</span>
                                <div class="flex gap-2">
                                    <input v-model="coupon" type="text" class="min-w-0 flex-1 !rounded-lg border border-gray-200 px-3 py-1.5 text-xs uppercase focus:border-primary-400 focus:ring-primary-100" :placeholder="t('Optional')" @input="couponPreview = null; couponError = ''" />
                                    <button type="button" :disabled="applyingCoupon" class="rounded-lg bg-gray-900 px-4 py-1.5 text-xs font-bold text-white dark:bg-gray-800/80 transition disabled:opacity-60" @click="applyCoupon">
                                        {{ applyingCoupon ? t('Applying...') : t('Apply') }}
                                    </button>
                                </div>
                                <span v-if="couponPreview?.coupon" class="mt-1 block text-xs font-bold text-primary-600">{{ t('Applied') }}: {{ couponPreview.coupon.code }} (-{{ couponPreview.coupon.discount_formatted }})</span>
                                <span v-else-if="couponError" class="mt-1 block text-xs font-bold text-red-600">{{ couponError }}</span>
                            </label>
                        </div>
                        <div v-if="summary.discount_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('Coupon discount') }}</span>
                            <span class="text-right text-green-600 font-bold">-{{ summary.discount_formatted }}</span>
                        </div>
                        <div v-if="(summary.proration_credit ?? 0) > 0" class="flex justify-between gap-4">
                            <span>{{ proration?.from_plan ? t('Plan credit (unused :plan)', { plan: proration.from_plan }) : t('Plan credit (unused)') }}</span>
                            <span class="text-right font-bold text-green-600">-{{ summary.proration_formatted }}</span>
                        </div>
                        <div v-if="summary.vat_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('VAT') }} ({{ pricing.cycle.vat_percentage }}%)</span>
                            <span class="text-right font-bold text-gray-900">{{ summary.vat_formatted }}</span>
                        </div>
                        <div v-if="selectedGatewayTotals && selectedGatewayTotals.fee_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('Processing fee') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ selectedGatewayTotals.fee_formatted }}</span>
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-100"></div>

                    <div class="flex items-end justify-between gap-4">
                        <span class="text-sm font-bold text-gray-500">{{ t('Payment total') }}</span>
                        <span class="text-3xl font-black text-gray-900">{{ selectedGatewayTotals?.total_formatted ?? summary.plan_total_formatted }}</span>
                    </div>

                    <p v-if="pricing?.is_localized && pricing.cycle?.display_formatted" class="mt-1 text-right text-[11px] font-semibold text-gray-400">
                        {{ t('≈ :localized in your local currency', { localized: pricing.cycle.display_formatted }) }}
                    </p>

                    <p v-if="pricing.cycle.is_trial" class="mt-3 rounded-lg bg-primary-50 p-3 text-xs font-semibold text-primary-700">
                        {{ t(':days days trial starts now. Renewal uses the selected billing cycle.', { days: String(pricing.cycle.trial_days ?? 0) }) }}
                    </p>

                    <button type="button" :disabled="!selectedGateway || submitting" @click="createCheckoutSession" class="mt-6 w-full rounded-xl btn-primary shadow-lg shadow-primary-600/20 transition disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none">
                        {{ submitting ? t('Creating session...') : selectedGateway ? t('Continue with :gateway', { gateway: selectedGateway.name }) : t('No gateway available') }}
                    </button>

                    <div class="mt-4 flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-gray-500">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>{{ t('SSL Secure Checkout') }}</span>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</Layout>
</template>
