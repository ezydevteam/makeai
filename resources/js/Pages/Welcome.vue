<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import Layout from '@/Layouts/AppLayout.vue'
import TemplateToolGrid from '@/Components/TemplateToolGrid.vue'
import AllToolsSection from '@/Components/AllToolsSection.vue'
import AdSection from '@/Components/AdSection.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'integrations' | 'custom_html' | 'template_grid' | 'all_tools' | 'richtext' | 'image_carousel' | 'ad_slot' | 'announcement'
type AdZone = 'header_banner' | 'sidebar_top' | 'sidebar_bottom' | 'content_top' | 'content_bottom' | 'content-injection' | 'between_posts' | 'between_ai_tools' | 'tool_page_top' | 'tool_page_bottom' | 'template_page' | 'chat_banner' | 'dashboard_top' | 'footer_banner' | 'custom_zone_1' | 'custom_zone_2'
type SectionItem = Record<string, string | number | boolean>
type SectionConfigValue = string | number | boolean | string[] | SectionItem[]
type SectionConfig = Record<string, SectionConfigValue>

interface Testimonial {
    id: number
    name: string
    role: string | null
    company: string | null
    avatar: string | null
    content: string
    rating: number
    is_featured: boolean
    source: string
}

interface Faq {
    id: number
    question: string
    answer: string
    category_id: number | null
    sort_order: number
    category?: { id: number; name: string; sort_order: number } | null
}

interface Announcement {
    id: number
    type: 'topbar' | 'popup' | 'notification'
    title: string | null
    content: string | null
    bg_color: string | null
    text_color: string | null
    cta_text: string | null
    cta_url: string | null
    image: string | null
    target_audience: string
    trigger_type: string | null
    trigger_value: string | null
    show_frequency: 'always' | 'session' | 'once'
}

interface ToolItem {
    slug: string
    name: string
    description: string
    icon: string | null
    color: string | null
    category: string | null
    tags?: Record<string, unknown> | string | null
    usage_count: number
    avg_rating: number | null
    is_featured: boolean
    created_at?: string
}

interface BlogPostPreview {
    title: string
    slug: string
    published_at: string | null
    image: string | null
    is_featured: boolean
}

interface PricingCycle {
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

interface PricingPlan {
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
        monthly: PricingCycle
        yearly: PricingCycle
        lifetime: PricingCycle
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

interface HomepageSection {
    id: string
    type: SectionType
    enabled: boolean
    core: boolean
    config: SectionConfig
}

interface HomepageSettings {
    seo: {
        meta_title: string
        meta_description: string
        og_image: string
    }
    scroll_to_top: {
        enabled: boolean
        position: 'left' | 'right'
        show_after_px: number
    }
    chat_widget_embed: string
}

interface HomepageConfig {
    sections: HomepageSection[]
    settings: HomepageSettings
}

const props = defineProps<{
    homepage: HomepageConfig | null
    templateData?: Record<string, any> | null
    testimonials: Testimonial[]
    faqs: Faq[]
    scrollToTopEnabled?: boolean
    allTools?: ToolItem[]
    allToolCategories?: string[]
    recentPosts?: BlogPostPreview[]
    pricingPlans?: PricingPlan[]
    pricingCountry?: string | null
    pricingSettings?: PricingSettings
}>()

const { t } = useTranslate()
const { formatCurrency } = useNumberFormat()
const page = usePage()
const appName = computed(() => String(page.props.branding?.site_name || t('Application')))
const allAnnouncements = computed(() => (page.props.announcements as Announcement[]) || [])
const recentBlogPosts = computed<BlogPostPreview[]>(() => (page.props.recentPosts as BlogPostPreview[]) || props.recentPosts || [])
const pricingBilling = ref<'monthly' | 'yearly' | 'lifetime'>('monthly')
const pricingSettings = computed<PricingSettings>(() => ({
    pricing_show_monthly: props.pricingSettings?.pricing_show_monthly ?? true,
    pricing_show_yearly: props.pricingSettings?.pricing_show_yearly ?? true,
    pricing_show_lifetime: props.pricingSettings?.pricing_show_lifetime ?? true,
    pricing_currency_code: props.pricingSettings?.pricing_currency_code ?? 'USD',
    pricing_trial_button_text: props.pricingSettings?.pricing_trial_button_text ?? t('Start Trial'),
    pricing_featured_label_text: props.pricingSettings?.pricing_featured_label_text ?? t('Recommended'),
    pricing_checkout_button_text: props.pricingSettings?.pricing_checkout_button_text ?? t('Choose Plan'),
}))

const defaultHomepage: HomepageConfig = {
    sections: [
        {
            id: 'hero',
            type: 'hero',
            enabled: true,
            core: true,
            config: {
                layout: 'center',
                headline: t('One Platform. Every AI Tool.'),
                subheadline: t('Unleash your creativity with the world’s most powerful AI models. From high-quality content to stunning images and precise code, :app has you covered.', { app: appName.value }),
                primary_cta_text: t('Get Started for Free'),
                primary_cta_link: '/register',
                primary_cta_style: 'primary_filled',
                primary_cta_icon: '',
                primary_cta_icon_position: 'left',
                secondary_cta_text: t('View Pricing'),
                secondary_cta_link: '/pricing',
                secondary_cta_style: 'outline',
                secondary_cta_icon: '',
                secondary_cta_icon_position: 'left',
                hero_background_type: 'image',
                hero_background_url: '',
                show_hero_gradient_overlay: true,
                show_stats_separator: true,
                hero_section_height: 'default',
                hero_vertical_padding: 48,
                hero_heading_size: 'lg',
                hero_heading_color: 'dark',
                hero_subheading_color: 'light',
                stats_number_color: 'dark',
                stats_label_color: 'light',
                trust_badge_text: t('Trusted by 50,000+ creators'),
                stats: [
                    { number: '50K+', label: t('Users Trusted') },
                    { number: '10M+', label: t('Assets Generated') },
                    { number: '99.9%', label: t('Uptime SLA') },
                    { number: '24/7', label: t('Expert Support') },
                ],
            },
        },
        {
            id: 'features',
            type: 'features',
            enabled: true,
            core: true,
            config: {
                title: t('Supercharge your workflow'),
                subtitle: t('Everything you need to build the future, powered by AI.'),
                layout: '3-column',
                feature_vertical_padding: 96,
                card_style: 'bordered',
                heading_color: 'dark',
                subheading_color: 'light',
                learn_more_text: t('Learn more'),
                items: [
                    { icon: 'ti ti-pencil', title: t('AI Writer'), description: t('Generate blogs, ads, and emails in seconds with our advanced copywriting models.'), image_url: '', link_url: '' },
                    { icon: 'ti ti-photo', title: t('AI Images'), description: t('Turn text into masterpiece. High-resolution images for any project or brand.'), image_url: '', link_url: '' },
                    { icon: 'ti ti-message-2', title: t('AI Chat'), description: t('Smart, contextual assistants ready to help you with research or customer support.'), image_url: '', link_url: '' },
                    { icon: 'ti ti-code', title: t('AI Code'), description: t('From debugging to writing entire functions. Code faster with AI companionship.'), image_url: '', link_url: '' },
                ],
                button_text: '',
                button_link: '',
                button_style: 'primary_filled',
                button_icon: '',
            },
        },
        {
            id: 'tools_showcase',
            type: 'tools_showcase',
            enabled: true,
            core: true,
            config: {
                title: t('AI Tools Showcase'),
                subtitle: t('Explore the tools buyers can launch immediately after signup.'),
                layout: '3-column',
                card_style: 'bordered',
                section_vertical_padding: 96,
                source: 'all',
                max_items: 6,
                heading_color: 'white',
                subheading_color: 'white',
                primary_text: t('View all tools'),
                primary_link: '/ai-tools',
                primary_icon: '',
                primary_style: 'primary_filled',
                background_style: 'gradient-1',
                background_image_url: '',
                width: 'contained',
            },
        },
        {
            id: 'testimonials',
            type: 'testimonials',
            enabled: true,
            core: true,
            config: {
                heading: t('What Our Users Say'),
                subheading: t('Show real customer feedback to build trust.'),
                icon: 'ti ti-message-2-heart',
                source: 'all',
                card_style: 'bordered',
                max_items: 6,
            },
        },
        {
            id: 'faq',
            type: 'faq',
            enabled: true,
            core: true,
            config: {
                heading: t('Frequently Asked Questions'),
                subheading: t('Answer common questions in a clean accordion layout.'),
                icon: 'ti ti-help-circle',
                max_items: 8,
            },
        },
        {
            id: 'stats_bar',
            type: 'stats_bar',
            enabled: true,
            core: true,
            config: {
                heading: t('Social Proof'),
                subheading: t('Show your best numbers and proof points in a clean row.'),
                icon: 'ti ti-chart-bar',
                show_stats_separator: true,
                stats_number_color: 'dark',
                stats_label_color: 'light',
                stats: [
                    { number: '50K+', label: t('Users Trusted') },
                    { number: '10M+', label: t('Assets Generated') },
                    { number: '99.9%', label: t('Uptime SLA') },
                ],
            },
        },
    ],
    settings: {
        seo: {
            meta_title: t(':app — The Ultimate AI Platform', { app: appName.value }),
            meta_description: t('Create content, images, chat responses, and code with one powerful AI platform.'),
            og_image: '',
        },
        scroll_to_top: {
            enabled: true,
            position: 'right',
            show_after_px: 500,
        },
        chat_widget_embed: '',
    },
}

const homepageConfig = computed<HomepageConfig>(() => props.homepage ?? defaultHomepage)
const enabledSections = computed(() => homepageConfig.value.sections.filter((section) => section.enabled))
const showScrollButton = ref(false)
const openFaqId = ref<number | null>(null)
const toggleFaq = (id: number) => { openFaqId.value = openFaqId.value === id ? null : id }

const stars = (n: number): boolean[] => Array.from({ length: 5 }, (_, i) => i < n)

// Limit testimonials shown by max_items config
const getTestimonialsSlice = (section: HomepageSection): Testimonial[] => {
    const max = parseInt(String(section.config.max_items ?? 6), 10)
    const source = asString(section.config.source, section.config.featured_only === true ? 'featured' : 'all')
    const list = source === 'featured' ? props.testimonials.filter(t => t.is_featured) : props.testimonials
    return list.slice(0, max)
}

const getFaqsSlice = (section: HomepageSection): Faq[] => {
    const max = parseInt(String(section.config.max_items ?? 10), 10)
    return props.faqs.slice(0, max)
}

const getAnnouncementSlice = (section: HomepageSection): Announcement[] => {
    const max = parseInt(String(section.config.max_items ?? 3), 10)
    const selectedType = asString(section.config.announcement_type, 'topbar')
    const filtered = selectedType === 'all'
        ? allAnnouncements.value
        : allAnnouncements.value.filter((announcement) => announcement.type === selectedType)

    return filtered.slice(0, max)
}

const resolveMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path

