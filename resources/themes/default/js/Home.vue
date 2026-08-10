<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import Layout from '@themes/default/js/Layouts/AppLayout.vue'
import { sectionComponentMap } from '@themes/default/js/Sections'
import { useTranslate } from '@/Composables/useTranslate'

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'custom_html' | 'all_tools' | 'richtext' | 'image_carousel' | 'ad_slot' | 'ad_slot_2' | 'ad_slot_3' | 'announcement'

interface Testimonial { id: number; name: string; role: string | null; company: string | null; avatar: string | null; content: string; rating: number; is_featured: boolean; source: string }
interface Faq { id: number; question: string; answer: string; category_id: number | null; sort_order: number; category?: { id: number; name: string; sort_order: number } | null }
interface Announcement { id: number; type: 'topbar' | 'popup' | 'notification'; title: string | null; content: string | null; bg_color: string | null; text_color: string | null; cta_text: string | null; cta_url: string | null; image: string | null; target_audience: string; trigger_type: string | null; trigger_value: string | null; show_frequency: 'always' | 'session' | 'once' }
interface ToolItem { slug: string; name: string; description: string; icon: string | null; color: string | null; category: string | null; usage_count: number; avg_rating: number | null; is_featured: boolean; created_at?: string }
interface BlogPostPreview { title: string; slug: string; published_at: string | null; image: string | null; is_featured: boolean }
interface PricingCycle { amount: number; subtotal_amount: number; original_amount: number | null; formatted: string; subtotal_formatted: string; original_formatted: string | null; vat_percentage: number; vat_formatted: string; uses_default: boolean; is_trial: boolean; trial_days: number | null }
interface PricingPlan { id: number; name: string; slug: string; description: string; bottom_info_text: string | null; credits: number | string; features: string[] | string; is_featured: boolean; is_free: boolean; pricing: Record<string, PricingCycle> }
interface PricingSettings { pricing_show_monthly: boolean; pricing_show_yearly: boolean; pricing_show_lifetime: boolean; pricing_currency_code: string; pricing_trial_button_text: string; pricing_featured_label_text: string; pricing_checkout_button_text: string }
type SectionConfigValue = string | number | boolean | null | unknown
interface HomepageSection { id: string; type: SectionType; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
interface HomepageConfig { sections: HomepageSection[]; settings: { seo: { meta_title: string; meta_description: string; og_image: string }; scroll_to_top: { enabled: boolean; position: 'left' | 'right'; show_after_px: number }; chat_widget_embed: string } }

const props = defineProps<{
    homepage?: HomepageConfig | null
    templateData?: Record<string, unknown> | null
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
const page = usePage()
const appName = computed(() => String(page.props.branding?.site_name || 'MakeAI'))
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const frontendHomepageSettings = computed<Record<string, boolean | string>>(() => (page.props.frontendHomepageSettings as Record<string, boolean | string> | undefined) ?? {})
const frontendHomepageConfig = computed(() => (page.props.frontendHomepageConfig as Record<string, unknown> | undefined) ?? {})
const allAnnouncements = computed(() => (page.props.announcements as Announcement[]) || [])
const recentBlogPosts = computed<BlogPostPreview[]>(() => (page.props.recentPosts as BlogPostPreview[]) || props.recentPosts || [])
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
        { id: 'hero', type: 'hero', enabled: true, core: true, config: { layout: 'center', headline: t('One Platform. Every AI Tool.'), subheadline: t('Unleash your creativity with the world\'s most powerful AI models.', { app: appName.value }), primary_cta_text: t('Get Started for Free'), primary_cta_link: '/register', primary_cta_style: 'primary_filled', secondary_cta_text: t('View Pricing'), secondary_cta_link: '/pricing', secondary_cta_style: 'outline', hero_background_type: 'image', show_hero_gradient_overlay: true, show_stats_separator: true, hero_vertical_padding: 48, hero_heading_size: 'lg', hero_heading_color: 'dark', hero_subheading_color: 'light', stats_number_color: 'dark', stats_label_color: 'light', trust_badge_text: t('Trusted by 50,000+ creators'), display_categories: [], display_tool_slugs: [], stats: [{ number: '50K+', label: t('Users Trusted') }, { number: '10M+', label: t('Assets Generated') }, { number: '99.9%', label: t('Uptime SLA') }, { number: '24/7', label: t('Expert Support') }] } },
        { id: 'features', type: 'features', enabled: true, core: true, config: { title: t('Supercharge your workflow'), subtitle: t('Everything you need to build the future, powered by AI.'), layout: '3-column', card_style: 'bordered', heading_color: 'dark', subheading_color: 'light', learn_more_text: t('Learn more'), items: [{ icon: 'ti ti-pencil', title: t('AI Writer'), description: t('Generate blogs, ads, and emails in seconds.') }, { icon: 'ti ti-photo', title: t('AI Images'), description: t('Turn text into masterpiece images.') }, { icon: 'ti ti-message-2', title: t('AI Chat'), description: t('Smart assistants for research or support.') }, { icon: 'ti ti-code', title: t('AI Code'), description: t('Code faster with AI companionship.') }] } },
        { id: 'tools_showcase', type: 'tools_showcase', enabled: true, core: true, config: { title: t('AI Tools Showcase'), subtitle: t('Explore the tools buyers can launch immediately after signup.'), layout: '3-column', card_style: 'style-1', source: 'all', max_items: 6, heading_color: 'white', subheading_color: 'white', primary_text: t('View all tools'), primary_link: '/ai-tools', primary_style: 'primary_filled', background_style: 'gradient-1', width: 'contained' } },
        { id: 'how_it_works', type: 'how_it_works', enabled: true, core: true, config: { heading: t('How It Works'), subheading: t('Help buyers explain the product flow in three simple steps.'), icon: 'ti ti-route', step_card_style: 'bordered', items: [{ icon: 'ti ti-user-plus', title: t('Create your account'), description: t('Sign up in seconds.') }, { icon: 'ti ti-adjustments-bolt', title: t('Choose the right AI tool'), description: t('Pick from writing, image, chat, and productivity tools.') }, { icon: 'ti ti-sparkles', title: t('Generate and refine results'), description: t('Review the output and keep iterating.') }] } },
        { id: 'pricing', type: 'pricing', enabled: true, core: true, config: { heading: t('Simple Pricing'), subheading: t('Present your plans clearly.'), icon: 'ti ti-credit-card', source: 'all' } },
        { id: 'cta_banner', type: 'cta_banner', enabled: true, core: true, config: { headline: t('Ready to create with AI?'), subheadline: t('Start with the tools your audience needs most.'), primary_text: t('Get Started for Free'), primary_link: '/register', primary_style: 'primary_filled', primary_shape: 'rounded_xl', primary_icon: '', primary_access_level: 'all', secondary_text: t('View Pricing'), secondary_link: '/pricing', secondary_style: 'outline', secondary_shape: 'rounded_xl', secondary_icon: '', secondary_access_level: 'all', width: 'contained', background_style: 'gradient-1', access: 'everyone' } },
        { id: 'testimonials', type: 'testimonials', enabled: true, core: true, config: { heading: t('What Our Users Say'), subheading: t('Show real customer feedback to build trust.'), icon: 'ti ti-message-2-heart', source: 'all', card_style: 'bordered', max_items: 6, slider_columns: '3', hide_controls: '0', autoplay_enabled: '0' } },
        { id: 'faq', type: 'faq', enabled: true, core: true, config: { heading: t('Frequently Asked Questions'), subheading: t('Answer common questions.'), icon: 'ti ti-help-circle', max_items: 8 } },
        { id: 'stats_bar', type: 'stats_bar', enabled: true, core: true, config: { heading: t('Social Proof'), subheading: t('Show your best numbers.'), icon: 'ti ti-chart-bar', show_stats_separator: true, show_stats: true, show_brands: false, stats_number_color: 'dark', stats_label_color: 'light', stats: [{ number: '50K+', label: t('Users Trusted') }, { number: '10M+', label: t('Assets Generated') }, { number: '99.9%', label: t('Uptime SLA') }], brands: [] } },
        { id: 'latest_posts', type: 'latest_posts', enabled: true, core: true, config: { title: t('Latest from the Blog'), subtitle: t('Keep the homepage fresh.'), icon: 'ti ti-article', source: 'recent', layout: 'grid', card_style: 'bordered', max_items: 3, show_button: true, show_description: true, button_text: t('Visit Blog'), button_link: '/blog', button_style: 'outline', button_icon: '' } },
        { id: 'newsletter', type: 'newsletter', enabled: true, core: true, config: { heading: t('Stay in the Loop'), subheading: t('Collect email subscribers.'), icon: 'ti ti-mail-star', layout: 'inline', placeholder_text: t('Enter your email address'), button_text: t('Subscribe'), button_style: 'primary_filled', background_style: 'white', newsletter_style: 'inline', privacy_text: t('No spam. Unsubscribe anytime.') } },
    ],
    settings: { seo: { meta_title: t(':app — The Ultimate AI Platform', { app: appName.value }), meta_description: t('Create content, images, chat responses, and code with one powerful AI platform.'), og_image: '' }, scroll_to_top: { enabled: true, position: 'right', show_after_px: 500 }, chat_widget_embed: '' },
}

