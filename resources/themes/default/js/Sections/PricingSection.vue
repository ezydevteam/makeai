<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface PricingCycle { amount: number; subtotal_amount: number; list_amount: number; original_amount: number | null; formatted: string; subtotal_formatted: string; original_formatted: string | null; vat_percentage: number; vat_formatted: string; renewal_formatted: string; display_renewal_formatted?: string; display_amount?: number; display_formatted?: string; display_subtotal_formatted?: string; display_vat_formatted?: string; display_original_formatted?: string | null; uses_default: boolean; is_trial: boolean; trial_days: number | null }
interface PricingInfo { currency_code: string; display_currency_code?: string; is_localized?: boolean; source?: 'country' | 'default'; monthly: PricingCycle; yearly: PricingCycle; lifetime: PricingCycle }
interface PricingPlan { id: number; name: string; slug: string; description: string; bottom_info_text: string | null; credits: number | string; features: string[] | string; is_featured: boolean; is_free: boolean; pricing: PricingInfo }
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
const pricingIsLocalized = (plan: PricingPlan) => !!plan.pricing?.is_localized
const pricingDisplayPrice = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    if (plan.is_free && c.subtotal_amount === 0) return t('Free')
    // Same figure in both branches — this used to show the VAT-inclusive total when
    // localized against the ex-VAT subtotal otherwise, inflating the localized headline.
    if (pricingIsLocalized(plan) && c.display_subtotal_formatted) return c.display_subtotal_formatted
    return c.subtotal_formatted
}
const pricingShowOriginal = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    return !!c.original_formatted && Number(c.original_amount) > c.subtotal_amount
}
const pricingOriginalPrice = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    return pricingIsLocalized(plan) && c.display_original_formatted ? c.display_original_formatted : c.original_formatted
}
const pricingVatPrice = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    return pricingIsLocalized(plan) && c.display_vat_formatted ? c.display_vat_formatted : c.vat_formatted
}
// What the plan renews at after the trial — NOT `formatted`, which is zeroed for trials.
const pricingRenewalPrice = (plan: PricingPlan) => {
    const c = pricingActiveCycle(plan)
    return pricingIsLocalized(plan) && c.display_renewal_formatted ? c.display_renewal_formatted : c.renewal_formatted
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
// A cycle the admin never priced is not sold — checkout rejects it server-side, so
// render a disabled CTA rather than a link that leads nowhere. Uses the configured
// list price, so a free trial or a 100%-off coupon still gets a working button.
const pricingCycleUnavailable = (plan: PricingPlan): boolean =>
    !plan.is_free && Number(pricingActiveCycle(plan).list_amount ?? 0) <= 0
const pricingPlanCardClass = (plan: PricingPlan): string[] => {
    if (isDark.value) {
        const bgClasses = plan.is_featured
            ? 'border-primary-500/30 bg-surface-900/60 shadow-2xl shadow-primary-500/5 ring-1 ring-primary-500/20'
            : 'border-surface-800/60 bg-surface-900/40 hover:border-primary-500/30 hover:bg-surface-900/80'
        return [
            bgClasses,
            'relative flex flex-col rounded-[2rem] border p-8 transition-all duration-300 hover:-translate-y-1 pricing-card',
        ]
    }
    const bgClasses = plan.is_featured
        ? 'border-primary-300 bg-gradient-to-b from-primary-500/10 via-white to-white shadow-2xl shadow-primary-500/10 ring-1 ring-primary-100'
        : 'border-gray-100 bg-gradient-to-b from-primary-500/5 via-white to-white hover:border-primary-400'
    return [
        bgClasses,
        'relative flex flex-col rounded-[2rem] border p-8 transition-all duration-300 hover:-translate-y-1 pricing-card',
    ]
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})
const pricingPlanButtonClass = (plan: PricingPlan): string[] => [
    'inline-flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black leading-none transition-all duration-200 ease-out hover:-translate-y-0.5',
    plan.is_featured
        ? 'bg-gradient-to-r from-primary-600 to-primary-500 !text-white shadow-xl shadow-primary-600/20 hover:from-primary-500 hover:to-primary-600 hover:shadow-primary-600/25'
        : 'border border-gray-200 bg-white text-gray-800 hover:border-primary-500 hover:text-primary-600 hover:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-400 dark:hover:text-primary-400 dark:hover:bg-primary-500/10',
]
const pricingSectionPlans = (): PricingPlan[] => {
    const plans = [...(props.pricingPlans ?? [])]
    const source = asString(props.section.config.source, 'all')
    if (source === 'featured') return plans.filter((p) => p.is_featured)
    if (source === 'free') return plans.filter((p) => p.is_free)
    if (source === 'paid') return plans.filter((p) => !p.is_free)
    return plans
}

const tabRefs = ref<Record<number, HTMLElement>>({})
const activeTabWidth = ref(0)
const activeTabOffset = ref(0)

