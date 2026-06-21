<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import SocialFollow from '@/Components/SocialFollow.vue'

type FooterBlockType = 'about_text' | 'menu_list' | 'contact_info' | 'social_icons' | 'newsletter' | 'custom_html' | 'recent_blog_posts' | 'ai_tool_categories' | 'legal_links' | 'custom_link' | 'image' | 'language_switcher' | 'dark_mode' | 'trust_badges' | 'store_badges' | 'divider' | 'copyright_text' | 'payment_icons' | 'back_to_top'
type ConfigValue = string | number | boolean | null | string[]
type SocialDisplayMode = 'icons' | 'counts' | 'cards'
type HeadingStyle = 'default' | 'accent' | 'minimal'

interface FooterBlock {
    id: string
    type: FooterBlockType
    enabled?: boolean
    config: Record<string, ConfigValue>
}

interface FooterColumn {
    id?: string
    title?: string
    subtitle?: string
    blocks?: FooterBlock[]
}

interface FooterBottomColumn {
    id?: string
    title?: string
    blocks?: FooterBlock[]
}

interface FooterConfig {
    layout?: number
    background?: { color?: string; image_url?: string; overlay_opacity?: number }
    custom_css?: string
    container_width?: string
    column_flex?: string
    text_color?: string
    heading_style?: HeadingStyle
    heading_color?: string
    heading_font_weight?: string
    heading_text_transform?: string
    heading_font_size?: string
    columns?: Array<FooterColumn | FooterBlock[]>
    bottom_blocks?: FooterBlock[]
    bottom_columns?: FooterBottomColumn[]
    bottom_bar?: {
        copyright_text?: string
        menu_slug?: string | null
        show_social_icons?: boolean
        show_payment_icons?: boolean
        payment_icons?: string[]
        show_back_to_top?: boolean
        border_top?: boolean
        border_color?: string
        border_width?: number
        padding?: number
        bg_color?: string
        text_color?: string
        column_flex?: string
    }
}

interface MenuItem {
    id: number | string
    title?: string
    label?: string
    url: string
    final_url?: string
    parent_id?: number | string | null
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

interface Branding {
    site_description?: string
    site_support_email?: string
    site_logo_light?: string
    site_logo_dark?: string
}

const isTruthySetting = (value: unknown, fallback = false) => {
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return value !== 0
    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase()
        if (['true', '1', 'yes', 'on'].includes(normalized)) return true
        if (['false', '0', 'no', 'off', ''].includes(normalized)) return false
    }

    return fallback
}

interface SimpleFooterSettings {
    layout?: string
    brand_title?: string
    brand_description?: string
    show_newsletter?: boolean
    newsletter_title?: string
    newsletter_description?: string
    newsletter_placeholder?: string
    newsletter_button_label?: string
    show_social_icons?: boolean
    contact_title?: string
    contact_email?: string
    contact_phone?: string
    contact_address?: string
    contact_details?: string
    show_payment_icons?: boolean
    payment_icons?: string
    show_bottom_social_icons?: boolean
    show_back_to_top?: boolean
    back_to_top_label?: string
    back_to_top_icon?: string
    back_to_top_shape?: string
    bottom_menu?: string
    bottom_bar_show_border?: boolean
    bottom_bar_border_color?: string
    bottom_bar_border_width?: number
    bottom_bar_bg_color?: string
    bottom_bar_text_color?: string
    bottom_bar_padding?: number
    copyright_text?: string
    menu_title_1?: string
    menu_title_2?: string
    menu_title_3?: string
    menu_column_1?: string
    menu_column_2?: string
    menu_column_3?: string
}

const page = usePage()
const { t } = useTranslate()
const { isDark, toggleDark } = useTheme()

const blockFilter = (block: FooterBlock) => block.enabled !== false
const bottomBarBlockTypes: FooterBlockType[] = ['copyright_text', 'social_icons', 'payment_icons', 'back_to_top']

