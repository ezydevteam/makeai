<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Layouts/AppLayout.vue'

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'integrations' | 'custom_html'
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
    preloader: {
        enabled: boolean
        animation_url: string
    }
    scroll_to_top: {
        enabled: boolean
        position: 'left' | 'right'
        show_after_px: number
    }
    cookie_consent: {
        enabled: boolean
        message: string
        accept_text: string
        policy_url: string
    }
    chat_widget_embed: string
}

interface HomepageConfig {
    sections: HomepageSection[]
    settings: HomepageSettings
}

const props = defineProps<{
    homepage: HomepageConfig | null
    testimonials: Testimonial[]
    faqs: Faq[]
}>()

const defaultHomepage: HomepageConfig = {
    sections: [
        {
            id: 'hero',
            type: 'hero',
            enabled: true,
            core: true,
            config: {
                layout: 'centered',
                headline: 'One Platform. Every AI Tool.',
                subheadline: 'Unleash your creativity with the world’s most powerful AI models. From high-quality content to stunning images and precise code, MakeAI has you covered.',
                primary_cta_text: 'Get Started for Free',
                primary_cta_link: '/register',
                primary_cta_style: 'filled',
                secondary_cta_text: 'View Pricing',
                secondary_cta_link: '/pricing',
                secondary_cta_style: 'outline',
                show_trust_badges: true,
                trust_badge_text: 'Next-Gen AI Technology',
                stats: [
                    { number: '50K+', label: 'Users Trusted' },
                    { number: '10M+', label: 'Assets Generated' },
                    { number: '99.9%', label: 'Uptime SLA' },
                    { number: '24/7', label: 'Expert Support' },
                ],
                hero_media_url: '',
            },
        },
        {
            id: 'features',
            type: 'features',
            enabled: true,
            core: true,
            config: {
                title: 'Supercharge your workflow',
                subtitle: 'Everything you need to build the future, powered by AI.',
                layout: '3-column',
                items: [
                    { icon: 'pencil', title: 'AI Writer', description: 'Generate blogs, ads, and emails in seconds with our advanced copywriting models.', image_url: '' },
                    { icon: 'photo', title: 'AI Images', description: 'Turn text into masterpiece. High-resolution images for any project or brand.', image_url: '' },
                    { icon: 'chat', title: 'AI Chat', description: 'Smart, contextual assistants ready to help you with research or customer support.', image_url: '' },
                    { icon: 'code', title: 'AI Code', description: 'From debugging to writing entire functions. Code faster with AI companionship.', image_url: '' },
                ],
                cta_text: '',
                cta_link: '',
            },
        },
    ],
    settings: {
        seo: {
            meta_title: 'MakeAI — The Ultimate AI Platform',
            meta_description: 'Create content, images, chat responses, and code with one powerful AI platform.',
            og_image: '',
        },
        preloader: {
            enabled: false,
            animation_url: '',
        },
        scroll_to_top: {
            enabled: true,
            position: 'right',
            show_after_px: 500,
        },
        cookie_consent: {
            enabled: false,
            message: 'We use cookies to improve your experience.',
            accept_text: 'Accept',
            policy_url: '/privacy-policy',
        },
        chat_widget_embed: '',
    },
}

const homepageConfig = computed<HomepageConfig>(() => props.homepage ?? defaultHomepage)
const enabledSections = computed(() => homepageConfig.value.sections.filter((section) => section.enabled))
const showScrollButton = ref(false)
const cookieAccepted = ref(false)
const openFaqId = ref<number | null>(null)
const toggleFaq = (id: number) => { openFaqId.value = openFaqId.value === id ? null : id }

const stars = (n: number): boolean[] => Array.from({ length: 5 }, (_, i) => i < n)

// Limit testimonials shown by max_items config
const getTestimonialsSlice = (section: HomepageSection): Testimonial[] => {
    const max = parseInt(String(section.config.max_items ?? 6), 10)
    const featuredOnly = section.config.featured_only === true
    const list = featuredOnly ? props.testimonials.filter(t => t.is_featured) : props.testimonials
    return list.slice(0, max)
}