const isEnabled = (val: unknown, fallback = true): boolean => {
    if (val === undefined || val === null || val === '') return fallback
    if (typeof val === 'boolean') return val
    if (typeof val === 'number') return val !== 0
    const str = String(val).trim().toLowerCase()
    if (['0', 'false', 'no', 'off'].includes(str)) return false
    if (['1', 'true', 'yes', 'on'].includes(str)) return true
    return fallback
}

const buildSimpleHomepageConfig = (): HomepageConfig => {
    const simpleSettings = frontendHomepageSettings.value
    const presetSections = (frontendHomepageConfig.value as Record<string, unknown>)?.sections as HomepageSection[] | undefined
    const sectionSource = presetSections && presetSections.length > 0 ? presetSections : defaultHomepage.sections
    const sectionMap = new Map(sectionSource.map((s: HomepageSection) => [s.type, JSON.parse(JSON.stringify(s))]))
    const heroSection = sectionMap.get('hero')
    if (heroSection) heroSection.config.layout = String(simpleSettings.hero_variant || 'centered-gradient')
    const ordered: HomepageSection[] = []
    const push = (type: SectionType, enabled = true) => { const s = sectionMap.get(type); if (s) { s.enabled = enabled; ordered.push(s) } }
    push('hero', isEnabled(simpleSettings.show_hero, true))
    push('features', isEnabled(simpleSettings.show_features, true))
    push('tools_showcase', isEnabled(simpleSettings.show_tools, true))
    push('how_it_works', isEnabled(simpleSettings.show_steps, true))
    // Never render pricing without billing, even if show_pricing is still true in a config
    // saved back when subscriptions were enabled.
    push('pricing', isProAvailable.value && isEnabled(simpleSettings.show_pricing, false))
    push('testimonials', isEnabled(simpleSettings.show_testimonials, true))
    push('faq', isEnabled(simpleSettings.show_faq, true))
    push('stats_bar', isEnabled(simpleSettings.show_social_proof, true))
    push('cta_banner', isEnabled(simpleSettings.show_cta, true))
    push('latest_posts', isEnabled(simpleSettings.show_blog, true))
    push('newsletter', isEnabled(simpleSettings.show_newsletter, true))
    push('custom_html', isEnabled(simpleSettings.show_custom_html, false))
    push('richtext', isEnabled(simpleSettings.show_richtext, false))
    push('image_carousel', isEnabled(simpleSettings.show_image_carousel, false))
    push('ad_slot', isEnabled(simpleSettings.show_ad_slot, false))
    push('ad_slot_2', isEnabled(simpleSettings.show_ad_slot_2, false))
    push('ad_slot_3', isEnabled(simpleSettings.show_ad_slot_3, false))
    const sorted = ordered.filter((s) => s.enabled).sort((a, b) => {
        const ao = Number((a.config as Record<string, unknown>).sort_order ?? 0)
        const bo = Number((b.config as Record<string, unknown>).sort_order ?? 0)
        return ao - bo
    })
    const presetSettings = (frontendHomepageConfig.value as Record<string, unknown>)?.settings as Record<string, unknown> | undefined
    const mergedSettings = presetSettings
        ? { ...structuredClone(defaultHomepage.settings), ...presetSettings }
        : structuredClone(defaultHomepage.settings)
    return { ...structuredClone(defaultHomepage), sections: sorted, settings: mergedSettings as HomepageConfig['settings'] }
}