const branding = computed(() => (page.props.branding as Branding | undefined) ?? {})
const frontendFooterSettings = computed(() => (page.props.frontendFooterSettings as SimpleFooterSettings | undefined) ?? {})
const resolveFooterMenuSlug = (value: string | undefined, fallback: string) => {
    if (!value) return fallback
    if (value === 'company') return 'footer-company'
    if (value === 'support') return 'footer-support'
    if (value === 'legal') return 'footer-legal'
    return value
}
const footerMenuTitle = (value: string | undefined, fallback: string) => value?.trim() || fallback
const footerTextOrFallback = (value: string | undefined, fallback: string) => value?.trim() || fallback
const paymentIconList = (value: string | undefined) => {
    if (typeof value !== 'string') return []
    const icon = value.trim()
    return icon ? [icon] : []
}
const buildSimplifiedFooterConfig = (settings: SimpleFooterSettings): FooterConfig => {
    const layout = settings.layout ?? 'columns'
    const showNewsletter = isTruthySetting(settings.show_newsletter, false)
    const showSocialIcons = isTruthySetting(settings.show_social_icons, true)
    const showPaymentIcons = isTruthySetting(settings.show_payment_icons, true)
    const showBottomSocialIcons = isTruthySetting(settings.show_bottom_social_icons, false)
    const showBackToTop = isTruthySetting(settings.show_back_to_top, true)
    const showBottomMenu = Boolean(settings.bottom_menu?.trim())
    const copyrightText = settings.copyright_text || t('© {year} :app. All rights reserved.', { app: String(page.props.appName || '') })
    const paymentIcons = paymentIconList(settings.payment_icons)
    const brandTitle = settings.brand_title?.trim() || ''
    const brandDescription = footerTextOrFallback(
        settings.brand_description,
        branding.value.site_description || '',
    )
    const contactTitle = footerTextOrFallback(settings.contact_title, t('Contact'))
    const contactEmail = settings.contact_email?.trim() || branding.value.site_support_email || ''
    const newsletterTitle = footerTextOrFallback(settings.newsletter_title, t('Stay Updated'))
    const newsletterDescription = footerTextOrFallback(
        settings.newsletter_description,
        t('Get product news and launch updates in your inbox.'),
    )
    const newsletterPlaceholder = footerTextOrFallback(settings.newsletter_placeholder, t('Enter your email'))
    const newsletterButtonLabel = footerTextOrFallback(settings.newsletter_button_label, t('Subscribe'))
    const menuTitles = [
        footerMenuTitle(settings.menu_title_1, t('Company')),
        footerMenuTitle(settings.menu_title_2, t('Support')),
        footerMenuTitle(settings.menu_title_3, t('Legal')),
    ]

    const aboutBlock: FooterBlock = {
        id: 'simple_footer_about',
        type: 'about_text',
        enabled: true,
        config: {
            title: brandTitle,
            description: brandDescription,
            show_social_icon: showSocialIcons,
        },
    }

    const newsletterBlock: FooterBlock = {
        id: 'simple_footer_newsletter',
        type: 'newsletter',
        enabled: true,
        config: {
            title: newsletterTitle,
            description: newsletterDescription,
            placeholder: newsletterPlaceholder,
            button_text: newsletterButtonLabel,
        },
    }

    const contactBlock: FooterBlock = {
        id: 'simple_footer_contact',
        type: 'contact_info',
        enabled: true,
        config: {
            title: contactTitle,
            email: contactEmail,
            phone: settings.contact_phone?.trim() || '',
            address: settings.contact_address?.trim() || '',
            details: settings.contact_details?.trim() || '',
        },
    }

    const menuBlocks: FooterBlock[] = [
        {
            id: 'simple_footer_menu_1',
            type: 'menu_list',
            enabled: true,
            config: {
                title: menuTitles[0],
                menu_slug: resolveFooterMenuSlug(settings.menu_column_1, 'footer-company'),
            },
        },
        {
            id: 'simple_footer_menu_2',
            type: 'menu_list',
            enabled: true,
            config: {
                title: menuTitles[1],
                menu_slug: resolveFooterMenuSlug(settings.menu_column_2, 'footer-support'),
            },
        },
        {
            id: 'simple_footer_menu_3',
            type: 'menu_list',
            enabled: true,
            config: {
                title: menuTitles[2],
                menu_slug: resolveFooterMenuSlug(settings.menu_column_3, 'footer-legal'),
            },
        },
    ]

    let columns: FooterColumn[] = []
    let layoutColumns = 4

    if (layout === 'simple') {
        layoutColumns = 2
        columns = [
            {
                id: 'footer_column_1',
                blocks: [aboutBlock],
            },
            {
                id: 'footer_column_2',
                blocks: [menuBlocks[0], ...(showNewsletter ? [newsletterBlock] : [contactBlock])],
            },
        ]
    } else if (layout === 'minimal') {
        layoutColumns = 1
        columns = [
            {
                id: 'footer_column_1',
                blocks: [aboutBlock],
            },
        ]
    } else if (layout === 'company') {
        layoutColumns = 4
        columns = [
            {
                id: 'footer_column_1',
                blocks: [aboutBlock, contactBlock],
            },
            { id: 'footer_column_2', blocks: [menuBlocks[0]] },
            { id: 'footer_column_3', blocks: [menuBlocks[1]] },
            { id: 'footer_column_4', blocks: [menuBlocks[2]] },
        ]
    } else if (layout === 'newsletter') {
        layoutColumns = 4
        columns = [
            {
                id: 'footer_column_1',
                blocks: [aboutBlock],
            },
            { id: 'footer_column_2', blocks: [menuBlocks[0]] },
            { id: 'footer_column_3', blocks: [menuBlocks[1]] },
            {
                id: 'footer_column_4',
                blocks: [menuBlocks[2], ...(showNewsletter ? [newsletterBlock] : [contactBlock])],
            },
        ]
    } else if (layout === 'stacked') {
        layoutColumns = 3
        columns = [
            {
                id: 'footer_column_1',
                blocks: [aboutBlock],
            },
            {
                id: 'footer_column_2',
                blocks: [menuBlocks[0], menuBlocks[1], menuBlocks[2]],
            },
            {
                id: 'footer_column_3',
                blocks: [showNewsletter ? newsletterBlock : contactBlock],
            },
        ]
    } else {
        layoutColumns = 4
        columns = [
            {
                id: 'footer_column_1',
                blocks: [aboutBlock],
            },
            { id: 'footer_column_2', blocks: [menuBlocks[0]] },
            { id: 'footer_column_3', blocks: [menuBlocks[1]] },
            {
                id: 'footer_column_4',
                blocks: [menuBlocks[2], ...(showNewsletter ? [newsletterBlock] : [contactBlock])],
            },
        ]
    }

    return {
        layout: layoutColumns,
        container_width: '1280px',
        columns,
        bottom_blocks: [
            {
                id: 'simple_bottom_copyright',
                type: 'copyright_text',
                enabled: true,
                config: { text: copyrightText },
            },
            ...(showBottomSocialIcons ? [{
                id: 'simple_bottom_social_icons',
                type: 'social_icons' as const,
                enabled: true,
                config: { display_mode: 'icons' },
            }] : []),
            ...(showPaymentIcons ? [{
                id: 'simple_bottom_payment_icons',
                type: 'payment_icons' as const,
                enabled: true,
                config: { icons: paymentIcons },
            }] : []),
            ...(showBackToTop ? [{
                id: 'simple_bottom_back_to_top',
                type: 'back_to_top' as const,
                enabled: true,
                config: {
                    label: settings.back_to_top_label?.trim() || '',
                    icon: settings.back_to_top_icon?.trim() || 'ti ti-arrow-up',
                    shape: settings.back_to_top_shape?.trim() || 'rounded',
                },
            }] : []),
        ],
        bottom_columns: [
            {
                id: 'left',
                title: t('Left Column'),
                blocks: [{
                    id: 'simple_bottom_left_copyright',
                    type: 'copyright_text',
                    enabled: true,
                    config: { text: copyrightText },
                }],
            },
            {
                id: 'right',
                title: t('Right Column'),
                blocks: [
                    ...(showBottomSocialIcons ? [{
                        id: 'simple_bottom_right_social_icons',
                        type: 'social_icons' as const,
                        enabled: true,
                        config: { display_mode: 'icons' },
                    }] : []),
                    ...(showPaymentIcons ? [{
                        id: 'simple_bottom_right_payment_icons',
                        type: 'payment_icons' as const,
                        enabled: true,
                        config: { icons: paymentIcons },
                    }] : []),
                    ...(showBackToTop ? [{
                        id: 'simple_bottom_right_back_to_top',
                        type: 'back_to_top' as const,
                        enabled: true,
                        config: {
                            label: settings.back_to_top_label?.trim() || '',
                            icon: settings.back_to_top_icon?.trim() || 'ti ti-arrow-up',
                            shape: settings.back_to_top_shape?.trim() || 'rounded',
                        },
                    }] : []),
                ],
            },
        ],
        bottom_bar: {
            copyright_text: copyrightText,
            menu_slug: showBottomMenu ? resolveFooterMenuSlug(settings.bottom_menu, '') : null,
            show_social_icons: showBottomSocialIcons,
            show_payment_icons: showPaymentIcons,
            payment_icons: paymentIcons,
            show_back_to_top: showBackToTop,
            border_top: isTruthySetting(settings.bottom_bar_show_border, true),
            border_color: settings.bottom_bar_border_color?.trim() || '',
            border_width: Number(settings.bottom_bar_border_width ?? 1),
            padding: Number(settings.bottom_bar_padding ?? 32),
            bg_color: settings.bottom_bar_bg_color?.trim() || '',
            text_color: settings.bottom_bar_text_color?.trim() || '',
        },
    }
}
const footerConfig = computed(() => buildSimplifiedFooterConfig(frontendFooterSettings.value))
const footerData = computed(() => (page.props.footerData as FooterData | undefined) ?? {})
const globalMenus = computed(() => (page.props.globalMenus as MenuOption[] | undefined) ?? [])
const appName = computed(() => String(page.props.appName || ''))
const currentYear = new Date().getFullYear()
const footerLogo = computed(() => isDark.value ? (branding.value.site_logo_dark || branding.value.site_logo_light || '') : (branding.value.site_logo_light || branding.value.site_logo_dark || ''))

