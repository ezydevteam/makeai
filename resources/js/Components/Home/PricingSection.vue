<script setup lang="ts">
import { computed, watch, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface PricingCycle { amount: number; subtotal_amount: number; original_amount: number | null; formatted: string; subtotal_formatted: string; original_formatted: string | null; vat_percentage: number; vat_formatted: string; uses_default: boolean; is_trial: boolean; trial_days: number | null }
interface PricingPlan { id: number; name: string; slug: string; description: string; bottom_info_text: string | null; credits: number | string; features: string[] | string; is_featured: boolean; is_free: boolean; pricing: Record<string, PricingCycle> }
interface PricingSettings { pricing_show_monthly: boolean; pricing_show_yearly: boolean; pricing_show_lifetime: boolean; pricing_currency_code: string; pricing_trial_button_text: string; pricing_featured_label_text: string; pricing_checkout_button_text: string }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }

const props = defineProps<{ section: HomepageSection; pricingPlans: PricingPlan[]; pricingSettings?: PricingSettings }>()
const { t } = useTranslate()
const { formatCurrency } = useNumberFormat()
const page = usePage()

const pricingBilling = ref<'monthly' | 'yearly' | 'lifetime'>('monthly')
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const ps = computed<PricingSettings>(() => ({
    pricing_show_monthly: props.pricingSettings?.pricing_show_monthly ?? true,
    pricing_show_yearly: props.pricingSettings?.pricing_show_yearly ?? true,
    pricing_show_lifetime: props.pricingSettings?.pricing_show_lifetime ?? true,
    pricing_currency_code: props.pricingSettings?.pricing_currency_code ?? 'USD',
    pricing_trial_button_text: props.pricingSettings?.pricing_trial_button_text ?? t('Start Trial'),
    pricing_featured_label_text: props.pricingSettings?.pricing_featured_label_text ?? t('Recommended'),
    pricing_checkout_button_text: props.pricingSettings?.pricing_checkout_button_text ?? t('Choose Plan'),
}))

const pricingBillingLabels: Record<string, string> = { monthly: t('Monthly'), yearly: t('Yearly'), lifetime: t('Lifetime') }
const pricingBillingCycles = computed<Array<'monthly' | 'yearly' | 'lifetime'>>(() => {
    const c: Array<'monthly' | 'yearly' | 'lifetime'> = []
    if (ps.value.pricing_show_monthly) c.push('monthly')
    if (ps.value.pricing_show_yearly) c.push('yearly')
    if (ps.value.pricing_show_lifetime) c.push('lifetime')
    return c.length > 0 ? c : ['monthly']
})

watch(pricingBillingCycles, (c) => { if (!c.includes(pricingBilling.value)) pricingBilling.value = c[0] ?? 'monthly' }, { immediate: true })

const pricingActiveCycle = (plan: PricingPlan) => plan.pricing[pricingBilling.value]
const pricingDisplayPrice = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    return plan.is_free && c.subtotal_amount === 0 ? t('Free') : c.subtotal_formatted
}
const pricingPriceSuffix = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    if (c.is_trial || (plan.is_free && c.subtotal_amount === 0)) return ''
    return pricingBilling.value === 'monthly' ? t('/month') : pricingBilling.value === 'yearly' ? t('/year') : ''
}
const pricingSavingsText = (plan: PricingPlan) => {
    const currency = ps.value.pricing_currency_code
    const monthly = plan.pricing.monthly?.subtotal_amount || 0
    const yearly = plan.pricing.yearly?.subtotal_amount || 0
    const lifetime = plan.pricing.lifetime?.subtotal_amount || 0
    if (pricingBilling.value === 'yearly' && monthly > 0 && yearly > 0) {
        const s = monthly * 12 - yearly; return s > 0 ? t('Save :amount', { amount: formatCurrency(s, currency) }) : ''
    }
    if (pricingBilling.value === 'lifetime' && lifetime > 0) {
        const o = plan.pricing.lifetime?.original_amount ?? 0
        if (o > lifetime) return t('Save :amount', { amount: formatCurrency(o - lifetime, currency) })
        const y = yearly > lifetime ? yearly - lifetime : 0
        const m = monthly > 0 && monthly * 12 > lifetime ? monthly * 12 - lifetime : 0
        const s = Math.max(y, m); return s > 0 ? t('Save :amount', { amount: formatCurrency(s, currency) }) : ''
    }
    return ''
}
const pricingPlanFeatures = (plan: PricingPlan): string[] => {
    const f = Array.isArray(plan.features) ? [...plan.features] : typeof plan.features === 'string' ? plan.features.split(/[\r\n,]+/).map((s) => s.trim()).filter(Boolean) : []
    if (Number(plan.credits) > 0) f.push(t(':count credits', { count: Number(plan.credits).toLocaleString() }))
    return f
}
const pricingPlanActionUrl = (plan: PricingPlan) => {
    const q = new URLSearchParams({ plan: plan.slug, billing: pricingBilling.value })
    const u = (page.props.auth as { user?: unknown } | undefined)?.user
    return `${u ? '/checkout' : '/register'}?${q.toString()}`
}
const pricingPlanCardClass = (plan: PricingPlan): string[] => [
    plan.is_featured ? 'border-primary-200 bg-white shadow-2xl shadow-primary-500/10 ring-1 ring-primary-100' : 'border-gray-100 bg-white hover:border-gray-200',
    'relative flex flex-col rounded-[2rem] border p-8 transition-all duration-300 hover:-translate-y-1',
]
const pricingPlanButtonClass = (plan: PricingPlan): string[] => [
    'inline-flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black leading-none transition-all duration-200 ease-out hover:-translate-y-0.5',
    plan.is_featured ? 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-xl shadow-primary-600/20 hover:from-primary-500 hover:to-primary-600 hover:shadow-primary-600/25' : 'bg-gray-100 text-gray-900 hover:bg-gray-200',
]
const pricingSectionPlans = (): PricingPlan[] => {
    const plans = [...(props.pricingPlans ?? [])]
    const source = asString(props.section.config.source, 'all')
    if (source === 'featured') return plans.filter((p) => p.is_featured)
    if (source === 'free') return plans.filter((p) => p.is_free)
    if (source === 'paid') return plans.filter((p) => !p.is_free)
    return plans
}
</script>

