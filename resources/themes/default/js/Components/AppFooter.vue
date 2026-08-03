<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import SocialFollow from '@themes/default/js/Components/SocialFollow.vue'
import LanguageSwitcher from '@/Components/Utility/LanguageSwitcher.vue'
import { mediaUrl } from '@/lib/media'

type FooterStyle = 'default' | 'centered' | 'spotlight' | 'card_grid' | 'split_band' | 'floating_panel'
// `menu` and `menu_secondary` render the same markup from two independent menu sources,
// so a layout can show two different link lists (e.g. Company and Legal) side by side.
type FooterContentKey = 'about_text' | 'logo' | 'menu' | 'menu_secondary' | 'contact_info' | 'custom_text' | 'categories' | 'newsletter' | 'social_icons'
type ConfigValue = string | number | boolean | null

interface MenuItem {
    id: number | string
    title?: string
    label?: string
    url: string
    final_url?: string
    parent_id?: number | string | null
    target?: string
    icon?: string | null
    badge_text?: string | null
    badge_color?: string | null
    is_active?: boolean
    requires_auth?: string | null
}

interface GlobalMenu {
    slug: string
    items: MenuItem[]
}

interface FooterAiCategory {
    name: string
    slug: string
    tools_count?: number
}

interface FooterData {
    aiCategories?: FooterAiCategory[]
}

interface SocialFollowProfile {
    platform: string
    label: string
    url: string
    count?: number
    unit?: string
}

interface SocialFollowPayload {
    display_mode?: string
    profiles?: SocialFollowProfile[]
}

interface Branding {
    site_description?: string
    site_support_email?: string
    site_logo_light?: string
    site_logo_dark?: string
    site_favicon_png?: string
    site_favicon_ico?: string
}

interface SimpleFooterSettings {
    layout?: string
    logo_mode?: string
    style_columns?: Record<string, Record<string, FooterContentKey | FooterContentKey[] | string | string[]>>
    brand_title?: string
    brand_description?: string
    show_newsletter?: boolean
    social_title?: string
    social_multi_color?: boolean
    newsletter_title?: string
    newsletter_description?: string
    newsletter_placeholder?: string
    newsletter_button_label?: string
    newsletter_button_style?: string
    show_social_icons?: boolean
    contact_title?: string
    contact_email?: string
    contact_phone?: string
    contact_address?: string
    contact_details?: string
    menu_title?: string
    menu_column?: string
    secondary_menu_title?: string
    secondary_menu_column?: string
    custom_title?: string
    custom_text?: string
    tool_categories_title?: string
    tool_categories_items?: string[]
    footer_bg_color?: string
    footer_text_color?: string
    footer_heading_color?: string
    footer_heading_text_case?: string
    container_width?: string
    show_logo_about?: boolean
    enable_card_style?: boolean
    hide_categories_count?: boolean
    footer_vertical_padding?: number
    show_payment_icons?: boolean
    payment_icons?: string
    show_bottom_social_icons?: boolean
    show_bottom_language_selector?: boolean
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
    bottom_bar_centered?: boolean
    copyright_text?: string
}

const page = usePage()
const { t } = useTranslate()
const { isDark } = useTheme()

interface SimpleHeaderSettings {
    mobile_bottom?: {
        enabled?: boolean
    }
}

const branding = computed(() => (page.props.branding as Branding | undefined) ?? {})
const frontendFooterSettings = computed(() => (page.props.frontendFooterSettings as SimpleFooterSettings | undefined) ?? {})
const globalMenus = computed(() => (page.props.globalMenus as GlobalMenu[] | undefined) ?? [])
const footerData = computed(() => (page.props.footerData as FooterData | undefined) ?? {})
const socialFollow = computed(() => (page.props.socialFollow as SocialFollowPayload | undefined) ?? { profiles: [] })
const appName = computed(() => String(page.props.appName || ''))
const frontendHeaderSettings = computed(() => (page.props.frontendHeaderSettings as SimpleHeaderSettings | undefined) ?? {})
const isMobileBottomHeaderEnabled = computed(() => frontendHeaderSettings.value.mobile_bottom?.enabled === true)
const currentYear = new Date().getFullYear()

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

const footerStyle = computed<FooterStyle>(() => {
    const value = String(frontendFooterSettings.value.layout || 'default')
    if (['default', 'centered', 'spotlight', 'card_grid', 'split_band', 'floating_panel'].includes(value)) {
        return value as FooterStyle
    }

    return 'default'
})

const defaultFooterStyleColumns: Record<FooterStyle, Record<string, FooterContentKey[]>> = {
    default: { col_1: ['about_text'], col_2: ['menu'], col_3: ['contact_info'], col_4: ['custom_text'] },
    centered: { col_1: ['about_text'], col_2: ['menu'], col_3: ['contact_info'] },
    spotlight: { col_1: ['menu'], col_2: ['custom_text'], col_3: ['contact_info'], col_4: ['categories'], full_width: ['custom_text'] },
    card_grid: { col_1: ['about_text'], col_2: ['menu'], col_3: ['custom_text'], col_4: ['contact_info'], col_5: ['categories'] },
    split_band: { col_1: ['menu'], col_2: ['contact_info'], col_3: ['custom_text'], col_4: ['categories'] },
    floating_panel: { col_1: ['menu'], col_2: ['contact_info'], col_3: ['custom_text'], col_4: ['categories'] },
}

const styleColumnDefinitions: Record<FooterStyle, string[]> = {
    default: ['col_1', 'col_2', 'col_3', 'col_4'],
    centered: ['col_1', 'col_2', 'col_3'],
    spotlight: ['col_1', 'col_2', 'col_3', 'col_4', 'full_width'],
    card_grid: ['col_1', 'col_2', 'col_3', 'col_4', 'col_5'],
    split_band: ['col_1', 'col_2', 'col_3', 'col_4'],
    floating_panel: ['col_1', 'col_2', 'col_3', 'col_4'],
}

const brandTitle = computed(() => frontendFooterSettings.value.brand_title?.trim() || '')
const brandDescription = computed(() => frontendFooterSettings.value.brand_description?.trim() || branding.value.site_description || '')
const menuTitle = computed(() => frontendFooterSettings.value.menu_title?.trim() || '')
const secondaryMenuTitle = computed(() => frontendFooterSettings.value.secondary_menu_title?.trim() || '')
const customTitle = computed(() => frontendFooterSettings.value.custom_title?.trim() || '')
const customText = computed(() => frontendFooterSettings.value.custom_text?.trim() || '')
const toolCategoriesTitle = computed(() => frontendFooterSettings.value.tool_categories_title?.trim() || '')
const contactTitle = computed(() => frontendFooterSettings.value.contact_title?.trim() || '')
const contactEmail = computed(() => frontendFooterSettings.value.contact_email?.trim() || branding.value.site_support_email || '')
const newsletterTitle = computed(() => frontendFooterSettings.value.newsletter_title?.trim() || t('Stay Updated'))
const socialColumnTitle = computed(() => frontendFooterSettings.value.social_title?.trim() || '')
const newsletterColumnTitle = computed(() => frontendFooterSettings.value.newsletter_title?.trim() || '')
const newsletterDescription = computed(() => frontendFooterSettings.value.newsletter_description?.trim() || t('Get product news and launch updates in your inbox.'))
const newsletterPlaceholder = computed(() => frontendFooterSettings.value.newsletter_placeholder?.trim() || t('Enter your email'))
const newsletterButtonLabel = computed(() => frontendFooterSettings.value.newsletter_button_label?.trim() || t('Subscribe'))
const newsletterButtonStyle = computed(() => String(frontendFooterSettings.value.newsletter_button_style || 'primary'))
const showNewsletter = computed(() => isTruthySetting(frontendFooterSettings.value.show_newsletter, true))
const showSocialIcons = computed(() => isTruthySetting(frontendFooterSettings.value.show_social_icons, true))
const showLogoAbout = computed(() => {
    if (footerStyle.value === 'default' || footerStyle.value === 'card_grid') {
        return true
    }

    return isTruthySetting(frontendFooterSettings.value.show_logo_about, true)
})
const enableCardStyle = computed(() => isTruthySetting(frontendFooterSettings.value.enable_card_style, true))
const hideCategoriesCount = computed(() => isTruthySetting(frontendFooterSettings.value.hide_categories_count, false))
const normalizeFooterContentItems = (value: unknown, fallback: FooterContentKey[]) => {
    if (Array.isArray(value)) {
        const filtered = value.filter((item): item is FooterContentKey => typeof item === 'string' && item.length > 0)
        return filtered.length ? filtered : [...fallback]
    }

    if (typeof value === 'string' && value.length > 0) {
        return [value as FooterContentKey]
    }

    return [...fallback]
}

const footerStyleColumns = computed<Record<FooterStyle, Record<string, FooterContentKey[]>>>(() => {
    const stored = frontendFooterSettings.value.style_columns ?? {}

    return {
        default: Object.fromEntries(styleColumnDefinitions.default.map((slot) => [slot, normalizeFooterContentItems(stored.default?.[slot], defaultFooterStyleColumns.default[slot])])) as Record<string, FooterContentKey[]>,
        centered: Object.fromEntries(styleColumnDefinitions.centered.map((slot) => [slot, normalizeFooterContentItems(stored.centered?.[slot], defaultFooterStyleColumns.centered[slot])])) as Record<string, FooterContentKey[]>,
        spotlight: Object.fromEntries(styleColumnDefinitions.spotlight.map((slot) => [slot, normalizeFooterContentItems(stored.spotlight?.[slot], defaultFooterStyleColumns.spotlight[slot])])) as Record<string, FooterContentKey[]>,
        card_grid: Object.fromEntries(styleColumnDefinitions.card_grid.map((slot) => [slot, normalizeFooterContentItems(stored.card_grid?.[slot], defaultFooterStyleColumns.card_grid[slot])])) as Record<string, FooterContentKey[]>,
        split_band: Object.fromEntries(styleColumnDefinitions.split_band.map((slot) => [slot, normalizeFooterContentItems(stored.split_band?.[slot], defaultFooterStyleColumns.split_band[slot])])) as Record<string, FooterContentKey[]>,
        floating_panel: Object.fromEntries(styleColumnDefinitions.floating_panel.map((slot) => [slot, normalizeFooterContentItems(stored.floating_panel?.[slot], defaultFooterStyleColumns.floating_panel[slot])])) as Record<string, FooterContentKey[]>,
    }
})

const footerLogo = computed(() => isDark.value
    ? (branding.value.site_logo_dark || branding.value.site_logo_light || '')
    : (branding.value.site_logo_light || branding.value.site_logo_dark || ''))

// The footer's own `logo_mode` choice (light / dark / favicon), resolved the same way the
// `logo` content block resolves it. Brand-bar logos used to read footerLogo directly, which
// only ever switches on the active theme — so picking a mode in the footer settings had no
// effect there. Falls back to footerLogo when the chosen asset was never uploaded, so the
// brand bar keeps rendering instead of going blank.
const footerModeLogo = computed(() => {
    const mode = frontendFooterSettings.value.logo_mode ?? 'light'
    if (mode === 'dark') return branding.value.site_logo_dark || footerLogo.value
    if (mode === 'favicon') return branding.value.site_favicon_png || branding.value.site_favicon_ico || footerLogo.value
    return branding.value.site_logo_light || footerLogo.value
})

const footerContainerClass = computed(() => {
    const cw = frontendFooterSettings.value.container_width ?? '1280px'
    if (cw === 'full') return 'w-full px-4 sm:px-6'
    return 'mx-auto w-full px-4 sm:px-6'
})

const footerContainerStyle = computed(() => {
    const cw = frontendFooterSettings.value.container_width ?? '1280px'
    if (cw === 'full') return {}
    return { maxWidth: cw === 'boxed' ? '1080px' : cw === 'stretched' ? '1536px' : cw }
})

const isColorLight = (color: string): boolean => {
    const cleaned = color.trim().toLowerCase()
    if (['white', 'lightgray', 'silver', 'yellow', 'transparent'].includes(cleaned)) return true
    if (['black', 'darkgray', 'navy', '#0f172a', '#0e1526', '#090d16'].includes(cleaned)) return false

    if (cleaned.startsWith('#')) {
        const hex = cleaned.substring(1)
        let r = 0, g = 0, b = 0
        if (hex.length === 3) {
            r = parseInt(hex[0] + hex[0], 16)
            g = parseInt(hex[1] + hex[1], 16)
            b = parseInt(hex[2] + hex[2], 16)
        } else if (hex.length === 6) {
            r = parseInt(hex.substring(0, 2), 16)
            g = parseInt(hex.substring(2, 4), 16)
            b = parseInt(hex.substring(4, 6), 16)
        }
        const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000
        return yiq >= 128
    }

    if (cleaned.startsWith('rgb')) {
        const matches = cleaned.match(/\d+/g)
        if (matches && matches.length >= 3) {
            const r = parseInt(matches[0], 10)
            const g = parseInt(matches[1], 10)
            const b = parseInt(matches[2], 10)
            const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000
            return yiq >= 128
        }
    }

    return false
}

const isColorDark = (color: string): boolean => {
    return !isColorLight(color)
}