const footerColumns = computed<FooterColumn[]>(() => {
    const columns = footerConfig.value?.columns ?? []

    return columns.map((column, index) => {
        let blocks: FooterBlock[]
        if (Array.isArray(column)) {
            blocks = column
        } else {
            blocks = column.blocks ?? []
        }
        return {
            id: Array.isArray(column) ? `legacy_footer_column_${index + 1}` : (column.id ?? `footer_column_${index + 1}`),
            title: Array.isArray(column) ? '' : (column.title ?? ''),
            subtitle: Array.isArray(column) ? '' : (column.subtitle ?? ''),
            blocks: blocks.filter(blockFilter),
        }
    })
})

const enabledBottomColumns = computed<FooterBottomColumn[]>(() => {
    const columns = footerConfig.value?.bottom_columns ?? []

    return columns.map((column, index) => ({
        id: column.id ?? (index === 0 ? 'left' : 'right'),
        title: column.title ?? '',
        blocks: (column.blocks ?? []).filter((block) => blockFilter(block) && bottomBarBlockTypes.includes(block.type)),
    }))
})

const hasFooterContent = computed(() => {
    const hasColumns = footerColumns.value.some((column) => (column.blocks ?? []).length > 0)
    const hasBottom = enabledBottomColumns.value.some((column) => (column.blocks ?? []).length > 0)
    const hasBottomMenu = Boolean(footerConfig.value?.bottom_bar?.menu_slug && topMenuItems(footerConfig.value.bottom_bar.menu_slug).length)
    return hasColumns || hasBottom || hasBottomMenu
})

