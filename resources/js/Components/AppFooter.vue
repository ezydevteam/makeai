<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import SocialFollow from '@/Components/SocialFollow.vue'

type FooterBlockType = 'about_text' | 'menu_list' | 'contact_info' | 'social_icons' | 'newsletter' | 'custom_html' | 'recent_blog_posts' | 'ai_tool_categories' | 'legal_links' | 'language_switcher' | 'dark_mode' | 'trust_badges' | 'store_badges' | 'divider' | 'copyright_text' | 'payment_icons' | 'back_to_top'
type ConfigValue = string | number | boolean | null | string[]
type SocialDisplayMode = 'icons' | 'counts' | 'cards'
type HeadingStyle = 'default' | 'accent' | 'minimal'
type BottomAlignment = 'left' | 'center' | 'right' | 'between'

interface FooterBlock {
    id: string
    type: FooterBlockType
    enabled?: boolean
    config: Record<string, ConfigValue>
}

interface FooterColumn {
    id?: string
    width?: number
    title?: string
    subtitle?: string
    heading_style?: HeadingStyle
    blocks?: FooterBlock[]
}

interface FooterBottomColumn {
    id?: string
    title?: string
    blocks?: FooterBlock[]
}

interface FooterConfig {
    layout?: number
    columns?: Array<FooterColumn | FooterBlock[]>
    bottom_blocks?: FooterBlock[]
    bottom_columns?: FooterBottomColumn[]
    bottom_bar?: {
        copyright_text?: string
        menu_slug?: string | null
        show_payment_icons?: boolean
        payment_icons?: string[]
        show_back_to_top?: boolean
        layout_desktop?: number
        layout_tablet?: number
        layout_mobile?: number
        alignment_desktop?: BottomAlignment
        alignment_tablet?: BottomAlignment
        alignment_mobile?: BottomAlignment
        padding_desktop?: number
        padding_tablet?: number
        padding_mobile?: number
        border_top?: boolean
    }
}

interface MenuItem {
    id: number | string
    title: string
    url: string
    target?: string
}

interface MenuOption {
    slug: string
    items: MenuItem[]
}

interface FooterPost {
    title: string
    slug: string
    published_at?: string | null
}

interface FooterAiCategory {
    name: string
    slug: string
    tools_count?: number
}

interface FooterData {
    recentPosts?: FooterPost[]
    aiCategories?: FooterAiCategory[]
}

const page = usePage()
const { t } = useTranslate()

const footerConfig = computed(() => page.props.footerConfig as FooterConfig | null)
const footerData = computed(() => (page.props.footerData as FooterData | undefined) ?? {})
const globalMenus = computed(() => (page.props.globalMenus as MenuOption[] | undefined) ?? [])
const appName = computed(() => String(page.props.appName || ''))
const currentYear = new Date().getFullYear()

const footerColumns = computed<FooterColumn[]>(() => {
    const columns = footerConfig.value?.columns ?? []

    return columns.map((column, index) => {
        if (Array.isArray(column)) {
            return {
                id: `legacy_footer_column_${index + 1}`,
                width: undefined,
                title: '',
                subtitle: '',
                heading_style: 'default',
                blocks: column,
            }
        }

        return {
            id: column.id ?? `footer_column_${index + 1}`,
            width: column.width,
            title: column.title ?? '',
            subtitle: column.subtitle ?? '',
            heading_style: column.heading_style ?? 'default',
            blocks: column.blocks ?? [],
        }
    })
})

const enabledBottomBlocks = computed(() => {
    const blocks = (footerConfig.value?.bottom_blocks ?? []).filter((block) => block.enabled !== false)

    if (blocks.length) return blocks

    return [
        {
            id: 'legacy_copyright',
            type: 'copyright_text' as const,
            enabled: true,
            config: { text: footerConfig.value?.bottom_bar?.copyright_text ?? '' },
        },
        {
            id: 'legacy_payment_icons',
            type: 'payment_icons' as const,
            enabled: footerConfig.value?.bottom_bar?.show_payment_icons ?? true,
            config: { icons: footerConfig.value?.bottom_bar?.payment_icons ?? [] },
        },
        {
            id: 'legacy_back_to_top',
            type: 'back_to_top' as const,
            enabled: footerConfig.value?.bottom_bar?.show_back_to_top ?? true,
            config: { label: t('Back to top') },
        },
    ]
})