<template>
    <section class="bg-gray-50 py-24 transition-colors duration-300 dark:bg-surface-900">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-16 text-center">
                <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.heading ?? section.config.title, t('Simple Pricing')) }}</h2>
                <p v-if="asString(section.config.subheading ?? section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
                <div v-if="pricingBillingCycles.length > 1" class="mt-10 inline-flex items-center justify-center gap-1 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm">
                    <button v-for="cycle in pricingBillingCycles" :key="cycle" type="button" @click="pricingBilling = cycle" :class="pricingBilling === cycle ? 'btn-primary shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="rounded-xl px-5 py-2 text-sm font-bold transition">{{ pricingBillingLabels[cycle] }}</button>
                </div>
                <div v-else class="mt-10 flex justify-center">
                    <span class="rounded-full border border-primary-200 bg-primary-50 px-5 py-2 text-sm font-bold text-primary-700">{{ pricingBillingLabels[pricingBillingCycles[0]] }}</span>
                </div>
            </div>
            <div v-if="pricingSectionPlans().length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article v-for="plan in pricingSectionPlans()" :key="plan.id" :class="pricingPlanCardClass(plan)">
                    <div v-if="plan.is_featured" class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary-600 to-accent-600 px-5 py-1.5 text-[10px] font-black uppercase tracking-widest text-white shadow-lg">{{ ps.pricing_featured_label_text }}</div>
                    <h3 class="mb-1 text-xl font-black text-gray-900">{{ plan.name }}</h3>
                    <p class="mb-6 text-sm font-medium leading-relaxed text-gray-500">{{ plan.description }}</p>
                    <div class="mb-6">
                        <div class="flex flex-wrap items-end gap-2">
                            <span v-if="pricingActiveCycle(plan).original_formatted && Number(pricingActiveCycle(plan).original_amount) > pricingActiveCycle(plan).subtotal_amount" class="mb-1 text-lg font-bold text-gray-400 line-through">{{ pricingActiveCycle(plan).original_formatted }}</span>
                            <span class="text-4xl font-black tracking-tight text-gray-900">{{ pricingDisplayPrice(plan) }}</span>
                            <span v-if="pricingPriceSuffix(plan)" class="mb-1 text-sm font-bold text-gray-400">{{ pricingPriceSuffix(plan) }}</span>
                        </div>
                        <p v-if="pricingActiveCycle(plan).is_trial" class="mt-1 text-xs font-bold text-primary-600">{{ t(':days days trial, then renews at :price', { days: String(pricingActiveCycle(plan).trial_days ?? 0), price: pricingActiveCycle(plan).formatted }) }}</p>
                        <p v-else-if="pricingBilling === 'yearly' && pricingSavingsText(plan)" class="mt-1 text-xs font-bold text-success-600">{{ pricingSavingsText(plan) }}</p>
                        <p v-else-if="pricingBilling === 'lifetime'" class="mt-1 text-xs font-bold text-success-600">{{ t('One-time lifetime access') }}</p>
                        <p v-if="pricingBilling === 'lifetime' && pricingSavingsText(plan)" class="mt-1 text-xs font-bold text-success-600">{{ pricingSavingsText(plan) }}</p>
                        <p v-if="pricingActiveCycle(plan).vat_percentage > 0" class="mt-1 text-xs font-semibold text-gray-500">{{ t('Includes :percentage% VAT (:amount)', { percentage: String(pricingActiveCycle(plan).vat_percentage), amount: pricingActiveCycle(plan).vat_formatted }) }}</p>
                        <p v-if="pricingActiveCycle(plan).uses_default === false" class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-400">{{ t('Country price') }}</p>
                    </div>
                    <ul class="mb-8 flex-1 space-y-3.5">
                        <li v-for="feature in pricingPlanFeatures(plan)" :key="feature" class="flex items-start gap-3 text-sm font-medium leading-tight text-gray-600">
                            <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 shadow-sm shadow-primary-500/20">
                                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </span>
                            {{ feature }}
                        </li>
                    </ul>
                    <Link :href="pricingPlanActionUrl(plan)" :class="pricingPlanButtonClass(plan)">{{ pricingActiveCycle(plan).is_trial ? ps.pricing_trial_button_text : ps.pricing_checkout_button_text }}</Link>
                    <p v-if="plan.bottom_info_text" class="mt-4 text-center text-xs font-semibold text-gray-500">{{ plan.bottom_info_text }}</p>
                </article>
            </div>
            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No plans are available for this pricing section yet.') }}</div>
        </div>
    </section>
</template>
