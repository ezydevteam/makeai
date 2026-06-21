<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import Layout from '@/Layouts/AppLayout.vue'
import { sectionComponentMap } from '@/Components/Home'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'integrations' | 'custom_html' | 'all_tools' | 'richtext' | 'image_carousel' | 'ad_slot' | 'announcement'

interface Testimonial { id: number; name: string; role: string | null; company: string | null; avatar: string | null; content: string; rating: number; is_featured: boolean; source: string }
interface Faq { id: number; question: string; answer: string; category_id: number | null; sort_order: number; category?: { id: number; name: string; sort_order: number } | null }
interface Announcement { id: number; type: 'topbar' | 'popup' | 'notification'; title: string | null; content: string | null; bg_color: string | null; text_color: string | null; cta_text: string | null; cta_url: string | null; image: string | null; target_audience: string; trigger_type: string | null; trigger_value: string | null; show_frequency: 'always' | 'session' | 'once' }
interface ToolItem { slug: string; name: string; description: string; icon: string | null; color: string | null; category: string | null; usage_count: number; avg_rating: number | null; is_featured: boolean; created_at?: string }
interface BlogPostPreview { title: string; slug: string; published_at: string | null; image: string | null; is_featured: boolean }
interface PricingCycle { amount: number; subtotal_amount: number; original_amount: number | null; formatted: string; subtotal_formatted: string; original_formatted: string | null; vat_percentage: number; vat_formatted: string; uses_default: boolean; is_trial: boolean; trial_days: number | null }
interface PricingPlan { id: number; name: string; slug: string; description: string; bottom_info_text: string | null; credits: number | string; features: string[] | string; is_featured: boolean; is_free: boolean; pricing: Record<string, PricingCycle> }
interface PricingSettings { pricing_show_monthly: boolean; pricing_show_yearly: boolean; pricing_show_lifetime: boolean; pricing_currency_code: string; pricing_trial_button_text: string; pricing_featured_label_text: string; pricing_checkout_button_text: string }
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
const appName = computed(() => String(page.props.branding?.site_name || t('Application')))
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
        { id: 'hero', type: 'hero', enabled: true, core: true, config: { layout: 'center', headline: t('One Platform. Every AI Tool.'), subheadline: t('Unleash your creativity with the world\'s most powerful AI models.', { app: appName.value }), primary_cta_text: t('Get Started for Free'), primary_cta_link: '/register', primary_cta_style: 'primary_filled', secondary_cta_text: t('View Pricing'), secondary_cta_link: '/pricing', secondary_cta_style: 'outline', hero_background_type: 'image', show_hero_gradient_overlay: true, show_stats_separator: true, hero_vertical_padding: 48, hero_heading_size: 'lg', hero_heading_color: 'dark', hero_subheading_color: 'light', stats_number_color: 'dark', stats_label_color: 'light', trust_badge_text: t('Trusted by 50,000+ creators'), stats: [{ number: '50K+', label: t('Users Trusted') }, { number: '10M+', label: t('Assets Generated') }, { number: '99.9%', label: t('Uptime SLA') }, { number: '24/7', label: t('Expert Support') }] } },
        { id: 'features', type: 'features', enabled: true, core: true, config: { title: t('Supercharge your workflow'), subtitle: t('Everything you need to build the future, powered by AI.'), layout: '3-column', card_style: 'bordered', heading_color: 'dark', subheading_color: 'light', learn_more_text: t('Learn more'), items: [{ icon: 'ti ti-pencil', title: t('AI Writer'), description: t('Generate blogs, ads, and emails in seconds.') }, { icon: 'ti ti-photo', title: t('AI Images'), description: t('Turn text into masterpiece images.') }, { icon: 'ti ti-message-2', title: t('AI Chat'), description: t('Smart assistants for research or support.') }, { icon: 'ti ti-code', title: t('AI Code'), description: t('Code faster with AI companionship.') }] } },
        { id: 'tools_showcase', type: 'tools_showcase', enabled: true, core: true, config: { title: t('AI Tools Showcase'), subtitle: t('Explore the tools buyers can launch immediately after signup.'), layout: '3-column', card_style: 'bordered', source: 'all', max_items: 6, heading_color: 'white', subheading_color: 'white', primary_text: t('View all tools'), primary_link: '/ai-tools', primary_style: 'primary_filled', background_style: 'gradient-1', width: 'contained' } },
        { id: 'how_it_works', type: 'how_it_works', enabled: true, core: true, config: { heading: t('How It Works'), subheading: t('Help buyers explain the product flow in three simple steps.'), icon: 'ti ti-route', step_card_style: 'bordered', items: [{ icon: 'ti ti-user-plus', title: t('Create your account'), description: t('Sign up in seconds.') }, { icon: 'ti ti-adjustments-bolt', title: t('Choose the right AI tool'), description: t('Pick from writing, image, chat, and productivity tools.') }, { icon: 'ti ti-sparkles', title: t('Generate and refine results'), description: t('Review the output and keep iterating.') }] } },
        { id: 'pricing', type: 'pricing', enabled: true, core: true, config: { heading: t('Simple Pricing'), subheading: t('Present your plans clearly.'), icon: 'ti ti-credit-card', source: 'all' } },
        { id: 'cta_banner', type: 'cta_banner', enabled: true, core: true, config: { headline: t('Ready to create with AI?'), subheadline: t('Start with the tools your audience needs most.'), primary_text: t('Get Started for Free'), primary_link: '/register', primary_style: 'primary_filled', secondary_text: t('View Pricing'), secondary_link: '/pricing', secondary_style: 'outline', width: 'contained', background_style: 'gradient-1', access: 'everyone' } },
        { id: 'testimonials', type: 'testimonials', enabled: true, core: true, config: { heading: t('What Our Users Say'), subheading: t('Show real customer feedback to build trust.'), icon: 'ti ti-message-2-heart', source: 'all', card_style: 'bordered', max_items: 6 } },
        { id: 'faq', type: 'faq', enabled: true, core: true, config: { heading: t('Frequently Asked Questions'), subheading: t('Answer common questions.'), icon: 'ti ti-help-circle', max_items: 8 } },
        { id: 'stats_bar', type: 'stats_bar', enabled: true, core: true, config: { heading: t('Social Proof'), subheading: t('Show your best numbers.'), icon: 'ti ti-chart-bar', show_stats_separator: true, stats_number_color: 'dark', stats_label_color: 'light', stats: [{ number: '50K+', label: t('Users Trusted') }, { number: '10M+', label: t('Assets Generated') }, { number: '99.9%', label: t('Uptime SLA') }] } },
        { id: 'latest_posts', type: 'latest_posts', enabled: true, core: true, config: { title: t('Latest from the Blog'), subtitle: t('Keep the homepage fresh.'), icon: 'ti ti-article', source: 'recent', layout: 'grid', card_style: 'bordered', max_items: 3, button_text: t('Visit Blog'), button_link: '/blog', button_style: 'outline' } },
        { id: 'newsletter', type: 'newsletter', enabled: true, core: true, config: { heading: t('Stay in the Loop'), subheading: t('Collect email subscribers.'), icon: 'ti ti-mail-star', layout: 'inline', placeholder_text: t('Enter your email address'), button_text: t('Subscribe'), button_style: 'primary_filled', privacy_text: t('No spam. Unsubscribe anytime.') } },
    ],
    settings: { seo: { meta_title: t(':app — The Ultimate AI Platform', { app: appName.value }), meta_description: t('Create content, images, chat responses, and code with one powerful AI platform.'), og_image: '' }, scroll_to_top: { enabled: true, position: 'right', show_after_px: 500 }, chat_widget_embed: '' },
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
    push('hero', simpleSettings.show_hero !== false); push('features', simpleSettings.show_features !== false); push('tools_showcase', simpleSettings.show_tools !== false)
    push('how_it_works', simpleSettings.show_steps !== false); push('pricing', simpleSettings.show_pricing === true)
    push('testimonials', simpleSettings.show_testimonials !== false); push('faq', simpleSettings.show_faq !== false)
    push('stats_bar', simpleSettings.show_social_proof !== false); push('cta_banner', simpleSettings.show_cta !== false)
    push('latest_posts', simpleSettings.show_blog !== false); push('newsletter', simpleSettings.show_newsletter !== false)
    push('custom_html', simpleSettings.show_custom_html === true); push('richtext', simpleSettings.show_richtext === true)
    push('ad_slot', simpleSettings.show_ad_slot === true)
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
const enabledSections = computed(() => homepageConfig.value.sections.filter((s) => s.enabled))
const showScrollButton = ref(false)
const themeShowBackToTop = computed(() => {
    const settings = (page.props.appearanceThemeSettings as Record<string, string>) || {}
    const val = settings.show_back_to_top
    return val === undefined || val === '' || val === 'true' || val === '1'
})