const splitBottomBlocks = (blocks: FooterBlock[]) => {
    const leftTypes: FooterBlockType[] = ['copyright_text']
    const left = blocks.filter((block) => leftTypes.includes(block.type))
    const right = blocks.filter((block) => !leftTypes.includes(block.type))

    return { left, right }
}

const enabledBottomColumns = computed<FooterBottomColumn[]>(() => {
    const columns = footerConfig.value?.bottom_columns ?? []

    if (columns.length === 2) {
        const leftBlocks = (columns[0]?.blocks ?? []).filter((block) => block.enabled !== false)
        const rightBlocks = (columns[1]?.blocks ?? []).filter((block) => block.enabled !== false)

        if (rightBlocks.length === 0 && leftBlocks.length > 1) {
            const split = splitBottomBlocks(leftBlocks)

            return [
                {
                    id: 'left',
                    title: columns[0]?.title ?? '',
                    blocks: split.left,
                },
                {
                    id: 'right',
                    title: columns[1]?.title ?? '',
                    blocks: split.right,
                },
            ]
        }

        return columns.map((column, index) => ({
            id: column.id ?? (index === 0 ? 'left' : 'right'),
            title: column.title ?? '',
            blocks: (column.blocks ?? []).filter((block) => block.enabled !== false),
        }))
    }

    const split = splitBottomBlocks(enabledBottomBlocks.value)

    return [
        {
            id: 'left',
            title: '',
            blocks: split.left,
        },
        {
            id: 'right',
            title: '',
            blocks: split.right,
        },
    ]
})

const parsedCopyright = computed(() => {
    const text = footerConfig.value?.bottom_bar?.copyright_text || ''

    return text.replace('{year}', currentYear.toString())
})

const layoutClass = computed(() => {
    const layout = footerConfig.value?.layout || footerColumns.value.length || 4

    if (layout === 1) return 'grid-cols-1'
    if (layout === 2) return 'grid-cols-1 md:grid-cols-2'
    if (layout === 3) return 'grid-cols-1 md:grid-cols-3'

    return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
})

const getMenu = (slug?: ConfigValue) => {
    if (typeof slug !== 'string' || slug === '') return null

    return globalMenus.value.find((menu) => menu.slug === slug) ?? null
}

const limitedPosts = (count: ConfigValue) => footerData.value.recentPosts?.slice(0, Number(count || 3)) ?? []
const limitedCategories = (count: ConfigValue) => footerData.value.aiCategories?.slice(0, Number(count || 6)) ?? []
const socialDisplayMode = (value: ConfigValue): SocialDisplayMode => {
    return value === 'icons' || value === 'counts' || value === 'cards' ? value : 'icons'
}

const withYear = (value: ConfigValue) => {
    return String(value ?? '').replace('{year}', currentYear.toString())
}

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const legalLinks = [
    { key: 'privacy', label: t('Privacy Policy'), href: '/privacy-policy' },
    { key: 'terms', label: t('Terms of Service'), href: '/terms' },
    { key: 'refund', label: t('Refund Policy'), href: '/refund-policy' },
    { key: 'contact', label: t('Contact'), href: '/contact' },
]

const enabledLegalLinks = (links: ConfigValue) => {
    if (!Array.isArray(links)) return legalLinks

    return legalLinks.filter((link) => links.includes(link.key))
}

const columnHeadingClass = (style?: HeadingStyle) => {
    if (style === 'accent') return 'border-l-2 border-primary-500 pl-3 rtl:border-l-0 rtl:border-r-2 rtl:pl-0 rtl:pr-3'
    if (style === 'minimal') return 'opacity-80'

    return ''
}

const bottomPaddingStyle = computed(() => ({
    '--footer-bottom-padding-mobile': `${Number(footerConfig.value?.bottom_bar?.padding_mobile ?? 20)}px`,
    '--footer-bottom-padding-tablet': `${Number(footerConfig.value?.bottom_bar?.padding_tablet ?? 24)}px`,
    '--footer-bottom-padding-desktop': `${Number(footerConfig.value?.bottom_bar?.padding_desktop ?? 32)}px`,
}))
</script>