const footerRootStyle = computed<Record<string, string>>(() => {
    let backgroundColor = frontendFooterSettings.value.footer_bg_color?.trim()
    let textColor = frontendFooterSettings.value.footer_text_color?.trim()
    let headingColor = frontendFooterSettings.value.footer_heading_color?.trim()
    const headingTextCase = String(frontendFooterSettings.value.footer_heading_text_case || 'capitalize')
    const padding = Number(frontendFooterSettings.value.footer_vertical_padding ?? 56)

    if (isDark.value) {
        if (!backgroundColor || isColorLight(backgroundColor)) {
            backgroundColor = '#0e1526'
        }
        if (textColor && isColorDark(textColor)) {
            textColor = ''
        }
        if (headingColor && isColorDark(headingColor)) {
            headingColor = ''
        }
    } else {
        if (!backgroundColor) {
            backgroundColor = '#0f172a'
        }
    }

    const bgIsLight = isColorLight(backgroundColor)
    const defaultText = bgIsLight ? '#334155' : '#cbd5e1'
    const defaultHeading = bgIsLight ? '#0f172a' : '#ffffff'

    if (!textColor) {
        textColor = defaultText
    }
    if (!headingColor) {
        headingColor = defaultHeading
    }

    // For floating panel with active newsletter, remove the root padding-top so the floating band aligns exactly on the border (50/50)
    const isFloatingWithNewsletter = footerStyle.value === 'floating_panel' && showNewsletter.value
    const resolvedPaddingTop = isFloatingWithNewsletter ? '0px' : `${Math.max(24, padding)}px`

    return {
        backgroundColor,
        color: textColor,
        '--footer-text-color': textColor,
        '--footer-text-muted': `color-mix(in srgb, ${textColor} 74%, transparent)`,
        '--footer-text-soft': `color-mix(in srgb, ${textColor} 62%, transparent)`,
        '--footer-text-subtle': `color-mix(in srgb, ${textColor} 46%, transparent)`,
        '--footer-input-placeholder': `color-mix(in srgb, ${textColor} 42%, transparent)`,
        '--footer-heading-color': headingColor,
        '--footer-heading-transform': ['lowercase', 'uppercase', 'capitalize'].includes(headingTextCase) ? headingTextCase : 'capitalize',
        '--footer-panel-border': bgIsLight ? 'rgba(0, 0, 0, 0.08)' : 'rgb(255 255 255 / 0.12)',
        '--footer-panel-bg': bgIsLight ? 'rgba(0, 0, 0, 0.03)' : 'rgb(255 255 255 / 0.06)',
        '--footer-panel-bg-strong': bgIsLight ? 'rgba(0, 0, 0, 0.06)' : 'rgb(255 255 255 / 0.1)',
        '--footer-floating-shell-bg': bgIsLight ? backgroundColor : `color-mix(in srgb, ${backgroundColor} 88%, #0f172a 12%)`,
        '--footer-floating-shell-edge': bgIsLight ? 'rgba(0, 0, 0, 0.08)' : `color-mix(in srgb, ${backgroundColor} 72%, #0f172a 28%)`,
        '--footer-floating-shell-shadow': '0 -24px 80px rgba(15, 23, 42, 0.14)',
        '--footer-soft-text': 'inherit',
        '--footer-bottom-gap': `${Math.max(24, Math.round(padding * 0.72))}px`,
        paddingTop: resolvedPaddingTop,
    }
})

const isBgLight = computed(() => {
    const bg = footerRootStyle.value.backgroundColor
    return bg ? isColorLight(bg) : false
})

const panelToneClass = (value: string) => {
    if (!enableCardStyle.value) return ''
    if (isBgLight.value) {
        return value
            .replaceAll('border-white/12', 'border-slate-900/10')
            .replaceAll('border-white/10', 'border-slate-900/8')
            .replaceAll('border-white/8', 'border-slate-900/8')
            .replaceAll('from-white/12', 'from-slate-900/8')
            .replaceAll('via-white/8', 'via-slate-900/4')
            .replaceAll('via-white/6', 'via-slate-900/3')
            .replaceAll('via-white/5', 'via-slate-900/2')
            .replaceAll('from-white/8', 'from-slate-900/6')
            .replaceAll('bg-white/6', 'bg-slate-900/4')
            .replaceAll('bg-white/5', 'bg-slate-900/3')
    }
    return value
}

const cardToneClass = (value: string) => {
    if (!enableCardStyle.value) return ''
    if (isBgLight.value) {
        return value
            .replaceAll('border-white/12', 'border-slate-900/10')
            .replaceAll('border-white/10', 'border-slate-900/8')
            .replaceAll('border-white/8', 'border-slate-900/8')
            .replaceAll('from-white/12', 'from-slate-900/8')
            .replaceAll('via-white/8', 'via-slate-900/4')
            .replaceAll('via-white/6', 'via-slate-900/3')
            .replaceAll('via-white/5', 'via-slate-900/2')
            .replaceAll('from-white/8', 'from-slate-900/6')
            .replaceAll('bg-white/6', 'bg-slate-900/4')
            .replaceAll('bg-white/5', 'bg-slate-900/3')
    }
    return value
}

const topSocialIconStyle = computed<Record<string, string>>(() => ({
    color: 'inherit',
    borderColor: isBgLight.value ? 'rgba(15, 23, 42, 0.08)' : 'rgb(255 255 255 / 0.12)',
    background: isBgLight.value ? 'rgba(15, 23, 42, 0.04)' : 'rgb(255 255 255 / 0.06)',
}))

// Multi colour on (the default) leaves SocialFollow on its own props, which already paint
// each platform in its brand colour. Off swaps in the same neutral treatment the
// newsletter block's row uses, so the two read as one family.
const socialMultiColor = computed(() => isTruthySetting(frontendFooterSettings.value.social_multi_color, true))
const socialColumnIconProps = computed(() => socialMultiColor.value
    ? {}
    : {
        iconItemClass: 'footer-social-icon',
        iconItemStyle: topSocialIconStyle.value,
        iconUsePlatformColor: false,
        iconUsePlatformSurface: false,
    })

const cardGridSocialIconStyle = computed<Record<string, string>>(() => {
    if (isDark.value) {
        return {
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '2.7rem',
            height: '2.7rem',
            border: '1px solid color-mix(in srgb, var(--color-primary-900, #064e3b) 22%, #1e293b)',
            borderRadius: '9999px',
            background: 'linear-gradient(180deg, color-mix(in srgb, var(--color-primary-900, #064e3b) 12%, #0f172a), color-mix(in srgb, var(--color-primary-900, #064e3b) 7%, #0f172a))',
            color: 'var(--color-primary-400, #34d399)',
            boxShadow: '0 10px 26px rgba(0, 0, 0, 0.3)',
            transition: 'transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease',
        }
    }

    return {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '2.7rem',
        height: '2.7rem',
        border: '1px solid color-mix(in srgb, var(--color-primary-500, #10b981) 22%, white)',
        borderRadius: '9999px',
        background: 'linear-gradient(180deg, color-mix(in srgb, var(--color-primary-500, #10b981) 12%, white), color-mix(in srgb, var(--color-primary-500, #10b981) 7%, white))',
        color: 'var(--color-primary-600, #059669)',
        boxShadow: '0 10px 26px color-mix(in srgb, var(--color-primary-500, #10b981) 14%, transparent)',
        transition: 'transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease',
    }
})

const cardGridSocialIconInnerStyle = computed<Record<string, string>>(() => ({
    color: isDark.value ? 'var(--color-primary-400, #34d399)' : 'var(--color-primary-600, #059669)',
}))

const resolveFooterMenuSlug = (value: string | undefined, fallback: string) => {
    if (!value) return fallback
    if (value === 'company') return 'footer-company'
    if (value === 'support') return 'footer-support'
    if (value === 'legal') return 'footer-legal'
    return value
}

const getMenu = (slug?: string | null) => {
    if (!slug) return null
    return globalMenus.value.find((menu) => menu.slug === slug) ?? null
}

const visibleMenuItems = (menu: GlobalMenu | null) => {
    if (!menu) return []

    const loggedIn = Boolean(page.props.auth?.user)
    const isProUser = (page.props.auth?.user as any)?.subscription_status === 'active'

    return (menu.items ?? [])
        .filter((item) => item.is_active !== false)
        .filter((item) => {
            const rule = item.requires_auth ?? 'none'
            if (rule === 'guest') return !loggedIn
            if (rule === 'auth') return loggedIn
            if (rule === 'pro') return isProUser
            return true
        })
}

const topMenuItems = (slug?: string | null) => visibleMenuItems(getMenu(slug)).filter((item) => !item.parent_id)
const menuSlug = computed(() => resolveFooterMenuSlug(frontendFooterSettings.value.menu_column, 'footer-company'))
const menuItems = computed(() => visibleMenuItems(getMenu(menuSlug.value)))
// No fallback slug: an unconfigured secondary menu hides its block rather than
// duplicating the primary one — see shouldRenderFooterItem.
const secondaryMenuSlug = computed(() => resolveFooterMenuSlug(frontendFooterSettings.value.secondary_menu_column, ''))
const secondaryMenuItems = computed(() => visibleMenuItems(getMenu(secondaryMenuSlug.value)))
const bottomMenuItems = computed(() => topMenuItems(resolveFooterMenuSlug(frontendFooterSettings.value.bottom_menu, '')))

function shouldRenderFooterItem(item: FooterContentKey): boolean {
    if (item === 'about_text') {
        return showLogoAbout.value || showSocialIcons.value
    }

    if (item === 'logo') {
        const mode = frontendFooterSettings.value.logo_mode ?? 'light'
        if (mode === 'dark') return Boolean(branding.value.site_logo_dark)
        if (mode === 'favicon') return Boolean(branding.value.site_favicon_png || branding.value.site_favicon_ico)
        return Boolean(branding.value.site_logo_light)
    }

    if (item === 'menu_secondary') {
        return Boolean(secondaryMenuSlug.value)
    }

    if ((footerStyle.value === 'floating_panel' || footerStyle.value === 'spotlight' || footerStyle.value === 'card_grid') && item === 'newsletter') {
        return false
    }

    return true
}

const activeStyleItems = computed<Array<{ slot: string; items: FooterContentKey[] }>>(() => {
    const style = footerStyle.value
    const columns = footerStyleColumns.value[style]
    const orderWeight = (item: FooterContentKey) => {
        if (item === 'logo') return 0
        return 1
    }

    return styleColumnDefinitions[style]
        .map((slot) => ({
            slot,
            items: [...(columns[slot] ?? defaultFooterStyleColumns[style][slot])]
                .filter((item) => shouldRenderFooterItem(item))
                .sort((left, right) => orderWeight(left) - orderWeight(right)),
        }))
        .filter((entry) => entry.items.length > 0)
})

const spotlightMainEntries = computed(() => activeStyleItems.value.filter((entry) => entry.slot !== 'full_width'))
const hasSpotlightBrandBar = computed(() => showLogoAbout.value || showSocialIcons.value)
const spotlightBrandName = computed(() => brandTitle.value)
const spotlightSocialProfiles = computed(() => socialFollow.value.profiles ?? [])
const spotlightBrandBarCentered = computed(() => !(showLogoAbout.value && showSocialIcons.value))

const categoryBlockItems = (value: string[] | undefined) => {
    const selected = Array.isArray(value) ? value : []
    if (!selected.length) return []

    const map = new Map((footerData.value.aiCategories ?? []).map((category) => [category.slug, category]))
    return selected.map((slug) => map.get(slug)).filter(Boolean) as FooterAiCategory[]
}

const isMenuBlock = (item: FooterContentKey) => item === 'menu' || item === 'menu_secondary'
const menuBlockTitle = (item: FooterContentKey) => item === 'menu_secondary' ? secondaryMenuTitle.value : menuTitle.value
const menuBlockItems = (item: FooterContentKey) => item === 'menu_secondary' ? secondaryMenuItems.value : menuItems.value
const isCustomBlock = (item: FooterContentKey) => item === 'custom_text'
const customBlockTitle = (item: FooterContentKey) => customTitle.value
const customBlockText = (item: FooterContentKey) => customText.value
const isLogoBlock = (item: FooterContentKey) => item === 'logo'
const logoBlockSrc = (item: FooterContentKey) => {
    const mode = frontendFooterSettings.value.logo_mode ?? 'light'
    if (mode === 'dark') return branding.value.site_logo_dark || ''
    if (mode === 'favicon') return branding.value.site_favicon_png || branding.value.site_favicon_ico || ''
    return branding.value.site_logo_light || ''
}
const logoBlockAlt = (item: FooterContentKey) => {
    const mode = frontendFooterSettings.value.logo_mode ?? 'light'
    return mode === 'favicon' ? t('Favicon') : mode === 'dark' ? t('Dark logo') : t('Light logo')
}
const socialPlatformIconClass = (platform: string) => ({
    facebook: 'ti ti-brand-facebook',
    x: 'ti ti-brand-x',
    twitter: 'ti ti-brand-x',
    instagram: 'ti ti-brand-instagram',
    linkedin: 'ti ti-brand-linkedin',
    youtube: 'ti ti-brand-youtube',
    tiktok: 'ti ti-brand-tiktok',
    github: 'ti ti-brand-github',
    discord: 'ti ti-brand-discord',
}[platform] ?? 'ti ti-world')
const isCategoryBlock = (item: FooterContentKey) => item === 'categories'
const categoryBlockTitle = (item: FooterContentKey) => toolCategoriesTitle.value
const categoryBlockList = (item: FooterContentKey) => categoryBlockItems(frontendFooterSettings.value.tool_categories_items)

const menuItemHref = (item: MenuItem) => String(item.final_url || item.url || '#')
const menuItemLabel = (item: MenuItem) => item.label || item.title || ''
const menuItemIcon = (item: MenuItem) => String(item.icon || '').trim()
const menuItemBadgeText = (item: MenuItem) => String(item.badge_text || '').trim()
const menuItemBadgeColor = (item: MenuItem) => {
    const color = String(item.badge_color || 'gray')
    return ['green', 'blue', 'violet', 'amber', 'red', 'gray'].includes(color) ? color : 'gray'
}
const menuItemTarget = (item: MenuItem) => item.target === '_blank' ? '_blank' : '_self'
const menuItemRel = (item: MenuItem) => menuItemTarget(item) === '_blank' ? 'noopener noreferrer' : undefined

const panelClass = computed(() => enableCardStyle.value ? 'rounded-3xl border p-6 shadow-[0_24px_80px_rgba(15,23,42,0.16)] backdrop-blur-sm' : '')
const cardClass = computed(() => enableCardStyle.value ? 'rounded-3xl border p-6 shadow-[0_18px_60px_rgba(15,23,42,0.14)] backdrop-blur-sm' : '')
const splitBandTopSpacingClass = computed(() => enableCardStyle.value ? '' : 'py-3 sm:py-4')
const splitBandColumnClass = computed(() => enableCardStyle.value ? '' : 'py-3')
const splitBandColumnContentClass = computed(() => enableCardStyle.value ? 'space-y-8' : 'space-y-10')

