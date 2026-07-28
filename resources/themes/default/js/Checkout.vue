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

// The header is hidden on this page (hide_header from CheckoutController), so the logo
// is rendered here instead — the one piece of chrome worth keeping, because a payment
// page with no branding at all is exactly what a phishing page looks like.
const branding = computed(() => (page.props.branding as { site_name?: string; site_logo_light?: string; site_logo_dark?: string }) ?? {})
const logoLight = computed(() => String(branding.value.site_logo_light || ''))
const logoDark = computed(() => String(branding.value.site_logo_dark || logoLight.value))

const billingLabel = computed(() => {
    if (props.billing === 'yearly') return t('Yearly')
    if (props.billing === 'lifetime') return t('Lifetime')

    return t('Monthly')
})

// Checkout no longer re-sells the plan: the feature list, description and credit count
// left with the plan-details column. Someone who reached this page has already chosen —
// repeating the pitch here only competes with the one action the page exists for. The
// plan name links back to pricing for anyone who wants to compare again.
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
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div class="absolute inset-y-0 left-0 w-1/3 max-w-md bg-gradient-to-r from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
            <div class="absolute inset-y-0 right-0 w-1/3 max-w-md bg-gradient-to-l from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
        </div>

        <div class="relative z-10 w-full pt-8 md:pt-12 pb-12">
            <div class="mb-8 flex justify-center px-4">
                <Link href="/" class="inline-flex items-center">
                    <img v-if="logoLight" :src="logoLight" :alt="branding.site_name || 'Logo'" class="h-9 w-auto dark:hidden" />
                    <img v-if="logoDark" :src="logoDark" :alt="branding.site_name || 'Logo'" class="hidden h-9 w-auto dark:block" />
                    <span v-if="!logoLight && !logoDark" class="text-xl font-black text-gray-900 dark:text-white">{{ branding.site_name }}</span>
                </Link>
            </div>

            <div class="flex justify-center px-4 sm:px-6">
                <div class="w-full max-w-lg">
                <h1 class="mb-6 text-center text-3xl font-black text-gray-900 dark:text-white">{{ t('Complete your payment') }}</h1>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="mb-5 text-lg font-black text-gray-900 dark:text-white">{{ t('Order summary') }}</h2>

                    <div class="space-y-4 text-sm font-medium text-gray-600 dark:text-gray-300">
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan') }}</span>
                            <!--
                                Opens in a new tab on purpose: this is a live checkout, and a
                                same-tab navigation to go and re-read the plans would throw away
                                an applied coupon and any selection made here.
                            -->
                            <a
                                href="/pricing"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-right font-bold text-primary-600 underline decoration-primary-300 underline-offset-2 transition hover:text-primary-700 dark:text-primary-400 dark:decoration-primary-500/40 dark:hover:text-primary-300"
                            >{{ plan.name }}</a>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Billing') }}</span>
                            <span class="text-right font-bold text-gray-900 dark:text-white">{{ billingLabel }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan price') }}</span>
                            <span class="text-right font-bold text-gray-900 dark:text-white">{{ pricing.cycle.subtotal_formatted }}</span>
                        </div>
                        <!--
                            The coupon FIELD lives down by the Continue button; only the
                            resulting discount belongs in the figures. Applying a code is an
                            action, not a line item, and it read as one more thing to fill in
                            before the total made sense.
                        -->
                        <div v-if="summary.discount_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('Coupon discount') }}</span>
                            <span class="text-right text-green-600 font-bold dark:text-green-400">-{{ summary.discount_formatted }}</span>
                        </div>
                        <div v-if="(summary.proration_credit ?? 0) > 0" class="flex justify-between gap-4">
                            <span>{{ proration?.from_plan ? t('Plan credit (unused :plan)', { plan: proration.from_plan }) : t('Plan credit (unused)') }}</span>
                            <span class="text-right font-bold text-green-600 dark:text-green-400">-{{ summary.proration_formatted }}</span>
                        </div>
                        <div v-if="summary.vat_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('VAT') }} ({{ pricing.cycle.vat_percentage }}%)</span>
                            <span class="text-right font-bold text-gray-900 dark:text-white">{{ summary.vat_formatted }}</span>
                        </div>
                        <div v-if="selectedGatewayTotals && selectedGatewayTotals.fee_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('Processing fee') }}</span>
                            <span class="text-right font-bold text-gray-900 dark:text-white">{{ selectedGatewayTotals.fee_formatted }}</span>
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-100 dark:border-surface-800"></div>

                    <div class="flex items-end justify-between gap-4">
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Payment total') }}</span>
                        <span class="text-3xl font-black text-gray-900 dark:text-white">{{ selectedGatewayTotals?.total_formatted ?? summary.plan_total_formatted }}</span>
                    </div>

                    <p v-if="pricing?.is_localized && pricing.cycle?.display_formatted" class="mt-1 text-right text-[11px] font-semibold text-gray-400 dark:text-gray-500">
                        {{ t('≈ :localized in your local currency', { localized: pricing.cycle.display_formatted }) }}
                    </p>

                    <p v-if="pricing.cycle.is_trial" class="mt-3 rounded-lg bg-primary-50 p-3 text-xs font-semibold text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                        {{ t(':days days trial starts now. Renewal uses the selected billing cycle.', { days: String(pricing.cycle.trial_days ?? 0) }) }}
                    </p>

                    <!--
                        Only shown when there is a choice to make. With one gateway enabled a
                        selector is a control with a single option — the button already names
                        it ("Continue with Stripe"), which says the same thing in one place.
                        Per-gateway processing fees are not repeated here: the fee line in the
                        summary above already tracks the selection.
                    -->
                    <fieldset v-if="gateways.length > 1" class="mt-6 border-t border-gray-100 pt-5 dark:border-surface-800">
                        <legend class="mb-1 text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Payment method') }}</legend>
                        <div class="space-y-1">
                            <label
                                v-for="gateway in gateways"
                                :key="gateway.id"
                                class="flex cursor-pointer items-center gap-3 rounded-full px-2 py-2 transition hover:bg-gray-50 dark:hover:bg-surface-800"
                            >
                                <input
                                    v-model="selectedGatewaySlug"
                                    type="radio"
                                    name="gateway"
                                    :value="gateway.slug"
                                    class="h-4 w-4 shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-surface-600 dark:bg-surface-800"
                                />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ gateway.name }}</span>
                                <!-- Only ever visible on a gateway in test mode, where mistaking it for a live one costs a real order. -->
                                <span v-if="gateway.is_test_mode" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                    {{ t('Test') }}
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div v-else-if="!gateways.length" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:border-amber-900/30 dark:bg-amber-900/20 dark:text-amber-500">
                        {{ t('No payment gateway is enabled yet. Please contact support or enable a gateway from admin.') }}
                    </div>

                    <!--
                        Last thing before committing. Collapsed to a single line by default so
                        the empty field is not an unanswered question sitting between the buyer
                        and the button — the discount it produces still appears up in the
                        figures, where the total can be checked against it.
                    -->
                    <div v-if="couponsEnabled" class="mt-5">
                        <button v-if="!showCouponInput" type="button" class="text-xs font-bold text-primary-600 transition hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300" @click="showCouponInput = true">
                            {{ t('Have a coupon? Apply it') }}
                        </button>
                        <label v-else class="block">
                            <span class="mb-1 block text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Coupon code') }}</span>
                            <div class="flex gap-2">
                                <input v-model="coupon" type="text" class="min-w-0 flex-1 !rounded-full border border-gray-200 px-3 py-1.5 text-xs uppercase focus:border-primary-400 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder-gray-500 dark:focus:border-primary-500/50 dark:focus:ring-primary-900/30" :placeholder="t('Optional')" @input="couponPreview = null; couponError = ''" />
                                <button type="button" :disabled="applyingCoupon" class="rounded-full bg-gray-900 px-4 py-1.5 text-xs font-bold text-white transition disabled:opacity-60 dark:bg-gray-800/80" @click="applyCoupon">
                                    {{ applyingCoupon ? t('Applying...') : t('Apply') }}
                                </button>
                            </div>
                            <span v-if="couponPreview?.coupon" class="mt-1 block text-xs font-bold text-primary-600 dark:text-primary-400">{{ t('Applied') }}: {{ couponPreview.coupon.code }} (-{{ couponPreview.coupon.discount_formatted }})</span>
                            <span v-else-if="couponError" class="mt-1 block text-xs font-bold text-red-600 dark:text-red-400">{{ couponError }}</span>
                        </label>
                    </div>

                    <button type="button" :disabled="!selectedGateway || submitting" @click="createCheckoutSession" class="mt-4 w-full rounded-xl btn-primary shadow-lg shadow-primary-600/20 transition disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none">
                        {{ submitting ? t('Creating session...') : selectedGateway ? t('Continue with :gateway', { gateway: selectedGateway.name }) : t('No gateway available') }}
                    </button>

                    <div class="mt-4 flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                        <svg class="h-4 w-4 text-emerald-500 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>{{ t('SSL Secure Checkout') }}</span>
                    </div>
                </section>

                <div class="mt-5 text-center">
                    <Link href="/pricing" class="text-sm font-medium text-gray-300 underline-offset-4 transition hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400">
                        <i class="ti ti-arrow-left"></i>
                        {{ t('Back to pricing') }}
                    </Link>
                </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