<template>
    <footer v-if="footerConfig" class="mt-auto border-t border-gray-100 bg-white dark:border-surface-800 dark:bg-surface-900">
        <div class="mx-auto max-w-7xl px-6 py-16">
            <div class="grid gap-12" :class="layoutClass">
                <div v-for="(column, index) in footerColumns" :key="column.id ?? index" class="space-y-8">
                    <div v-if="column.title || column.subtitle" :class="columnHeadingClass(column.heading_style)">
                        <h3 v-if="column.title" class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ column.title }}</h3>
                        <p v-if="column.subtitle" class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ column.subtitle }}</p>
                    </div>

                    <template v-for="block in column.blocks?.filter((item) => item.enabled !== false)" :key="block.id">
                        <div v-if="block.type === 'about_text'" class="space-y-6">
                            <Link href="/" class="flex items-center gap-3">
                                <img v-if="block.config.logo" :src="String(block.config.logo)" :alt="String(block.config.alt || appName)" class="h-9 max-w-36 object-contain">
                                <template v-else>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary-600 to-accent-600">
                                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                                    </span>
                                    <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ appName }}</span>
                                </template>
                            </Link>
                            <p v-if="block.config.description" class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ block.config.description }}</p>
                        </div>

                        <div v-else-if="block.type === 'menu_list'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <ul class="space-y-4">
                                <template v-if="getMenu(block.config.menu_slug)">
                                    <li v-for="item in getMenu(block.config.menu_slug)?.items" :key="item.id">
                                        <a :href="item.url" :target="item.target" class="text-sm font-medium text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ item.title }}</a>
                                    </li>
                                </template>
                                <li v-else class="text-sm italic text-gray-400">{{ t('Menu not found') }}</li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'contact_info'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <ul class="space-y-4">
                                <li v-if="block.config.address" class="flex items-start gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <i class="ti ti-map-pin mt-0.5 shrink-0 text-lg text-primary-500"></i>
                                    <span>{{ block.config.address }}</span>
                                </li>
                                <li v-if="block.config.phone" class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <i class="ti ti-phone shrink-0 text-lg text-primary-500"></i>
                                    <a :href="`tel:${block.config.phone}`" class="transition-colors hover:text-primary-600">{{ block.config.phone }}</a>
                                </li>
                                <li v-if="block.config.email" class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <i class="ti ti-mail shrink-0 text-lg text-primary-500"></i>
                                    <a :href="`mailto:${block.config.email}`" class="transition-colors hover:text-primary-600">{{ block.config.email }}</a>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'newsletter'" class="rounded-xl border border-gray-100 bg-gray-50 p-6 dark:border-surface-700 dark:bg-surface-800">
                            <h4 v-if="block.config.title" class="mb-2 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <p v-if="block.config.description" class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ block.config.description }}</p>
                            <form method="post" action="/newsletter/subscribe" class="flex gap-2">
                                <input type="email" name="email" required :placeholder="t('Enter your email')" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 transition focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200">
                                <button type="submit" class="rounded-lg bg-primary-600 px-3 text-white transition hover:bg-primary-500" :aria-label="t('Subscribe')">
                                    <i class="ti ti-arrow-right"></i>
                                </button>
                            </form>
                        </div>

                        <div v-else-if="block.type === 'social_icons'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <SocialFollow :style="socialDisplayMode(block.config.display_mode)" />
                        </div>

                        <div v-else-if="block.type === 'recent_blog_posts'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <ul class="space-y-3">
                                <li v-for="post in limitedPosts(block.config.count)" :key="post.slug">
                                    <Link :href="`/blog/${post.slug}`" class="text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ post.title }}</Link>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'ai_tool_categories'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <ul class="space-y-3">
                                <li v-for="category in limitedCategories(block.config.count)" :key="category.slug">
                                    <Link :href="`/ai-tools/category/${category.slug}`" class="text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                                        {{ category.name }}
                                        <span v-if="category.tools_count !== undefined" class="text-xs text-gray-400">({{ category.tools_count }})</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'legal_links'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <ul class="space-y-3">
                                <li v-for="link in enabledLegalLinks(block.config.links)" :key="link.key">
                                    <Link :href="link.href" class="text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ t(link.label) }}</Link>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'trust_badges'" class="rounded-xl border border-primary-100 bg-primary-50 p-4 dark:border-primary-900/40 dark:bg-primary-900/10">
                            <h4 v-if="block.config.title" class="text-sm font-bold text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <p v-if="block.config.text" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ block.config.text }}</p>
                        </div>

                        <div v-else-if="block.type === 'custom_html'">
                            <h4 v-if="block.config.title" class="mb-6 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ block.config.title }}</h4>
                            <div class="prose prose-sm text-gray-500 dark:prose-invert dark:text-gray-400" v-html="block.config.content"></div>
                        </div>

                        <div v-else-if="block.type === 'divider'" class="border-t border-gray-100 dark:border-surface-800" :style="{ marginBlock: `${Number(block.config.spacing || 24) / 2}px` }"></div>
                    </template>
                </div>
            </div>

            <div v-if="footerConfig.bottom_bar" class="footer-bottom-grid mt-12 flex flex-col gap-4 dark:border-surface-800 md:flex-row" :class="footerConfig.bottom_bar.border_top !== false ? 'border-t border-gray-100' : ''" :style="bottomPaddingStyle">
                <div v-for="column in enabledBottomColumns" :key="column.id" class="footer-bottom-column" :class="column.id === 'right' ? 'footer-bottom-column-right' : 'footer-bottom-column-left'">
                    <div v-for="block in column.blocks" :key="block.id" class="footer-bottom-item">
                        <p v-if="block.type === 'copyright_text'" class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ withYear(block.config.text) }}</p>
                        <SocialFollow v-else-if="block.type === 'social_icons'" :style="socialDisplayMode(block.config.display_mode)" />
                        <div v-else-if="block.type === 'legal_links'" class="flex flex-wrap items-center gap-4">
                            <Link v-for="link in enabledLegalLinks(block.config.links)" :key="link.key" :href="link.href" class="text-xs font-medium text-gray-400 transition hover:text-primary-600 dark:text-gray-500 dark:hover:text-primary-400">{{ t(link.label) }}</Link>
                        </div>
                        <div v-else-if="block.type === 'custom_html'" class="text-xs text-gray-400 dark:text-gray-500" v-html="block.config.content"></div>
                        <span v-else-if="block.type === 'divider'" class="h-px w-full bg-gray-200 dark:bg-surface-700"></span>
                        <div v-else-if="block.type === 'payment_icons' && Array.isArray(block.config.icons) && block.config.icons.length" class="flex flex-wrap items-center gap-2">
                            <span v-for="icon in block.config.icons" :key="icon" class="flex h-6 items-center justify-center rounded border border-gray-100 bg-gray-50 px-2 text-[10px] font-black uppercase text-gray-400 dark:border-surface-700 dark:bg-surface-800">{{ icon.replace('_', ' ') }}</span>
                        </div>
                        <button v-else-if="block.type === 'back_to_top'" type="button" class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-50 text-gray-400 shadow-sm transition hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:hover:bg-primary-900/20" :aria-label="t('Back to top')" @click="scrollToTop">
                            <i class="ti ti-arrow-up"></i>
                        </button>
                    </div>
                    <div v-if="column.id === 'right' && footerConfig.bottom_bar.menu_slug && getMenu(footerConfig.bottom_bar.menu_slug)" class="footer-bottom-item">
                        <ul class="flex flex-wrap items-center justify-end gap-4">
                            <li v-for="item in getMenu(footerConfig.bottom_bar.menu_slug)?.items" :key="item.id">
                                <a :href="item.url" :target="item.target" class="text-xs font-medium text-gray-400 transition-colors hover:text-primary-600 dark:text-gray-500 dark:hover:text-primary-400">{{ item.title }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.footer-bottom-grid {
    padding-block: var(--footer-bottom-padding-mobile);
}

.footer-bottom-column {
    display: flex;
    flex: 1 1 100%;
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
}

.footer-bottom-column-left {
    justify-content: flex-start;
    text-align: start;
}

.footer-bottom-column-right {
    justify-content: flex-start;
    text-align: start;
}

.footer-bottom-item {
    display: inline-flex;
    align-items: center;
    min-width: 0;
}

@media (min-width: 768px) {
    .footer-bottom-column {
        flex-basis: calc(50% - 0.5rem);
        flex-direction: row;
        align-items: center;
    }

    .footer-bottom-column-right {
        justify-content: flex-end;
        text-align: end;
    }
}

@media (min-width: 768px) {
    .footer-bottom-grid {
        padding-block: var(--footer-bottom-padding-tablet);
    }
}

@media (min-width: 1280px) {
    .footer-bottom-grid {
        padding-block: var(--footer-bottom-padding-desktop);
    }
}
</style>
