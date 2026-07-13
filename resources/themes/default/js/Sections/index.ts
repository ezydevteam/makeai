import type { Component } from 'vue'

import HeroSection from './HeroSection.vue'
import FeaturesSection from './FeaturesSection.vue'
import ToolsShowcaseSection from './ToolsShowcaseSection.vue'
import HowItWorksSection from './HowItWorksSection.vue'
import PricingSection from './PricingSection.vue'
import TestimonialsSection from './TestimonialsSection.vue'
import FaqSection from './FaqSection.vue'
import StatsBarSection from './StatsBarSection.vue'
import CtaBannerSection from './CtaBannerSection.vue'
import LatestPostsSection from './LatestPostsSection.vue'
import NewsletterSection from './NewsletterSection.vue'
import CustomHtmlSection from './CustomHtmlSection.vue'
import RichtextSection from './RichtextSection.vue'
import ImageCarouselSection from './ImageCarouselSection.vue'
import AdSlotSection from './AdSlotSection.vue'
import AnnouncementSection from './AnnouncementSection.vue'
import AllToolsSection from './AllToolsSection.vue'

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