    return `/storage/${path}`
}

const asString = (value: SectionConfigValue | undefined, fallback = ''): string => typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
const asBoolean = (value: SectionConfigValue | undefined, fallback = false): boolean => typeof value === 'boolean' ? value : fallback
const asItems = (value: SectionConfigValue | undefined): SectionItem[] => Array.isArray(value) && value.every((item) => typeof item !== 'string') ? value : []
const replaceVariables = (value: string): string => value.replaceAll('{app_name}', appName.value)

const headingParts = (headline: string): [string, string] => {
    const parts = replaceVariables(headline).split('. ')
    return parts.length > 1 ? [`${parts[0]}.`, parts.slice(1).join('. ')] : [replaceVariables(headline), '']
}

const sectionTitle = (section: HomepageSection, fallback: string): string => asString(section.config.title, fallback)
const sectionSubtitle = (section: HomepageSection): string => asString(section.config.subtitle)
const pricingSectionHeading = (section: HomepageSection): string => asString(section.config.heading ?? section.config.title, t('Pricing'))
const pricingSectionSubheading = (section: HomepageSection): string => asString(section.config.subheading ?? section.config.subtitle)
const pricingSectionIcon = (section: HomepageSection): string => asString(section.config.icon)
const pricingSectionSource = (section: HomepageSection): string => asString(section.config.source, 'all')
const faqSectionIcon = (section: HomepageSection): string => asString(section.config.icon, 'ti ti-help-circle')
const statsSectionHeading = (section: HomepageSection): string => asString(section.config.heading ?? section.config.title, t('Social Proof'))
const statsSectionSubheading = (section: HomepageSection): string => asString(section.config.subheading ?? section.config.subtitle)
const statsSectionIcon = (section: HomepageSection): string => asString(section.config.icon, 'ti ti-chart-bar')
const pricingBillingLabels = {
    monthly: t('Monthly'),
    yearly: t('Yearly'),
    lifetime: t('Lifetime'),
}
const pricingBillingCycles = computed(() => {
    const cycles: Array<'monthly' | 'yearly' | 'lifetime'> = []
    if (pricingSettings.value.pricing_show_monthly) cycles.push('monthly')
    if (pricingSettings.value.pricing_show_yearly) cycles.push('yearly')
    if (pricingSettings.value.pricing_show_lifetime) cycles.push('lifetime')
    return cycles.length > 0 ? cycles : (['monthly'] as Array<'monthly' | 'yearly' | 'lifetime'>)
})
const pricingActiveCycle = (plan: PricingPlan) => plan.pricing[pricingBilling.value]
const pricingDisplayPrice = (plan: PricingPlan) => {
    const cycle = pricingActiveCycle(plan)
    if (plan.is_free && cycle.subtotal_amount === 0) return t('Free')
    return cycle.subtotal_formatted
}
const pricingPriceSuffix = (plan: PricingPlan) => {
    const cycle = pricingActiveCycle(plan)
    if (cycle.is_trial || (plan.is_free && cycle.subtotal_amount === 0)) return ''
    if (pricingBilling.value === 'monthly') return t('/month')
    if (pricingBilling.value === 'yearly') return t('/year')
    return ''
}
const pricingSavingsText = (plan: PricingPlan) => {
    const currency = plan.pricing.currency_code
    const monthly = plan.pricing.monthly.subtotal_amount
    const yearly = plan.pricing.yearly.subtotal_amount
    const lifetime = plan.pricing.lifetime.subtotal_amount
    if (pricingBilling.value === 'yearly' && monthly > 0 && yearly > 0) {
        const savings = monthly * 12 - yearly
        return savings > 0 ? t('Save :amount', { amount: formatCurrency(savings, currency) }) : ''
    }
    if (pricingBilling.value === 'lifetime' && lifetime > 0) {
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
const pricingPlanFeatures = (plan: PricingPlan) => {
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
const pricingPlanActionUrl = (plan: PricingPlan) => {
    const query = new URLSearchParams({
        plan: plan.slug,
        billing: pricingBilling.value,
    })
    const authUser = (page.props.auth as { user?: unknown } | undefined)?.user
    return `${authUser ? '/checkout' : '/register'}?${query.toString()}`
}
const pricingPlanCardClass = (plan: PricingPlan): string[] => [
    plan.is_featured
        ? 'border-primary-200 bg-white shadow-2xl shadow-primary-500/10 ring-1 ring-primary-100'
        : 'border-gray-100 bg-white hover:border-gray-200',
    'relative flex flex-col rounded-[2rem] border p-8 transition-all duration-300 hover:-translate-y-1',
]
const pricingPlanButtonClass = (plan: PricingPlan): string[] => [
    'inline-flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black leading-none transition-all duration-200 ease-out hover:-translate-y-0.5',
    plan.is_featured
        ? 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-xl shadow-primary-600/20 hover:from-primary-500 hover:to-primary-600 hover:shadow-primary-600/25'
        : 'bg-gray-100 text-gray-900 hover:bg-gray-200',
]
const pricingSectionPlans = (section: HomepageSection): PricingPlan[] => {
    const plans = [...(props.pricingPlans ?? [])]
    const source = pricingSectionSource(section)

    if (source === 'featured') {
        return plans.filter((plan) => plan.is_featured)
    }

    if (source === 'free') {
        return plans.filter((plan) => plan.is_free)
    }

    if (source === 'paid') {
        return plans.filter((plan) => !plan.is_free)
    }

    return plans
}
watch(pricingBillingCycles, (cycles) => {
    if (!cycles.includes(pricingBilling.value)) {
        pricingBilling.value = cycles[0] ?? 'monthly'
    }
}, { immediate: true })
const heroAlignmentClass = (layout: string): string => {
    if (layout === 'left') return 'text-left items-start justify-start'
    if (layout === 'right') return 'text-right items-end justify-end'
    return 'text-center items-center justify-center'
}
const heroHeadingSizeClass = (size: string): string => ({
    sm: 'text-3xl md:text-5xl lg:text-6xl',
    md: 'text-4xl md:text-6xl lg:text-7xl',
    lg: 'text-5xl md:text-7xl lg:text-8xl',
    xl: 'text-6xl md:text-8xl lg:text-[5.5rem]',
}[size] ?? 'text-5xl md:text-7xl lg:text-8xl')
const heroSectionHeightClass = (height: string): string => ({
    default: '',
    compact: 'min-h-[540px]',
    comfortable: 'min-h-[640px]',
    tall: 'min-h-[760px]',
    full: 'min-h-screen',
}[height] ?? '')
const featureGridClass = (layout: string): string => ({
    '2-column': 'lg:grid-cols-2',
    '3-column': 'lg:grid-cols-3',
    '4-column': 'lg:grid-cols-4',
}[layout] ?? 'lg:grid-cols-3')
const featureCardClass = (style: string): string => ({
    simple: 'relative h-full overflow-hidden rounded-[1.5rem] border border-transparent bg-white/80 shadow-[0_10px_28px_rgba(15,23,42,0.05)] backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-[0_18px_45px_rgba(31,117,254,0.08)] dark:bg-surface-800/70 dark:hover:bg-surface-800',
    bordered: 'relative h-full overflow-hidden rounded-[2rem] border-2 border-slate-200/90 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-800',
    image_focus: 'relative h-full overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.14)] dark:border-surface-700/70 dark:bg-surface-800',
}[style] ?? 'relative h-full overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700/70 dark:bg-surface-800')
const featureCardMediaClass = (style: string): string => ({
    simple: 'mx-auto mb-8 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-none dark:bg-primary-500/10 dark:text-primary-300',
    bordered: 'mx-auto mb-8 flex h-16 w-16 items-center justify-center rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white text-primary-600 shadow-[0_10px_24px_rgba(31,117,254,0.14)] dark:border-primary-500/20 dark:from-primary-500/15 dark:to-surface-800 dark:text-primary-300',
    image_focus: 'w-full h-56 rounded-none mb-0 flex items-center justify-center',
}[style] ?? 'mx-auto mb-8 flex h-16 w-16 items-center justify-center rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white text-primary-600 shadow-[0_10px_24px_rgba(31,117,254,0.14)] dark:border-primary-500/20 dark:from-primary-500/15 dark:to-surface-800 dark:text-primary-300')
const featureCardBodyClass = (style: string): string => ({
    simple: 'relative z-10 px-7 pb-7 pt-2',
    bordered: 'relative z-10 p-8',
    image_focus: 'relative z-10 p-8',
}[style] ?? 'relative z-10 p-8')
const featureCardImageClass = (style: string): string => ({
    simple: 'w-full h-32 object-cover mb-8',
    bordered: 'w-full h-32 object-cover mb-8',
    image_focus: 'w-full h-56 object-cover',
}[style] ?? 'w-full h-32 object-cover mb-8')
const howItWorksSectionHeading = (section: HomepageSection): string => asString(section.config.heading ?? section.config.title, t('How It Works'))
const howItWorksSectionSubheading = (section: HomepageSection): string => asString(section.config.subheading ?? section.config.subtitle)
const howItWorksSectionIcon = (section: HomepageSection): string => asString(section.config.icon, 'ti ti-route')
const howItWorksSectionLayout = (section: HomepageSection): string => asString(section.config.step_layout, 'cards')
const howItWorksSectionCardStyle = (section: HomepageSection): string => asString(section.config.step_card_style, 'bordered')
const howItWorksSectionPaddingStyle = (section: HomepageSection): Record<string, string> => {
    const verticalPadding = Number(section.config.section_vertical_padding ?? 96)
    return {
        paddingTop: `${verticalPadding}px`,
        paddingBottom: `${verticalPadding}px`,
    }
}
const howItWorksSteps = (section: HomepageSection): SectionItem[] => asItems(section.config.items).slice(0, Number(asString(section.config.max_items, '6')))
const howItWorksStepCardClass = (style: string): string => ({
    simple: 'rounded-[1.5rem] border border-transparent bg-white/80 p-6 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-[0_18px_45px_rgba(31,117,254,0.08)] dark:bg-surface-800/70 dark:hover:bg-surface-800',
    bordered: 'rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
}[style] ?? 'rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900')
const howItWorksStepIndexClass = (style: string): string => ({
    simple: 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300',
    bordered: 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/20',
}[style] ?? 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/20')
const toolsShowcasePaddingStyle = (section: HomepageSection): Record<string, string> => {
    const verticalPadding = Number(section.config.section_vertical_padding ?? 96)
    return {
        paddingTop: `${verticalPadding}px`,
        paddingBottom: `${verticalPadding}px`,
    }
}
const toolsShowcaseGridClass = (layout: string): string => ({
    '2-column': 'grid-cols-1 md:grid-cols-2',
    '3-column': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
    '4-column': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
}[layout] ?? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3')
const toolsShowcaseCardClass = (style: string): string => ({
    simple: 'group relative flex h-full flex-col overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white/90 p-6 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_22px_60px_rgba(31,117,254,0.10)] dark:border-surface-700 dark:bg-surface-900/80',
    bordered: 'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
    image_focus: 'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
}[style] ?? 'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900')
const toolsShowcaseCardAccentClass = (style: string): string => ({
    simple: 'h-16 w-16 rounded-2xl',
    bordered: 'h-16 w-16 rounded-2xl',
    image_focus: 'h-24 w-full rounded-none',
}[style] ?? 'h-16 w-16 rounded-2xl')
const toolsShowcaseCardBodyClass = (style: string): string => ({
    simple: 'flex flex-1 flex-col gap-4',
    bordered: 'flex flex-1 flex-col gap-4',
    image_focus: 'flex flex-1 flex-col gap-4 px-6 pb-6 pt-5',
}[style] ?? 'flex flex-1 flex-col gap-4')
const toolsShowcaseWidthClass = (width: string): string => ctaBannerWidthClass(width)
const toolsShowcaseSurfaceClass = (style: string): string => ctaBannerSurfaceClass(style)
const toolsShowcaseImageOverlayClass = (style: string): string => ctaBannerImageOverlayClass(style)
const toolsShowcaseIsLightSurface = (style: string): boolean => ctaBannerIsLightSurface(style)
const toolsShowcaseButtonStyle = (section: HomepageSection): string => asString(section.config.primary_style, 'primary_filled')
const toolsShowcaseButtonText = (section: HomepageSection): string => asString(section.config.primary_text, '')
const toolsShowcaseButtonLink = (section: HomepageSection): string => asString(section.config.primary_link, '/ai-tools')
const toolsShowcaseButtonIcon = (section: HomepageSection): string => asString(section.config.primary_icon, '')
const testimonialsCardClass = (style: string): string => ({
    simple: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white/90 p-7 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_20px_50px_rgba(31,117,254,0.10)] dark:border-surface-700 dark:bg-surface-900/80',
    bordered: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-8 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_70px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
    spotlight: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.75rem] border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-sky-50 p-8 shadow-[0_18px_50px_rgba(16,185,129,0.10)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(16,185,129,0.16)] dark:border-primary-900/40 dark:from-primary-950/30 dark:via-surface-900 dark:to-surface-900',
}[style] ?? 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-8 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_70px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900')
const sectionAnchorId = (section: HomepageSection): string | undefined => {
    const anchor = asString(section.config.section_anchor).trim()
    return anchor ? anchor : undefined
}
const sectionCustomClass = (section: HomepageSection): string => asString(section.config.custom_class).trim()
const sectionStyleClass = (section: HomepageSection, style: string): string => {
    const commonStyles: Record<string, string> = {
        default: '',
        left_border: 'relative overflow-hidden before:pointer-events-none before:absolute before:inset-y-6 before:left-0 before:w-1 before:rounded-full before:bg-gradient-to-b before:from-primary-500 before:via-secondary-500 before:to-violet-500',
        bottom_accent: 'relative overflow-hidden after:pointer-events-none after:absolute after:inset-x-6 after:bottom-0 after:h-1 after:rounded-full after:bg-gradient-to-r after:from-primary-500 after:via-secondary-500 after:to-violet-500 after:shadow-[0_0_24px_rgba(16,185,129,0.24)]',
        right_border: 'relative overflow-hidden before:pointer-events-none before:absolute before:inset-y-6 before:right-0 before:w-1 before:rounded-full before:bg-gradient-to-b before:from-secondary-500 before:via-primary-500 before:to-violet-500',
        bottom_border: 'relative overflow-hidden border-b border-primary-200/80 pb-4 dark:border-primary-800/70',
    }

    const sectionSpecificStyles: Record<HomepageSection['type'], Record<string, string>> = {
        hero: {
            default: '',
            left_border: 'relative overflow-hidden border-l-4 border-primary-500/60 pl-6 md:pl-10',
            bottom_accent: 'relative overflow-hidden after:pointer-events-none after:absolute after:inset-x-10 after:bottom-0 after:h-1 after:rounded-full after:bg-gradient-to-r after:from-primary-400 after:via-secondary-500 after:to-violet-500',
            right_border: 'relative overflow-hidden border-r-4 border-secondary-500/60 pr-6 md:pr-10',
            bottom_border: 'relative overflow-hidden border-b border-white/20 pb-4',
        },
        features: commonStyles,
        stats_bar: commonStyles,
        cta_banner: {
            default: '',
            left_border: 'relative overflow-hidden before:pointer-events-none before:absolute before:inset-y-8 before:left-6 before:w-px before:bg-gradient-to-b before:from-primary-500 before:to-secondary-500',
            bottom_accent: 'relative overflow-hidden after:pointer-events-none after:absolute after:inset-x-8 after:bottom-0 after:h-px after:bg-gradient-to-r after:from-primary-500 after:via-secondary-500 after:to-violet-500',
            right_border: 'relative overflow-hidden before:pointer-events-none before:absolute before:inset-y-8 before:right-6 before:w-px before:bg-gradient-to-b before:from-secondary-500 before:to-primary-500',
            bottom_border: 'relative overflow-hidden border-b border-primary-100/80 dark:border-primary-900/60',
        },
        testimonials: commonStyles,
        faq: commonStyles,
        tools_showcase: {
            default: '',
            left_border: 'relative overflow-hidden before:pointer-events-none before:absolute before:inset-y-6 before:left-0 before:w-1 before:rounded-full before:bg-gradient-to-b before:from-primary-500 before:to-secondary-500',
            bottom_accent: 'relative overflow-hidden after:pointer-events-none after:absolute after:inset-x-6 after:bottom-0 after:h-1 after:rounded-full after:bg-gradient-to-r after:from-primary-500 after:via-secondary-500 after:to-violet-500',
            right_border: 'relative overflow-hidden before:pointer-events-none before:absolute before:inset-y-6 before:right-0 before:w-1 before:rounded-full before:bg-gradient-to-b before:from-secondary-500 before:to-primary-500',
            bottom_border: 'relative overflow-hidden border-b border-primary-100/80 dark:border-primary-900/60',
        },
        how_it_works: commonStyles,
        pricing: commonStyles,
        latest_posts: commonStyles,
        newsletter: commonStyles,
        integrations: commonStyles,
        custom_html: commonStyles,
        richtext: commonStyles,
        image_carousel: commonStyles,
        ad_slot: commonStyles,
        announcement: commonStyles,
        template_grid: commonStyles,
        all_tools: commonStyles,
    }

    return sectionSpecificStyles[section.type]?.[style] ?? commonStyles[style] ?? ''
}
const sectionAnimationClass = (animation: string): string => ({
    none: '',
    'fade-up': 'homepage-animate-fade-up',
    'fade-in': 'homepage-animate-fade-in',
    'slide-up': 'homepage-animate-slide-up',
}[animation] ?? '')
const sectionOverlayStyle = (section: HomepageSection, fallbackOpacity = 0.45): Record<string, string> => {
    const opacityValue = section.config.overlay_opacity ?? Math.round(fallbackOpacity * 100)
    const opacity = Math.max(0, Math.min(100, Number(opacityValue || 0))) / 100
    return { opacity: String(opacity) }
}
const sectionMediaLoading = (section: HomepageSection): 'eager' | 'lazy' => asBoolean(section.config.lazy_load_media, false) ? 'lazy' : 'eager'
const sectionMediaPreload = (section: HomepageSection): 'auto' | 'metadata' | 'none' => asBoolean(section.config.lazy_load_media, false) ? 'none' : 'metadata'
const sectionVisibilityClass = (visibility: string): string => ({
    all: '',
    desktop: 'hidden lg:block',
    tablet: 'hidden md:block lg:hidden',
    mobile: 'block md:hidden',
    desktop_tablet: 'hidden md:block',
    tablet_mobile: 'block lg:hidden',
}[visibility] ?? '')
const toolsShowcaseItems = (section: HomepageSection): ToolItem[] => {
    const maxItems = Number(section.config.max_items ?? 6)
    const source = asString(section.config.source, 'all')
    const tools = [...(props.allTools ?? [])]

    const filtered = source === 'featured'
        ? tools.filter((tool) => tool.is_featured)
        : source === 'popular'
            ? tools.sort((a, b) => (b.usage_count ?? 0) - (a.usage_count ?? 0))
            : source === 'recent'
                ? tools.sort((a, b) => {
                    const da = a.created_at ? Date.parse(a.created_at) : 0
                    const db = b.created_at ? Date.parse(b.created_at) : 0
                    return db - da
                })
                : tools

    return filtered.slice(0, maxItems)
}
const toolsShowcaseCardLink = (tool: ToolItem): string => `/ai-tools/${tool.slug}`
const toolsShowcaseFormatCount = (value: number | undefined): string => {
    if (!value) return '0'
    if (value >= 1000000) return `${(value / 1000000).toFixed(1)}M`
    if (value >= 1000) return `${(value / 1000).toFixed(1)}K`
    return String(value)
}
const ctaBannerWidthClass = (width: string): string => ({
    contained: 'max-w-6xl',
    wide: 'max-w-7xl',
    full: 'max-w-none',
}[width] ?? 'max-w-6xl')
const ctaBannerSurfaceClass = (style: string): string => ({
    'gradient-1': 'bg-gradient-to-r from-primary-600 to-violet-600 text-white shadow-2xl shadow-primary-600/20',
    'gradient-2': 'bg-gradient-to-r from-secondary-600 to-primary-600 text-white shadow-2xl shadow-secondary-600/20',
    'gradient-3': 'bg-gradient-to-br from-primary-700 via-sky-600 to-violet-700 text-white shadow-2xl shadow-violet-700/20',
    primary_light: 'bg-primary-50 text-gray-900 border border-primary-100 shadow-xl shadow-primary-500/10 dark:bg-primary-900/20 dark:border-primary-800 dark:text-white',
    green_light: 'bg-green-50 text-gray-900 border border-green-100 shadow-xl shadow-green-500/10 dark:bg-green-900/20 dark:border-green-800 dark:text-white',
    white: 'bg-white text-gray-900 border border-gray-100 shadow-xl shadow-gray-900/5 dark:bg-surface-900 dark:border-surface-700 dark:text-white',
    light: 'bg-gray-50 text-gray-900 border border-gray-100 shadow-xl shadow-gray-900/5 dark:bg-surface-800 dark:border-surface-700 dark:text-white',
    dark: 'bg-surface-950 text-white border border-surface-800 shadow-2xl shadow-surface-950/30',
}[style] ?? 'bg-gradient-to-r from-primary-600 to-violet-600 text-white shadow-2xl shadow-primary-600/20')
const ctaBannerImageOverlayClass = (style: string): string => ({
    'gradient-1': 'bg-slate-950/45',
    'gradient-2': 'bg-slate-950/45',
    'gradient-3': 'bg-slate-950/50',
    primary_light: 'bg-primary-500/20',
    green_light: 'bg-green-500/20',
    white: 'bg-white/65',
    light: 'bg-white/55',
    dark: 'bg-slate-950/60',
}[style] ?? 'bg-slate-950/45')
const ctaBannerButtonClass = (style: string, variant: 'primary' | 'secondary'): string => {
    const isDarkSurface = ['gradient-1', 'gradient-2', 'gradient-3', 'dark'].includes(style)

    if (variant === 'primary') {
        return isDarkSurface
            ? 'bg-white text-gray-900 hover:bg-gray-100'
            : 'bg-primary-600 text-white hover:bg-primary-700'
    }

    return isDarkSurface
        ? 'border-2 border-white/40 text-white hover:bg-white/10'
        : 'border-2 border-primary-200 text-primary-700 hover:bg-primary-50 dark:border-primary-500/30 dark:text-primary-300 dark:hover:bg-primary-900/20'
}
const ctaBannerButtonStyle = (section: HomepageSection, variant: 'primary' | 'secondary'): string => {
    if (variant === 'primary') {
        return asString(section.config.primary_style ?? section.config.primary_cta_style, 'primary_filled')
    }

    return asString(section.config.secondary_style ?? section.config.secondary_cta_style, 'outline')
}
const ctaBannerIsLightSurface = (style: string): boolean => ['primary_light', 'green_light', 'white', 'light'].includes(style)
const ctaBannerButtonText = (section: HomepageSection, variant: 'primary' | 'secondary'): string => {
    if (variant === 'primary') {
        return asString(section.config.primary_text ?? section.config.primary_cta_text)
    }

    return asString(section.config.secondary_text ?? section.config.secondary_cta_text)
}
const ctaBannerButtonLink = (section: HomepageSection, variant: 'primary' | 'secondary'): string => {
    if (variant === 'primary') {
        return asString(section.config.primary_link ?? section.config.primary_cta_link, '/register')
    }

    return asString(section.config.secondary_link ?? section.config.secondary_cta_link, '/pricing')
}
const ctaBannerButtonIcon = (section: HomepageSection, variant: 'primary' | 'secondary'): string => {
    if (variant === 'primary') {
        return asString(section.config.primary_icon ?? section.config.primary_cta_icon)
    }

    return asString(section.config.secondary_icon ?? section.config.secondary_cta_icon)
}
const ctaBannerCanDisplay = (section: HomepageSection): boolean => {
    const access = asString(section.config.access, 'everyone')

    if (access === 'everyone') {
        return true
    }

    const authUser = (page.props.auth as { user?: unknown } | undefined)?.user

    if (access === 'logged_in') {
        return Boolean(authUser)
    }

    return Boolean(authUser) && Boolean((page.props as { isProAvailable?: boolean } | undefined)?.isProAvailable)
}
const latestPostsSource = (section: HomepageSection): string => asString(section.config.source, 'recent')
const latestPostsLayout = (section: HomepageSection): string => asString(section.config.layout, 'grid')
const latestPostsCardStyle = (section: HomepageSection): string => asString(section.config.card_style, 'bordered')
const latestPostsButtonText = (section: HomepageSection): string => asString(section.config.button_text, '')
const latestPostsButtonLink = (section: HomepageSection): string => asString(section.config.button_link, '/blog')
const latestPostsButtonIcon = (section: HomepageSection): string => asString(section.config.button_icon, '')
const latestPostsButtonStyle = (section: HomepageSection): string => asString(section.config.button_style, 'outline')
const latestPostsItems = (section: HomepageSection): BlogPostPreview[] => {
    const posts = [...recentBlogPosts.value]
    const source = latestPostsSource(section)

    const filtered = source === 'featured'
        ? posts.filter((post) => post.is_featured)
        : posts

    return filtered.slice(0, Number(asString(section.config.max_items, '3')))
}
const isExternalUrl = (value: string): boolean => /^https?:\/\//i.test(value)
const heroColorClass = (color: string, tone: 'heading' | 'subheading' = 'heading'): string => {
    const headingMap: Record<string, string> = {
        primary: 'text-primary-600 dark:text-primary-400',
        dark: 'text-gray-900 dark:text-white',
        green: 'text-success-600 dark:text-success-400',
        purple: 'text-violet-600 dark:text-violet-400',
        white: 'text-white',
        light: 'text-gray-100',
        red: 'text-red-600 dark:text-red-400',
        yellow: 'text-amber-400 dark:text-amber-300',
        gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-accent-600 bg-clip-text text-transparent dark:from-primary-400 dark:via-violet-400 dark:to-accent-400',
    }
    const subheadingMap: Record<string, string> = {
        primary: 'text-primary-500 dark:text-primary-300',
        dark: 'text-gray-700 dark:text-gray-200',
        green: 'text-success-500 dark:text-success-300',
        purple: 'text-violet-500 dark:text-violet-300',
        white: 'text-white/90',
        light: 'text-gray-500 dark:text-gray-300',
        red: 'text-red-500 dark:text-red-300',
        yellow: 'text-amber-500 dark:text-amber-200',
    }

    return tone === 'heading' ? (headingMap[color] ?? headingMap.dark) : (subheadingMap[color] ?? subheadingMap.light)
}
const heroButtonClass = (style: string): string => ({
    primary_filled: 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    dark: 'bg-gray-900 text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800',
    purple: 'bg-violet-600 text-white shadow-2xl shadow-violet-600/20 hover:bg-violet-700',
    gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
    red: 'bg-red-600 text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    green: 'bg-success-600 text-white shadow-2xl shadow-success-600/20 hover:bg-success-700',
    outline: 'border-2 border-white/40 bg-transparent text-white hover:bg-white/10 dark:border-white/30 dark:bg-transparent dark:text-white dark:hover:bg-white/10',
    white: 'bg-white text-gray-900 shadow-xl hover:bg-gray-100',
    light: 'bg-white text-gray-900 shadow-xl hover:bg-gray-100',
}[style] ?? 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')

const latestPostsSectionCardClass = (style: string): string => ({
    simple: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800',
    bordered: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800',
    image_focus: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800',
}[style] ?? 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800')
const newsletterLayout = (section: HomepageSection): string => asString(section.config.layout, 'inline')
const newsletterHeading = (section: HomepageSection): string => sectionTitle(section, t('Stay in the loop'))
const newsletterSubheading = (section: HomepageSection): string => sectionSubtitle(section)
const newsletterPlaceholder = (section: HomepageSection): string => asString(section.config.placeholder_text, t('Enter your email'))
const newsletterButtonText = (section: HomepageSection): string => asString(section.config.button_text, t('Subscribe'))
const newsletterButtonIcon = (section: HomepageSection): string => asString(section.config.button_icon, '')
const newsletterButtonStyle = (section: HomepageSection): string => asString(section.config.button_style, 'primary_filled')
const newsletterPrivacyText = (section: HomepageSection): string => asString(section.config.privacy_text, t('We respect your inbox. Unsubscribe at any time.'))
const newsletterAction = (section: HomepageSection): string => asString(section.config.button_link, '/newsletter/subscribe')
const integrationsLayout = (section: HomepageSection): string => asString(section.config.layout, 'grid')
const integrationsItems = (section: HomepageSection): SectionItem[] => asItems(section.config.items).slice(0, Number(asString(section.config.max_items, '6')))

const onScroll = () => {
    showScrollButton.value = window.scrollY >= homepageConfig.value.settings.scroll_to_top.show_after_px
}

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()
})

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll)
})
</script>

<template>
    <Head :title="homepageConfig.settings.seo.meta_title || appName + ' — ' + t('The Ultimate AI Platform')">
        <meta v-if="homepageConfig.settings.seo.meta_description" name="description" :content="homepageConfig.settings.seo.meta_description">
        <meta v-if="homepageConfig.settings.seo.og_image" property="og:image" :content="homepageConfig.settings.seo.og_image">
    </Head>

    <Layout>
        <template v-for="section in enabledSections" :key="section.id">
            <section
                v-if="section.type === 'hero'"
                :id="sectionAnchorId(section)"
                :style="{ '--hero-section-padding': `${Number(asString(section.config.hero_vertical_padding, 48))}px` }"
                :class="[heroSectionHeightClass(asString(section.config.hero_section_height, 'default')), sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]"
                class="relative isolate overflow-hidden bg-white py-[var(--hero-section-padding)] dark:bg-surface-950 transition-colors duration-300"
            >
                <div v-if="asString(section.config.hero_background_url)" class="absolute inset-0 z-0">
                    <img
                        v-if="asString(section.config.hero_background_type, 'image') === 'image'"
                        :src="resolveMediaUrl(asString(section.config.hero_background_url))"
                        alt=""
                        :loading="sectionMediaLoading(section)"
                        class="h-full w-full object-cover"
                    >
                    <video
                        v-else
                        :src="resolveMediaUrl(asString(section.config.hero_background_url))"
                        :preload="sectionMediaPreload(section)"
                        class="h-full w-full object-cover"
                        autoplay
                        muted
                        loop
                        playsinline
                    ></video>
                </div>
                <div v-if="asBoolean(section.config.show_hero_gradient_overlay, true)" :style="sectionOverlayStyle(section, 0.55)" class="absolute inset-0 z-10 bg-gradient-to-b from-black/70 via-black/45 to-black/25"></div>
                <div class="relative z-20 max-w-7xl mx-auto px-6">
                    <div :class="heroAlignmentClass(asString(section.config.layout, 'center'))" class="flex flex-col">
                    <div>
                        <div v-if="asString(section.config.trust_badge_text)" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 border border-primary-100 text-primary-600 text-xs font-black uppercase tracking-widest mb-10 shadow-sm">
                            <span class="w-2 h-2 bg-primary-500 rounded-full animate-pulse"></span>
                            {{ asString(section.config.trust_badge_text, t('Trusted by 50,000+ creators')) }}
                        </div>
                        <h1 :class="[heroHeadingSizeClass(asString(section.config.hero_heading_size, 'lg')), heroColorClass(asString(section.config.hero_heading_color, 'dark'))]" class="font-black leading-[1.1] tracking-tight mb-8">
                            {{ headingParts(asString(section.config.headline, 'One Platform. Every AI Tool.'))[0] }}<br>
                            <span v-if="headingParts(asString(section.config.headline, 'One Platform. Every AI Tool.'))[1]">{{ headingParts(asString(section.config.headline, 'One Platform. Every AI Tool.'))[1] }}</span>
                        </h1>
                        <p :class="[heroColorClass(asString(section.config.hero_subheading_color, 'light'), 'subheading'), asString(section.config.layout, 'center') === 'center' ? 'mx-auto' : '', asString(section.config.layout, 'center') === 'right' ? 'ml-auto' : '']" class="text-lg md:text-xl max-w-3xl mb-12 leading-relaxed font-medium">
                            {{ asString(section.config.subheadline) }}
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4" :class="heroAlignmentClass(asString(section.config.layout, 'center'))">
                            <Link v-if="asString(section.config.primary_cta_text)" :href="asString(section.config.primary_cta_link, '/register')" :class="heroButtonClass(asString(section.config.primary_cta_style, 'primary_filled'))" class="inline-flex w-full items-center justify-center gap-3 px-10 py-5 text-lg font-black transition-all hover:-translate-y-1 sm:w-auto rounded-2xl">
                                <i v-if="asString(section.config.primary_cta_icon) && asString(section.config.primary_cta_icon_position, 'left') === 'left'" :class="[asString(section.config.primary_cta_icon), 'block text-lg leading-none shrink-0']"></i>
                                {{ asString(section.config.primary_cta_text) }}
                                <i v-if="asString(section.config.primary_cta_icon) && asString(section.config.primary_cta_icon_position, 'left') === 'right'" :class="[asString(section.config.primary_cta_icon), 'block text-lg leading-none shrink-0']"></i>
                            </Link>
                            <Link v-if="asString(section.config.secondary_cta_text)" :href="asString(section.config.secondary_cta_link, '/pricing')" :class="heroButtonClass(asString(section.config.secondary_cta_style, 'outline'))" class="inline-flex w-full items-center justify-center gap-3 px-10 py-5 text-lg font-black transition-all sm:w-auto rounded-2xl">
                                <i v-if="asString(section.config.secondary_cta_icon) && asString(section.config.secondary_cta_icon_position, 'left') === 'left'" :class="[asString(section.config.secondary_cta_icon), 'block text-lg leading-none shrink-0']"></i>
                                {{ asString(section.config.secondary_cta_text) }}
                                <i v-if="asString(section.config.secondary_cta_icon) && asString(section.config.secondary_cta_icon_position, 'left') === 'right'" :class="[asString(section.config.secondary_cta_icon), 'block text-lg leading-none shrink-0']"></i>
                            </Link>
                        </div>
                        <div v-if="asItems(section.config.stats).length > 0" :class="asBoolean(section.config.show_stats_separator, true) ? 'mt-24 pt-12 border-t border-gray-100 dark:border-surface-800' : 'mt-24 pt-12'" class="grid grid-cols-2 md:grid-cols-4 gap-8">
                            <div v-for="stat in asItems(section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                                <p :class="heroColorClass(asString(section.config.stats_number_color, 'dark'))" class="text-3xl font-black">{{ stat.number }}</p>
                                <p :class="heroColorClass(asString(section.config.stats_label_color, 'light'), 'subheading')" class="text-xs font-black uppercase tracking-widest mt-1">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="absolute top-0 left-1/2 z-0 -translate-x-1/2 w-full h-full pointer-events-none opacity-50">
                    <div class="absolute top-0 left-0 w-[800px] h-[800px] bg-primary-100/50 rounded-full blur-[120px] -translate-x-1/2 -translate-y-1/2"></div>
                    <div class="absolute bottom-0 right-0 w-[600px] h-[600px] bg-accent-100/50 rounded-full blur-[120px] translate-x-1/3 translate-y-1/3"></div>
                </div>
            </section>

            <section
                v-else-if="section.type === 'features'"
                :id="sectionAnchorId(section)"
                :style="{ '--feature-section-padding': `${Number(asString(section.config.feature_vertical_padding, 96))}px` }"
                class="py-[var(--feature-section-padding)] bg-gray-50 dark:bg-surface-900 transition-colors duration-300"
                :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]"
            >
                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center mb-20">
                        <h2 :class="heroColorClass(asString(section.config.heading_color, 'dark'))" class="text-3xl md:text-5xl font-black mb-4">{{ sectionTitle(section, t('Supercharge your workflow')) }}</h2>
                        <p v-if="sectionSubtitle(section)" :class="heroColorClass(asString(section.config.subheading_color, 'light'), 'subheading')" class="font-medium">{{ sectionSubtitle(section) }}</p>
                    </div>
                    <div :class="[featureGridClass(asString(section.config.layout, '3-column'))]" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <component
                            v-for="item in asItems(section.config.items)"
                            :is="String(item.link_url) ? (isExternalUrl(String(item.link_url)) ? 'a' : Link) : 'div'"
                            :key="`${item.title}_${item.icon}`"
                            :href="String(item.link_url || '') || undefined"
                            :target="String(item.link_url) && (isExternalUrl(String(item.link_url)) || item.link_open_new_tab) ? '_blank' : undefined"
                            :rel="String(item.link_url) && (isExternalUrl(String(item.link_url)) || item.link_open_new_tab) ? 'noopener noreferrer' : undefined"
                            :class="[featureCardClass(asString(section.config.card_style, 'bordered')), 'group block h-full text-left']"
                        >
                            <div v-if="asString(section.config.card_style, 'bordered') !== 'image_focus'" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-violet-500 to-secondary-500 opacity-70"></div>
                            <div class="absolute -right-14 -top-14 h-32 w-32 rounded-full bg-primary-500/10 blur-3xl transition-transform duration-300 group-hover:scale-125"></div>
                            <img v-if="item.image_url" :src="resolveMediaUrl(String(item.image_url))" alt="" :loading="sectionMediaLoading(section)" :class="featureCardImageClass(asString(section.config.card_style, 'bordered'))">
                            <div :class="featureCardBodyClass(asString(section.config.card_style, 'bordered'))">
                                <div v-if="!item.image_url" :class="[featureCardMediaClass(asString(section.config.card_style, 'bordered')), 'mx-0 mb-6 group-hover:scale-105 transition-transform duration-300']">
                                    <i :class="String(item.icon || 'ti ti-sparkles')" class="block text-2xl leading-none shrink-0"></i>
                                </div>
                                <h3 class="text-[1.15rem] font-black tracking-tight text-gray-900 dark:text-white mb-3">{{ item.title }}</h3>
                                <p class="text-gray-500 dark:text-gray-400 text-sm leading-7 font-medium">{{ item.description }}</p>
                                <div v-if="String(item.link_url) && asString(section.config.learn_more_text)" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 transition group-hover:gap-3 dark:text-primary-400">
                                    {{ asString(section.config.learn_more_text, t('Learn more')) }}
                                    <i class="ti ti-arrow-right text-base leading-none"></i>
                                </div>
                            </div>
                        </component>
                    </div>
                    <div v-if="asString(section.config.button_text) && asString(section.config.button_link)" class="text-center mt-12">
                        <Link :href="asString(section.config.button_link)" :class="heroButtonClass(asString(section.config.button_style, 'primary_filled'))" class="inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl font-black transition-colors">
                            <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'block text-lg leading-none shrink-0']"></i>
                            {{ asString(section.config.button_text) }}
                        </Link>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'stats_bar'" :id="sectionAnchorId(section)" class="py-16 bg-white dark:bg-surface-950 border-y border-gray-100 dark:border-surface-800" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="mb-12 text-center">
                        <div v-if="statsSectionIcon(section)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[statsSectionIcon(section), 'text-2xl']"></i>
                        </div>
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ statsSectionHeading(section) }}</h2>
                        <p v-if="statsSectionSubheading(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">{{ statsSectionSubheading(section) }}</p>
                    </div>
                    <div :class="asBoolean(section.config.show_stats_separator, true) ? 'pt-12 border-t border-gray-100 dark:border-surface-800' : 'pt-12'" class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                        <div v-for="stat in asItems(section.config.stats)" :key="`${stat.number}_${stat.label}`">
                            <p :class="heroColorClass(asString(section.config.stats_number_color, 'dark'))" class="text-4xl font-black">{{ stat.number }}</p>
                            <p :class="heroColorClass(asString(section.config.stats_label_color, 'light'), 'subheading')" class="text-xs font-black uppercase tracking-widest mt-2">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'cta_banner' && ctaBannerCanDisplay(section)" :id="sectionAnchorId(section)" class="py-24 bg-white dark:bg-surface-950" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div :class="ctaBannerWidthClass(asString(section.config.width, 'contained'))" class="mx-auto px-6">
                    <div :class="ctaBannerSurfaceClass(asString(section.config.background_style, 'gradient-1'))" class="relative isolate overflow-hidden rounded-[2.5rem] p-10 md:p-16 text-center">
                        <div v-if="asString(section.config.background_image_url)" class="absolute inset-0 z-0 overflow-hidden">
                            <img :src="resolveMediaUrl(asString(section.config.background_image_url))" alt="" :loading="sectionMediaLoading(section)" class="h-full w-full object-cover">
                            <div :class="ctaBannerImageOverlayClass(asString(section.config.background_style, 'gradient-1'))" :style="sectionOverlayStyle(section, 0.45)" class="absolute inset-0"></div>
                        </div>
                        <div class="relative z-10">
                            <h2 class="text-3xl md:text-5xl font-black mb-4">{{ asString(section.config.headline, sectionTitle(section, t('Ready to build with AI?'))) }}</h2>
                            <p v-if="asString(section.config.subheadline)" class="mx-auto mb-8 max-w-2xl" :class="ctaBannerIsLightSurface(asString(section.config.background_style, 'gradient-1')) ? 'text-gray-700 dark:text-gray-200' : 'text-white/80'">
                                {{ asString(section.config.subheadline) }}
                            </p>
                            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                                <Link v-if="ctaBannerButtonText(section, 'primary')" :href="ctaBannerButtonLink(section, 'primary')" :class="[heroButtonClass(ctaBannerButtonStyle(section, 'primary'))]" class="w-full sm:w-auto px-8 py-4 rounded-2xl font-black transition-colors">
                                    <span class="inline-flex items-center justify-center gap-3">
                                        <i v-if="ctaBannerButtonIcon(section, 'primary')" :class="[ctaBannerButtonIcon(section, 'primary'), 'block text-lg leading-none shrink-0']"></i>
                                        {{ ctaBannerButtonText(section, 'primary') }}
                                    </span>
                                </Link>
                                <Link v-if="ctaBannerButtonText(section, 'secondary')" :href="ctaBannerButtonLink(section, 'secondary')" :class="[heroButtonClass(ctaBannerButtonStyle(section, 'secondary'))]" class="w-full sm:w-auto px-8 py-4 rounded-2xl font-black transition-colors">
                                    <span class="inline-flex items-center justify-center gap-3">
                                        <i v-if="ctaBannerButtonIcon(section, 'secondary')" :class="[ctaBannerButtonIcon(section, 'secondary'), 'block text-lg leading-none shrink-0']"></i>
                                        {{ ctaBannerButtonText(section, 'secondary') }}
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ Testimonials ═══ -->
            <section v-else-if="section.type === 'testimonials'" :id="sectionAnchorId(section)" class="py-24 bg-gray-50 dark:bg-surface-900 transition-colors duration-300" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center mb-16">
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, t('What Our Users Say')) }}</h2>
                        <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">{{ sectionSubtitle(section) }}</p>
                    </div>
                    <!-- Live DB testimonials -->
                    <div v-if="getTestimonialsSlice(section).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="t in getTestimonialsSlice(section)"
                            :key="t.id"
                            :class="testimonialsCardClass(asString(section.config.card_style, 'bordered'))"
                        >
                            <div v-if="asString(section.config.card_style, 'bordered') !== 'simple'" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-violet-500"></div>
                            <div v-if="asString(section.config.card_style, 'spotlight') === 'spotlight'" class="absolute -right-12 -top-12 h-28 w-28 rounded-full bg-primary-500/10 blur-3xl"></div>
                            <!-- Stars -->
                            <div class="relative z-10 flex items-center gap-0.5">
                                <svg v-for="(filled, i) in stars(t.rating)" :key="i" class="h-4 w-4" :class="filled ? 'text-yellow-400' : 'text-gray-200 dark:text-surface-700'" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                            <!-- Quote -->
                            <p class="relative z-10 flex-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">&ldquo;{{ t.content }}&rdquo;</p>
                            <!-- Author -->
                            <div class="relative z-10 flex items-center gap-3 border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div v-if="t.avatar" class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-700">
                                    <img :src="resolveMediaUrl(t.avatar)" :alt="t.name" class="h-full w-full object-cover">
                                </div>
                                <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-black text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                                    {{ t.name.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ t.name }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ [t.role, t.company].filter(Boolean).join(' · ') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Empty state (no DB entries yet) -->
                    <div v-else class="text-center py-16 text-gray-400 dark:text-gray-600">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <p class="font-medium">{{ t('No testimonials yet. Add some from the admin panel.') }}</p>
                    </div>
                </div>
            </section>

            <!-- ═══ FAQ ═══ -->
            <section v-else-if="section.type === 'faq'" :id="sectionAnchorId(section)" class="py-24 bg-white dark:bg-surface-950 transition-colors duration-300" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-3xl mx-auto px-6">
                    <div class="text-center mb-16">
                        <div v-if="faqSectionIcon(section)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[faqSectionIcon(section), 'text-2xl']"></i>
                        </div>
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, t('Frequently Asked Questions')) }}</h2>
                        <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">{{ sectionSubtitle(section) }}</p>
                    </div>
                    <!-- Live DB FAQs -->
                    <div v-if="getFaqsSlice(section).length > 0" class="space-y-3">
                        <div
                            v-for="faq in getFaqsSlice(section)"
                            :key="faq.id"
                            class="bg-gray-50 dark:bg-surface-900 border border-gray-100 dark:border-surface-800 rounded-2xl overflow-hidden transition-all"
                        >
                            <button
                                @click="toggleFaq(faq.id)"
                                type="button"
                                class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left"
                            >
                                <span class="font-black text-gray-900 dark:text-white text-sm md:text-base">{{ faq.question }}</span>
                                <svg
                                    :class="openFaqId === faq.id ? 'rotate-180' : ''"
                                    class="w-5 h-5 text-gray-400 shrink-0 transition-transform duration-200"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </button>
                            <div v-show="openFaqId === faq.id" class="px-6 pb-5">
                                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed" v-html="faq.answer"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Empty state -->
                    <div v-else class="text-center py-16 text-gray-400 dark:text-gray-600">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="font-medium">{{ t('No FAQs yet. Add some from the admin panel.') }}</p>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'tools_showcase'" :id="sectionAnchorId(section)" class="overflow-hidden bg-white dark:bg-surface-950 transition-colors duration-300" :style="toolsShowcasePaddingStyle(section)" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div :class="toolsShowcaseWidthClass(asString(section.config.width, 'contained'))" class="mx-auto px-6">
                    <div :class="toolsShowcaseSurfaceClass(asString(section.config.background_style, 'gradient-1'))" class="relative isolate overflow-hidden rounded-[2.5rem] p-10 md:p-16">
                        <div v-if="asString(section.config.background_image_url)" class="absolute inset-0 z-0 overflow-hidden">
                            <img :src="resolveMediaUrl(asString(section.config.background_image_url))" alt="" :loading="sectionMediaLoading(section)" class="h-full w-full object-cover">
                            <div :class="toolsShowcaseImageOverlayClass(asString(section.config.background_style, 'gradient-1'))" :style="sectionOverlayStyle(section, 0.45)" class="absolute inset-0"></div>
                        </div>

                        <div class="relative z-10">
                            <div class="mb-12 text-center">
                                <h2 class="mb-4 text-3xl font-black md:text-5xl" :class="heroColorClass(asString(section.config.heading_color, toolsShowcaseIsLightSurface(asString(section.config.background_style, 'gradient-1')) ? 'dark' : 'white'))">
                                    {{ sectionTitle(section, t('AI Tools Showcase')) }}
                                </h2>
                                <p v-if="sectionSubtitle(section)" class="mx-auto max-w-2xl font-medium" :class="heroColorClass(asString(section.config.subheading_color, toolsShowcaseIsLightSurface(asString(section.config.background_style, 'gradient-1')) ? 'dark' : 'white'), 'subheading')">
                                    {{ sectionSubtitle(section) }}
                                </p>
                            </div>

                            <div v-if="toolsShowcaseItems(section).length > 0" class="grid gap-5" :class="toolsShowcaseGridClass(String(section.config.layout ?? '3-column'))">
                                <Link
                                    v-for="tool in toolsShowcaseItems(section)"
                                    :key="tool.slug"
                                    :href="toolsShowcaseCardLink(tool)"
                                    class="group"
                                    :class="toolsShowcaseCardClass(String(section.config.card_style ?? 'bordered'))"
                                >
                                    <div
                                        v-if="String(section.config.card_style ?? 'bordered') === 'image_focus'"
                                        :class="toolsShowcaseCardAccentClass(String(section.config.card_style ?? 'bordered'))"
                                        class="flex shrink-0 items-center justify-center overflow-hidden"
                                        :style="tool.color ? { background: `linear-gradient(135deg, ${tool.color}, color-mix(in srgb, ${tool.color} 55%, #000 45%))` } : {}"
                                    >
                                        <i v-if="tool.icon" :class="[tool.icon, 'text-2xl text-white']"></i>
                                        <span v-else class="text-xl font-black text-white">{{ tool.name.charAt(0) }}</span>
                                    </div>

                                    <div :class="toolsShowcaseCardBodyClass(String(section.config.card_style ?? 'bordered'))">
                                        <div class="flex items-start gap-3">
                                            <div v-if="String(section.config.card_style ?? 'bordered') !== 'image_focus'" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg" :style="tool.color ? { background: tool.color } : { background: 'var(--color-primary-500)' }">
                                                <i v-if="tool.icon" :class="[tool.icon, 'text-lg']"></i>
                                                <span v-else class="text-sm font-black">{{ tool.name.charAt(0) }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ tool.name }}</h3>
                                                    <span v-if="tool.is_featured" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                                        {{ t('Featured') }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ tool.description }}</p>
                                            </div>
                                        </div>

                                        <div class="mt-auto flex items-center justify-between gap-4 border-t border-gray-100 pt-4 dark:border-surface-700">
                                            <span v-if="tool.avg_rating" class="inline-flex items-center gap-1 text-xs font-medium text-gray-400">
                                                <i class="ti ti-star-filled text-amber-400 text-xs"></i>
                                                {{ Number(tool.avg_rating).toFixed(1) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400">
                                                <i class="ti ti-users text-xs"></i>
                                                {{ toolsShowcaseFormatCount(tool.usage_count) }}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </div>

                            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-gray-50 p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400">
                                {{ t('No tools are available for this showcase yet.') }}
                            </div>

                            <div v-if="toolsShowcaseButtonText(section) && toolsShowcaseButtonLink(section)" class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                                <Link
                                    :href="toolsShowcaseButtonLink(section)"
                                    :class="heroButtonClass(toolsShowcaseButtonStyle(section))"
                                    class="inline-flex w-full items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors sm:w-auto"
                                >
                                    <i v-if="toolsShowcaseButtonIcon(section)" :class="[toolsShowcaseButtonIcon(section), 'block text-lg leading-none shrink-0']"></i>
                                    {{ toolsShowcaseButtonText(section) }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'pricing'" :id="sectionAnchorId(section)" class="py-24 bg-gray-50 dark:bg-surface-900 transition-colors duration-300" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="mb-16 text-center">
                        <div v-if="pricingSectionIcon(section)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[pricingSectionIcon(section), 'text-2xl']"></i>
                        </div>
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ pricingSectionHeading(section) }}</h2>
                        <p v-if="pricingSectionSubheading(section)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ pricingSectionSubheading(section) }}</p>

                        <div v-if="pricingBillingCycles.length > 1" class="inline-flex items-center justify-center gap-1 mt-10 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm">
                            <button
                                v-for="cycle in pricingBillingCycles"
                                :key="cycle"
                                type="button"
                                @click="pricingBilling = cycle"
                                :class="pricingBilling === cycle ? 'btn-primary shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
                                class="rounded-xl px-5 py-2 text-sm font-bold transition"
                            >
                                {{ pricingBillingLabels[cycle] }}
                            </button>
                        </div>
                        <div v-else class="mt-10 flex justify-center">
                            <span class="rounded-full border border-primary-200 bg-primary-50 px-5 py-2 text-sm font-bold text-primary-700">
                                {{ pricingBillingLabels[pricingBillingCycles[0]] }}
                            </span>
                        </div>
                    </div>

                    <div v-if="pricingSectionPlans(section).length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <article
                            v-for="plan in pricingSectionPlans(section)"
                            :key="plan.id"
                            :class="pricingPlanCardClass(plan)"
                        >
                            <div v-if="plan.is_featured" class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary-600 to-accent-600 px-5 py-1.5 text-[10px] font-black uppercase tracking-widest text-white shadow-lg">
                                {{ t('Recommended') }}
                            </div>

                            <h3 class="mb-1 text-xl font-black text-gray-900">{{ plan.name }}</h3>
                            <p class="mb-6 text-sm font-medium leading-relaxed text-gray-500">{{ plan.description }}</p>

                            <div class="mb-6">
                                <div class="flex flex-wrap items-end gap-2">
                                    <span v-if="pricingActiveCycle(plan).original_formatted && Number(pricingActiveCycle(plan).original_amount) > pricingActiveCycle(plan).subtotal_amount" class="mb-1 text-lg font-bold text-gray-400 line-through">
                                        {{ pricingActiveCycle(plan).original_formatted }}
                                    </span>
                                    <span class="text-4xl font-black tracking-tight text-gray-900">
                                        {{ pricingDisplayPrice(plan) }}
                                    </span>
                                    <span v-if="pricingPriceSuffix(plan)" class="mb-1 text-sm font-bold text-gray-400">
                                        {{ pricingPriceSuffix(plan) }}
                                    </span>
                                </div>
                                <p v-if="pricingActiveCycle(plan).is_trial" class="text-xs text-primary-600 font-bold mt-1">
                                    {{ t(':days days trial, then renews at :price', { days: String(pricingActiveCycle(plan).trial_days ?? 0), price: pricingActiveCycle(plan).formatted }) }}
                                </p>
                                <p v-else-if="pricingBilling === 'yearly' && pricingSavingsText(plan)" class="text-xs font-bold mt-1 text-success-600">{{ pricingSavingsText(plan) }}</p>
                                <p v-else-if="pricingBilling === 'lifetime'" class="text-xs font-bold mt-1 text-success-600">{{ t('One-time lifetime access') }}</p>
                                <p v-if="pricingBilling === 'lifetime' && pricingSavingsText(plan)" class="text-xs font-bold mt-1 text-success-600">{{ pricingSavingsText(plan) }}</p>
                                <p v-if="pricingActiveCycle(plan).vat_percentage > 0" class="text-xs text-gray-500 font-semibold mt-1">
                                    {{ t('Includes :percentage% VAT (:amount)', { percentage: String(pricingActiveCycle(plan).vat_percentage), amount: pricingActiveCycle(plan).vat_formatted }) }}
                                </p>
                                <p v-if="pricingActiveCycle(plan).source === 'country'" class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-2">{{ t('Country price') }}</p>
                            </div>

                            <ul class="mb-8 flex-1 space-y-3.5">
                                <li v-for="feature in pricingPlanFeatures(plan)" :key="feature" class="flex items-start gap-3 text-sm font-medium leading-tight text-gray-600">
                                    <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 shadow-sm shadow-primary-500/20">
                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </span>
                                    {{ feature }}
                                </li>
                            </ul>

                            <Link :href="pricingPlanActionUrl(plan)" :class="pricingPlanButtonClass(plan)">
                                {{ pricingActiveCycle(plan).is_trial ? pricingSettings.pricing_trial_button_text : pricingSettings.pricing_checkout_button_text }}
                            </Link>

                            <p v-if="plan.bottom_info_text" class="mt-4 text-center text-xs font-semibold text-gray-500">
                                {{ plan.bottom_info_text }}
                            </p>
                        </article>
                    </div>

                    <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">
                        {{ t('No plans are available for this pricing section yet.') }}
                    </div>
                </div>
            </section>

            <!-- ═══ Other sections (newsletter, etc.) ═══ -->
            <section v-else-if="section.type === 'how_it_works'" :id="sectionAnchorId(section)" class="bg-gray-50 dark:bg-surface-900" :style="howItWorksSectionPaddingStyle(section)" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="mb-12 text-center">
                        <div v-if="howItWorksSectionIcon(section)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[howItWorksSectionIcon(section), 'text-2xl']"></i>
                        </div>
                        <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">
                            {{ howItWorksSectionHeading(section) }}
                        </h2>
                        <p v-if="howItWorksSectionSubheading(section)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">
                            {{ howItWorksSectionSubheading(section) }}
                        </p>
                    </div>

                    <div v-if="howItWorksSteps(section).length > 0" :class="howItWorksSectionLayout(section) === 'timeline' ? 'mx-auto max-w-5xl' : ''">
                        <div v-if="howItWorksSectionLayout(section) === 'timeline'" class="relative space-y-6 md:space-y-8">
                            <div class="absolute bottom-0 start-5 top-5 hidden w-px bg-gradient-to-b from-primary-300 via-primary-200 to-transparent md:block"></div>
                            <article
                                v-for="(item, index) in howItWorksSteps(section)"
                                :key="`${item.title}_${index}`"
                                class="relative flex gap-5 rounded-[2rem] border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-800"
                            >
                                <div class="relative z-10 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-sm font-black text-white shadow-lg shadow-primary-500/20">
                                    {{ String(index + 1).padStart(2, '0') }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="mb-3 flex items-center gap-3">
                                        <span v-if="item.icon" class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                                            <i :class="String(item.icon)"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary-500 dark:text-primary-300">
                                                {{ t('Step :count', { count: String(index + 1).padStart(2, '0') }) }}
                                            </p>
                                            <h3 class="mt-1 text-xl font-black text-gray-900 dark:text-white">{{ item.title || item.label || item.name }}</h3>
                                        </div>
                                    </div>
                                    <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description || item.text || item.number }}</p>
                                </div>
                            </article>
                        </div>
                        <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="(item, index) in howItWorksSteps(section)"
                                :key="`${item.title}_${index}`"
                                :class="howItWorksStepCardClass(howItWorksSectionCardStyle(section))"
                            >
                                <div class="mb-5 flex items-center justify-between gap-3">
                                    <span :class="['inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black', howItWorksStepIndexClass(howItWorksSectionCardStyle(section))]">
                                        {{ String(index + 1).padStart(2, '0') }}
                                    </span>
                                    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary-500 dark:text-primary-300">
                                        {{ t('Step :count', { count: String(index + 1).padStart(2, '0') }) }}
                                    </p>
                                    <span v-if="item.icon" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                                        <i :class="String(item.icon)"></i>
                                    </span>
                                </div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white">{{ item.title || item.label || item.name }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description || item.text || item.number }}</p>
                            </article>
                        </div>
                    </div>

                    <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">
                        {{ t('No steps have been added to this section yet.') }}
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'latest_posts'" :id="sectionAnchorId(section)" class="py-24 bg-gray-50 dark:bg-surface-900" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="mb-12 text-center">
                        <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                        </div>
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, t('Homepage Section')) }}</h2>
                        <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">
                            {{ sectionSubtitle(section) }}
                        </p>
                    </div>

                    <div v-if="latestPostsItems(section).length" :class="latestPostsLayout(section) === 'list' ? 'space-y-5' : 'grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3'">
                        <Link
                            v-for="post in latestPostsItems(section)"
                            :key="post.slug"
                            :href="route('blog.show', post.slug)"
                            :class="[latestPostsSectionCardClass(latestPostsCardStyle(section)), latestPostsLayout(section) === 'list' ? 'group flex overflow-hidden rounded-[1.5rem]' : 'group flex h-full flex-col']"
                        >
                            <div
                                :class="latestPostsLayout(section) === 'list' ? 'w-44 shrink-0' : 'aspect-[16/9]'"
                                class="overflow-hidden bg-gray-100 dark:bg-surface-800"
                            >
                                <img v-if="post.image" :src="post.image" :alt="post.title" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 to-secondary-100 text-primary-300 dark:from-primary-900/30 dark:to-surface-800">
                                    <i class="ti ti-article text-4xl"></i>
                                </div>
                            </div>
                            <div :class="latestPostsLayout(section) === 'list' ? 'flex-1 p-5 text-left' : 'flex flex-1 flex-col p-6 text-left'">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span v-if="post.is_featured" class="rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-bold text-violet-700">{{ t('Featured') }}</span>
                                    <span class="text-xs text-gray-400">{{ formatDate(post.published_at) }}</span>
                                </div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white">{{ post.title }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400 line-clamp-3">{{ t('Read the full post for more details.') }}</p>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">
                        {{ t('No blog posts available yet.') }}
                    </div>

                    <div class="mt-10 text-center" v-if="latestPostsButtonText(section)">
                        <Link :href="latestPostsButtonLink(section)" :class="heroButtonClass(latestPostsButtonStyle(section))" class="inline-flex items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors">
                            <i v-if="latestPostsButtonIcon(section)" :class="[latestPostsButtonIcon(section), 'text-lg']"></i>
                            {{ latestPostsButtonText(section) }}
                        </Link>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'newsletter'" :id="sectionAnchorId(section)" class="py-24 bg-gray-50 dark:bg-surface-900" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="mb-12 text-center">
                        <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                        </div>
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ newsletterHeading(section) }}</h2>
                        <p v-if="newsletterSubheading(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">
                            {{ newsletterSubheading(section) }}
                        </p>
                    </div>

                    <div :class="newsletterLayout(section) === 'stacked' ? 'mx-auto max-w-xl' : 'mx-auto max-w-4xl'">
                        <form
                            method="post"
                            :action="newsletterAction(section)"
                            :class="newsletterLayout(section) === 'stacked' ? 'space-y-4' : 'flex flex-col gap-3 sm:flex-row sm:items-center'"
                            class="mt-8 rounded-[2rem] border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                        >
                            <input
                                name="email"
                                type="email"
                                required
                                :placeholder="newsletterPlaceholder(section)"
                                class="flex-1 rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-gray-900 focus:border-primary-400 focus:ring-primary-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                            <button type="submit" :class="heroButtonClass(newsletterButtonStyle(section))" class="inline-flex items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors">
                                <i v-if="newsletterButtonIcon(section)" :class="[newsletterButtonIcon(section), 'text-lg']"></i>
                                {{ newsletterButtonText(section) }}
                            </button>
                        </form>
                        <p v-if="newsletterPrivacyText(section)" class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ newsletterPrivacyText(section) }}
                        </p>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'integrations'" :id="sectionAnchorId(section)" class="py-24 bg-gray-50 dark:bg-surface-900" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="mb-12 text-center">
                        <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                            <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                        </div>
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, t('Technology Logos')) }}</h2>
                        <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">
                            {{ sectionSubtitle(section) }}
                        </p>
                    </div>

                    <div v-if="integrationsItems(section).length" :class="integrationsLayout(section) === 'ticker' ? 'mx-auto flex max-w-6xl flex-nowrap gap-4 overflow-x-auto pb-2' : 'grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6'">
                        <a
                            v-for="(item, index) in integrationsItems(section)"
                            :key="`${String(item.title ?? item.label ?? item.name ?? 'logo')}_${index}`"
                            :href="String(item.link_url ?? '') || undefined"
                            :target="asBoolean(item.link_open_new_tab, false) ? '_blank' : undefined"
                            :rel="asBoolean(item.link_open_new_tab, false) ? 'noopener noreferrer' : undefined"
                            class="group flex min-w-0 flex-col items-center text-center"
                            :class="integrationsLayout(section) === 'ticker' ? 'shrink-0 w-44' : ''"
                        >
                            <div class="flex w-full items-center justify-center rounded-[1.5rem] border border-gray-200 bg-white px-5 py-4 shadow-sm transition duration-200 group-hover:-translate-y-0.5 group-hover:border-primary-200 group-hover:shadow-lg dark:border-surface-700 dark:bg-surface-800">
                                <img
                                    v-if="String(item.image_url ?? '')"
                                    :src="resolveMediaUrl(String(item.image_url))"
                                    :alt="String(item.title ?? '')"
                                    class="max-h-10 w-auto object-contain"
                                >
                                <div v-else class="flex h-10 w-full items-center justify-center rounded-[1rem] bg-gradient-to-br from-primary-50 to-secondary-50 px-4 py-3 text-sm font-black text-gray-700 dark:from-primary-500/10 dark:to-secondary-500/10 dark:text-white">
                                    {{ String(item.title ?? item.label ?? item.name ?? t('Logo')) }}
                                </div>
                            </div>
                            <p v-if="String(item.title ?? '')" class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ String(item.title ?? '') }}
                            </p>
                        </a>
                    </div>
                    <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">
                        {{ t('No logos have been added yet.') }}
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'custom_html'" :id="sectionAnchorId(section)" class="bg-white dark:bg-surface-950" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]" v-html="asString(section.config.content)"></section>

            <section v-else-if="section.type === 'richtext'" :id="sectionAnchorId(section)" class="py-24 bg-white dark:bg-surface-950" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-4xl mx-auto px-6">
                    <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-12 text-center">
                        <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.title) }}</h2>
                        <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
                    </div>
                    <article class="prose prose-gray max-w-none rounded-[2rem] border border-gray-100 bg-white p-8 shadow-sm dark:prose-invert dark:border-surface-800 dark:bg-surface-900" v-html="asString(section.config.content)"></article>
                </div>
            </section>

            <section v-else-if="section.type === 'image_carousel'" :id="sectionAnchorId(section)" class="py-24 bg-gray-50 dark:bg-surface-900" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-7xl mx-auto px-6">
                    <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-12 text-center">
                        <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.title) }}</h2>
                        <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="(item, index) in asItems(section.config.items)" :key="`${item.title}_${index}`" class="overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800">
                            <img v-if="item.image_url" :src="resolveMediaUrl(String(item.image_url))" :alt="String(item.title || '')" :loading="sectionMediaLoading(section)" class="h-56 w-full object-cover">
                            <div v-else class="flex h-56 items-center justify-center bg-gray-100 text-gray-400 dark:bg-surface-700 dark:text-gray-500">
                                <i class="ti ti-photo text-4xl"></i>
                            </div>
                            <div class="p-6">
                                <h3 v-if="item.title" class="text-xl font-black text-gray-900 dark:text-white">{{ item.title }}</h3>
                                <p v-if="item.description" class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                                <a v-if="item.link_url" :href="String(item.link_url)" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 transition hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                    {{ t('Learn more') }}
                                    <i class="ti ti-arrow-right text-sm"></i>
                                </a>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'ad_slot'" :id="sectionAnchorId(section)" class="py-16 bg-white dark:bg-surface-950" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-5xl mx-auto px-6">
                    <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-8 text-center">
                        <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-4xl">{{ asString(section.config.title) }}</h2>
                        <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
                    </div>
                    <AdSection :zone="asString(section.config.zone, 'content_top') as AdZone" />
                </div>
            </section>

            <section v-else-if="section.type === 'announcement'" :id="sectionAnchorId(section)" class="py-16 bg-gray-50 dark:bg-surface-900" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
                <div class="max-w-5xl mx-auto px-6">
                    <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-8 text-center">
                        <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-4xl">{{ asString(section.config.title) }}</h2>
                        <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
                    </div>
                    <div v-if="getAnnouncementSlice(section).length > 0" class="space-y-4">
                        <article
                            v-for="announcement in getAnnouncementSlice(section)"
                            :key="announcement.id"
                            class="overflow-hidden rounded-[2rem] border border-transparent p-6 shadow-sm"
                            :class="asString(section.config.style, 'cards') === 'compact' ? 'flex items-center justify-between gap-4' : ''"
                            :style="{ backgroundColor: announcement.bg_color || '#111827', color: announcement.text_color || '#ffffff' }"
                        >
                            <div :class="asString(section.config.style, 'cards') === 'compact' ? 'flex-1 min-w-0' : ''">
                                <div class="mb-2 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                                    {{ t(announcement.type) }}
                                </div>
                                <h3 v-if="announcement.title" class="text-xl font-black">{{ announcement.title }}</h3>
                                <div v-if="announcement.content" class="mt-2 text-sm leading-relaxed opacity-90" v-html="announcement.content"></div>
                            </div>
                            <a
                                v-if="announcement.cta_text && announcement.cta_url"
                                :href="announcement.cta_url"
                                class="mt-5 inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-gray-900 transition hover:bg-gray-100"
                                :class="asString(section.config.style, 'cards') === 'compact' ? 'mt-0 shrink-0' : ''"
                            >
                                {{ announcement.cta_text }}
                            </a>
                        </article>
                    </div>
                    <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">
                        {{ t('No active announcements are available for this section.') }}
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'template_grid'" :id="sectionAnchorId(section)" class="py-24 bg-white dark:bg-surface-950" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
              <div class="max-w-7xl mx-auto px-6">
                <div v-if="asString(section.config.title)" class="text-center mb-12">
                  <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">
                    {{ asString(section.config.title) }}
                  </h2>
                  <p v-if="asString(section.config.subtitle)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">
                    {{ asString(section.config.subtitle) }}
                  </p>
                </div>
                <TemplateToolGrid
                  v-if="templateData && templateData[asString(section.config.template_slug)]"
                  v-bind="templateData[asString(section.config.template_slug)]"
                  :max-items="Number(section.config.max_items ?? 12)"
                  :show-filter="asBoolean(section.config.show_filter, true)"
                />
                <div v-else class="text-center py-16 text-gray-400 dark:text-gray-500">
                  {{ t('Template tools are not available.') }}
                </div>
              </div>
            </section>

            <section v-else-if="section.type === 'all_tools'" :id="sectionAnchorId(section)" class="py-24 bg-white dark:bg-surface-950" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), sectionStyleClass(section, asString(section.config.section_style, 'default')), sectionAnimationClass(asString(section.config.animation, 'none')), sectionCustomClass(section)]">
              <div class="max-w-7xl mx-auto px-6">
                <div v-if="asString(section.config.title)" class="text-center mb-12">
                  <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">
                    {{ asString(section.config.title) }}
                  </h2>
                  <p v-if="asString(section.config.subtitle)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">
                    {{ asString(section.config.subtitle) }}
                  </p>
                </div>
                <AllToolsSection
                  :tools="props.allTools ?? []"
                  :categories="props.allToolCategories ?? []"
                  :max-items="Number(section.config.max_items ?? 12)"
                  :default-tab="asString(section.config.default_tab, 'popular') as 'popular' | 'featured' | 'recent'"
                  :show-search="asBoolean(section.config.show_search, true)"
                  :show-categories="asBoolean(section.config.show_categories, true)"
                />
              </div>
            </section>
        </template>

        <button
            v-if="props.scrollToTopEnabled !== false && showScrollButton"
            @click="scrollToTop"
            type="button"
            :class="homepageConfig.settings.scroll_to_top.position === 'left' ? 'left-6' : 'right-6'"
            class="fixed bottom-6 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-primary-500 text-white shadow-xl shadow-primary-600/30 transition-colors"
            :aria-label="t('Scroll to top')"
        >
            <i class="ti ti-arrow-up text-lg"></i>
        </button>

        <div v-if="homepageConfig.settings.chat_widget_embed" v-html="homepageConfig.settings.chat_widget_embed"></div>
    </Layout>
</template>

<style scoped>
@keyframes homepage-fade-up {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes homepage-fade-in {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes homepage-slide-up {
    from {
        opacity: 0;
        transform: translateY(28px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.homepage-animate-fade-up {
    animation: homepage-fade-up 0.65s ease both;
}

.homepage-animate-fade-in {
    animation: homepage-fade-in 0.45s ease both;
}

.homepage-animate-slide-up {
    animation: homepage-slide-up 0.75s ease both;
}

@media (prefers-reduced-motion: reduce) {
    .homepage-animate-fade-up,
    .homepage-animate-fade-in,
    .homepage-animate-slide-up {
        animation: none;
    }
}
</style>