const getFaqsSlice = (section: HomepageSection): Faq[] => {
    const max = parseInt(String(section.config.max_items ?? 10), 10)
    return props.faqs.slice(0, max)
}

const resolveMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) return path

    return `/storage/${path}`
}

const asString = (value: SectionConfigValue | undefined, fallback = ''): string => typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
const asBoolean = (value: SectionConfigValue | undefined, fallback = false): boolean => typeof value === 'boolean' ? value : fallback
const asItems = (value: SectionConfigValue | undefined): SectionItem[] => Array.isArray(value) && value.every((item) => typeof item !== 'string') ? value : []
const replaceVariables = (value: string): string => value.replaceAll('{app_name}', 'MakeAI')

const headingParts = (headline: string): [string, string] => {
    const parts = replaceVariables(headline).split('. ')
    return parts.length > 1 ? [`${parts[0]}.`, parts.slice(1).join('. ')] : [replaceVariables(headline), '']
}

const sectionTitle = (section: HomepageSection, fallback: string): string => asString(section.config.title, fallback)
const sectionSubtitle = (section: HomepageSection): string => asString(section.config.subtitle)
const primaryButtonClass = (style: string): string => style === 'outline'
    ? 'bg-white dark:bg-surface-900 text-gray-900 dark:text-white border-2 border-gray-100 dark:border-surface-800 hover:bg-gray-50 dark:hover:bg-surface-800'
    : 'bg-gray-900 dark:bg-white dark:text-gray-900 text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800 dark:hover:bg-gray-100'
const secondaryButtonClass = (style: string): string => style === 'filled'
    ? 'bg-gray-900 dark:bg-white dark:text-gray-900 text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800 dark:hover:bg-gray-100'
    : 'bg-white dark:bg-surface-900 text-gray-900 dark:text-white border-2 border-gray-100 dark:border-surface-800 hover:bg-gray-50 dark:hover:bg-surface-800'

const onScroll = () => {
    showScrollButton.value = window.scrollY >= homepageConfig.value.settings.scroll_to_top.show_after_px
}

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const acceptCookies = () => {
    cookieAccepted.value = true
    localStorage.setItem('makeai_cookie_consent', 'accepted')
}

onMounted(() => {
    cookieAccepted.value = localStorage.getItem('makeai_cookie_consent') === 'accepted'
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()
})

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll)
})
</script>