const newsletterButtonClass = computed(() => {
    const style = newsletterButtonStyle.value

    if (style === 'dark') {
        return 'rounded-xl bg-gray-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-800'
    }
    if (style === 'danger') {
        return 'rounded-xl bg-linear-to-r from-rose-500 to-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90'
    }
    if (style === 'success') {
        return 'rounded-xl bg-linear-to-r from-emerald-500 to-green-600 px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90'
    }
    if (style === 'warning') {
        return 'rounded-xl bg-linear-to-r from-amber-400 to-orange-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:opacity-90'
    }
    if (style === 'outline') {
        return 'rounded-xl border border-white/20 bg-transparent px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10'
    }
    if (style === 'ghost') {
        return 'rounded-xl bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15'
    }
    if (style === 'gradient_sunset') {
        return 'rounded-xl bg-linear-to-r from-orange-400 via-rose-500 to-fuchsia-600 px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90'
    }
    if (style === 'purple' || style === 'gradient_royal') {
        return 'rounded-xl bg-linear-to-r from-violet-500 to-indigo-500 px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90'
    }
    if (style === 'gradient_ocean' || style === 'gradient') {
        return 'rounded-xl bg-linear-to-r from-sky-500 to-cyan-500 px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90'
    }

    return 'rounded-xl btn-primary px-5 py-3 text-sm font-semibold text-white transition'
})

const splitBandNewsletterButtonClass = computed(() => {
    const style = newsletterButtonStyle.value

    if (style === 'dark') {
        return 'split-band-newsletter-submit bg-gray-950 text-white hover:bg-gray-800'
    }
    if (style === 'danger') {
        return 'split-band-newsletter-submit bg-linear-to-r from-rose-500 to-red-600 text-white hover:opacity-90'
    }
    if (style === 'success') {
        return 'split-band-newsletter-submit bg-linear-to-r from-emerald-500 to-green-600 text-white hover:opacity-90'
    }
    if (style === 'warning') {
        return 'split-band-newsletter-submit bg-linear-to-r from-amber-400 to-orange-500 text-slate-950 hover:opacity-90'
    }
    if (style === 'outline') {
        return 'split-band-newsletter-submit border border-slate-300 bg-white text-slate-900 hover:bg-slate-50'
    }
    if (style === 'ghost') {
        return 'split-band-newsletter-submit bg-slate-100 text-slate-900 hover:bg-slate-200'
    }
    if (style === 'gradient_sunset') {
        return 'split-band-newsletter-submit bg-linear-to-r from-orange-400 via-rose-500 to-fuchsia-600 text-white hover:opacity-90'
    }
    if (style === 'purple' || style === 'gradient_royal') {
        return 'split-band-newsletter-submit bg-linear-to-r from-violet-500 to-indigo-500 text-white hover:opacity-90'
    }
    if (style === 'gradient_ocean' || style === 'gradient') {
        return 'split-band-newsletter-submit bg-linear-to-r from-sky-500 to-cyan-500 text-white hover:opacity-90'
    }

    return 'split-band-newsletter-submit btn-primary text-white'
})

const paymentIconList = computed(() => {
    const value = frontendFooterSettings.value.payment_icons
    if (typeof value !== 'string') return []
    const icon = value.trim()
    return icon ? [icon] : []
})

const paymentIconSrc = (value: ConfigValue) => {
    if (typeof value !== 'string') return ''
    const icon = value.trim()
    if (icon.startsWith('http://') || icon.startsWith('https://') || icon.startsWith('/') || icon.startsWith('data:')) {
        return icon
    }
    if (icon.includes('/') || icon.includes('\\') || /\.[a-z0-9]{2,5}$/i.test(icon)) {
        return mediaUrl(icon)
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
        visa: 'bg-blue-50 text-blue-700 ring-blue-100',
        mastercard: 'bg-orange-50 text-orange-700 ring-orange-100',
        paypal: 'bg-sky-50 text-sky-700 ring-sky-100',
        stripe: 'bg-violet-50 text-violet-700 ring-violet-100',
        amex: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        apple_pay: 'bg-gray-100 text-gray-700 ring-gray-200',
        google_pay: 'bg-rose-50 text-rose-700 ring-rose-100',
    }

    return classes[normalized] ?? 'bg-gray-100 text-gray-700 ring-gray-200'
}

const showBottomSocialIcons = computed(() => isTruthySetting(frontendFooterSettings.value.show_bottom_social_icons, false))
const showBottomLanguageSelector = computed(() => isTruthySetting(frontendFooterSettings.value.show_bottom_language_selector, false))
const showPaymentIcons = computed(() => isTruthySetting(frontendFooterSettings.value.show_payment_icons, true))
const showBackToTop = computed(() => isTruthySetting(frontendFooterSettings.value.show_back_to_top, true))
const showBottomBarBorder = computed(() => isTruthySetting(frontendFooterSettings.value.bottom_bar_show_border, true))
const bottomBarCentered = computed(() => isTruthySetting(frontendFooterSettings.value.bottom_bar_centered, false))
const hasBrandIntroPanel = computed(() => showLogoAbout.value || showSocialIcons.value)
const hasSpotlightLeadPanel = computed(() => showLogoAbout.value || showSocialIcons.value || showNewsletter.value)
const hasSplitBandTopSection = computed(() => showLogoAbout.value || showSocialIcons.value || showNewsletter.value)

const bottomBarStyle = computed<Record<string, string>>(() => {
    const padding = Number(frontendFooterSettings.value.bottom_bar_padding ?? 32)
    const style: Record<string, string> = {
        '--footer-bottom-padding': `${padding}px`,
        '--footer-bottom-border-width': `${Math.max(1, Number(frontendFooterSettings.value.bottom_bar_border_width ?? 1))}px`,
    }

    let backgroundColor = frontendFooterSettings.value.bottom_bar_bg_color?.trim()
    let textColor = frontendFooterSettings.value.bottom_bar_text_color?.trim()
    let borderColor = frontendFooterSettings.value.bottom_bar_border_color?.trim()

    if (isDark.value) {
        if (!backgroundColor || isColorLight(backgroundColor)) {
            backgroundColor = 'rgba(255, 255, 255, 0.02)'
        }
        if (!textColor || isColorDark(textColor)) {
            textColor = '#cbd5e1'
        }
        if (!borderColor || isColorLight(borderColor)) {
            borderColor = 'rgba(255, 255, 255, 0.08)'
        }
    } else {
        if (isBgLight.value) {
            if (!textColor) textColor = '#475569'
            if (!borderColor) borderColor = 'rgba(0, 0, 0, 0.08)'
        } else {
            if (!textColor) textColor = '#cbd5e1'
            if (!borderColor) borderColor = 'rgba(255, 255, 255, 0.08)'
        }
    }

    if (backgroundColor) style.backgroundColor = backgroundColor
    if (textColor) style.color = textColor
    if (borderColor) style.borderTopColor = borderColor

    return style
})

const bottomBarLinkStyle = computed<Record<string, string>>(() => {
    const style: Record<string, string> = {}
    let textColor = frontendFooterSettings.value.bottom_bar_text_color?.trim()
    if (isDark.value && (!textColor || isColorDark(textColor))) {
        textColor = '#cbd5e1'
    } else if (!isDark.value && isBgLight.value && !textColor) {
        textColor = '#475569'
    }
    if (textColor) style.color = textColor
    return style
})

const bottomBarSocialIconStyle = computed<Record<string, string>>(() => {
    let textColor = frontendFooterSettings.value.bottom_bar_text_color?.trim()
    if (isDark.value && (!textColor || isColorDark(textColor))) {
        textColor = '#cbd5e1'
    } else if (!isDark.value && isBgLight.value && !textColor) {
        textColor = '#475569'
    }
    const style: Record<string, string> = {
        color: textColor || 'inherit',
        borderColor: isDark.value ? 'rgba(255, 255, 255, 0.08)' : (isBgLight.value ? 'rgba(0, 0, 0, 0.08)' : 'rgb(148 163 184 / 0.22)'),
        background: isDark.value ? 'rgba(255, 255, 255, 0.04)' : (isBgLight.value ? 'rgba(0, 0, 0, 0.03)' : 'rgb(148 163 184 / 0.12)'),
    }
    return style
})

const bottomBarLanguageSwitcherStyle = computed<Record<string, string>>(() => {
    const textColor = frontendFooterSettings.value.bottom_bar_text_color?.trim() || 'inherit'

    return {
        color: textColor,
        '--header-control-text-color': textColor,
        '--header-control-hover-color': textColor,
        '--header-soft-icon-border': 'rgb(148 163 184 / 0.22)',
        '--header-soft-icon-bg': 'rgb(148 163 184 / 0.12)',
        '--header-soft-icon-hover-border': 'rgb(148 163 184 / 0.28)',
        '--header-soft-icon-hover-bg': 'rgb(148 163 184 / 0.18)',
        '--header-soft-icon-hover-bg-dark': 'rgb(148 163 184 / 0.2)',
    }
})

const copyrightLine = computed(() => {
    const fallback = t('© {year} :app. All rights reserved.', { app: appName.value })
    return String(frontendFooterSettings.value.copyright_text || fallback)
        .replaceAll('{copyright}', '©')
        .replaceAll('{year}', currentYear.toString())
        .replaceAll('{site_name}', appName.value)
})

const backToTopShapeClass = (shape: ConfigValue | undefined, hasLabel: boolean) => {
    if (shape === 'square') return 'h-9 w-9 rounded-none px-0'
    if (shape === 'pill') return hasLabel ? 'rounded-full px-3.5' : 'h-9 w-9 rounded-full px-0'
    if (shape === 'circle') return 'h-9 w-9 rounded-full px-0'
    return hasLabel ? 'rounded-xl px-3.5' : 'h-9 w-9 rounded-xl px-0'
}

const backToTopIcon = computed(() => frontendFooterSettings.value.back_to_top_icon?.trim() || 'ti ti-arrow-up')
const backToTopLabel = computed(() => frontendFooterSettings.value.back_to_top_label?.trim() || '')
// Icon-only either because no label was set, or because the circle/square shapes have no
// room to draw one. Both cases leave the button unexplained, so it gets a tooltip —
// carrying the admin's own label when there is one, since the shape is what hid it.
const backToTopIsIconOnly = computed(() => {
    const shape = String(frontendFooterSettings.value.back_to_top_shape || '')
    return !backToTopLabel.value || shape === 'circle' || shape === 'square'
})
const backToTopTitle = computed(() => backToTopLabel.value || t('Scroll to top'))
const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })

const hasFooterContent = computed(() => {
    return Boolean(
        brandTitle.value
        || brandDescription.value
        || footerLogo.value
        || menuItems.value.length
        || customText.value
        || contactEmail.value
        || frontendFooterSettings.value.contact_phone?.trim()
        || frontendFooterSettings.value.contact_address?.trim()
        || showNewsletter.value
        || showBottomSocialIcons.value
        || showPaymentIcons.value
        || showBackToTop.value
        || bottomMenuItems.value.length,
    )
})
</script>

