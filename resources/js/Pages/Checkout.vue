<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Layout from '@/Layouts/AppLayout.vue'
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
    processing_fee_currency: string
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
        source: 'country' | 'default'
        cycle: CyclePrice
    }
    gateways: Gateway[]
}>()

const { t } = useTranslate()
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
const summary = computed(() => couponPreview.value?.summary ?? {
    subtotal_formatted: props.pricing.cycle.subtotal_formatted,
    discount_amount: 0,
    discount_formatted: '',
    vat_amount: props.pricing.cycle.vat_amount,
    vat_formatted: props.pricing.cycle.vat_formatted,
    plan_total_formatted: props.pricing.cycle.formatted,
})

const billingLabel = computed(() => {
    if (props.billing === 'yearly') return t('Yearly')
    if (props.billing === 'lifetime') return t('Lifetime')

    return t('Monthly')
})

const featureList = computed(() => {
    const features = [...props.plan.features]

    if (Number(props.plan.credits) > 0) {
        features.push(t(':count credits', { count: Number(props.plan.credits).toLocaleString() }))
    }

    return features
})

const createCheckoutSession = () => {
    if (!selectedGateway.value) return

    submitting.value = true
    router.post('/checkout/session', {
        plan: props.plan.slug,
        billing: props.billing,
        gateway: selectedGateway.value.slug,
        coupon: coupon.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false
        },
    })
}

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''

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
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold uppercase tracking-widest text-primary-600">{{ t('Secure Checkout') }}</p>
                    <h1 class="mt-2 text-3xl font-black text-gray-900">{{ t('Complete your subscription') }}</h1>
                </div>
                <Link href="/pricing" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-600">
                    {{ t('Back to pricing') }}
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-6">
                        <div>
                            <h2 class="text-2xl font-black text-gray-900">{{ plan.name }}</h2>
                            <p v-if="plan.description" class="mt-1 text-sm font-medium text-gray-500">{{ plan.description }}</p>
                        </div>
                        <span class="rounded-full bg-primary-50 px-4 py-2 text-sm font-bold text-primary-700">{{ billingLabel }}</span>
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
                        <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
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

                <aside class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
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
                        <label class="block">
                            <span class="mb-1 block text-sm font-bold text-gray-700">{{ t('Coupon code') }}</span>
                            <div class="flex gap-2">
                                <input v-model="coupon" type="text" class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-primary-400 focus:ring-primary-100" :placeholder="t('Optional')" @input="couponPreview = null; couponError = ''" />
                                <button type="button" :disabled="applyingCoupon" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-bold text-white transition disabled:opacity-60" @click="applyCoupon">
                                    {{ applyingCoupon ? t('Applying...') : t('Apply') }}
                                </button>
                            </div>
                            <span v-if="couponPreview?.coupon" class="mt-1 block text-xs font-bold text-primary-600">{{ t('Applied') }}: {{ couponPreview.coupon.code }} (-{{ couponPreview.coupon.discount_formatted }})</span>
                            <span v-else-if="couponError" class="mt-1 block text-xs font-bold text-red-600">{{ couponError }}</span>
                            <span v-else class="mt-1 block text-xs font-medium text-gray-400">{{ t('Coupon is validated and applied server-side before payment.') }}</span>
                        </label>
                        <div v-if="summary.discount_amount > 0" class="flex justify-between gap-4 text-primary-600">
                            <span>{{ t('Coupon discount') }}</span>
                            <span class="text-right font-bold">-{{ summary.discount_formatted }}</span>
                        </div>
                        <div v-if="summary.vat_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('VAT') }} ({{ pricing.cycle.vat_percentage }}%)</span>
                            <span class="text-right font-bold text-gray-900">{{ summary.vat_formatted }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan total') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ summary.plan_total_formatted }}</span>
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

                    <p v-if="pricing.cycle.is_trial" class="mt-3 rounded-lg bg-primary-50 p-3 text-xs font-semibold text-primary-700">
                        {{ t(':days days trial starts now. Renewal uses the selected billing cycle.', { days: String(pricing.cycle.trial_days ?? 0) }) }}
                    </p>

                    <button type="button" :disabled="!selectedGateway || submitting" @click="createCheckoutSession" class="mt-6 w-full rounded-xl btn-primary shadow-lg shadow-primary-600/20 transition disabled:cursor-not-allowed disabled:bg-gray-300 disabled:shadow-none">
                        {{ submitting ? t('Creating session...') : selectedGateway ? t('Continue with :gateway', { gateway: selectedGateway.name }) : t('No gateway available') }}
                    </button>

                    <p class="mt-3 text-center text-xs font-medium text-gray-400">
                        {{ t('Payment session creation will verify plan, billing, country price, tax, and gateway server-side.') }}
                    </p>
                </aside>
            </div>
        </div>
    </Layout>
</template>