const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })
const onScroll = () => { showScrollButton.value = window.scrollY >= homepageConfig.value.settings.scroll_to_top.show_after_px }

onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
    <Head :title="homepageConfig.settings.seo.meta_title || appName + ' — ' + t('The Ultimate AI Platform')">
        <meta v-if="homepageConfig.settings.seo.meta_description" name="description" :content="homepageConfig.settings.seo.meta_description">
        <meta v-if="homepageConfig.settings.seo.og_image" property="og:image" :content="homepageConfig.settings.seo.og_image">
    </Head>

    <Layout>
        <component
            :is="sectionComponentMap[section.type]"
            v-for="section in enabledSections"
            :key="section.id"
            :section="section"
            :testimonials="section.type === 'testimonials' ? testimonials : undefined"
            :faqs="section.type === 'faq' ? faqs : undefined"
            :pricing-plans="section.type === 'pricing' ? pricingPlans : undefined"
            :pricing-settings="section.type === 'pricing' ? pricingSettings : undefined"
            :all-tools="(section.type === 'tools_showcase' || section.type === 'all_tools') ? (allTools || []) : undefined"
            :all-tool-categories="section.type === 'all_tools' ? (allToolCategories || []) : undefined"
            :recent-posts="section.type === 'latest_posts' ? (recentBlogPosts || []) : undefined"
            :announcements="section.type === 'announcement' ? allAnnouncements : undefined"
        />

        <button
            v-if="showScrollButton && themeShowBackToTop"
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