const activeIndex = computed(() => pricingBillingCycles.value.indexOf(pricingBilling.value))

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

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section Header Entrance
    const header = sectionRef.value!.querySelector('.mb-16')
    if (header) {
      gsap.from(header, {
        opacity: 0,
        y: 30,
        duration: 0.7,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        },
      })
    }

    // Pricing Cards — Staggered Entrance
    const cards = sectionRef.value!.querySelectorAll('.pricing-card')
    if (cards.length) {
      gsap.from(cards, {
        opacity: 0,
        y: 50,
        duration: 0.6,
        stagger: 0.12,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        }
      })
    }
  }, sectionRef.value!)

  setTimeout(() => {
    ScrollTrigger.refresh()
  }, 100)
})

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section
        :id="sectionAnchorId(section.config.section_anchor)" ref="sectionRef" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-16">
                    <!-- Top Position Icon -->
                    <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="asString(section.config.icon)"></i>
                    </div>
                    <div class="w-full">
                        <span v-if="asString(section.config.badge_text)" :class="badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ asString(section.config.badge_text) }}
                        </span>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.heading ?? section.config.title, t('Simple Pricing')) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="['font-medium mt-4 max-w-2xl', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
                    </div>
                </div>
                <div v-if="pricingBillingCycles.length > 1" :class="['mt-10 flex', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                    <div class="relative inline-flex items-center justify-center rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-surface-700 dark:bg-surface-800">
                        <!-- Sliding background pill -->
                        <div
                            class="absolute top-1 bottom-1 left-1 rounded-full bg-primary-600 transition-all duration-300 ease-out dark:bg-primary-500"
                            :style="slidingPillStyle"
                        ></div>
                        <button
                            v-for="(cycle, idx) in pricingBillingCycles"
                            :key="cycle"
                            :ref="(el) => { if (el) tabRefs[idx] = el as HTMLElement }"
                            type="button"
                            @click="pricingBilling = cycle"
                            :class="[
                                pricingBilling === cycle
                                    ? 'text-white'
                                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                                'relative z-10 rounded-full px-5 py-2 text-sm font-bold transition-colors duration-200'
                            ]"
                        >
                            {{ pricingBillingLabels[cycle] }}
                        </button>
                    </div>
                </div>
                <div v-else :class="['mt-10 flex', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                        <span class="rounded-full border border-primary-200 bg-primary-50 px-5 py-2 text-sm font-bold text-primary-700 dark:border-primary-900/50 dark:bg-primary-500/10 dark:text-primary-400">{{ pricingBillingLabels[pricingBillingCycles[0]] }}</span>
                    </div>
                <div v-if="pricingSectionPlans().length > 0" class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="plan in pricingSectionPlans()" :key="plan.id" :class="pricingPlanCardClass(plan)">
                        <div v-if="plan.is_featured" class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary-600 to-accent-600 px-5 py-1.5 text-[10px] font-black uppercase tracking-widest text-white shadow-lg">{{ ps.pricing_featured_label_text }}</div>
                        <h3 class="mb-1 text-xl font-black text-gray-900 dark:text-white">{{ plan.name }}</h3>
                        <p class="mb-6 text-sm font-medium leading-relaxed text-gray-500 dark:text-gray-400">{{ plan.description }}</p>
                        <div class="mb-6">
                            <div class="flex flex-wrap items-end gap-2">
                                <span v-if="pricingShowOriginal(plan)" class="mb-1 text-lg font-bold text-gray-400 dark:text-gray-500 line-through">{{ pricingOriginalPrice(plan) }}</span>
                                <span class="text-4xl font-black tracking-tight text-gray-900 dark:text-white">{{ pricingDisplayPrice(plan) }}</span>
                                <span v-if="pricingPriceSuffix(plan)" class="mb-1 text-sm font-bold text-gray-400 dark:text-gray-500">{{ pricingPriceSuffix(plan) }}</span>
                            </div>
                            <p v-if="pricingActiveCycle(plan).is_trial" class="mt-1 text-xs font-bold text-primary-600 dark:text-primary-400">{{ t(':days days trial, then renews at :price', { days: String(pricingActiveCycle(plan).trial_days ?? 0), price: pricingRenewalPrice(plan) }) }}</p>
                            <p v-else-if="pricingBilling === 'yearly' && pricingSavingsText(plan)" class="mt-1 text-xs font-bold text-success-600 dark:text-success-400">{{ pricingSavingsText(plan) }}</p>
                            <p v-else-if="pricingBilling === 'lifetime'" class="mt-1 text-xs font-bold text-success-600 dark:text-success-400">{{ t('One-time lifetime access') }}</p>
                            <p v-if="pricingBilling === 'lifetime' && pricingSavingsText(plan)" class="mt-1 text-xs font-bold text-success-600 dark:text-success-400">{{ pricingSavingsText(plan) }}</p>
                            <p v-if="pricingActiveCycle(plan).vat_percentage > 0" class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ t('Includes :percentage% VAT (:amount)', { percentage: String(pricingActiveCycle(plan).vat_percentage), amount: pricingVatPrice(plan) }) }}</p>
                            <p v-if="pricingActiveCycle(plan).uses_default === false" class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Country price') }}</p>
                            <p v-if="plan.pricing?.is_localized" class="mt-1 text-[11px] font-semibold text-gray-400 dark:text-gray-500">{{ t('Billed in :currency', { currency: plan.pricing.currency_code }) }}</p>
                        </div>
                        <ul class="mb-8 flex-1 space-y-3.5">
                            <li v-for="feature in pricingPlanFeatures(plan)" :key="feature" class="flex items-start gap-3 text-sm font-medium leading-tight text-gray-600 dark:text-gray-300">
                                <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 shadow-sm shadow-primary-500/20">
                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                </span>
                                {{ feature }}
                            </li>
                        </ul>
                        <span v-if="pricingCycleUnavailable(plan)" :class="[...pricingPlanButtonClass(plan), 'opacity-60 cursor-default pointer-events-none']">{{ t('Not available') }}</span>
                        <Link v-else :href="pricingPlanActionUrl(plan)" :class="pricingPlanButtonClass(plan)">{{ pricingActiveCycle(plan).is_trial ? ps.pricing_trial_button_text : ps.pricing_checkout_button_text }}</Link>
                        <p v-if="plan.bottom_info_text" class="mt-4 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">{{ plan.bottom_info_text }}</p>
                    </article>
                </div>
                <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No plans are available for this pricing section yet.') }}</div>
            </div>
        </div>
    </section>
</template>