const homepageConfig = computed<HomepageConfig>(() => props.homepage ?? buildSimpleHomepageConfig())
const enabledSections = computed(() => homepageConfig.value.sections.filter((s) => isEnabled(s.enabled, true)))

// ─── Below-the-fold sections ───
//
// Sections/index.ts splits every non-hero section into its own chunk, but that alone
// buys nothing: the v-for renders all of them on mount, so all their chunks are
// requested at once anyway — measured as MORE files and MORE bytes than the single
// bundle, because each chunk carries its own wrapper. The saving only exists if the
// section is not rendered yet, which is what this does.
//
// EAGER_SECTIONS covers what can be on screen at first paint. The rest render a bare
// spacer until they come within EAGER_MARGIN of the viewport, then swap to the real
// component — and the spacer is dropped entirely rather than left as a wrapper, so a
// rendered section has exactly the same parentage and CSS it had before.
//
// Loading a screen ahead means the swap happens off-screen, where a height correction
// costs no CLS. Without IntersectionObserver every section renders immediately, which
// is the old behaviour rather than a broken page.
const EAGER_SECTIONS = 2
const EAGER_MARGIN = '800px'

// Keyed by position, not section.id — these config objects carry no id (the existing
// :key="section.id" has always been undefined), so an id-keyed Set never matches and
// nothing would ever reveal. Position is stable for a given enabledSections render.
const revealed = ref(new Set<number>())
const supportsObserver = typeof IntersectionObserver !== 'undefined'
let sectionObserver: IntersectionObserver | null = null