const parsedCopyright = computed(() => (footerConfig.value?.bottom_bar?.copyright_text || '').replace('{year}', currentYear.toString()))

const containerClass = computed(() => {
    const w = footerConfig.value?.container_width ?? '1280px'
    if (w === 'full') return 'w-full px-4 sm:px-6'
    if (w === '1080px' || w === 'boxed') return 'mx-auto w-full max-w-[1080px] px-4 sm:px-6'
    if (w === '1536px') return 'mx-auto w-full max-w-[1536px] px-4 sm:px-6'
    return 'mx-auto w-full max-w-7xl px-4 sm:px-6'
})

const layoutClass = computed(() => {
    const layout = footerConfig.value?.layout || footerColumns.value.length || 4
    if (layout === 1) return 'grid-cols-1'
    if (layout === 2) return 'grid-cols-1 md:grid-cols-2'
    if (layout === 3) return 'grid-cols-1 md:grid-cols-3'
    return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
})

const footerColFlexClass = (index: number, total: number) => {
    const flex = footerConfig.value?.column_flex ?? 'default'
    if (flex === 'column-1') return index === 0 ? 'flex-1 min-w-0' : ''
    if (flex === 'column-2') return index === 1 ? 'flex-1 min-w-0' : ''
    if (flex === 'column-3') return index === 2 ? 'flex-1 min-w-0' : ''
    if (flex === 'column-4') return index === 3 ? 'flex-1 min-w-0' : ''
    return index === 0 ? 'flex-1 min-w-0' : ''
}

const footerTextStyle = computed(() => {
    const style: Record<string, string> = {}
    if (footerConfig.value?.text_color) style.color = footerConfig.value.text_color
    return style
})
const footerMenuLinkStyle = computed(() => {
    const style: Record<string, string> = {}
    if (footerConfig.value?.text_color) style.color = footerConfig.value.text_color
    return style
})

const headingStyleVars = computed(() => {
    const style: Record<string, string> = {}
    if (footerConfig.value?.heading_color) style['--footer-heading-color'] = footerConfig.value.heading_color
    if (footerConfig.value?.heading_font_weight) style['--footer-heading-weight'] = footerConfig.value.heading_font_weight
    if (footerConfig.value?.heading_text_transform) style['--footer-heading-transform'] = footerConfig.value.heading_text_transform
    if (footerConfig.value?.heading_font_size) style['--footer-heading-size'] = footerConfig.value.heading_font_size
    return style
})

const footerBackgroundStyle = computed(() => {
    const bg = footerConfig.value?.background
    if (!bg) return {}
    const style: Record<string, string> = {}
    if (bg.color) style.backgroundColor = bg.color
    if (bg.image_url) { style.backgroundImage = `url(${bg.image_url})`; style.backgroundSize = 'cover'; style.backgroundPosition = 'center' }
    if (bg.image_url && (bg.overlay_opacity ?? 0) > 0) style['--footer-bg-overlay'] = `rgba(0,0,0,${Number(bg.overlay_opacity)})`
    return style
})

const bottomBarStyle = computed(() => {
    const style: Record<string, string> = {}
    const bar = footerConfig.value?.bottom_bar
    const pad = Number(bar?.padding ?? 32)
    style['--footer-bottom-padding'] = `${pad}px`
    style['--footer-bottom-border-width'] = `${Math.max(1, Number(bar?.border_width ?? 1))}px`
    if (bar?.bg_color) style.backgroundColor = bar.bg_color
    if (bar?.text_color) style.color = bar.text_color
    if (bar?.border_color) style.borderTopColor = bar.border_color
    return style
})
const bottomBarLinkStyle = computed(() => {
    const style: Record<string, string> = {}
    const bar = footerConfig.value?.bottom_bar
    if (bar?.text_color) style.color = bar.text_color
    return style
})
const bottomBarSocialIconStyle = computed(() => {
    const style: Record<string, string> = {}
    const bar = footerConfig.value?.bottom_bar
    if (bar?.text_color) style.color = bar.text_color
    return style
})

const bottomColFlexClass = (col: 'left' | 'right') => {
    const flex = footerConfig.value?.bottom_bar?.column_flex ?? 'default'
    if (flex === col || (flex === 'default' && col === 'left')) return 'flex-1 min-w-0'
    return ''
}

const getMenu = (slug?: ConfigValue) => {
    if (typeof slug !== 'string' || slug === '') return null
    return globalMenus.value.find((menu) => menu.slug === slug) ?? null
}