<template>
    <Head :title="homepageConfig.settings.seo.meta_title || 'MakeAI — The Ultimate AI Platform'">
        <meta v-if="homepageConfig.settings.seo.meta_description" name="description" :content="homepageConfig.settings.seo.meta_description">
        <meta v-if="homepageConfig.settings.seo.og_image" property="og:image" :content="homepageConfig.settings.seo.og_image">
    </Head>

    <Layout>
        <div v-if="homepageConfig.settings.preloader.enabled" class="fixed inset-0 z-50 flex items-center justify-center bg-white dark:bg-surface-950">
            <img v-if="homepageConfig.settings.preloader.animation_url" :src="homepageConfig.settings.preloader.animation_url" alt="Loading" class="w-20 h-20 object-contain">
            <div v-else class="w-12 h-12 rounded-full border-4 border-primary-100 border-t-primary-600 animate-spin"></div>
        </div>

        <template v-for="section in enabledSections" :key="section.id">
            <section v-if="section.type === 'hero'" class="relative overflow-hidden bg-white dark:bg-surface-950 transition-colors duration-300">
                <div :class="asString(section.config.layout) === 'split' ? 'grid lg:grid-cols-2 gap-12 items-center text-left' : 'text-center'" class="relative z-10 max-w-7xl mx-auto px-6 pt-20 pb-32 md:pt-32 md:pb-48">
                    <div>
                        <div v-if="asBoolean(section.config.show_trust_badges, true)" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 border border-primary-100 text-primary-600 text-xs font-black uppercase tracking-widest mb-10 shadow-sm">
                            <span class="w-2 h-2 bg-primary-500 rounded-full animate-pulse"></span>
                            {{ asString(section.config.trust_badge_text, 'Next-Gen AI Technology') }}
                        </div>
                        <h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-gray-900 dark:text-white leading-[1.1] tracking-tight mb-8">
                            {{ headingParts(asString(section.config.headline, 'One Platform. Every AI Tool.'))[0] }}<br>
                            <span v-if="headingParts(asString(section.config.headline, 'One Platform. Every AI Tool.'))[1]" class="bg-gradient-to-r from-primary-600 via-accent-600 to-primary-600 bg-clip-text text-transparent">{{ headingParts(asString(section.config.headline, 'One Platform. Every AI Tool.'))[1] }}</span>
                        </h1>
                        <p class="text-lg md:text-xl text-gray-500 dark:text-gray-400 max-w-3xl mb-12 leading-relaxed font-medium" :class="asString(section.config.layout) === 'split' ? '' : 'mx-auto'">
                            {{ asString(section.config.subheadline) }}
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4" :class="asString(section.config.layout) === 'split' ? 'items-start justify-start' : 'items-center justify-center'">
                            <Link v-if="asString(section.config.primary_cta_text)" :href="asString(section.config.primary_cta_link, '/register')" :class="primaryButtonClass(asString(section.config.primary_cta_style, 'filled'))" class="w-full sm:w-auto px-10 py-5 rounded-2xl font-black text-lg transition-all hover:-translate-y-1">
                                {{ asString(section.config.primary_cta_text) }}
                            </Link>
                            <Link v-if="asString(section.config.secondary_cta_text)" :href="asString(section.config.secondary_cta_link, '/pricing')" :class="secondaryButtonClass(asString(section.config.secondary_cta_style, 'outline'))" class="w-full sm:w-auto px-10 py-5 rounded-2xl font-black text-lg transition-all">
                                {{ asString(section.config.secondary_cta_text) }}
                            </Link>
                        </div>
                        <div v-if="asItems(section.config.stats).length > 0" class="mt-24 pt-12 border-t border-gray-100 dark:border-surface-800 grid grid-cols-2 md:grid-cols-4 gap-8">
                            <div v-for="stat in asItems(section.config.stats)" :key="`${stat.number}_${stat.label}`">
                                <p class="text-3xl font-black text-gray-900 dark:text-white">{{ stat.number }}</p>
                                <p class="text-xs text-gray-400 font-black uppercase tracking-widest mt-1">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="asString(section.config.layout) === 'split' && asString(section.config.hero_media_url)" class="rounded-[2rem] overflow-hidden border border-gray-100 dark:border-surface-800 shadow-2xl">
                        <img :src="asString(section.config.hero_media_url)" alt="" class="w-full h-full object-cover">
                    </div>
                </div>
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 pointer-events-none opacity-50">
                    <div class="absolute top-0 left-0 w-[800px] h-[800px] bg-primary-100/50 rounded-full blur-[120px] -translate-x-1/2 -translate-y-1/2"></div>
                    <div class="absolute bottom-0 right-0 w-[600px] h-[600px] bg-accent-100/50 rounded-full blur-[120px] translate-x-1/3 translate-y-1/3"></div>
                </div>
            </section>

            <section v-else-if="section.type === 'features'" class="py-24 bg-gray-50 dark:bg-surface-900 transition-colors duration-300">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center mb-20">
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, 'Supercharge your workflow') }}</h2>
                        <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium">{{ sectionSubtitle(section) }}</p>
                    </div>
                    <div :class="asString(section.config.layout) === '2-column' ? 'lg:grid-cols-2' : 'lg:grid-cols-4'" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div v-for="item in asItems(section.config.items)" :key="`${item.title}_${item.icon}`" class="bg-white dark:bg-surface-800 p-10 rounded-[2.5rem] border border-gray-100 dark:border-surface-700 shadow-sm hover:shadow-xl hover:shadow-primary-500/5 transition-all group">
                            <img v-if="item.image_url" :src="String(item.image_url)" alt="" class="w-full h-32 object-cover rounded-2xl mb-8">
                            <div v-else class="w-14 h-14 bg-primary-50 dark:bg-primary-500/10 rounded-2xl flex items-center justify-center text-primary-600 dark:text-primary-400 mb-8 group-hover:scale-110 transition-transform">
                                <span class="text-xl font-black">{{ String(item.icon || 'AI').slice(0, 2).toUpperCase() }}</span>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white mb-3">{{ item.title }}</h3>
                            <p class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed font-medium">{{ item.description }}</p>
                        </div>
                    </div>
                    <div v-if="asString(section.config.cta_text) && asString(section.config.cta_link)" class="text-center mt-12">
                        <Link :href="asString(section.config.cta_link)" class="inline-flex px-8 py-4 rounded-2xl bg-primary-600 text-white font-black hover:bg-primary-500 transition-colors">{{ asString(section.config.cta_text) }}</Link>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'stats_bar'" class="py-16 bg-white dark:bg-surface-950 border-y border-gray-100 dark:border-surface-800">
                <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div v-for="item in asItems(section.config.items).length ? asItems(section.config.items) : asItems(section.config.stats)" :key="`${item.number}_${item.label}`">
                        <p class="text-4xl font-black text-gray-900 dark:text-white">{{ item.number }}</p>
                        <p class="text-xs text-gray-400 font-black uppercase tracking-widest mt-2">{{ item.label }}</p>
                    </div>
                </div>
            </section>

            <section v-else-if="section.type === 'cta_banner'" class="py-24 bg-white dark:bg-surface-950">
                <div :class="asString(section.config.width) === 'full' ? 'max-w-none rounded-none' : 'max-w-6xl rounded-[2.5rem]'" class="mx-auto px-6">
                    <div class="bg-gradient-to-r from-primary-600 to-accent-600 p-10 md:p-16 text-center shadow-2xl shadow-primary-600/20" :class="asString(section.config.width) === 'full' ? '' : 'rounded-[2.5rem]'">
                        <h2 class="text-3xl md:text-5xl font-black text-white mb-4">{{ asString(section.config.headline, sectionTitle(section, 'Ready to build with AI?')) }}</h2>
                        <p v-if="asString(section.config.subheadline)" class="text-white/80 max-w-2xl mx-auto mb-8">{{ asString(section.config.subheadline) }}</p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <Link v-if="asString(section.config.primary_text)" :href="asString(section.config.primary_link, '/register')" class="w-full sm:w-auto bg-white text-gray-900 px-8 py-4 rounded-2xl font-black hover:bg-gray-100 transition-colors">{{ asString(section.config.primary_text) }}</Link>
                            <Link v-if="asString(section.config.secondary_text)" :href="asString(section.config.secondary_link, '/pricing')" class="w-full sm:w-auto border-2 border-white/40 text-white px-8 py-4 rounded-2xl font-black hover:bg-white/10 transition-colors">{{ asString(section.config.secondary_text) }}</Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ Testimonials ═══ -->
            <section v-else-if="section.type === 'testimonials'" class="py-24 bg-gray-50 dark:bg-surface-900 transition-colors duration-300">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center mb-16">
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, 'What Our Users Say') }}</h2>
                        <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto">{{ sectionSubtitle(section) }}</p>
                    </div>
                    <!-- Live DB testimonials -->
                    <div v-if="getTestimonialsSlice(section).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="t in getTestimonialsSlice(section)"
                            :key="t.id"
                            class="bg-white dark:bg-surface-800 rounded-[2rem] border border-gray-100 dark:border-surface-700 p-8 flex flex-col gap-5 hover:shadow-xl hover:shadow-primary-500/5 transition-all"
                        >
                            <!-- Stars -->
                            <div class="flex items-center gap-0.5">
                                <svg v-for="(filled, i) in stars(t.rating)" :key="i" class="w-4 h-4" :class="filled ? 'text-yellow-400' : 'text-gray-200 dark:text-surface-700'" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                            <!-- Quote -->
                            <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed flex-1">&ldquo;{{ t.content }}&rdquo;</p>
                            <!-- Author -->
                            <div class="flex items-center gap-3 pt-4 border-t border-gray-100 dark:border-surface-700">
                                <div v-if="t.avatar" class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 dark:bg-surface-700 shrink-0">
                                    <img :src="resolveMediaUrl(t.avatar)" :alt="t.name" class="w-full h-full object-cover">
                                </div>
                                <div v-else class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 font-black text-sm shrink-0">
                                    {{ t.name.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ t.name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ [t.role, t.company].filter(Boolean).join(' · ') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Empty state (no DB entries yet) -->
                    <div v-else class="text-center py-16 text-gray-400 dark:text-gray-600">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <p class="font-medium">No testimonials yet. Add some from the admin panel.</p>
                    </div>
                </div>
            </section>

            <!-- ═══ FAQ ═══ -->
            <section v-else-if="section.type === 'faq'" class="py-24 bg-white dark:bg-surface-950 transition-colors duration-300">
                <div class="max-w-3xl mx-auto px-6">
                    <div class="text-center mb-16">
                        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, 'Frequently Asked Questions') }}</h2>
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
                        <p class="font-medium">No FAQs yet. Add some from the admin panel.</p>
                    </div>
                </div>
            </section>

            <!-- ═══ Other sections (newsletter, pricing, etc.) ═══ -->
            <section v-else-if="section.type === 'how_it_works' || section.type === 'tools_showcase' || section.type === 'pricing' || section.type === 'latest_posts' || section.type === 'newsletter' || section.type === 'integrations'" class="py-24 bg-gray-50 dark:bg-surface-900">
                <div class="max-w-7xl mx-auto px-6 text-center">
                    <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">{{ sectionTitle(section, 'Homepage Section') }}</h2>
                    <p v-if="sectionSubtitle(section)" class="text-gray-500 dark:text-gray-400 font-medium max-w-2xl mx-auto mb-10">{{ sectionSubtitle(section) }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div v-for="item in asItems(section.config.items).slice(0, Number(asString(section.config.max_items, '6')))" :key="`${item.title}_${item.label}`" class="bg-white dark:bg-surface-800 rounded-3xl border border-gray-100 dark:border-surface-700 p-8 text-left">
                            <h3 class="font-black text-gray-900 dark:text-white mb-2">{{ item.title || item.label || item.name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ item.description || item.text || item.number }}</p>
                        </div>
                    </div>
                    <form v-if="section.type === 'newsletter'" method="post" action="/newsletter/subscribe" class="max-w-xl mx-auto mt-10 flex flex-col sm:flex-row gap-3">
                        <input name="email" type="email" required placeholder="Enter your email" class="flex-1 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-2xl px-5 py-4 text-gray-900 dark:text-white">
                        <button type="submit" class="bg-primary-600 text-white px-8 py-4 rounded-2xl font-black hover:bg-primary-500 transition-colors">Subscribe</button>
                    </form>
                </div>
            </section>

            <section v-else-if="section.type === 'custom_html'" class="bg-white dark:bg-surface-950" v-html="asString(section.config.content)"></section>
        </template>

        <button v-if="homepageConfig.settings.scroll_to_top.enabled && showScrollButton" @click="scrollToTop" type="button" :class="homepageConfig.settings.scroll_to_top.position === 'left' ? 'left-6' : 'right-6'" class="fixed bottom-6 z-40 w-12 h-12 rounded-full bg-primary-600 text-white shadow-xl shadow-primary-600/30 hover:bg-primary-500 transition-colors">↑</button>

        <div v-if="homepageConfig.settings.cookie_consent.enabled && !cookieAccepted" class="fixed left-6 right-6 bottom-6 z-50 max-w-4xl mx-auto bg-white dark:bg-surface-900 border border-gray-100 dark:border-surface-700 rounded-2xl p-5 shadow-2xl flex flex-col md:flex-row md:items-center gap-4">
            <p class="flex-1 text-sm text-gray-600 dark:text-gray-300">{{ homepageConfig.settings.cookie_consent.message }}</p>
            <div class="flex items-center gap-3">
                <Link :href="homepageConfig.settings.cookie_consent.policy_url" class="text-sm font-bold text-primary-600 dark:text-primary-400">Learn more</Link>
                <button @click="acceptCookies" type="button" class="px-5 py-2.5 rounded-xl bg-primary-600 text-white text-sm font-bold hover:bg-primary-500 transition-colors">{{ homepageConfig.settings.cookie_consent.accept_text }}</button>
            </div>
        </div>

        <div v-if="homepageConfig.settings.chat_widget_embed" v-html="homepageConfig.settings.chat_widget_embed"></div>
    </Layout>
</template>