const isRevealed = (index: number): boolean =>
    index < EAGER_SECTIONS || ! supportsObserver || revealed.value.has(index)

const observeSection = (el: Element | null, index: number): void => {
    if (! el || ! sectionObserver) return
    ;(el as HTMLElement).dataset.sectionIndex = String(index)
    sectionObserver.observe(el)
}

if (supportsObserver) {
    sectionObserver = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (! entry.isIntersecting) continue
            const raw = (entry.target as HTMLElement).dataset.sectionIndex
            if (raw === undefined) continue
            sectionObserver?.unobserve(entry.target)
            // Reassigned rather than mutated: a Set mutation is not reactive in Vue 3.
            revealed.value = new Set(revealed.value).add(Number(raw))
        }
    }, { rootMargin: EAGER_MARGIN })
}

onUnmounted(() => sectionObserver?.disconnect())

const showScrollButton = ref(false)
interface SimpleHeaderSettings {
    mobile_bottom?: {
        enabled?: boolean
    }
}

// These arrive as real booleans from settings.json and as "1"/"0" strings once the theme
// form has been saved through multipart, so both shapes have to read the same — isEnabled
// covers them. Hand-rolled string comparisons here silently dropped the default `true`.
const themeShowBackToTop = computed(() => {
    const settings = (page.props.appearanceThemeSettings as Record<string, unknown>) || {}
    return isEnabled(settings.show_back_to_top, true)
})

const themeHideScrollTopMobile = computed(() => {
    const settings = (page.props.appearanceThemeSettings as Record<string, unknown>) || {}
    return isEnabled(settings.hide_scroll_top_mobile, false)
})

const isMobileBottomHeaderEnabled = computed(() => {
    const headerSettings = (page.props.frontendHeaderSettings as SimpleHeaderSettings | undefined) ?? {}
    return headerSettings.mobile_bottom?.enabled === true
})

const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })
const onScroll = () => { showScrollButton.value = window.scrollY >= homepageConfig.value.settings.scroll_to_top.show_after_px }