const visibleMenuItems = (menu: MenuOption | null) => {
    if (!menu) return []
    const items = menu.items?.filter((item) => item.is_active !== false) || []
    const loggedIn = Boolean(page.props.auth?.user)
    const isPro = page.props.auth?.user?.subscription_status === 'active'

    return items.filter((item: any) => {
        const rule = item.requires_auth ?? 'none'
        if (rule === 'guest') return !loggedIn
        if (rule === 'auth') return loggedIn
        if (rule === 'pro') return isPro
        return true
    })
}
const menuItemId = (item: MenuItem) => item.id ?? item.url
const menuItemHref = (item: MenuItem) => String(item.final_url || item.url || '#')
const menuItemLabel = (item: MenuItem) => item.label || item.title || ''
const topMenuItems = (slug?: string | null) => visibleMenuItems(getMenu(slug)).filter((item) => !item.parent_id)

const limitedPosts = (count: ConfigValue) => footerData.value.recentPosts?.slice(0, Number(count || 3)) ?? []
const limitedCategories = (count: ConfigValue) => footerData.value.aiCategories?.slice(0, Number(count || 6)) ?? []
const socialDisplayMode = (value: ConfigValue): SocialDisplayMode => {
    return value === 'icons' || value === 'counts' || value === 'cards' ? value : 'icons'
}
const showSocialIcon = (value: ConfigValue) => value === true
const paymentIconSrc = (value: ConfigValue) => {
    if (typeof value !== 'string') return ''
    const icon = value.trim()
    if (icon.startsWith('http://') || icon.startsWith('https://') || icon.startsWith('/') || icon.startsWith('data:')) {
        return icon
    }
    if (icon.includes('/') || icon.includes('\\') || /\.[a-z0-9]{2,5}$/i.test(icon)) {
        return `/storage/${icon.replaceAll('\\', '/')}`
    }
    return ''
}
const paymentIconLabel = (value: ConfigValue) => {
    if (typeof value !== 'string') return t('Payment')

    const labels: Record<string, string> = {
        visa: 'Visa',
        mastercard: 'Mastercard',
        paypal: 'PayPal',
        stripe: 'Stripe',
        amex: 'Amex',
        apple_pay: 'Apple Pay',
        google_pay: 'Google Pay',
    }

    const normalized = value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_')
    return labels[normalized] ?? value
}
const paymentBadgeClass = (value: ConfigValue) => {
    const normalized = typeof value === 'string' ? value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_') : ''

    const classes: Record<string, string> = {
        visa: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20',
        mastercard: 'bg-orange-50 text-orange-700 ring-orange-100 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-500/20',
        paypal: 'bg-sky-50 text-sky-700 ring-sky-100 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20',
        stripe: 'bg-violet-50 text-violet-700 ring-violet-100 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20',
        amex: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
        apple_pay: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-surface-800 dark:text-gray-200 dark:ring-surface-700',
        google_pay: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20',
    }

    return classes[normalized] ?? 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-surface-800 dark:text-gray-200 dark:ring-surface-700'
}

const withYear = (value: ConfigValue) => String(value ?? '').replace('{year}', currentYear.toString())

const scrollToTop = () => { window.scrollTo({ top: 0, behavior: 'smooth' }) }

const backToTopShapeClass = (shape: ConfigValue, hasLabel: boolean) => {
    if (shape === 'square') return 'h-8 w-8 rounded-none px-0'
    if (shape === 'pill') return hasLabel ? 'rounded-full px-3' : 'h-8 w-8 rounded-full px-0'
    if (shape === 'circle') return 'h-8 w-8 rounded-full px-0'
    return hasLabel ? 'rounded-lg' : 'h-8 w-8 rounded-lg px-0'
}

const backToTopStyle = (config: Record<string, ConfigValue>) => {
    const style: Record<string, string> = {}
    if (typeof config.bg_color === 'string' && config.bg_color) style.backgroundColor = config.bg_color
    if (typeof config.text_color === 'string' && config.text_color) style.color = config.text_color
    return style
}

const backToTopIcon = (config: Record<string, ConfigValue>) => {
    return typeof config.icon === 'string' && config.icon ? config.icon : 'ti ti-arrow-up'
}

const legalLinks = [
    { key: 'privacy', label: t('Privacy Policy'), href: '/privacy-policy' },
    { key: 'terms', label: t('Terms of Service'), href: '/terms' },
    { key: 'cookies', label: t('Cookie Preferences'), href: '#' },
    { key: 'refund', label: t('Refund Policy'), href: '/refund-policy' },
    { key: 'contact', label: t('Contact'), href: '/contact' },
]

const enabledLegalLinks = (links: ConfigValue) => {
    if (!Array.isArray(links)) return legalLinks
    return legalLinks.filter((link) => links.includes(link.key))
}

const customLinkHref = (config: Record<string, ConfigValue>) => {
    if (config.link_type === 'page' && typeof config.page_slug === 'string' && config.page_slug) {
        return `/${config.page_slug}`
    }
    if (config.link_type === 'tool_category' && typeof config.tool_category_slug === 'string' && config.tool_category_slug) {
        return `/ai-tools/category/${config.tool_category_slug}`
    }
    if (config.link_type === 'custom' && typeof config.custom_url === 'string') {
        return config.custom_url
    }
    return ''
}

