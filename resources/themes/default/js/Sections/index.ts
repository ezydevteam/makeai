import { defineAsyncComponent, type Component } from 'vue'

import HeroSection from './HeroSection.vue'

/**
 * Section components, all but the hero split out of the entry bundle.
 *
 * Every section used to be a static import here, so a visitor downloaded and parsed
 * all seventeen before anything could paint — including the ones their homepage has
 * switched off, since the map is built at module scope and cannot know which are
 * enabled. The page has no SSR, so that parse cost sits directly in front of first
 * paint.
 *
 * The hero stays static on purpose: it is always first, always above the fold, and
 * making it async would trade a smaller bundle for a blank screen until its chunk
 * arrives — the opposite of the point.
 *
 * Everything else is a separate chunk fetched while the hero is already rendering.
 * Vue resolves an async component without blocking its siblings, so a slow section
 * chunk delays only itself.
 */
const asyncSection = (loader: () => Promise<Component>): Component =>
    defineAsyncComponent(loader as never)

const FeaturesSection = asyncSection(() => import('./FeaturesSection.vue'))
const ToolsShowcaseSection = asyncSection(() => import('./ToolsShowcaseSection.vue'))
const HowItWorksSection = asyncSection(() => import('./HowItWorksSection.vue'))
const PricingSection = asyncSection(() => import('./PricingSection.vue'))
const TestimonialsSection = asyncSection(() => import('./TestimonialsSection.vue'))
const FaqSection = asyncSection(() => import('./FaqSection.vue'))
const StatsBarSection = asyncSection(() => import('./StatsBarSection.vue'))
const CtaBannerSection = asyncSection(() => import('./CtaBannerSection.vue'))
const LatestPostsSection = asyncSection(() => import('./LatestPostsSection.vue'))
const NewsletterSection = asyncSection(() => import('./NewsletterSection.vue'))
const CustomHtmlSection = asyncSection(() => import('./CustomHtmlSection.vue'))
const RichtextSection = asyncSection(() => import('./RichtextSection.vue'))
const ImageCarouselSection = asyncSection(() => import('./ImageCarouselSection.vue'))
const AnnouncementSection = asyncSection(() => import('./AnnouncementSection.vue'))
const AllToolsSection = asyncSection(() => import('./AllToolsSection.vue'))

// One instance shared by the three ad slots. Three separate defineAsyncComponent
// wrappers would resolve the same chunk three times and each keep its own loading
// state, which shows up as three staggered inserts instead of one.
const AdSlotSection = asyncSection(() => import('./AdSlotSection.vue'))

export {
    HeroSection,
    FeaturesSection,
    ToolsShowcaseSection,
    HowItWorksSection,
    PricingSection,
    TestimonialsSection,
    FaqSection,
    StatsBarSection,
    CtaBannerSection,
    LatestPostsSection,
    NewsletterSection,
    CustomHtmlSection,
    RichtextSection,
    ImageCarouselSection,
    AdSlotSection,
    AnnouncementSection,
    AllToolsSection,
}

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'custom_html' | 'all_tools' | 'richtext' | 'image_carousel' | 'ad_slot' | 'ad_slot_2' | 'ad_slot_3' | 'announcement'

export const sectionComponentMap: Record<SectionType, Component> = {
    hero: HeroSection,
    features: FeaturesSection,
    tools_showcase: ToolsShowcaseSection,
    how_it_works: HowItWorksSection,
    pricing: PricingSection,
    testimonials: TestimonialsSection,
    faq: FaqSection,
    stats_bar: StatsBarSection,
    cta_banner: CtaBannerSection,
    latest_posts: LatestPostsSection,
    newsletter: NewsletterSection,
    custom_html: CustomHtmlSection,
    richtext: RichtextSection,
    image_carousel: ImageCarouselSection,
    ad_slot: AdSlotSection,
    ad_slot_2: AdSlotSection,
    ad_slot_3: AdSlotSection,
    announcement: AnnouncementSection,
    all_tools: AllToolsSection,
}