<template>
    <section v-if="hasFooterContent && footerStyle === 'card_grid' && showNewsletter" class="w-full py-6">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
            <section class="card-grid-newsletter-band">
                <p class="card-grid-newsletter-badge text-xs font-semibold uppercase tracking-[0.24em]">{{ t('Newsletter') }}</p>
                <div class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] xl:items-center">
                    <div class="space-y-3">
                        <h3 class="card-grid-newsletter-heading text-2xl font-bold sm:text-3xl">{{ newsletterTitle }}</h3>
                        <p class="card-grid-newsletter-copy max-w-3xl text-sm leading-7">{{ newsletterDescription }}</p>
                    </div>
                    <div :class="[showSocialIcons ? 'space-y-4' : '', 'w-full min-w-0']">
                        <form method="post" action="/newsletter/subscribe" class="card-grid-newsletter-form">
                            <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="card-grid-newsletter-input min-w-0 px-4 py-3 text-sm focus:outline-none">
                            <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">
                                {{ newsletterButtonLabel }}
                            </button>
                        </form>
                        <SocialFollow
                            v-if="showSocialIcons"
                            display-mode="icons"
                            icon-item-class="card-grid-social-icon"
                            :icon-item-style="cardGridSocialIconStyle"
                            :icon-inner-style="cardGridSocialIconInnerStyle"
                            :icon-use-platform-color="false"
                            :icon-use-platform-surface="false"
                        />
                    </div>
                </div>
            </section>
        </div>
    </section>

    <footer v-if="hasFooterContent" :class="['mt-auto', footerStyle === 'floating_panel' ? 'footer-shell-floating' : '', isMobileBottomHeaderEnabled ? 'has-mobile-bottom-nav' : '', isBgLight ? 'footer-bg-light' : '']" :style="footerRootStyle">
        <div :class="footerContainerClass" :style="footerContainerStyle">
            <div v-if="footerStyle === 'default'" class="space-y-8">
                <section
                    v-if="showNewsletter"
                    :class="[panelClass, panelToneClass('border-white/12 bg-linear-to-r from-white/12 via-white/6 to-transparent')]"
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center"
                >
                    <div class="space-y-3">
                        <h2 class="footer-heading-main text-2xl font-bold">{{ newsletterTitle }}</h2>
                        <p class="footer-copy-muted max-w-2xl text-sm leading-7">{{ newsletterDescription }}</p>
                    </div>
                    <div class="space-y-3 lg:self-center">
                        <SocialFollow
                            v-if="showSocialIcons"
                            display-mode="icons"
                            icon-item-class="footer-social-icon"
                            :icon-item-style="topSocialIconStyle"
                            :icon-use-platform-color="false"
                            :icon-use-platform-surface="false"
                        />
                        <form method="post" action="/newsletter/subscribe" class="default-newsletter-form w-full">
                            <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="default-newsletter-input  min-w-0 flex-1 rounded-xl border border-white/14 bg-white/8 px-4 py-3 text-sm focus:border-white/30 focus:outline-none">
                            <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">
                                {{ newsletterButtonLabel }}
                            </button>
                        </form>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-4">
                    <div v-for="entry in activeStyleItems" :key="entry.slot" :class="[cardClass, cardToneClass('border-white/10 bg-white/5')]">
                        <div class="space-y-8">
                            <div v-for="item in entry.items" :key="`${entry.slot}-${item}`">
                                <div v-if="item === 'about_text'" class="space-y-3">
                                    <template v-if="showLogoAbout">
                                        <h3 v-if="brandTitle" class="footer-heading-title">{{ brandTitle }}</h3>
                                        <p v-if="brandDescription" class="footer-copy text-sm leading-6">{{ brandDescription }}</p>
                                    </template>
                                </div>
                                <div v-else-if="isLogoBlock(item)">
                                    <img :src="logoBlockSrc(item)" :alt="logoBlockAlt(item)" class="h-10 max-w-40 object-contain">
                                </div>

                                <div v-else-if="isMenuBlock(item)">
                                    <h3 v-if="menuBlockTitle(item)" class="footer-heading-title mb-5">{{ menuBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="menuItem in menuBlockItems(item)" :key="menuItem.id">
                                            <a :href="menuItemHref(menuItem)" :target="menuItemTarget(menuItem)" :rel="menuItemRel(menuItem)" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <i v-if="menuItemIcon(menuItem)" :class="[menuItemIcon(menuItem), 'text-base leading-none']" aria-hidden="true" />
                                                <span>{{ menuItemLabel(menuItem) }}</span>
                                                <span v-if="menuItemBadgeText(menuItem)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(menuItem)}`">{{ menuItemBadgeText(menuItem) }}</span>
                                            </a>
                                        </li>
                                        <li v-if="menuBlockItems(item).length === 0" class="footer-subtle text-sm italic">{{ t('Menu not found') }}</li>
                                    </ul>
                                </div>

                                <div v-else-if="item === 'contact_info'">
                                    <h3 v-if="contactTitle" class="footer-heading-title mb-5">{{ contactTitle }}</h3>
                                    <ul class="footer-copy space-y-4 text-sm">
                                        <li v-if="frontendFooterSettings.contact_address?.trim()" class="flex items-start gap-3">
                                            <i class="footer-soft ti ti-map-pin mt-0.5 text-base"></i>
                                            <span>{{ frontendFooterSettings.contact_address }}</span>
                                        </li>
                                        <li v-if="frontendFooterSettings.contact_phone?.trim()" class="flex items-center gap-3">
                                            <i class="footer-soft ti ti-phone text-base"></i>
                                            <a :href="`tel:${frontendFooterSettings.contact_phone}`" class="footer-nav-link">{{ frontendFooterSettings.contact_phone }}</a>
                                        </li>
                                        <li v-if="contactEmail" class="flex items-center gap-3">
                                            <i class="footer-soft ti ti-mail text-base"></i>
                                            <a :href="`mailto:${contactEmail}`" class="footer-nav-link">{{ contactEmail }}</a>
                                        </li>
                                    </ul>
                                    <p v-if="frontendFooterSettings.contact_details?.trim()" class="footer-copy mt-5 text-sm leading-7">{{ frontendFooterSettings.contact_details }}</p>
                                </div>

                                <div v-else-if="item === 'social_icons'" class="space-y-4">
                                    <h3 v-if="socialColumnTitle" class="footer-heading-title">{{ socialColumnTitle }}</h3>
                                    <SocialFollow display-mode="icons" v-bind="socialColumnIconProps" />
                                </div>

                                <div v-else-if="item === 'newsletter'" class="space-y-4">
                                    <h3 v-if="newsletterColumnTitle" class="footer-heading-title">{{ newsletterColumnTitle }}</h3>
                                    <p class="footer-copy-muted text-sm leading-7">{{ newsletterDescription }}</p>
                                    <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-3">
                                        <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="footer-input min-w-0 flex-1 rounded-xl border border-white/14 bg-white/8 px-4 py-3 text-sm focus:border-white/30 focus:outline-none dark:!text-gray-100">
                                        <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">{{ newsletterButtonLabel }}</button>
                                    </form>
                                </div>

                                <div v-else-if="isCategoryBlock(item)">
                                    <h3 v-if="categoryBlockTitle(item)" class="footer-heading-title mb-5">{{ categoryBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="category in categoryBlockList(item)" :key="category.slug">
                                            <Link :href="`/ai-tools/category/${category.slug}`" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <span>{{ category.name }}</span>
                                                <span v-if="category.tools_count !== undefined && !hideCategoriesCount" class="footer-subtle text-xs">({{ category.tools_count }})</span>
                                            </Link>
                                        </li>
                                        <li v-if="categoryBlockList(item).length === 0" class="footer-subtle text-sm italic">{{ t('No categories selected') }}</li>
                                    </ul>
                                </div>
                                <div v-else>
                                    <h3 v-if="customBlockTitle(item)" class="footer-heading-title mb-5">{{ customBlockTitle(item) }}</h3>
                                    <p v-if="customBlockText(item)" class="footer-copy text-sm leading-6">{{ customBlockText(item) }}</p>
                                    <p v-else class="footer-subtle text-sm leading-6">{{ t('Use this area for trust signals, offer notes, or a short buyer-focused message.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else-if="footerStyle === 'centered'" class="mx-auto max-w-5xl space-y-8 text-center">
                <div class="space-y-4">
                    <template v-if="showLogoAbout">
                        <Link href="/" class="inline-flex items-center justify-center gap-3">
                            <img v-if="footerModeLogo" :src="footerModeLogo" :alt="appName" class="h-11 max-w-44 object-contain">
                            <span v-else class="footer-brand-mark text-2xl font-black tracking-tight">{{ appName }}</span>
                        </Link>
                        <h2 v-if="brandTitle" class="footer-heading-main text-3xl font-bold">{{ brandTitle }}</h2>
                        <p v-if="brandDescription" class="footer-copy mx-auto max-w-3xl text-sm leading-6">{{ brandDescription }}</p>
                    </template>
                    <SocialFollow
                        v-if="showSocialIcons"
                        display-mode="icons"
                        class="justify-center"
                        icon-item-class="footer-social-icon"
                        :icon-item-style="topSocialIconStyle"
                        :icon-use-platform-color="false"
                        :icon-use-platform-surface="false"
                    />
                </div>

                <section v-if="showNewsletter" :class="[panelClass, panelToneClass('border-white/12 bg-white/6')]">
                    <div class="mx-auto max-w-3xl space-y-4">
                        <h3 class="footer-heading-title text-base">{{ newsletterTitle }}</h3>
                        <p class="footer-copy-muted text-sm leading-7">{{ newsletterDescription }}</p>
                        <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-3 sm:flex-row">
                            <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="footer-input min-w-0 flex-1 rounded-xl border !border-white/14 !bg-transparent px-4 py-3 text-sm focus:!border-white/30 focus:outline-none dark:!text-gray-100">
                            <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">
                                {{ newsletterButtonLabel }}
                            </button>
                        </form>
                    </div>
                </section>

                <section class="grid gap-6 md:grid-cols-3">
                    <div v-for="entry in activeStyleItems" :key="entry.slot" :class="[cardClass, cardToneClass('border-white/10 bg-white/5')]" class="text-center">
                        <div class="space-y-8">
                            <div v-for="item in entry.items" :key="`${entry.slot}-${item}`">
                                <div v-if="item === 'about_text'" class="space-y-4">
                                    <template v-if="showLogoAbout">
                                        <h3 v-if="brandTitle" class="footer-heading-title">{{ brandTitle }}</h3>
                                        <p v-if="brandDescription" class="footer-copy text-sm leading-6
                                        ">{{ brandDescription }}</p>
                                    </template>
                                    <SocialFollow v-if="showSocialIcons" display-mode="icons" class="justify-center" icon-item-class="footer-social-icon" :icon-item-style="topSocialIconStyle" :icon-use-platform-color="false" :icon-use-platform-surface="false" />
                                </div>
                                <div v-else-if="isLogoBlock(item)">
                                    <img :src="logoBlockSrc(item)" :alt="logoBlockAlt(item)" class="mx-auto h-10 max-w-40 object-contain">
                                </div>
                                <div v-else-if="isMenuBlock(item)" class="space-y-4">
                                    <h3 v-if="menuBlockTitle(item)" class="footer-heading-title">{{ menuBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="menuItem in menuBlockItems(item)" :key="menuItem.id">
                                            <a :href="menuItemHref(menuItem)" :target="menuItemTarget(menuItem)" :rel="menuItemRel(menuItem)" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <span>{{ menuItemLabel(menuItem) }}</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div v-else-if="item === 'contact_info'" class="footer-copy space-y-3 text-sm leading-7">
                                    <h3 v-if="contactTitle" class="footer-heading-title">{{ contactTitle }}</h3>
                                    <p v-if="contactEmail"><a :href="`mailto:${contactEmail}`" class="footer-nav-link">{{ contactEmail }}</a></p>
                                    <p v-if="frontendFooterSettings.contact_phone?.trim()"><a :href="`tel:${frontendFooterSettings.contact_phone}`" class="footer-nav-link">{{ frontendFooterSettings.contact_phone }}</a></p>
                                    <p v-if="frontendFooterSettings.contact_address?.trim()">{{ frontendFooterSettings.contact_address }}</p>
                                    <p v-if="frontendFooterSettings.contact_details?.trim()" class="footer-copy">{{ frontendFooterSettings.contact_details }}</p>
                                </div>
                                <div v-else-if="item === 'social_icons'" class="space-y-4">
                                    <h3 v-if="socialColumnTitle" class="footer-heading-title">{{ socialColumnTitle }}</h3>
                                    <SocialFollow display-mode="icons" v-bind="socialColumnIconProps" />
                                </div>

                                <div v-else-if="item === 'newsletter'" class="space-y-4">
                                    <h3 v-if="newsletterColumnTitle" class="footer-heading-title">{{ newsletterColumnTitle }}</h3>
                                    <p class="footer-copy-muted text-sm leading-7">{{ newsletterDescription }}</p>
                                    <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-3">
                                        <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="footer-input min-w-0 flex-1 rounded-xl border border-white/14 bg-white/8 px-4 py-3 text-sm focus:border-white/30 focus:outline-none dark:!text-gray-100">
                                        <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">{{ newsletterButtonLabel }}</button>
                                    </form>
                                </div>
                                <div v-else-if="isCategoryBlock(item)" class="space-y-4">
                                    <h3 v-if="categoryBlockTitle(item)" class="footer-heading-title">{{ categoryBlockTitle(item) }}</h3>
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <Link v-for="category in categoryBlockList(item)" :key="category.slug" :href="`/ai-tools/category/${category.slug}`" class="footer-pill-link inline-flex items-center rounded-full border border-white/12 bg-white/6 px-3 py-1.5 text-xs font-semibold transition hover:bg-white/12">
                                            {{ category.name }}
                                        </Link>
                                    </div>
                                </div>
                                <div v-else class="space-y-4">
                                    <h3 v-if="customBlockTitle(item)" class="footer-heading-title">{{ customBlockTitle(item) }}</h3>
                                    <p v-if="customBlockText(item)" class="footer-copy text-sm leading-6">{{ customBlockText(item) }}</p>
                                    <p v-else class="footer-subtle text-sm leading-6">{{ t('Add onboarding hints, guarantees, or buyer-friendly product guidance here.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else-if="footerStyle === 'spotlight'" class="space-y-6">
                <section
                    v-if="showNewsletter"
                    class="spotlight-newsletter-band"
                >
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)] lg:items-center">
                        <div class="space-y-3 text-center lg:text-start">
                            <h2 class="spotlight-newsletter-heading text-3xl font-bold tracking-tight sm:text-4xl">{{ newsletterTitle }}</h2>
                            <p class="mx-auto max-w-2xl text-sm leading-7 text-slate-200 lg:mx-0">{{ newsletterDescription }}</p>
                        </div>
                        <form method="post" action="/newsletter/subscribe" class="spotlight-newsletter-form w-full">
                            <div class="spotlight-newsletter-input-wrap">
                                <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="spotlight-newsletter-input min-w-0 flex-1 rounded-full border px-5 py-3 pe-32 text-sm focus:outline-none sm:pe-40 dark:!text-gray-100">
                                <button type="submit" :aria-label="newsletterButtonLabel" class="spotlight-newsletter-button">
                                    <span class="hidden sm:inline">{{ newsletterButtonLabel }}</span>
                                    <i class="ti ti-arrow-up-right text-base sm:ms-2" aria-hidden="true"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="entry in spotlightMainEntries" :key="entry.slot" :class="[cardClass, cardToneClass('border-white/10 bg-white/5')]">
                        <div class="space-y-8">
                            <div v-for="item in entry.items" :key="`${entry.slot}-${item}`">
                                <div v-if="isMenuBlock(item)">
                                    <h3 v-if="menuBlockTitle(item)" class="footer-heading-title mb-5">{{ menuBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="menuItem in menuBlockItems(item)" :key="menuItem.id">
                                            <a :href="menuItemHref(menuItem)" :target="menuItemTarget(menuItem)" :rel="menuItemRel(menuItem)" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <i v-if="menuItemIcon(menuItem)" :class="[menuItemIcon(menuItem), 'text-base leading-none']" aria-hidden="true" />
                                                <span>{{ menuItemLabel(menuItem) }}</span>
                                                <span v-if="menuItemBadgeText(menuItem)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(menuItem)}`">{{ menuItemBadgeText(menuItem) }}</span>
                                            </a>
                                        </li>
                                        <li v-if="menuBlockItems(item).length === 0" class="footer-subtle text-sm italic">{{ t('Menu not found') }}</li>
                                    </ul>
                                </div>
                                <div v-else-if="item === 'contact_info'">
                                    <h3 v-if="contactTitle" class="footer-heading-title mb-5">{{ contactTitle }}</h3>
                                    <div class="footer-copy space-y-3 text-sm leading-7">
                                        <p v-if="contactEmail"><a :href="`mailto:${contactEmail}`" class="footer-nav-link">{{ contactEmail }}</a></p>
                                        <p v-if="frontendFooterSettings.contact_phone?.trim()"><a :href="`tel:${frontendFooterSettings.contact_phone}`" class="footer-nav-link">{{ frontendFooterSettings.contact_phone }}</a></p>
                                        <p v-if="frontendFooterSettings.contact_address?.trim()">{{ frontendFooterSettings.contact_address }}</p>
                                        <p v-if="frontendFooterSettings.contact_details?.trim()" class="footer-copy">{{ frontendFooterSettings.contact_details }}</p>
                                    </div>
                                </div>
                                <div v-else-if="item === 'social_icons'" class="space-y-4">
                                    <h3 v-if="socialColumnTitle" class="footer-heading-title">{{ socialColumnTitle }}</h3>
                                    <SocialFollow display-mode="icons" v-bind="socialColumnIconProps" />
                                </div>

                                <div v-else-if="item === 'newsletter'" class="space-y-4">
                                    <h3 v-if="newsletterColumnTitle" class="footer-heading-title">{{ newsletterColumnTitle }}</h3>
                                    <p class="footer-copy-muted text-sm leading-7">{{ newsletterDescription }}</p>
                                    <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-3">
                                        <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="footer-input min-w-0 flex-1 rounded-xl border border-white/14 bg-white/8 px-4 py-3 text-sm focus:border-white/30 focus:outline-none">
                                        <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">{{ newsletterButtonLabel }}</button>
                                    </form>
                                </div>
                                <div v-else-if="item === 'about_text'" class="space-y-4">
                                    <template v-if="showLogoAbout">
                                        <h3 v-if="brandTitle" class="footer-heading-title">{{ brandTitle }}</h3>
                                        <p v-if="brandDescription" class="footer-copy text-sm leading-6">{{ brandDescription }}</p>
                                    </template>
                                </div>
                                <div v-else-if="isLogoBlock(item)">
                                    <img :src="logoBlockSrc(item)" :alt="logoBlockAlt(item)" class="h-10 max-w-40 object-contain">
                                </div>
                                <div v-else-if="isCategoryBlock(item)">
                                    <h3 v-if="categoryBlockTitle(item)" class="footer-heading-title mb-5">{{ categoryBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="category in categoryBlockList(item)" :key="category.slug">
                                            <Link :href="`/ai-tools/category/${category.slug}`" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <span>{{ category.name }}</span>
                                                <span v-if="category.tools_count !== undefined && !hideCategoriesCount" class="footer-subtle text-xs">({{ category.tools_count }})</span>
                                            </Link>
                                        </li>
                                        <li v-if="categoryBlockList(item).length === 0" class="footer-subtle text-sm italic">{{ t('No categories selected') }}</li>
                                    </ul>
                                </div>
                                <div v-else>
                                    <h3 v-if="customBlockTitle(item)" class="footer-heading-title mb-5">{{ customBlockTitle(item) }}</h3>
                                    <p v-if="customBlockText(item)" class="footer-copy text-sm leading-6">{{ customBlockText(item) }}</p>
                                    <p v-else class="footer-subtle text-sm leading-6">{{ t('Use this card for trust badges, offer details, or short marketplace notes.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="hasSpotlightBrandBar" :class="[panelClass, panelToneClass('border-white/12 bg-linear-to-r from-white/8 via-white/5 to-transparent')]">
                    <div :class="spotlightBrandBarCentered ? 'flex flex-col items-center gap-5 text-center' : 'flex flex-col gap-5 md:flex-row md:items-center md:justify-between'">
                        <div v-if="showLogoAbout" :class="spotlightBrandBarCentered ? 'flex justify-center' : 'flex items-center gap-4'">
                            <Link href="/" class="inline-flex items-center gap-4">
                                <img v-if="footerModeLogo" :src="footerModeLogo" :alt="spotlightBrandName || appName" class="h-12 max-w-48 object-contain">
                                <span v-if="spotlightBrandName" class="footer-heading-title text-lg font-semibold normal-case">{{ spotlightBrandName }}</span>
                            </Link>
                        </div>
                        <div v-if="showSocialIcons && spotlightSocialProfiles.length" :class="['flex flex-wrap gap-2.5', spotlightBrandBarCentered ? 'justify-center' : 'md:justify-end']">
                            <a
                                v-for="profile in spotlightSocialProfiles"
                                :key="profile.platform"
                                :href="profile.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="spotlight-social-pill"
                                :aria-label="t('Follow :platform', { platform: profile.label })"
                            >
                                <span class="spotlight-social-pill__icon">
                                    <i :class="[socialPlatformIconClass(profile.platform), 'text-base leading-none']" aria-hidden="true" />
                                </span>
                                <span class="truncate">{{ profile.label }}</span>
                            </a>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else-if="footerStyle === 'split_band'" class="space-y-6">
                <section v-if="hasSplitBandTopSection" :class="[panelClass, panelToneClass('border-white/12 bg-linear-to-r from-white/12 via-white/8 to-transparent'), splitBandTopSpacingClass]">
                    <div :class="showLogoAbout ? 'grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-center' : 'mx-auto flex max-w-3xl flex-col gap-4 text-center'">
                        <div v-if="showLogoAbout" class="space-y-4">
                            <Link href="/" class="inline-flex items-center gap-3">
                                <img v-if="footerModeLogo" :src="footerModeLogo" :alt="appName" class="h-10 max-w-40 object-contain">
                                <span v-else class="footer-brand-mark text-xl font-black tracking-tight">{{ appName }}</span>
                            </Link>
                            <h2 v-if="brandTitle" class="footer-heading-main text-xl font-bold">{{ brandTitle }}</h2>
                            <p v-if="brandDescription" class="footer-copy max-w-2xl text-sm leading-6">{{ brandDescription }}</p>
                        </div>
                        <div :class="showLogoAbout ? 'space-y-4 lg:text-end' : 'space-y-4 text-center'">
                            <div v-if="showNewsletter" class="space-y-3">
                                <div :class="showLogoAbout ? 'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between' : 'flex flex-col items-center gap-3'">
                                    <SocialFollow
                                        v-if="showSocialIcons"
                                        display-mode="icons"
                                        :class="showLogoAbout ? 'sm:shrink-0' : 'justify-center'"
                                        icon-item-class="footer-social-icon"
                                        :icon-item-style="topSocialIconStyle"
                                        :icon-use-platform-color="false"
                                        :icon-use-platform-surface="false"
                                    />
                                    <div :class="showLogoAbout ? 'space-y-3' : 'space-y-3 text-center'">
                                        <p class="footer-kicker text-base font-semibold uppercase tracking-[0.24em]">{{ newsletterTitle }}</p>
                                        <p :class="showLogoAbout ? 'footer-copy-muted text-sm leading-6 lg:ms-auto lg:max-w-xl' : 'footer-copy-muted mx-auto max-w-2xl text-sm leading-7'">{{ newsletterDescription }}</p>
                                    </div>
                                </div>
                                <form method="post" action="/newsletter/subscribe" :class="showLogoAbout ? 'split-band-newsletter-form lg:justify-end' : 'split-band-newsletter-form mx-auto w-full max-w-xl'">
                                    <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="split-band-newsletter-input min-w-0 flex-1">
                                    <button type="submit" :aria-label="newsletterButtonLabel" :class="splitBandNewsletterButtonClass">
                                        {{ newsletterButtonLabel }}
                                    </button>
                                </form>
                            </div>
                            <SocialFollow
                                v-else-if="showSocialIcons"
                                display-mode="icons"
                                :class="showLogoAbout ? 'lg:justify-end' : 'justify-center'"
                                icon-item-class="footer-social-icon"
                                :icon-item-style="topSocialIconStyle"
                                :icon-use-platform-color="false"
                                :icon-use-platform-surface="false"
                            />
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="entry in activeStyleItems" :key="entry.slot" :class="[cardClass, cardToneClass('border-white/10 bg-white/5'), splitBandColumnClass]">
                        <div :class="splitBandColumnContentClass">
                            <div v-for="item in entry.items" :key="`${entry.slot}-${item}`">
                                <div v-if="isMenuBlock(item)">
                                    <h3 v-if="menuBlockTitle(item)" class="footer-heading-title mb-5">{{ menuBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="menuItem in menuBlockItems(item)" :key="menuItem.id">
                                            <a :href="menuItemHref(menuItem)" :target="menuItemTarget(menuItem)" :rel="menuItemRel(menuItem)" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <i v-if="menuItemIcon(menuItem)" :class="[menuItemIcon(menuItem), 'text-base leading-none']" aria-hidden="true" />
                                                <span>{{ menuItemLabel(menuItem) }}</span>
                                            </a>
                                        </li>
                                        <li v-if="menuBlockItems(item).length === 0" class="footer-subtle text-sm italic">{{ t('Menu not found') }}</li>
                                    </ul>
                                </div>
                                <div v-else-if="item === 'contact_info'">
                                    <h3 v-if="contactTitle" class="footer-heading-title mb-5">{{ contactTitle }}</h3>
                                    <div class="footer-copy space-y-3 text-sm leading-7">
                                        <p v-if="contactEmail"><a :href="`mailto:${contactEmail}`" class="footer-nav-link">{{ contactEmail }}</a></p>
                                        <p v-if="frontendFooterSettings.contact_phone?.trim()"><a :href="`tel:${frontendFooterSettings.contact_phone}`" class="footer-nav-link">{{ frontendFooterSettings.contact_phone }}</a></p>
                                        <p v-if="frontendFooterSettings.contact_address?.trim()">{{ frontendFooterSettings.contact_address }}</p>
                                    </div>
                                </div>
                                <div v-else-if="item === 'social_icons'" class="space-y-4">
                                    <h3 v-if="socialColumnTitle" class="footer-heading-title">{{ socialColumnTitle }}</h3>
                                    <SocialFollow display-mode="icons" v-bind="socialColumnIconProps" />
                                </div>

                                <div v-else-if="item === 'newsletter'" class="space-y-4">
                                    <h3 v-if="newsletterColumnTitle" class="footer-heading-title">{{ newsletterColumnTitle }}</h3>
                                    <p class="footer-copy-muted text-sm leading-7">{{ newsletterDescription }}</p>
                                    <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-3">
                                        <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="footer-input min-w-0 flex-1 rounded-xl border border-white/14 bg-white/8 px-4 py-3 text-sm focus:border-white/30 focus:outline-none">
                                        <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">{{ newsletterButtonLabel }}</button>
                                    </form>
                                </div>
                                <div v-else-if="item === 'about_text'" class="space-y-4">
                                    <template v-if="showLogoAbout">
                                        <h3 v-if="brandTitle" class="footer-heading-title">{{ brandTitle }}</h3>
                                        <p v-if="brandDescription" class="footer-copy text-sm leading-7">{{ brandDescription }}</p>
                                    </template>
                                </div>
                                <div v-else-if="isLogoBlock(item)">
                                    <img :src="logoBlockSrc(item)" :alt="logoBlockAlt(item)" class="h-10 max-w-40 object-contain">
                                </div>
                                <div v-else-if="isCategoryBlock(item)">
                                    <h3 v-if="categoryBlockTitle(item)" class="footer-heading-title mb-5">{{ categoryBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="category in categoryBlockList(item)" :key="category.slug">
                                            <Link :href="`/ai-tools/category/${category.slug}`" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <span>{{ category.name }}</span>
                                                <span v-if="category.tools_count !== undefined && !hideCategoriesCount" class="footer-subtle text-xs">({{ category.tools_count }})</span>
                                            </Link>
                                        </li>
                                        <li v-if="categoryBlockList(item).length === 0" class="footer-subtle text-sm italic">{{ t('No categories selected') }}</li>
                                    </ul>
                                </div>
                                <div v-else>
                                    <h3 v-if="customBlockTitle(item)" class="footer-heading-title mb-5">{{ customBlockTitle(item) }}</h3>
                                    <p v-if="customBlockText(item)" class="footer-copy text-sm leading-6">{{ customBlockText(item) }}</p>
                                    <p v-else class="footer-subtle text-sm leading-6">{{ t('Add a short product trust message or onboarding note here.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else-if="footerStyle === 'floating_panel'" :class="['space-y-8', showNewsletter ? '' : 'pt-10 sm:pt-12 lg:pt-14']">
                <section
                    v-if="showNewsletter"
                    class="floating-newsletter-band relative z-20 "
                >
                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.95fr)] xl:items-center">
                        <div class="space-y-4 lg:text-left text-center">
                            <h3 class="mb-2 floating-newsletter-title bg-gradient-to-r from-primary-500 to-primary-600 bg-clip-text !text-transparent">{{ newsletterTitle }}</h3>
                            <p class="floating-newsletter-copy">{{ newsletterDescription }}</p>
                        </div>
                        <form method="post" action="/newsletter/subscribe" class="floating-newsletter-form relative">
                            <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="floating-newsletter-input w-full dark:!text-gray-100">
                            <button type="submit" :aria-label="newsletterButtonLabel" :class="splitBandNewsletterButtonClass" class="floating-newsletter-button">
                                {{ newsletterButtonLabel }}
                            </button>
                        </form>
                    </div>
                </section>

                <section v-if="hasBrandIntroPanel" :class="[panelClass, panelToneClass('border-white/12 bg-linear-to-r from-white/12 via-white/8 to-transparent')]">
                    <div class="space-y-5 flex flex-col items-center justify-center text-center">
                        <div v-if="showLogoAbout" class="space-y-5">
                            <Link href="/" class="inline-flex items-center gap-3">
                                <img v-if="footerModeLogo" :src="footerModeLogo" :alt="appName" class="h-10 max-w-40 object-contain">
                                    <span v-else class="footer-brand-mark text-xl font-black tracking-tight">{{ appName }}</span>
                            </Link>
                            <h2 v-if="brandTitle" class="footer-heading-main text-4xl font-bold">{{ brandTitle }}</h2>
                            <p v-if="brandDescription" class="footer-copy max-w-3xl text-sm">{{ brandDescription }}</p>
                        </div>
                        <SocialFollow
                            v-if="showSocialIcons"
                            display-mode="icons"
                            icon-item-class="footer-social-icon"
                            :icon-item-style="topSocialIconStyle"
                            :icon-use-platform-color="false"
                            :icon-use-platform-surface="false"
                        />
                    </div>
                </section>

                <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="entry in activeStyleItems" :key="entry.slot" :class="[cardClass, cardToneClass('border-white/10 bg-white/5')]">
                        <div class="space-y-8">
                            <div v-for="item in entry.items" :key="`${entry.slot}-${item}`">
                                <div v-if="isMenuBlock(item)">
                                    <h3 v-if="menuBlockTitle(item)" class="footer-heading-title mb-5">{{ menuBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="menuItem in menuBlockItems(item)" :key="menuItem.id">
                                            <a :href="menuItemHref(menuItem)" :target="menuItemTarget(menuItem)" :rel="menuItemRel(menuItem)" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <i v-if="menuItemIcon(menuItem)" :class="[menuItemIcon(menuItem), 'text-base leading-none']" aria-hidden="true" />
                                                <span>{{ menuItemLabel(menuItem) }}</span>
                                                <span v-if="menuItemBadgeText(menuItem)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(menuItem)}`">{{ menuItemBadgeText(menuItem) }}</span>
                                            </a>
                                        </li>
                                        <li v-if="menuBlockItems(item).length === 0" class="footer-subtle text-sm italic">{{ t('Menu not found') }}</li>
                                    </ul>
                                </div>
                                <div v-else-if="item === 'contact_info'">
                                    <h3 v-if="contactTitle" class="footer-heading-title mb-5">{{ contactTitle }}</h3>
                                    <div class="footer-copy space-y-3 text-sm leading-7">
                                        <p v-if="contactEmail"><a :href="`mailto:${contactEmail}`" class="footer-nav-link">{{ contactEmail }}</a></p>
                                        <p v-if="frontendFooterSettings.contact_phone?.trim()"><a :href="`tel:${frontendFooterSettings.contact_phone}`" class="footer-nav-link">{{ frontendFooterSettings.contact_phone }}</a></p>
                                        <p v-if="frontendFooterSettings.contact_address?.trim()">{{ frontendFooterSettings.contact_address }}</p>
                                        <p v-if="frontendFooterSettings.contact_details?.trim()" class="footer-copy">{{ frontendFooterSettings.contact_details }}</p>
                                    </div>
                                </div>
                                <div v-else-if="item === 'social_icons'" class="space-y-4">
                                    <h3 v-if="socialColumnTitle" class="footer-heading-title">{{ socialColumnTitle }}</h3>
                                    <SocialFollow display-mode="icons" v-bind="socialColumnIconProps" />
                                </div>

                                <div v-else-if="item === 'newsletter'" class="space-y-4">
                                    <h3 v-if="newsletterColumnTitle" class="footer-heading-title">{{ newsletterColumnTitle }}</h3>
                                    <p class="footer-copy-muted text-sm leading-7">{{ newsletterDescription }}</p>
                                    <form method="post" action="/newsletter/subscribe" class="flex flex-col gap-3">
                                        <input type="email" name="email" required :placeholder="newsletterPlaceholder" class="footer-input footer-input-ghost min-w-0 flex-1 rounded-xl border border-white/18 bg-transparent px-4 py-3 text-sm focus:border-white/36 focus:outline-none dark:!text-gray-100">
                                        <button type="submit" :aria-label="newsletterButtonLabel" :class="newsletterButtonClass">{{ newsletterButtonLabel }}</button>
                                    </form>
                                </div>
                                <div v-else-if="item === 'about_text'" class="space-y-4">
                                    <template v-if="showLogoAbout">
                                        <h3 v-if="brandTitle" class="footer-heading-title">{{ brandTitle }}</h3>
                                        <p v-if="brandDescription" class="footer-copy text-sm leading-6">{{ brandDescription }}</p>
                                    </template>
                                </div>
                                <div v-else-if="isLogoBlock(item)">
                                    <img :src="logoBlockSrc(item)" :alt="logoBlockAlt(item)" class="h-10 max-w-40 object-contain">
                                </div>
                                <div v-else-if="isCategoryBlock(item)">
                                    <h3 v-if="categoryBlockTitle(item)" class="footer-heading-title mb-5">{{ categoryBlockTitle(item) }}</h3>
                                    <ul class="space-y-3">
                                        <li v-for="category in categoryBlockList(item)" :key="category.slug">
                                            <Link :href="`/ai-tools/category/${category.slug}`" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                                <span>{{ category.name }}</span>
                                                <span v-if="category.tools_count !== undefined && !hideCategoriesCount" class="footer-subtle text-xs">({{ category.tools_count }})</span>
                                            </Link>
                                        </li>
                                        <li v-if="categoryBlockList(item).length === 0" class="footer-subtle text-sm italic">{{ t('No categories selected') }}</li>
                                    </ul>
                                </div>
                                <div v-else>
                                    <h3 v-if="customBlockTitle(item)" class="footer-heading-title mb-5">{{ customBlockTitle(item) }}</h3>
                                    <p v-if="customBlockText(item)" class="footer-copy text-sm leading-7">{{ customBlockText(item) }}</p>
                                    <p v-else class="footer-subtle text-sm leading-7">{{ t('Use this floating card for benefits, trust points, or a short marketplace-ready message.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else class="space-y-6">
                <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
                    <div v-for="entry in activeStyleItems" :key="entry.slot" :class="[cardClass, cardToneClass('border-white/10 bg-white/5')]">
                    <div class="space-y-8">
                        <div v-for="item in entry.items" :key="`${entry.slot}-${item}`">
                            <div v-if="isMenuBlock(item)">
                                <h3 v-if="menuBlockTitle(item)" class="footer-heading-title mb-5">{{ menuBlockTitle(item) }}</h3>
                                <ul class="space-y-3">
                                    <li v-for="menuItem in menuBlockItems(item)" :key="menuItem.id">
                                        <a :href="menuItemHref(menuItem)" :target="menuItemTarget(menuItem)" :rel="menuItemRel(menuItem)" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                            <i v-if="menuItemIcon(menuItem)" :class="[menuItemIcon(menuItem), 'text-base leading-none']" aria-hidden="true" />
                                            <span>{{ menuItemLabel(menuItem) }}</span>
                                            <span v-if="menuItemBadgeText(menuItem)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(menuItem)}`">{{ menuItemBadgeText(menuItem) }}</span>
                                        </a>
                                    </li>
                                    <li v-if="menuBlockItems(item).length === 0" class="footer-subtle text-sm italic">{{ t('Menu not found') }}</li>
                                </ul>
                            </div>
                            <div v-else-if="item === 'contact_info'">
                                <h3 v-if="contactTitle" class="footer-heading-title mb-5">{{ contactTitle }}</h3>
                                <div class="footer-copy space-y-3 text-sm leading-7">
                                    <p v-if="contactEmail"><a :href="`mailto:${contactEmail}`" class="footer-nav-link">{{ contactEmail }}</a></p>
                                    <p v-if="frontendFooterSettings.contact_phone?.trim()"><a :href="`tel:${frontendFooterSettings.contact_phone}`" class="footer-nav-link">{{ frontendFooterSettings.contact_phone }}</a></p>
                                    <p v-if="frontendFooterSettings.contact_address?.trim()">{{ frontendFooterSettings.contact_address }}</p>
                                    <p v-if="frontendFooterSettings.contact_details?.trim()" class="footer-copy">{{ frontendFooterSettings.contact_details }}</p>
                                </div>
                            </div>
                            <div v-else-if="item === 'about_text'" class="space-y-4">
                                <h3 v-if="brandTitle" class="footer-heading-title">{{ brandTitle }}</h3>
                                <p v-if="brandDescription" class="footer-copy text-sm leading-6">{{ brandDescription }}</p>
                            </div>
                            <div v-else-if="isLogoBlock(item)">
                                <img :src="logoBlockSrc(item)" :alt="logoBlockAlt(item)" class="h-10 max-w-40 object-contain">
                            </div>
                            <div v-else-if="isCategoryBlock(item)">
                                <h3 v-if="categoryBlockTitle(item)" class="footer-heading-title mb-5">{{ categoryBlockTitle(item) }}</h3>
                                <ul class="space-y-3">
                                    <li v-for="category in categoryBlockList(item)" :key="category.slug">
                                        <Link :href="`/ai-tools/category/${category.slug}`" class="footer-nav-link inline-flex items-center gap-2 text-sm font-normal">
                                            <span>{{ category.name }}</span>
                                            <span v-if="category.tools_count !== undefined && !hideCategoriesCount" class="footer-subtle text-xs">({{ category.tools_count }})</span>
                                        </Link>
                                    </li>
                                    <li v-if="categoryBlockList(item).length === 0" class="footer-subtle text-sm italic">{{ t('No categories selected') }}</li>
                                </ul>
                            </div>
                            <div v-else>
                                <h3 v-if="customBlockTitle(item)" class="footer-heading-title mb-5">{{ customBlockTitle(item) }}</h3>
                                <p v-if="customBlockText(item)" class="footer-copy text-sm leading-6">{{ customBlockText(item) }}</p>
                                <p v-else class="footer-subtle text-sm leading-6">{{ t('Share a support promise, launch note, or short buyer reassurance here.') }}</p>
                            </div>
                        </div>
                    </div>
                    </div>
                </section>
            </div>
        </div>

        <section
            class="footer-bottom-shell"
            :class="[
                showBottomBarBorder ? 'footer-bottom-shell--border' : '',
                bottomBarCentered ? 'footer-bottom-shell--centered' : '',
            ]"
            :style="bottomBarStyle"
        >
            <div v-if="bottomBarCentered" :class="['flex w-full flex-col items-center gap-4 text-center', footerContainerClass]" :style="footerContainerStyle">
                <div v-if="showPaymentIcons && paymentIconList.length" class="footer-payment-image-wrap justify-center">
                    <template v-for="(icon, index) in paymentIconList" :key="`${icon}-${index}`">
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

                <SocialFollow
                    v-if="showBottomSocialIcons"
                    display-mode="icons"
                    class="justify-center"
                    icon-item-class="footer-bottom-social-icon"
                    :icon-item-style="bottomBarSocialIconStyle"
                    :icon-use-platform-color="false"
                    :icon-use-platform-surface="false"
                />

                <div v-if="showBackToTop || showBottomLanguageSelector" class="flex flex-wrap items-center justify-center gap-3">
                    <button
                        v-if="showBackToTop"
                        type="button"
                        class="footer-back-to-top flex items-center justify-center gap-2 bg-white/8 px-2.5 text-sm font-semibold text-inherit transition hover:bg-white/14"
                        :class="backToTopShapeClass(frontendFooterSettings.back_to_top_shape, Boolean(backToTopLabel))"
                        :aria-label="backToTopTitle"
                        :title="backToTopIsIconOnly ? backToTopTitle : undefined"
                        @click="scrollToTop"
                    >
                        <i :class="backToTopIcon"></i>
                        <span v-if="frontendFooterSettings.back_to_top_shape !== 'circle' && frontendFooterSettings.back_to_top_shape !== 'square' && backToTopLabel">
                            {{ backToTopLabel }}
                        </span>
                    </button>

                    <LanguageSwitcher
                        v-if="showBottomLanguageSelector"
                        display="icon_label"
                        placement="up"
                        :ui="{ buttonClass: 'footer-language-switcher inline-flex min-w-0 items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold', buttonStyle: bottomBarLanguageSwitcherStyle }"
                    />
                </div>

                <div v-if="bottomMenuItems.length" class="footer-bottom-item">
                    <ul class="flex flex-wrap items-center justify-center gap-4">
                        <li v-for="item in bottomMenuItems" :key="item.id">
                            <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" class="footer-bottom-link inline-flex items-center gap-2 text-xs font-medium" :style="bottomBarLinkStyle">
                                <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-sm leading-none']" aria-hidden="true" />
                                <span>{{ menuItemLabel(item) }}</span>
                                <span v-if="menuItemBadgeText(item)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <p class="text-xs font-medium">{{ copyrightLine }}</p>
            </div>
            <div v-else :class="['flex w-full flex-col items-center gap-4 text-center lg:hidden', footerContainerClass]" :style="footerContainerStyle">
                <div v-if="showPaymentIcons && paymentIconList.length" class="footer-payment-image-wrap justify-center">
                    <template v-for="(icon, index) in paymentIconList" :key="`mobile-${icon}-${index}`">
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

                <SocialFollow
                    v-if="showBottomSocialIcons"
                    display-mode="icons"
                    class="justify-center"
                    icon-item-class="footer-bottom-social-icon"
                    :icon-item-style="bottomBarSocialIconStyle"
                    :icon-use-platform-color="false"
                    :icon-use-platform-surface="false"
                />

                <div v-if="bottomMenuItems.length" class="footer-bottom-item justify-center">
                    <ul class="flex flex-wrap items-center justify-center gap-4">
                        <li v-for="item in bottomMenuItems" :key="`mobile-${item.id}`">
                            <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" class="footer-bottom-link inline-flex items-center gap-2 text-xs font-medium" :style="bottomBarLinkStyle">
                                <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-sm leading-none']" aria-hidden="true" />
                                <span>{{ menuItemLabel(item) }}</span>
                                <span v-if="menuItemBadgeText(item)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div v-if="showBackToTop || showBottomLanguageSelector" class="flex flex-wrap items-center justify-center gap-3">
                    <button
                        v-if="showBackToTop"
                        type="button"
                        class="footer-back-to-top flex items-center justify-center gap-2 bg-white/8 px-2.5 text-sm font-semibold text-inherit transition hover:bg-white/14"
                        :class="backToTopShapeClass(frontendFooterSettings.back_to_top_shape, Boolean(backToTopLabel))"
                        :aria-label="backToTopTitle"
                        :title="backToTopIsIconOnly ? backToTopTitle : undefined"
                        @click="scrollToTop"
                    >
                        <i :class="backToTopIcon"></i>
                        <span v-if="frontendFooterSettings.back_to_top_shape !== 'circle' && frontendFooterSettings.back_to_top_shape !== 'square' && backToTopLabel">
                            {{ backToTopLabel }}
                        </span>
                    </button>

                    <LanguageSwitcher
                        v-if="showBottomLanguageSelector"
                        display="icon_label"
                        placement="up"
                        :ui="{ buttonClass: 'footer-language-switcher inline-flex min-w-0 items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold', buttonStyle: bottomBarLanguageSwitcherStyle }"
                    />
                </div>

                <p class="text-xs font-medium">{{ copyrightLine }}</p>
            </div>
            <div v-if="!bottomBarCentered" :class="['hidden w-full flex-col gap-4 lg:flex lg:flex-row lg:items-center lg:justify-between', footerContainerClass]" :style="footerContainerStyle">
                <div class="footer-bottom-column footer-bottom-column-left">
                    <p class="text-xs font-medium">{{ copyrightLine }}</p>
                </div>

                <div class="footer-bottom-column footer-bottom-column-right">
                    <div v-if="bottomMenuItems.length" class="footer-bottom-item">
                        <ul class="flex flex-wrap items-center justify-center gap-4 md:justify-end">
                            <li v-for="item in bottomMenuItems" :key="item.id">
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" class="footer-bottom-link inline-flex items-center gap-2 text-xs font-medium" :style="bottomBarLinkStyle">
                                    <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-sm leading-none']" aria-hidden="true" />
                                    <span>{{ menuItemLabel(item) }}</span>
                                    <span v-if="menuItemBadgeText(item)" class="footer-menu-badge" :class="`footer-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <SocialFollow
                        v-if="showBottomSocialIcons"
                        display-mode="icons"
                        icon-item-class="footer-bottom-social-icon"
                        :icon-item-style="bottomBarSocialIconStyle"
                        :icon-use-platform-color="false"
                        :icon-use-platform-surface="false"
                    />

                    <div v-if="showPaymentIcons && paymentIconList.length" class="footer-payment-image-wrap">
                        <template v-for="(icon, index) in paymentIconList" :key="`${icon}-${index}`">
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

                    <button
                        v-if="showBackToTop"
                        type="button"
                        class="footer-back-to-top flex items-center justify-center gap-2 bg-white/8 px-2.5 text-sm font-semibold text-inherit transition hover:bg-white/14"
                        :class="backToTopShapeClass(frontendFooterSettings.back_to_top_shape, Boolean(backToTopLabel))"
                        :aria-label="backToTopTitle"
                        :title="backToTopIsIconOnly ? backToTopTitle : undefined"
                        @click="scrollToTop"
                    >
                        <i :class="backToTopIcon"></i>
                        <span v-if="frontendFooterSettings.back_to_top_shape !== 'circle' && frontendFooterSettings.back_to_top_shape !== 'square' && backToTopLabel">
                            {{ backToTopLabel }}
                        </span>
                    </button>

                    <LanguageSwitcher
                        v-if="showBottomLanguageSelector"
                        display="icon_label"
                        placement="up"
                        :ui="{ buttonClass: 'footer-language-switcher inline-flex min-w-0 items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold', buttonStyle: bottomBarLanguageSwitcherStyle }"
                    />
                </div>
            </div>
        </section>
    </footer>
</template>

<style scoped>
.footer-heading-main,
.footer-heading-title,
.footer-brand-mark,
.footer-kicker {
    color: var(--footer-heading-color, #ffffff) !important;
}

.footer-heading-main,
.footer-heading-title,
.footer-kicker {
    text-transform: var(--footer-heading-transform, capitalize);
    /* The capitalize transform closes the gaps up optically — "Popular Categories" reads
       as one long word at this weight and size. A touch of word-spacing separates them
       again without touching the tracking. */
    word-spacing: 0.08em;
}

/* Column headings only (h3): the spotlight layout puts .footer-heading-title on a span
   beside the brand mark, where turning it into a flex row would break the inline layout.
   The rule runs from the end of the text to the edge of the column, legend style. */
h3.footer-heading-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

h3.footer-heading-title::after {
    content: "";
    flex: 1 1 auto;
    height: 1px;
    background: currentColor;
    opacity: 0.18;
}

.footer-nav-link,
.footer-nav-link span,
.footer-nav-link i,
.footer-bottom-link,
.footer-bottom-link span,
.footer-bottom-link i {
    color: inherit !important;
    transition: opacity 0.2s ease, color 0.2s ease;
}

.footer-nav-link:hover,
.footer-bottom-link:hover {
    opacity: 0.84;
}

/* Full strength, not the 74% muted tier: the column items are the footer's content, and
   about text / custom text / the contact list were reading a shade lighter than the menu
   and category links sitting right beside them. The muted, soft and subtle tiers stay for
   what they are actually for — icons, meta lines and fine print. */
.footer-copy {
    color: var(--footer-text-color) !important;
}

/* The newsletter blurb is the one body text that stays muted — it is a prompt for the
   form under it, not column content competing with the links. */
.footer-copy-muted {
    color: var(--footer-text-muted) !important;
}

.footer-soft {
    color: var(--footer-text-soft) !important;
}

.footer-subtle {
    color: var(--footer-text-subtle) !important;
}

.footer-pill-link {
    color: var(--footer-text-muted) !important;
}

.footer-input {
    color: var(--footer-text-color) !important;
    -webkit-text-fill-color: var(--footer-text-color);
    caret-color: var(--footer-text-color);
}

.footer-input-ghost {
    background: transparent !important;
    color: var(--footer-heading-color, #ffffff) !important;
    -webkit-text-fill-color: var(--footer-heading-color, #ffffff);
    caret-color: var(--footer-heading-color, #ffffff);
}

.footer-input::placeholder {
    color: var(--footer-input-placeholder) !important;
}

.footer-input-ghost::placeholder {
    color: color-mix(in srgb, var(--footer-heading-color, #ffffff) 52%, transparent) !important;
}

.dark .footer-input {
    color: rgb(241 245 249) !important;
    -webkit-text-fill-color: rgb(241 245 249);
    caret-color: rgb(241 245 249);
}

.dark .footer-input::placeholder {
    color: rgb(148 163 184 / 0.94) !important;
}

.split-band-newsletter-form {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.split-band-newsletter-input {
    min-height: 3.25rem;
    border: 1px solid rgb(255 255 255 / 0.18);
    padding: 0.8rem 1rem;
    background: rgb(255 255 255 / 0.96);
    color: rgb(15 23 42);
    -webkit-text-fill-color: rgb(15 23 42);
    caret-color: rgb(15 23 42);
    box-shadow: 0 10px 30px rgb(15 23 42 / 0.08);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.split-band-newsletter-input:focus {
    outline: none;
    border-color: rgb(59 130 246 / 0.5);
    box-shadow: 0 0 0 3px rgb(59 130 246 / 0.14);
}

.split-band-newsletter-input::placeholder {
    color: rgb(100 116 139);
}

.dark .split-band-newsletter-input,
.dark .spotlight-newsletter-input,
.dark .card-grid-newsletter-input,
.dark .floating-newsletter-input {
    border-color: rgb(71 85 105 / 0.8);
    background: rgb(15 23 42 / 0.82);
    color: rgb(241 245 249);
    -webkit-text-fill-color: rgb(241 245 249);
    caret-color: rgb(241 245 249);
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.04);
}

.dark .split-band-newsletter-input::placeholder,
.dark .spotlight-newsletter-input::placeholder,
.dark .card-grid-newsletter-input::placeholder,
.dark .floating-newsletter-input::placeholder {
    color: rgb(148 163 184 / 0.94);
}

.default-newsletter-form {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 0;
}

.default-newsletter-input {
    color: rgb(15 23 42);
    width: 100%;
    border-radius: 0.75rem;
    border-end-start-radius: 0;
    border-end-end-radius: 0;
}

.spotlight-newsletter-form {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 0;
}

.spotlight-newsletter-input-wrap {
    position: relative;
    width: 100%;
}

.default-newsletter-form :deep(button) {
    border-radius: 0.75rem !important;
    margin-top: -1px;
    border-start-start-radius: 0 !important;
    border-start-end-radius: 0 !important;
}

.spotlight-newsletter-band {
    overflow: hidden;
    border: 1px solid rgb(148 163 184 / 0.2);
    border-radius: 1.75rem;
    padding: 1.5rem;
    background:
        radial-gradient(circle at top right, rgb(191 219 254 / 0.7), transparent 24%),
        radial-gradient(circle at bottom left, rgb(220 252 231 / 0.8), transparent 28%),
        linear-gradient(135deg, #0f172a 0%, #172554 45%, #0369a1 100%);
    box-shadow:
        0 24px 70px rgba(15, 23, 42, 0.22),
        0 10px 24px rgba(15, 23, 42, 0.12);
}

.spotlight-newsletter-heading {
    display: inline-block;
    background: linear-gradient(135deg, #fde68a 0%, #fb923c 32%, #f97316 58%, #d946ef 82%, #8b5cf6 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
}

.spotlight-newsletter-input {
    width: 100%;
    border-radius: 9999px !important;
    border-color: rgb(255 255 255 / 0.16);
    background: rgb(255 255 255 / 0.96);
    color: rgb(15 23 42);
    -webkit-text-fill-color: rgb(15 23 42);
    caret-color: rgb(15 23 42);
}

.spotlight-newsletter-input::placeholder {
    color: rgb(100 116 139);
}

.spotlight-newsletter-icon-button {
    position: absolute;
    inset-inline-end: 0.5rem;
    top: 50%;
    display: none;
    height: 2.5rem;
    width: 2.5rem;
    transform: translateY(-50%);
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: linear-gradient(135deg, #f97316, #d946ef);
    color: white;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.spotlight-newsletter-icon-button:hover {
    opacity: 0.92;
}

@media (max-width: 639px) {
    .spotlight-newsletter-icon-button {
        display: inline-flex;
    }
}

.spotlight-newsletter-button {
    position: absolute;
    inset-inline-end: 0;
    top: 50%;
    min-height: 2.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px !important;
    padding: 0.65rem 0.95rem;
    background: linear-gradient(135deg, #f97316, #ef4444);
    color: white;
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
    transform: translateY(-50%);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.spotlight-newsletter-button:hover {
    opacity: 0.92;
}

.card-grid-newsletter-band {
    position: relative;
    overflow: visible;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 1.75rem;
    padding: 2.6rem 1.5rem 1.5rem;
    background:
        radial-gradient(circle at top right, rgb(219 234 254 / 0.9), transparent 24%),
        radial-gradient(circle at bottom left, rgb(220 252 231 / 0.92), transparent 30%),
        linear-gradient(135deg, #ffffff 0%, #f8fafc 48%, #eef2ff 100%);
    box-shadow:
        0 24px 70px rgba(148, 163, 184, 0.18),
        0 10px 24px rgba(148, 163, 184, 0.1);
}

.card-grid-newsletter-badge {
    position: absolute;
    inset-block-start: 0;
    inset-inline-start: 50%;
    transform: translate(-50%, -50%);
    border: 1px solid rgb(165 180 252 / 0.8);
    border-radius: 9999px;
    padding: 0.45rem 0.95rem;
    color: rgb(79 70 229);
    background: linear-gradient(135deg, rgb(238 242 255), rgb(224 231 255));
    box-shadow: 0 12px 26px rgb(129 140 248 / 0.18);
    white-space: nowrap;
    z-index: 2;
}

.card-grid-newsletter-heading {
    background: linear-gradient(
        135deg,
        var(--color-primary-500, #10b981) 0%,
        var(--color-primary-600, #059669) 52%,
        var(--color-primary-700, #047857) 100%
    );
    color: transparent;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.card-grid-newsletter-copy {
    color: rgb(71 85 105);
}

.dark .card-grid-newsletter-band {
    border-color: rgb(30 41 59);
    background:
        radial-gradient(circle at top right, rgb(16 185 129 / 0.06), transparent 28%),
        radial-gradient(circle at bottom left, rgb(99 102 241 / 0.06), transparent 30%),
        linear-gradient(135deg, rgb(15 23 42) 0%, rgb(9 15 30) 100%);
    box-shadow:
        0 24px 70px rgba(0, 0, 0, 0.4),
        0 10px 24px rgba(0, 0, 0, 0.2);
}

.dark .card-grid-newsletter-badge {
    border-color: rgb(99 102 241 / 0.4);
    color: rgb(165 180 252);
    background: linear-gradient(135deg, rgb(30 41 59), rgb(17 24 39));
    box-shadow: 0 12px 26px rgba(0, 0, 0, 0.3);
}

.dark .card-grid-newsletter-copy {
    color: rgb(148 163 184);
}

.card-grid-newsletter-form {
    display: flex;
    align-items: stretch;
    gap: 0;
    width: 100%;
}

.card-grid-newsletter-input {
    flex: 1 1 auto;
    width: 100%;
    min-width: 0;
    border: 1px solid rgb(203 213 225);
    border-top-left-radius: 9999px !important;
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    border-bottom-left-radius: 9999px !important;
    background: rgba(255, 255, 255, 0.9);
    color: rgb(15 23 42);
    -webkit-text-fill-color: rgb(15 23 42);
    caret-color: rgb(15 23 42);
    box-shadow: 0 10px 30px rgb(148 163 184 / 0.12);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.card-grid-newsletter-input:focus {
    border-color: rgb(99 102 241 / 0.5);
    box-shadow: 0 0 0 3px rgb(99 102 241 / 0.12);
}

.card-grid-newsletter-input::placeholder {
    color: rgb(148 163 184);
}

.card-grid-newsletter-form :deep(button) {
    margin-top: 0;
    flex: 0 0 auto;
    white-space: nowrap;
    border-radius: 9999px !important;
    border-start-start-radius: 0 !important;
    border-end-start-radius: 0 !important;
    border-start-end-radius: 9999px !important;
    border-end-end-radius: 9999px !important;
    margin-inline-start: -1px;
}

:deep(.card-grid-social-icon) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.7rem;
    height: 2.7rem;
    border: 1px solid color-mix(in srgb, var(--color-primary-500, #10b981) 22%, white);
    border-radius: 9999px;
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-primary-500, #10b981) 12%, white),
        color-mix(in srgb, var(--color-primary-500, #10b981) 7%, white)
    );
    color: var(--color-primary-600, #059669);
    box-shadow: 0 10px 26px color-mix(in srgb, var(--color-primary-500, #10b981) 14%, transparent);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

:deep(.card-grid-social-icon:hover) {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--color-primary-500, #10b981) 36%, white);
    box-shadow: 0 14px 30px color-mix(in srgb, var(--color-primary-500, #10b981) 20%, transparent);
}

:deep(.card-grid-social-icon i),
:deep(.card-grid-social-icon svg) {
    color: inherit;
}

.dark :deep(.card-grid-social-icon) {
    border-color: color-mix(in srgb, var(--color-primary-900, #064e3b) 22%, #1e293b);
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-primary-900, #064e3b) 12%, #0f172a),
        color-mix(in srgb, var(--color-primary-900, #064e3b) 7%, #0f172a)
    );
    color: var(--color-primary-400, #34d399);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.3);
}

.dark :deep(.card-grid-social-icon:hover) {
    border-color: color-mix(in srgb, var(--color-primary-500, #10b981) 36%, #1e293b);
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-primary-900, #064e3b) 18%, #0f172a),
        color-mix(in srgb, var(--color-primary-900, #064e3b) 12%, #0f172a)
    );
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.4);
}

.split-band-newsletter-submit {
    min-height: 3.25rem;
    border-radius: 0.9rem !important;
    padding: 0.8rem 1.2rem;
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1;
    transition: opacity 0.2s ease, transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
}

@media (min-width: 640px) {
    .spotlight-newsletter-form {
        align-items: stretch;
    }

    .default-newsletter-form {
        flex-direction: row;
        align-items: stretch;
    }

    .default-newsletter-input {
        border-radius: 0.75rem;
        border-end-start-radius: 0.75rem;
        border-start-end-radius: 0 !important;
        border-end-end-radius: 0 !important;
    }

    .default-newsletter-form :deep(button) {
        border-radius: 0.75rem !important;
        margin-top: 0;
        border-start-start-radius: 0 !important;
        border-end-start-radius: 0 !important;
        border-start-end-radius: 0.75rem !important;
        border-end-end-radius: 0.75rem !important;
        margin-inline-start: -1px;
    }

    .spotlight-newsletter-button {
        min-height: 2.875rem;
        padding-inline: 1.15rem;
    }

    .card-grid-newsletter-form {
        flex-direction: row;
        align-items: stretch;
    }

    .split-band-newsletter-form {
        flex-direction: row;
        align-items: stretch;
    }

    .split-band-newsletter-input {
        border-start-end-radius: 0 !important;
        border-end-end-radius: 0 !important;
    }

    .split-band-newsletter-submit {
        border-start-start-radius: 0 !important;
        border-end-start-radius: 0 !important;
        margin-top: -2px;
    }

    .floating-newsletter-button {
        margin-top: -2px;
    }
}

@media (max-width: 639px) {
    .split-band-newsletter-form {
        gap: 0.75rem;
    }

    .card-grid-newsletter-band {
        text-align: center;
    }

    .card-grid-newsletter-copy {
        margin-inline: auto;
    }

    .card-grid-newsletter-form {
        flex-direction: column;
        gap: 0.75rem;
    }

    .card-grid-newsletter-input {
        border-radius: 9999px !important
    }

    .card-grid-newsletter-form :deep(button) {
        width: 100%;
        margin-inline-start: 0;
        border-radius: 9999px !important;
    }

    :deep(.card-grid-newsletter-band .flex.shrink-0.items-center.gap-2\.5) {
        justify-content: center;
    }

    .floating-newsletter-form {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .floating-newsletter-input {
        padding: 0.9rem 1.5rem !important;
        width: 100% !important;
    }

    .floating-newsletter-button {
        position: relative !important;
        inset: auto !important;
        width: 100% !important;
        min-height: 3.5rem !important;
        justify-content: center;
    }

    .spotlight-newsletter-input {
        padding-inline-end: 3.5rem !important;
    }

    .spotlight-newsletter-button {
        inset-inline-end: 0.375rem !important;
        padding: 0 !important;
        width: 2.75rem !important;
        height: 2.75rem !important;
        min-height: 2.75rem !important;
    }

    .default-newsletter-form {
        gap: 0.75rem;
    }

    .default-newsletter-input {
        border-radius: 0.75rem !important;
    }

    .default-newsletter-form :deep(button) {
        border-radius: 0.75rem !important;
        margin-top: 0 !important;
    }
}

.footer-menu-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.footer-menu-badge--green { background: rgb(220 252 231) !important; color: rgb(21 128 61) !important; }
.footer-menu-badge--blue { background: rgb(219 234 254) !important; color: rgb(29 78 216) !important; }
.footer-menu-badge--violet { background: rgb(237 233 254) !important; color: rgb(109 40 217) !important; }
.footer-menu-badge--amber { background: rgb(254 243 199) !important; color: rgb(180 83 9) !important; }
.footer-menu-badge--red { background: rgb(254 226 226) !important; color: rgb(220 38 38) !important; }
.footer-menu-badge--gray { background: rgb(243 244 246) !important; color: rgb(75 85 99) !important; }

:deep(.footer-social-icon),
:deep(.footer-bottom-social-icon) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    color: inherit;
    border-radius: 9999px;
    box-shadow: none;
    transition: all 0.2s ease;
}

:deep(.footer-social-icon:hover) {
    background: rgb(255 255 255 / 0.12) !important;
    border-color: rgb(255 255 255 / 0.2) !important;
    transform: translateY(-2px);
}

.footer-bg-light :deep(.footer-social-icon:hover) {
    background: rgba(15, 23, 42, 0.1) !important;
    border-color: rgba(15, 23, 42, 0.16) !important;
}

:deep(.footer-bottom-social-icon:hover) {
    background: rgb(148 163 184 / 0.2) !important;
    border-color: rgb(148 163 184 / 0.3) !important;
    transform: translateY(-2px);
}

.spotlight-social-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 9999px;
    border: 1px solid color-mix(in srgb, var(--footer-text-color) 14%, transparent);
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--footer-text-color) 12%, transparent),
        color-mix(in srgb, var(--footer-text-color) 5%, transparent)
    );
    padding: 0.25rem 1rem 0.5rem 0.25rem;
    color: var(--footer-text-color);
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    backdrop-filter: blur(10px);
    box-shadow: inset 0 1px 0 color-mix(in srgb, var(--footer-text-color) 8%, transparent), 0 10px 24px rgb(15 23 42 / 0.16);
    transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}

.spotlight-social-pill,
.spotlight-social-pill span,
.spotlight-social-pill i {
    color: var(--footer-text-color) !important;
    -webkit-text-fill-color: var(--footer-text-color);
}

.spotlight-social-pill:hover {
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--footer-text-color) 16%, transparent),
        color-mix(in srgb, var(--footer-text-color) 8%, transparent)
    );
    border-color: color-mix(in srgb, var(--footer-text-color) 22%, transparent);
    box-shadow: inset 0 1px 0 color-mix(in srgb, var(--footer-text-color) 10%, transparent), 0 14px 30px rgb(15 23 42 / 0.2);
    transform: translateY(-1px);
}

.spotlight-social-pill__icon {
    display: inline-flex;
    height: 1.5rem;
    width: 1.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--footer-text-color) 18%, rgb(15 23 42 / 0.72)),
        color-mix(in srgb, var(--footer-text-color) 10%, rgb(15 23 42 / 0.92))
    );
    border: 1px solid color-mix(in srgb, var(--footer-text-color) 14%, transparent);
    color: var(--footer-text-color);
    box-shadow: inset 0 1px 0 color-mix(in srgb, var(--footer-text-color) 8%, transparent);
    flex-shrink: 0;
}

.footer-shell-floating {
    position: relative;
    margin-top: 6rem;
    border-top: 1px solid var(--footer-floating-shell-edge, rgb(15 23 42 / 0.12));
    border-top-left-radius: 2rem;
    border-top-right-radius: 2rem;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--footer-floating-shell-bg, #0f172a) 96%, white 4%) 0%, var(--footer-floating-shell-bg, #0f172a) 100%);
    box-shadow: var(--footer-floating-shell-shadow, 0 -24px 80px rgba(15, 23, 42, 0.14));
    overflow: visible;
}

@media (max-width: 1023px) {
    .footer-shell-floating {
        margin-top: 5rem;
    }
}

@media (max-width: 767px) {
    .footer-shell-floating {
        margin-top: 4rem;
    }
}

.footer-shell-floating::before {
    content: none;
}

.footer-shell-floating::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(180deg, rgb(255 255 255 / 0.06), transparent 20%);
}

.floating-newsletter-band {
    position: relative;
    z-index: 20;
    transform: translateY(-50%);
    margin-bottom: -2.5rem;
    overflow: hidden;
    border: 1px solid rgb(226 232 240);
    border-radius: 1.75rem;
    padding: 1.5rem;
    background:
        radial-gradient(circle at top right, rgb(191 219 254 / 0.7), transparent 24%),
        radial-gradient(circle at bottom left, rgb(220 252 231 / 0.85), transparent 26%),
        linear-gradient(135deg, #ffffff 0%, #f8fafc 52%, #eef6ff 100%);
    box-shadow:
        0 24px 70px rgba(15, 23, 42, 0.18),
        0 10px 24px rgba(15, 23, 42, 0.08);
}

@media (max-width: 1023px) {
    .floating-newsletter-band {
        margin-bottom: -5rem;
    }
}

.floating-newsletter-band::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(135deg, rgb(255 255 255 / 0.74), transparent 58%);
}

.dark .floating-newsletter-band {
    border-color: rgb(30 41 59 / 0.8);
    background:
        radial-gradient(circle at top right, rgb(59 130 246 / 0.14), transparent 24%),
        radial-gradient(circle at bottom left, rgb(16 185 129 / 0.14), transparent 26%),
        linear-gradient(135deg, #0e1526 0%, #1e293b 52%, #0f172a 100%);
    box-shadow:
        0 24px 70px rgba(0, 0, 0, 0.4),
        0 10px 24px rgba(0, 0, 0, 0.2);
}

.dark .floating-newsletter-band::before {
    background: linear-gradient(135deg, rgb(255 255 255 / 0.04), transparent 58%);
}

.floating-newsletter-title {
    position: relative;
    z-index: 1;
    max-width: 36rem;
    font-size: clamp(1.75rem, 2.8vw, 2.5rem);
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.03em;
    text-transform: none;
}

.floating-newsletter-copy {
    position: relative;
    z-index: 1;
    max-width: 40rem;
    color: rgb(71 85 105);
    font-size: 0.95rem;
    line-height: 1.8;
}

.dark .floating-newsletter-copy {
    color: rgb(148 163 184);
}

.floating-newsletter-form {
    position: relative;
    z-index: 1;
}

.floating-newsletter-input {
    position: relative;
    z-index: 1;
    min-height: 3.75rem;
    border: 1px solid rgb(203 213 225);
    border-radius: 9999px !important;
    padding: 0.9rem 8.5rem 0.9rem 1.5rem;
    background: rgb(255 255 255 / 0.88);
    color: rgb(15 23 42);
    -webkit-text-fill-color: rgb(15 23 42);
    caret-color: rgb(15 23 42);
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.75);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.floating-newsletter-input:focus {
    outline: none;
    border-color: var(--color-primary-500);
    background: rgb(255 255 255);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary-500) 20%, transparent);
}

.floating-newsletter-input::placeholder {
    color: rgb(148 163 184);
}

.floating-newsletter-button {
    position: absolute;
    z-index: 2;
    inset-block: 0.35rem;
    right: 0.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 9999px !important;
    padding: 0 1.5rem;
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
}

.floating-newsletter-button:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}

.footer-bottom-shell {
    background-color: inherit;
    color: inherit;
    margin-top: var(--footer-bottom-gap, 40px);
}

.footer-bottom-shell--border {
    border-top-style: solid;
    border-top-width: var(--footer-bottom-border-width, 1px);
}

.footer-bottom-shell--centered {
    text-align: center;
}

.footer-bottom-column {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 1rem;
}

.footer-bottom-column-left {
    justify-content: center;
}

.footer-bottom-column-right {
    flex: 1 1 auto;
    flex-wrap: wrap;
    justify-content: center;
}

.footer-bottom-item {
    display: inline-flex;
    align-items: center;
}

.footer-payment-image-wrap {
    flex-shrink: 0;
}

.footer-payment-image {
    display: block;
    flex: 0 0 auto;
    width: auto;
    height: 32px;
    object-fit: contain;
}

.footer-back-to-top {
    min-height: 36px;
}

@media (min-width: 768px) {
    .footer-bottom-shell {
        padding-block: var(--footer-bottom-padding, 32px);
    }

    .footer-bottom-column-left {
        justify-content: flex-start;
        text-align: start;
    }

    .footer-bottom-column-right {
        justify-content: flex-end;
    }
}

@media (max-width: 767px) {
    .footer-bottom-shell {
        padding-block: var(--footer-bottom-padding, 32px);
    }

    footer.has-mobile-bottom-nav {
        padding-bottom: var(--mobile-bottom-height, 60px) !important;
    }
}
</style>