const canShowCustomLink = (config: Record<string, ConfigValue>) => {
    const access = typeof config.access === 'string' ? config.access : 'all'
    const loggedIn = Boolean(page.props.auth?.user)
    const isFreeUser = loggedIn && page.props.auth?.user?.subscription_status !== 'active'

    if (access === 'logged_in') return loggedIn
    if (access === 'free') return isFreeUser
    return true
}

const customLinkClass = (config: Record<string, ConfigValue>) => {
    const displayMode = typeof config.display_mode === 'string' ? config.display_mode : 'vertical'
    return displayMode === 'horizontal'
        ? 'inline-flex items-center'
        : 'block'
}
</script>

<template>
    <component v-if="footerConfig?.custom_css" is="style">{{ footerConfig.custom_css }}</component>
    <footer v-if="footerConfig && hasFooterContent" class="mt-auto border-t border-gray-100 bg-white dark:border-surface-800 dark:bg-surface-900" :style="[footerBackgroundStyle, footerTextStyle, headingStyleVars]">
        <div class="footer-section-overlay py-12" :class="containerClass">
            <div class="grid gap-12" :class="layoutClass">
                <div v-for="(column, index) in footerColumns" :key="column.id ?? index" class="space-y-8" :class="footerColFlexClass(index, footerColumns.length)">
                    <div v-if="column.title || column.subtitle" class="footer-heading">
                        <h3 v-if="column.title" class="footer-heading-title">{{ column.title }}</h3>
                        <p v-if="column.subtitle" class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ column.subtitle }}</p>
                    </div>

                    <template v-for="block in column.blocks?.filter((item) => item.enabled !== false)" :key="block.id">
                        <div v-if="block.type === 'about_text'" class="space-y-6">
                            <Link href="/" class="flex items-center gap-3">
                                <img v-if="footerLogo" :src="footerLogo" :alt="appName" class="h-9 max-w-36 object-contain">
                                <template v-else>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary-600 to-accent-600">
                                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                                    </span>
                                    <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ appName }}</span>
                                </template>
                            </Link>
                            <h4 v-if="block.config.title" class="footer-heading-title">{{ block.config.title }}</h4>
                            <p v-if="block.config.description" class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ block.config.description }}</p>
                            <SocialFollow v-if="showSocialIcon(block.config.show_social_icon)" display-mode="icons" />
                        </div>

                        <div v-else-if="block.type === 'menu_list'">
                            <h4 v-if="block.config.title" class="footer-heading-title mb-6">{{ block.config.title }}</h4>
                            <ul class="space-y-4">
                                <template v-if="visibleMenuItems(getMenu(block.config.menu_slug)).length">
                                    <li v-for="item in visibleMenuItems(getMenu(block.config.menu_slug))" :key="item.id">
                                        <a :href="item.final_url || item.url" :target="item.target" class="footer-nav-link text-sm font-medium" :style="footerMenuLinkStyle">{{ item.label }}</a>
                                    </li>
                                </template>
                                <li v-else class="text-sm italic text-gray-400">{{ t('Menu not found') }}</li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'contact_info'" class="rounded-2xl border border-gray-100 bg-gray-50/80 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                            <h4 v-if="block.config.title" class="footer-heading-title mb-6">{{ block.config.title }}</h4>
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
                            <p v-if="block.config.details" class="pt-4 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ block.config.details }}</p>
                        </div>

                        <div v-else-if="block.type === 'newsletter'" class="rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white p-6 shadow-sm dark:border-primary-500/20 dark:bg-linear-to-br dark:from-primary-500/10 dark:to-surface-800">
                            <h4 v-if="block.config.title" class="mb-2 footer-heading-title">{{ block.config.title }}</h4>
                            <p v-if="block.config.description" class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ block.config.description }}</p>
                            <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-2 sm:flex-row">
                                <input type="email" name="email" required :placeholder="String(block.config.placeholder || t('Enter your email'))" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 transition focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200">
                                <button type="submit" class="rounded-lg btn-primary justify-center transition sm:self-start" :aria-label="String(block.config.button_text || t('Subscribe'))">
                                    <span>{{ block.config.button_text || t('Subscribe') }}</span>
                                </button>
                            </form>
                        </div>

                        <div v-else-if="block.type === 'social_icons'">
                            <h4 v-if="block.config.title" class="mb-6 footer-heading-title">{{ block.config.title }}</h4>
                            <SocialFollow :display-mode="socialDisplayMode(block.config.display_mode)" />
                        </div>

                        <div v-else-if="block.type === 'recent_blog_posts'">
                            <h4 v-if="block.config.title" class="mb-6 footer-heading-title">{{ block.config.title }}</h4>
                            <ul class="space-y-3">
                                <li v-for="post in limitedPosts(block.config.count)" :key="post.slug">
                                    <Link :href="`/blog/${post.slug}`" class="text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ post.title }}</Link>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'ai_tool_categories'">
                            <h4 v-if="block.config.title" class="mb-6 footer-heading-title">{{ block.config.title }}</h4>
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
                            <h4 v-if="block.config.title" class="mb-6 footer-heading-title">{{ block.config.title }}</h4>
                            <ul class="space-y-3">
                                <li v-for="link in enabledLegalLinks(block.config.links)" :key="link.key">
                                    <Link :href="link.href" class="text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ t(link.label) }}</Link>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="block.type === 'custom_link' && canShowCustomLink(block.config)">
                            <Link v-if="customLinkHref(block.config)" :href="customLinkHref(block.config)" :target="typeof block.config.target === 'string' ? block.config.target : '_self'" class="text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400" :class="customLinkClass(block.config)">
                                {{ block.config.label }}
                            </Link>
                        </div>

                        <div v-else-if="block.type === 'image'">
                            <h4 v-if="block.config.title" class="mb-4 footer-heading-title">{{ block.config.title }}</h4>
                            <component :is="block.config.link ? 'a' : 'div'" :href="block.config.link || undefined" :target="typeof block.config.target === 'string' ? block.config.target : '_self'" class="inline-flex max-w-full">
                                <img v-if="block.config.image_url" :src="String(block.config.image_url)" :alt="block.config.title || appName" class="object-contain" :style="{ width: `${Number(block.config.width || 120)}px`, height: `${Number(block.config.height || 40)}px` }">
                            </component>
                        </div>

                        <div v-else-if="block.type === 'trust_badges'" class="rounded-xl border border-primary-100 bg-primary-50 p-4 dark:border-primary-900/40 dark:bg-primary-900/10">
                            <h4 v-if="block.config.title" class="footer-heading-title text-sm font-bold text-gray-900 dark:text-white" style="color: inherit">{{ block.config.title }}</h4>
                            <p v-if="block.config.text" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ block.config.text }}</p>
                        </div>

                        <div v-else-if="block.type === 'custom_html'">
                            <h4 v-if="block.config.title" class="mb-6 footer-heading-title">{{ block.config.title }}</h4>
                            <div class="prose prose-sm text-gray-500 dark:prose-invert dark:text-gray-400" v-html="block.config.content"></div>
                        </div>

                        <div v-else-if="block.type === 'language_switcher'" class="flex items-center gap-2">
                            <h4 v-if="block.config.title" class="footer-heading-title">{{ block.config.title }}</h4>
                            <LanguageSwitcher />
                        </div>

                        <button v-else-if="block.type === 'dark_mode'" type="button" class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" @click="toggleDark()">
                            <svg v-if="isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                            <span v-if="block.config.title">{{ block.config.title }}</span>
                        </button>

                        <div v-else-if="block.type === 'store_badges'">
                            <h4 v-if="block.config.title" class="mb-6 footer-heading-title">{{ block.config.title }}</h4>
                            <div class="flex flex-wrap gap-3">
                                <a v-for="(badge, idx) in (Array.isArray(block.config.links) ? block.config.links : [])" :key="idx" :href="typeof badge === 'object' && badge ? (badge as any).url || '#' : '#'" target="_blank" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20">
                                    <span>{{ typeof badge === 'object' && badge ? ((badge as any).label || (badge as any).url) : badge }}</span>
                                </a>
                                <span v-if="!Array.isArray(block.config.links) || block.config.links.length === 0" class="text-sm italic text-gray-400">{{ t('No store badges configured.') }}</span>
                            </div>
                        </div>

                        <div v-else-if="block.type === 'divider'" class="border-t border-gray-100 dark:border-surface-800" :style="{ marginBlock: `${Number(block.config.spacing || 24) / 2}px`, borderColor: typeof block.config.color === 'string' && block.config.color ? block.config.color : undefined }"></div>
                    </template>
                </div>
            </div>

        </div>

        <section v-if="footerConfig.bottom_bar" class="footer-bottom-shell" :class="footerConfig.bottom_bar.border_top !== false ? 'footer-bottom-shell--border' : ''" :style="bottomBarStyle">
            <div class="footer-bottom-grid flex flex-col gap-4 md:flex-row" :class="containerClass">
                <div v-for="column in enabledBottomColumns" :key="column.id" class="footer-bottom-column" :class="[column.id === 'right' ? 'footer-bottom-column-right' : 'footer-bottom-column-left', bottomColFlexClass((column.id || 'left') as 'left' | 'right')]">
                    <div v-if="column.id === 'right' && footerConfig.bottom_bar.menu_slug && topMenuItems(footerConfig.bottom_bar.menu_slug).length" class="footer-bottom-item">
                        <ul class="flex flex-wrap items-center justify-center gap-4 md:justify-end">
                            <li v-for="item in topMenuItems(footerConfig.bottom_bar.menu_slug)" :key="menuItemId(item)">
                                <a :href="menuItemHref(item)" :target="item.target" class="footer-bottom-link text-xs font-medium" :style="bottomBarLinkStyle">{{ menuItemLabel(item) }}</a>
                            </li>
                        </ul>
                    </div>
                    <div v-for="block in column.blocks" :key="block.id" class="footer-bottom-item" :class="{ 'footer-bottom-item-copyright': block.type === 'copyright_text', 'footer-bottom-item-fixed': block.type === 'payment_icons' || block.type === 'social_icons' }">
                        <p v-if="block.type === 'copyright_text'" class="text-xs font-medium">{{ withYear(block.config.text) }}</p>
                        <SocialFollow
                            v-else-if="block.type === 'social_icons'"
                            :display-mode="socialDisplayMode(block.config.display_mode)"
                            icon-item-class="footer-bottom-social-icon"
                            :icon-item-style="bottomBarSocialIconStyle"
                            :icon-use-platform-surface="false"
                            :icon-use-platform-color="false"
                        />
                        <div v-else-if="block.type === 'payment_icons' && Array.isArray(block.config.icons) && block.config.icons.length" class="footer-payment-image-wrap">
                            <template v-for="(icon, index) in block.config.icons" :key="`${icon}-${index}`">
                                <img v-if="paymentIconSrc(icon)" :src="paymentIconSrc(icon)" :alt="paymentIconLabel(icon)" class="footer-payment-image">
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ring-1"
                                    :class="paymentBadgeClass(icon)"
                                >
                                    {{ paymentIconLabel(icon) }}
                                </span>
                            </template>
                        </div>
                        <button v-else-if="block.type === 'back_to_top'" type="button" class="flex min-h-8 items-center justify-center gap-2 bg-gray-50 px-2.5 text-gray-600 shadow-sm transition hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :class="backToTopShapeClass(block.config.shape, Boolean(block.config.label))" :style="backToTopStyle(block.config)" :aria-label="String(block.config.label || t('Back to top'))" @click="scrollToTop">
                            <i :class="backToTopIcon(block.config)"></i>
                            <span v-if="block.config.shape !== 'circle' && block.config.shape !== 'square' && block.config.label" class="text-xs font-semibold">
                                {{ block.config.label }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </footer>
</template>

<style scoped>
.footer-section-overlay {
    position: relative;
}
.footer-section-overlay::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--footer-bg-overlay, transparent);
    pointer-events: none;
    z-index: 1;
}
.footer-section-overlay > * {
    position: relative;
    z-index: 2;
}