const backToTopButtonStyle = computed(() => {
    const assistant = page.props.aiAssistant as any
    const scrollPosition = homepageConfig.value.settings.scroll_to_top.position ?? 'right'
    
    // Default bottom offsets (without assistant)
    let bottomDesktop = 24
    let bottomMobile = 24

    const mobileBottom = (page.props.frontendHeaderSettings as any)?.mobile_bottom ?? {}
    const mobileBottomHeight = mobileBottom.enabled === true
        ? (mobileBottom.hide_menu_labels === true ? 48 : 60)
        : 0

    if (isMobileBottomHeaderEnabled.value) {
        bottomMobile = 24 + mobileBottomHeight
    }

    if (assistant) {
        const assistantPosition = assistant.position ?? 'bottom-right'
        
        // Assistant side: left if 'bottom-left', right otherwise (including header-button mobile fallback)
        const assistantSide = assistantPosition === 'bottom-left' ? 'left' : 'right'
        
        if (assistantSide === scrollPosition) {
            // They are on the same side, so we offset to prevent overlap!
            const floatsOnDesktop = assistantPosition !== 'header-button'
            
            if (floatsOnDesktop) {
                // Desktop: assistant bubble bottom (24) + height (56) + gap (16) = 96px
                bottomDesktop = 96
            }
            
            // Mobile: assistant always floats. bubble bottom (24 + mobileBottomHeight) + height (56) + gap (16)
            bottomMobile = 96 + mobileBottomHeight
        }
    }

    return {
        '--back-to-top-bottom-desktop': `${bottomDesktop}px`,
        '--back-to-top-bottom-mobile': `${bottomMobile}px`,
    }
})

onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
    <!-- The homepage <title> is composed server-side (HomepageController::buildSeo) and
         the global callback (app.ts, component === 'Home') returns that seo.title
         verbatim, so the value passed here is only a non-empty trigger and is overridden. -->
    <Head :title="appName">
        <meta v-if="homepageConfig.settings.seo.meta_description" name="description" :content="homepageConfig.settings.seo.meta_description">
        <meta v-if="homepageConfig.settings.seo.og_image" property="og:image" :content="homepageConfig.settings.seo.og_image">
    </Head>

    <Layout>
        <!-- Keyed by type+position, not section.id: these configs carry no id, so the old
             :key="section.id" gave every item the same undefined key. Vue then reconciled
             them positionally and, once an item could switch between a spacer and a real
             section, patched the wrong nodes — leaving spacers that never resolved. -->
        <template v-for="(section, sectionIndex) in enabledSections" :key="`${section.type}-${sectionIndex}`">
        <div
            v-if="! isRevealed(sectionIndex)"
            :ref="(el) => observeSection(el as Element | null, sectionIndex)"
            class="min-h-[50vh]"
            aria-hidden="true"
        ></div>
        <component
            v-else
            :is="sectionComponentMap[section.type]"
            :section="section"
            :testimonials="section.type === 'testimonials' ? testimonials : undefined"
            :faqs="section.type === 'faq' ? faqs : undefined"
            :pricing-plans="section.type === 'pricing' ? pricingPlans : undefined"
            :pricing-settings="section.type === 'pricing' ? pricingSettings : undefined"
            :all-tools="(section.type === 'hero' || section.type === 'tools_showcase' || section.type === 'all_tools') ? (allTools || []) : undefined"
            :all-tool-categories="section.type === 'all_tools' ? (allToolCategories || []) : undefined"
            :recent-posts="section.type === 'latest_posts' ? (recentBlogPosts || []) : undefined"
            :announcements="section.type === 'announcement' ? allAnnouncements : undefined"
        />
        </template>

        <button
            v-if="showScrollButton && themeShowBackToTop"
            @click="scrollToTop"
            type="button"
            :class="[
                homepageConfig.settings.scroll_to_top.position === 'left' ? 'left-6' : 'right-6',
                themeHideScrollTopMobile ? 'hidden md:flex' : 'flex'
            ]"
            :style="backToTopButtonStyle"
            class="back-to-top-btn fixed z-40 h-12 w-12 items-center justify-center rounded-full bg-primary-500 text-white shadow-xl shadow-primary-600/30 transition-colors"
            :aria-label="t('Scroll to top')"
        >
            <i class="ti ti-arrow-up text-lg"></i>
        </button>

        <div v-if="homepageConfig.settings.chat_widget_embed" v-html="homepageConfig.settings.chat_widget_embed"></div>
    </Layout>
</template>

<style scoped>
.back-to-top-btn {
    bottom: var(--back-to-top-bottom-desktop, 24px);
}

@media (max-width: 767px) {
    .back-to-top-btn {
        bottom: var(--back-to-top-bottom-mobile, 24px) !important;
    }
}
</style>