.footer-heading-title {
    font-size: var(--footer-heading-size, 12px);
    font-weight: var(--footer-heading-weight, 900);
    text-transform: var(--footer-heading-transform, uppercase);
    letter-spacing: 0.1em;
    color: var(--footer-heading-color, inherit);
}

:deep(.footer-bottom-social-icon) {
    color: inherit;
    border-radius: 9999px;
    border: 1px solid rgb(148 163 184 / 0.22);
    background: rgb(148 163 184 / 0.12);
    box-shadow: none;
}

:deep(.footer-bottom-social-icon:hover) {
    color: inherit;
    border-color: rgb(148 163 184 / 0.32);
    background: rgb(148 163 184 / 0.18);
}

:deep(.footer-bottom-social-icon i) {
    color: inherit;
}

:global(.dark) :deep(.footer-bottom-social-icon) {
    border-color: rgb(255 255 255 / 0.18);
    background: rgb(255 255 255 / 0.08);
}

:global(.dark) :deep(.footer-bottom-social-icon:hover) {
    border-color: rgb(255 255 255 / 0.26);
    background: rgb(255 255 255 / 0.14);
}

.footer-nav-link,
.footer-bottom-link {
    color: inherit;
    transition: opacity 0.2s ease, color 0.2s ease;
}

.footer-nav-link:hover,
.footer-bottom-link:hover {
    opacity: 0.84;
}

.footer-bottom-grid {
    padding-block: var(--footer-bottom-padding, 32px);
}

.footer-bottom-shell {
    background-color: inherit;
    color: inherit;
}

.footer-bottom-shell--border {
    border-top-style: solid;
    border-top-width: var(--footer-bottom-border-width, 1px);
}

.footer-bottom-column {
    display: flex;
    flex: 1 1 100%;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.footer-bottom-column-left {
    justify-content: flex-start;
    text-align: center;
}

.footer-bottom-column-right {
    justify-content: flex-start;
    text-align: center;
}

.footer-bottom-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
}

.footer-bottom-item-fixed {
    flex: 0 0 auto;
    min-width: max-content;
    width: max-content;
    max-width: none;
    overflow: visible;
}

.footer-bottom-item-copyright {
    order: 99;
    width: 100%;
}

.footer-payment-image-wrap {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.5rem;
    width: max-content;
    min-width: max-content;
    max-width: none;
    overflow: visible;
    white-space: nowrap;
}

.footer-payment-image {
    display: block;
    flex: 0 0 auto;
    width: auto;
    min-width: max-content;
    max-width: none;
    height: 32px;
}

@media (min-width: 768px) {
    .footer-bottom-column {
        flex-basis: calc(50% - 0.5rem);
        flex-direction: row;
        align-items: center;
    }

    .footer-bottom-column-left {
        text-align: start;
    }

    .footer-bottom-column-right {
        justify-content: flex-end;
        text-align: end;
    }

    .footer-bottom-item {
        justify-content: flex-start;
    }

    .footer-bottom-item-copyright {
        order: 0;
        width: auto;
    }

    .footer-bottom-grid {
        padding-block: var(--footer-bottom-padding, 32px);
    }
}

@media (min-width: 1280px) {
    .footer-bottom-grid {
        padding-block: var(--footer-bottom-padding, 32px);
    }
}
</style>
