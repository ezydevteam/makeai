<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import type { CSSProperties } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import CommandPalette from '@/Components/CommandPalette.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import SocialFollow from '@/Components/SocialFollow.vue'

type HeaderStyle = CSSProperties & Record<`--${string}`, string>
type Branding = {
    site_name?: string
    site_logo_light?: string
    site_logo_dark?: string
}
type AppHeaderUser = {
    name?: string
    email?: string
    credits?: number
    subscription_status?: string | null
}
type SimpleDesktopHeaderSettings = {
    layout?: string
    sticky?: boolean
    sticky_behavior?: string
    shadow_style?: string
    show_notification_bell?: boolean
    notification_button_style?: string
    show_social_icons?: boolean
    social_icon_style?: string
    show_language_switcher?: boolean
    language_switcher_style?: string
    show_dark_mode_toggle?: boolean
    dark_mode_toggle_style?: string
    command_palette_style?: string
    auth_mode?: string
    guest_login_text?: string
    guest_login_icon_class?: string
    guest_login_style?: string
    guest_register_text?: string
    guest_register_icon_class?: string
    guest_register_style?: string
    guest_button_shape?: string
    account_avatar_style?: string
    show_cta_button?: boolean
    cta_text?: string
    cta_link?: string
    cta_icon?: string
    cta_style?: string
    cta_shape?: string
    cta_access_level?: string
    menu_source?: string
    height?: number
    container_width?: string
    bg_color?: string
    transparent_on_hero?: boolean
    text_color?: string
    menu_hover_color?: string
    show_border?: boolean
    show_shadow?: boolean
}
type SimpleMobileTopHeaderSettings = {
    enabled?: boolean
    layout?: string
    sticky?: boolean
    show_logo?: boolean
    show_hamburger?: boolean
    show_dark_mode_toggle?: boolean
}
type SimpleMobileBottomHeaderSettings = {
    enabled?: boolean
    layout?: string
    show_home?: boolean
    show_tools?: boolean
    show_dashboard?: boolean
    show_profile?: boolean
}
type SimpleHeaderSettings = {
    desktop?: SimpleDesktopHeaderSettings
    mobile_top?: SimpleMobileTopHeaderSettings
    mobile_bottom?: SimpleMobileBottomHeaderSettings
}

const { isDark, toggleDark } = useTheme()
const { t } = useTranslate()
const page = usePage()
const branding = computed(() => page.props.branding as Branding | undefined)
const siteName = computed(() => String(branding.value?.site_name || page.props.appName || t('Application')))

const user = computed(() => page.props.auth?.user as AppHeaderUser | undefined)
const frontendHeaderSettings = computed(() => (page.props.frontendHeaderSettings as SimpleHeaderSettings | undefined) ?? {})
const resolveFrontendMenuSlug = (value: string | undefined, fallback: string) => {
    if (!value) return fallback
    if (value === 'primary') return 'main'
    if (value === 'company') return 'footer-company'
    if (value === 'support') return 'footer-support'
    if (value === 'legal') return 'footer-legal'
    return value
}
const buildSimplifiedHeaderConfig = (settings: SimpleHeaderSettings) => {
    const desktop = settings.desktop ?? {}
    const mobileTop = settings.mobile_top ?? {}
    const mobileBottom = settings.mobile_bottom ?? {}
    const desktopLayout = (desktop.layout || 'classic') === 'landing' ? 'centered' : (desktop.layout || 'classic')
    const mobileTopLayout = mobileTop.layout || 'compact'
    const mobileBottomLayout = mobileBottom.layout || 'tabs'
    const desktopHeight = Number(desktop.height ?? (desktopLayout === 'compact' ? 64 : desktopLayout === 'centered' ? 80 : 72))
    const mobileTopHeight = mobileTopLayout === 'centered' ? 72 : 64
    const desktopTextColor = desktop.text_color || ''
    const desktopHoverColor = desktop.menu_hover_color || desktop.text_color || ''
    const desktopStickyBehavior = desktop.sticky_behavior || (desktop.sticky === false ? 'none' : 'always')
    const navBlockAlign = ['saas', 'compact'].includes(desktopLayout) ? 'left' : 'center'
    const logoBlockAlign = 'left'
    const desktopNavHoverStyle = desktopLayout === 'centered'
        ? 'pill'
        : ['saas', 'compact'].includes(desktopLayout)
            ? 'box'
            : 'underline'
    const mainRightBlocks: Array<Record<string, unknown>> = [
    ]
    const desktopAccountAvatarStyle = String(desktop.account_avatar_style || 'avatar_name_arrow')

    if ((desktop.command_palette_style || 'hidden') !== 'hidden') {
        mainRightBlocks.push({
            id: 'simple_command_palette',
            type: 'command_palette',
            enabled: true,
            config: {
                block_align: 'right',
                display_style: desktop.command_palette_style || 'icon_only',
                icon_class: 'ti ti-search',
                text_color: desktopTextColor,
                hover_color: desktopHoverColor,
                label: t('Search'),
                hint: t('Ctrl + K'),
            },
        })
    }

    if (desktop.show_language_switcher !== false && desktop.language_switcher_style !== 'hide') {
        mainRightBlocks.push({ id: 'simple_lang', type: 'language_switcher', enabled: true, config: { block_align: 'right', display_style: desktop.language_switcher_style || 'icon_with_label', text_color: desktopTextColor, hover_color: desktopHoverColor } })
    }

    if (desktop.show_dark_mode_toggle !== false && desktop.dark_mode_toggle_style !== 'hide') {
        mainRightBlocks.push({ id: 'simple_dark', type: 'dark_mode', enabled: true, config: { block_align: 'right', display_style: desktop.dark_mode_toggle_style || 'rounded_soft_bg', icon_color: desktopTextColor, text_color: desktopTextColor, hover_color: desktopHoverColor } })
    }

    if (desktop.show_notification_bell !== false && desktop.notification_button_style !== 'hide') {
        mainRightBlocks.push({ id: 'simple_notify', type: 'notification_bell', enabled: true, config: { block_align: 'right', display_style: desktop.notification_button_style || 'rounded_soft_bg', icon_color: desktopTextColor, text_color: desktopTextColor, hover_color: desktopHoverColor } })
    }

    if ((desktop.auth_mode || 'login_register') !== 'none') {
        mainRightBlocks.push({
            id: 'simple_user',
            type: 'user_menu',
            enabled: true,
            config: {
                show_credits: true,
                show_avatar: true,
                block_align: 'right',
                text_color: desktopTextColor,
                hover_color: desktopHoverColor,
                guest_mode: (desktop.auth_mode || 'login_register') === 'login_register' ? 'both' : 'login_only',
                guest_login_text: desktop.guest_login_text || t('Login'),
                guest_login_icon_class: desktop.guest_login_icon_class || 'ti ti-login-2',
                guest_login_style: desktop.guest_login_style || 'primary',
                guest_register_text: desktop.guest_register_text || t('Register'),
                guest_register_icon_class: desktop.guest_register_icon_class || 'ti ti-user-plus',
                guest_register_style: desktop.guest_register_style || 'dark',
                guest_button_shape: desktop.guest_button_shape || 'rounded_xl',
                auth_display: ['avatar_only', 'avatar_only_rounded', 'avatar_only_circle'].includes(desktopAccountAvatarStyle) ? 'avatar_only' : 'avatar_name',
                avatar_shape: desktopAccountAvatarStyle === 'avatar_only_circle' ? 'circle' : 'rounded',
                show_arrow_icon: desktopAccountAvatarStyle === 'avatar_name_arrow',
            },
        })
    }

    if (desktop.show_cta_button !== false) {
        mainRightBlocks.push({
            id: 'simple_cta',
            type: 'cta_button',
            enabled: true,
            config: {
                block_align: 'right',
                text: desktop.cta_text || t('Get Started'),
                link: desktop.cta_link || '/register',
                style: desktop.cta_style || 'primary',
                shape: desktop.cta_shape || 'rounded_xl',
                access_level: desktop.cta_access_level || 'all',
                icon_class: desktop.cta_icon || 'ti ti-rocket',
            },
        })
    }

    if (desktop.show_social_icons === true && desktop.social_icon_style !== 'hide') {
        mainRightBlocks.push({
            id: 'simple_social',
            type: 'social_icons',
            enabled: true,
            config: {
                block_align: 'right',
                display_mode: 'icons',
                display_style: desktop.social_icon_style || 'rounded_soft_bg',
                text_color: desktopTextColor,
                hover_color: desktopHoverColor,
            },
        })
    }

    const mobileBlocks: Array<Record<string, unknown>> = []
    if (mobileTop.show_hamburger !== false) {
        mobileBlocks.push({
            id: 'simple_mobile_hamburger',
            type: 'hamburger',
            enabled: true,
            config: {
                block_align: 'left',
                menu_slug: resolveFrontendMenuSlug(desktop.menu_source, 'mobile'),
                label: t('Menu'),
                show_label: false,
                icon_class: 'ti ti-menu-2',
            },
        })
    }
    if (mobileTop.show_logo !== false) {
        mobileBlocks.push({ id: 'simple_mobile_logo', type: 'logo', enabled: true, config: { block_align: 'left' } })
    }

    mobileBlocks.push({ id: 'simple_mobile_notify', type: 'notification_bell', enabled: true, config: { block_align: 'right' } })

    if (mobileTop.show_dark_mode_toggle !== false) {
        mobileBlocks.push({ id: 'simple_mobile_dark', type: 'dark_mode', enabled: true, config: { block_align: 'right' } })
    }

    const mobileBottomBlocks: Array<Record<string, unknown>> = []
    if (mobileBottom.show_home !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_home', type: 'home_link', enabled: true, config: { link: '/', label: t('Home'), icon_class: 'ti ti-home', show_label: true } })
    }
    if (mobileBottom.show_tools !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_tools', type: 'home_link', enabled: true, config: { link: '/ai-tools', label: t('Tools'), icon_class: 'ti ti-sparkles', show_label: true } })
    }
    if (mobileBottom.show_dashboard !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_dashboard', type: 'home_link', enabled: true, config: { link: '/user/dashboard', label: t('Dashboard'), icon_class: 'ti ti-layout-dashboard', show_label: true } })
    }
    if (mobileBottom.show_profile !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_profile', type: 'user_menu_icon', enabled: true, config: { label: t('Account'), guest_label: t('Sign In'), icon_class: 'ti ti-user', show_label: true } })
    }

    return {
        layout: desktopLayout,
        main: {
            enabled: true,
            sticky: desktop.sticky !== false,
            height: desktopHeight,
            layout: desktopLayout,
            container_width: desktop.container_width || '1280px',
            sticky_behavior: desktopStickyBehavior,
            shadow: desktop.show_shadow === true,
            show_border: desktop.show_border !== false,
            shadow_style: desktop.shadow_style || 'border_small',
            progressbar: false,
            background: { color: desktop.bg_color || '', image_url: '', overlay_opacity: 0 },
            text_color: desktopTextColor,
            menu_hover_color: desktopHoverColor,
            custom_css: '',
            blocks: [
                { id: 'simple_logo', type: 'logo', enabled: true, config: { block_align: logoBlockAlign } },
                { id: 'simple_nav', type: 'navigation', enabled: true, config: { menu_slug: resolveFrontendMenuSlug(desktop.menu_source, 'main'), alignment: ['saas', 'compact'].includes(desktopLayout) ? 'left' : 'center', block_align: navBlockAlign, hover_style: desktopNavHoverStyle, text_color: desktopTextColor, hover_color: desktopHoverColor } },
                ...mainRightBlocks,
            ],
        },
        mobile: {
            enabled: mobileTop.enabled !== false,
            sticky: mobileTop.sticky !== false,
            height: mobileTopHeight,
            layout: mobileTopLayout,
            container_width: '1280px',
            sticky_behavior: mobileTop.sticky === false ? 'none' : 'always',
            shadow: false,
            progressbar: false,
            background: { color: '', image_url: '', overlay_opacity: 0 },
            custom_css: '',
            blocks: mobileBlocks,
        },
        mobile_bottom: {
            enabled: mobileBottom.enabled === true,
            sticky: true,
            height: 64,
            layout: mobileBottomLayout,
            container_width: '1280px',
            sticky_behavior: 'always',
            shadow: true,
            progressbar: false,
            background: { color: '', image_url: '', overlay_opacity: 0 },
            custom_css: '',
            blocks: mobileBottomBlocks,
        },
    }
}
const rawHeaderConfig = computed(() => buildSimplifiedHeaderConfig(frontendHeaderSettings.value))
const headerConfig = computed(() => rawHeaderConfig.value?.main ?? rawHeaderConfig.value)
const mobileHeaderConfig = computed(() => rawHeaderConfig.value?.mobile)
const mobileBottomHeaderConfig = computed(() => rawHeaderConfig.value?.mobile_bottom)
const mainHeaderHeight = computed(() => Number(headerConfig.value?.height ?? 72))
const totalDesktopHeaderHeight = computed(() => mainHeaderHeight.value)
const desktopTransparentOnHero = computed(() => frontendHeaderSettings.value.desktop?.transparent_on_hero === true)
const isHeroOverlayActive = ref(false)
let clearHeroOverlayListeners: (() => void) | null = null
let heroOverlayFrame: number | null = null
let heroOverlayTimeout: ReturnType<typeof setTimeout> | null = null
let heroOverlayResizeObserver: ResizeObserver | null = null

const blockFilter = (b: any) => b.enabled

const activeBlocks = computed(() => (headerConfig.value?.blocks || []).filter(blockFilter))
const activeMobileBlocks = computed(() => (mobileHeaderConfig.value?.blocks || []).filter(blockFilter))
const activeMobileBottomBlocks = computed(() => (mobileBottomHeaderConfig.value?.blocks || []).filter(blockFilter))
const mainLeftBlocks = computed(() => activeBlocks.value.filter((block: any) => (block.config?.block_align || 'left') === 'left'))
const mainCenterBlocks = computed(() => activeBlocks.value.filter((block: any) => block.config?.block_align === 'center'))
const mainRightBlocks = computed(() => activeBlocks.value.filter((block: any) => block.config?.block_align === 'right'))
const mainHeaderLayout = computed(() => String(headerConfig.value?.layout || 'classic'))
const isStackedCenteredMainHeader = computed(() => mainHeaderLayout.value === 'centered' || mainHeaderLayout.value === 'landing')
const centeredMainLogoBlocks = computed(() => activeBlocks.value.filter((block: any) => block.type === 'logo'))
const centeredMainNavBlocks = computed(() => activeBlocks.value.filter((block: any) => block.type === 'navigation'))
const centeredMainActionBlocks = computed(() => activeBlocks.value.filter((block: any) => !['logo', 'navigation'].includes(block.type)))
const isOverlayCenteredMainHeader = computed(() => false)
const isLeftClusterMainHeader = computed(() => ['saas', 'compact'].includes(mainHeaderLayout.value))
const isMinimalMainHeader = computed(() => mainHeaderLayout.value === 'minimal')
const supportsTransparentHeroHeader = computed(() => desktopTransparentOnHero.value || headerConfig.value?.transparent_homepage === true)
const isTransparentDesktopHeaderActive = computed(() => supportsTransparentHeroHeader.value && isHeroOverlayActive.value)
const isTransparentMainHeaderActive = computed(() => isTransparentDesktopHeaderActive.value)
const mainHeaderBackgroundStyle = computed(() => isTransparentMainHeaderActive.value ? {} : sectionBackgroundStyle(headerConfig.value))
const isOverlayLightBlock = (_block: any) => isTransparentMainHeaderActive.value
const mainHeaderSectionStyle = computed<CSSProperties>(() => ({
    ...sectionStyle(headerConfig.value, 'main', 72),
    ...(isTransparentMainHeaderActive.value
        ? {
            position: stickyBehavior(headerConfig.value) === 'none' ? 'absolute' : 'fixed',
            top: stickyTop('main'),
            left: '0px',
            right: '0px',
        }
        : {}),
}))
const mainRowGapClass = computed(() => {
    return 'gap-2.5'
})
const mainRowLayoutClass = computed(() => {
    if (isStackedCenteredMainHeader.value) return 'flex-col justify-center py-4'
    if (isLeftClusterMainHeader.value) return 'justify-between'
    if (isMinimalMainHeader.value) return 'justify-between'
    return ''
})
const mainCenterNavClass = computed(() => {
    if (isStackedCenteredMainHeader.value) return 'max-w-full flex-wrap justify-center'
    return ''
})
const mainColumnGroupClass = (col: 'left' | 'center' | 'right') => {
    return col === 'right' ? 'gap-2.5 shrink-0 flex-nowrap [&>*]:shrink-0' : 'gap-2.5'
}
const mainNavClass = (zone: 'left' | 'center') => {
    const classes = ['hidden', 'items-center']

    if (zone === 'left') {
        if (isStackedCenteredMainHeader.value) {
            classes.push('md:flex', 'min-w-0', 'justify-center', 'gap-1')
            return classes
        }

        if (isLeftClusterMainHeader.value) {
            classes.push('md:flex', 'min-w-0', 'flex-1', 'gap-1.5')
        } else {
            classes.push('md:flex', 'min-w-0', 'gap-1')
        }

        return classes
    }

    classes.push('md:flex', 'min-w-0', 'max-w-full', 'justify-center', 'gap-1')
    return classes
}
const mainColFlexClass = (col: 'left' | 'center' | 'right') => {
    if (isStackedCenteredMainHeader.value) {
        if (col === 'left') return 'justify-center'
        if (col === 'center') return 'justify-center min-w-0'
        return 'justify-center shrink-0'
    }

    if (isLeftClusterMainHeader.value) {
        if (col === 'left') return 'min-w-0 flex-1'
        if (col === 'center') return 'hidden'
        return 'shrink-0 justify-end'
    }

    if (isMinimalMainHeader.value) {
        if (col === 'center') return 'flex-1 min-w-0 justify-center'
        return col === 'right' ? 'shrink-0 justify-end' : 'shrink-0'
    }

    if (col === 'left') return 'shrink-0'
    if (col === 'center') return 'flex-1 min-w-0 justify-center'
    return 'shrink-0 justify-end'
}
const mobileColFlexClass = (col: 'left' | 'right') => {
    const flex = (mobileHeaderConfig.value?.column_flex ?? 'default') as string
    if (flex === col || (flex === 'default' && col === 'left')) return 'flex-1 min-w-0'
    return ''
}
const mobileLeftBlocks = computed(() => activeMobileBlocks.value.filter((block: any) => (block.config?.block_align || 'left') === 'left'))
const mobileRightBlocks = computed(() => activeMobileBlocks.value.filter((block: any) => block.config?.block_align === 'right'))
const mobileHamburgerBlock = computed(() => activeMobileBlocks.value.find((block: any) => block.type === 'hamburger'))
const mobileDrawerMenuSlug = computed(() => mobileHamburgerBlock.value?.config?.menu_slug || 'mobile')
const mobileDrawerTitle = computed(() => mobileHamburgerBlock.value?.config?.drawer_title || page.props.appName)
const mobileTopLayout = computed(() => String(mobileHeaderConfig.value?.layout || 'compact'))
const isCenteredMobileTop = computed(() => mobileTopLayout.value === 'centered')
const mobileTopSideClass = computed(() => isCenteredMobileTop.value ? 'relative z-10' : '')
const mobileTopLogoClass = computed(() => isCenteredMobileTop.value ? 'absolute left-1/2 -translate-x-1/2 rtl:left-auto rtl:right-1/2 rtl:translate-x-1/2' : '')

const activeUserMenu = ref<'main' | null>(null)
const mobileMenuOpen = ref(false)
const lastScrollY = ref(0)
const scrollY = ref(0)
const scrollDirection = ref<'up' | 'down'>('down')
const scrollProgress = ref(0)

const logout = () => router.post(route('logout'))

const globalMenus = computed(() => page.props.globalMenus as Array<any> || [])
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))

const getMenu = (slug: string) => globalMenus.value.find((m: any) => m.slug === slug)

const visibleMenuItems = (slug: string) => {
    const menu = getMenu(slug)
    const items = menu?.items?.filter((item: any) => item.is_active !== false) || []
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

const menuItemId = (item: any) => item.id ?? item.key ?? item.url
const menuItemHref = (item: any) => String(item.final_url || item.url || '#')
const menuParentId = (item: any) => item.parent_id ?? item.parentId ?? null
const topMenuItems = (slug: string) => visibleMenuItems(slug).filter((item: any) => !menuParentId(item))
const submenuItems = (slug: string, parentId: string | number) => visibleMenuItems(slug).filter((item: any) => String(menuParentId(item)) === String(parentId))

const isActive = (url: string) => {
    if (!url) return false
    const path = new URL(url, window.location.origin).pathname
    return window.location.pathname === path
}

const stickyBehavior = (config: any) => config?.sticky_behavior || (config?.sticky === false ? 'none' : config?.hide_on_scroll ? 'upscroll' : 'always')
const stickyOffset = (config: any) => scrollDirection.value === 'up' ? Number(config?.upscroll_offset ?? 80) : Number(config?.downscroll_offset ?? 80)

const isHeaderVisible = (config: any) => {
    const behavior = stickyBehavior(config)
    if (behavior === 'none' || behavior === 'always') return true
    if (scrollY.value < stickyOffset(config)) return true
    return behavior === 'upscroll' ? scrollDirection.value === 'up' : scrollDirection.value === 'down'
}

const containerClass = (config: any, mobile = false) => {
    const cw = config?.container_width ?? '1280px'
    if (cw === 'full') return 'w-full px-4 sm:px-6'
    if (cw === '1080px' || cw === 'boxed') return mobile ? 'mx-auto w-full max-w-[1080px] px-4' : 'mx-auto w-full max-w-[1080px] px-4 sm:px-6'
    if (cw === '1536px') return mobile ? 'mx-auto w-full max-w-[1536px] px-4' : 'mx-auto w-full max-w-[1536px] px-4 sm:px-6'
    return mobile ? 'mx-auto w-full max-w-7xl px-4' : 'mx-auto w-full max-w-7xl px-4 sm:px-6'
}

const sectionBackgroundStyle = (config: any): CSSProperties => {
    const bg = config?.background
    const style: CSSProperties = {}
    if (bg) {
        if (bg.color) style.backgroundColor = bg.color
        if (bg.image_url) {
            style.backgroundImage = `url(${bg.image_url})`
            style.backgroundSize = 'cover'
            style.backgroundPosition = 'center'
        }
        if (bg.image_url && (bg.overlay_opacity ?? 0) > 0) {
            style['--header-bg-overlay'] = `rgba(0,0,0,${Number(bg.overlay_opacity)})`
        }
    }
    return style
}
const sectionAccentStyle = (config: any): HeaderStyle => {
    const style: HeaderStyle = {}
    const textColor = typeof config?.text_color === 'string' ? config.text_color : ''
    const hoverColor = typeof config?.menu_hover_color === 'string' ? config.menu_hover_color : ''

    if (textColor) {
        style.color = textColor
        style['--header-menu-text-color'] = textColor
        style['--header-control-text-color'] = textColor
        style['--header-action-text-color'] = textColor
    }

    if (hoverColor) {
        style['--header-menu-hover-color'] = hoverColor
        style['--header-control-hover-color'] = hoverColor
        style['--header-action-hover-color'] = hoverColor
        style['--header-menu-hover-bg'] = `color-mix(in srgb, ${hoverColor} 12%, transparent)`
        style['--header-menu-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 18%, transparent)`
        style['--header-control-hover-bg'] = `color-mix(in srgb, ${hoverColor} 10%, transparent)`
        style['--header-control-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 14%, transparent)`
        style['--header-action-hover-bg'] = style['--header-control-hover-bg']
        style['--header-action-hover-bg-dark'] = style['--header-control-hover-bg-dark']
    }

    return style
}
const hasCustomBackground = (config: any) => Boolean(config?.background?.color || config?.background?.image_url)

const sectionPositionClass = (config: any) => {
    const positionClass = stickyBehavior(config) === 'none' ? 'relative' : 'sticky'
    return `${positionClass} z-50`
}
const sectionTransitionClass = (config: any) => config?.transition_enabled === false
    ? ''
    : 'transform-gpu will-change-transform transition-[background-color,border-color,box-shadow,backdrop-filter,transform,opacity] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]'
const sectionChromeStyle = (config: any) => {
    const style = String(config?.shadow_style || '')

    if (['none', 'small', 'medium', 'large', 'border_small', 'border_large'].includes(style)) {
        return style
    }

    if (config?.shadow) return 'medium'
    if (config?.show_border === false) return 'none'
    if (config?.show_border) return 'border_small'

    return 'none'
}
const sectionShadowClass = (config: any) => {
    const chromeStyle = sectionChromeStyle(config)

    if (chromeStyle === 'small') return 'shadow-sm shadow-gray-900/8 dark:shadow-black/15'
    if (chromeStyle === 'medium') return 'shadow-md shadow-gray-900/10 dark:shadow-black/20'
    if (chromeStyle === 'large') return 'shadow-xl shadow-gray-900/14 dark:shadow-black/28'

    return ''
}
const sectionBorderClass = (config: any, edge: 'top' | 'bottom' = 'bottom', forceLight = false) => {
    const chromeStyle = sectionChromeStyle(config)
    const edgeClass = edge === 'top' ? 'border-t' : 'border-b'

    if (forceLight) {
        if (chromeStyle === 'none') return 'border-none'
        return `${edgeClass} border-white/18`
    }

    if (chromeStyle === 'border_small') return `${edgeClass} border-gray-200 dark:border-white/10`
    if (chromeStyle === 'border_large') return `${edgeClass}-2 border-gray-300 dark:border-white/15`

    return 'border-none'
}
const sectionVisibilityClass = (config: any) => isHeaderVisible(config)
    ? 'translate-y-0 opacity-100'
    : '-translate-y-full opacity-0 pointer-events-none'
const isBottomHeaderVisible = (config: any) => {
    const behavior = stickyBehavior(config)
    if (behavior === 'none' || behavior === 'always') return true
    const offset = behavior === 'upscroll' ? Number(config?.upscroll_offset ?? 80) : Number(config?.downscroll_offset ?? 80)
    if (scrollY.value < offset) return true
    return behavior === 'upscroll' ? scrollDirection.value === 'up' : scrollDirection.value === 'down'
}

const removeHeroOverlayListeners = () => {
    clearHeroOverlayListeners?.()
    clearHeroOverlayListeners = null

    if (heroOverlayFrame !== null && typeof window !== 'undefined') {
        window.cancelAnimationFrame(heroOverlayFrame)
        heroOverlayFrame = null
    }

    if (heroOverlayTimeout !== null) {
        clearTimeout(heroOverlayTimeout)
        heroOverlayTimeout = null
    }

    heroOverlayResizeObserver?.disconnect()
    heroOverlayResizeObserver = null
}

const syncHeroOverlayState = () => {
    if (typeof window === 'undefined') return

    const heroSection = document.querySelector<HTMLElement>('[data-home-hero="true"]')

    if (!supportsTransparentHeroHeader.value || !heroSection) {
        isHeroOverlayActive.value = false
        return
    }

    const heroBounds = heroSection.getBoundingClientRect()
    const isAtTop = window.scrollY <= 2
    isHeroOverlayActive.value = isAtTop && heroBounds.bottom > 0
}

const bindHeroOverlayState = async () => {
    removeHeroOverlayListeners()
    await nextTick()

    if (typeof window === 'undefined') return

    if (!supportsTransparentHeroHeader.value) {
        isHeroOverlayActive.value = false
        return
    }

    const handleViewportChange = () => syncHeroOverlayState()
    handleViewportChange()

    window.addEventListener('scroll', handleViewportChange, { passive: true })
    window.addEventListener('resize', handleViewportChange)

    clearHeroOverlayListeners = () => {
        window.removeEventListener('scroll', handleViewportChange)
        window.removeEventListener('resize', handleViewportChange)
    }

    const heroSection = document.querySelector<HTMLElement>('[data-home-hero="true"]')
    if (typeof ResizeObserver !== 'undefined' && heroSection) {
        heroOverlayResizeObserver = new ResizeObserver(() => {
            syncHeroOverlayState()
        })
        heroOverlayResizeObserver.observe(heroSection)
    }

    heroOverlayFrame = window.requestAnimationFrame(() => {
        heroOverlayFrame = window.requestAnimationFrame(() => {
            syncHeroOverlayState()
        })
    })

    heroOverlayTimeout = setTimeout(() => {
        syncHeroOverlayState()
    }, 180)
}

watch(
    [
        () => page.url,
        supportsTransparentHeroHeader,
        totalDesktopHeaderHeight,
    ],
    () => {
        void bindHeroOverlayState()
    },
    { immediate: true },
)

onUnmounted(() => {
    removeHeroOverlayListeners()
})
const bottomSectionVisibilityClass = (config: any) => isBottomHeaderVisible(config)
    ? 'translate-y-0 opacity-100'
    : 'translate-y-full opacity-0 pointer-events-none'

const stickyTop = (_section: 'main' | 'mobile' | 'mobile_bottom') => '0px'

const sectionStyle = (config: any, section: 'main' | 'mobile' | 'mobile_bottom', defaultHeight: number): CSSProperties => {
    const resolvedHeight = Number(config?.height ?? defaultHeight)

    if (section === 'main' && ['centered', 'landing'].includes(String(config?.layout || 'classic'))) {
        return {
            minHeight: `${Math.max(resolvedHeight, 148)}px`,
            top: stickyTop(section),
        }
    }

    return {
        height: `${resolvedHeight}px`,
        top: stickyTop(section),
    }
}


const mobileIconButtonClass = 'flex h-10 w-10 items-center justify-center rounded-xl bg-gray-50 text-gray-600 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
const mobileBottomItemClass = 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
const configString = (config: Record<string, unknown> | undefined, key: string, fallback = '') => typeof config?.[key] === 'string' ? (config[key] as string) : fallback
const sharedButtonStyleValue = (value: unknown) => {
    const style = String(value || 'primary')
    return ['primary', 'dark', 'danger', 'success', 'warning', 'purple', 'gradient_sunset', 'gradient_ocean', 'gradient_royal', 'outline', 'ghost', 'bg_light', 'custom', 'filled', 'green', 'gradient'].includes(style) ? style : 'primary'
}
const ctaStyleValue = (block: any) => {
    return sharedButtonStyleValue(block.config?.style)
}
const buttonShapeClass = (shapeValue: unknown) => {
    const shape = String(shapeValue || 'rounded_xl')
    if (shape === 'sharp') return 'rounded-none'
    if (shape === 'rounded') return 'rounded-md'
    if (shape === 'pill') return 'rounded-full'
    return 'rounded-xl'
}
const ctaShapeClass = (block: any) => buttonShapeClass(block.config?.shape)
const buttonVariantClass = (styleValue: string) => [
    styleValue === 'primary' || styleValue === 'filled' ? 'btn-primary shadow-lg shadow-primary-600/20' : '',
    styleValue === 'dark' ? 'bg-gray-900 text-white hover:bg-black dark:bg-surface-700 dark:hover:bg-surface-600' : '',
    styleValue === 'danger' ? 'bg-danger-600 text-white hover:bg-danger-700 shadow-lg shadow-danger-600/20' : '',
    styleValue === 'success' || styleValue === 'green' ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-600/20' : '',
    styleValue === 'warning' ? 'bg-amber-500 text-white hover:bg-amber-600 shadow-lg shadow-amber-500/20' : '',
    styleValue === 'purple' ? 'bg-violet-600 text-white hover:bg-violet-700 shadow-lg shadow-violet-600/20' : '',
    styleValue === 'gradient' || styleValue === 'gradient_sunset' ? 'bg-gradient-to-r from-orange-500 via-rose-500 to-pink-500 text-white hover:opacity-95 shadow-lg shadow-rose-500/25' : '',
    styleValue === 'gradient_ocean' ? 'bg-gradient-to-r from-cyan-500 via-sky-500 to-blue-600 text-white hover:opacity-95 shadow-lg shadow-sky-500/25' : '',
    styleValue === 'gradient_royal' ? 'bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-500 text-white hover:opacity-95 shadow-lg shadow-violet-500/25' : '',
    styleValue === 'outline' ? 'border border-primary-600 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : '',
    styleValue === 'ghost' ? 'text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : '',
    styleValue === 'bg_light' ? 'bg-gray-50 text-gray-600 hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300' : '',
    styleValue === 'custom' ? 'hover:opacity-90' : '',
]
const mobileCtaClass = (block: any, bottom = false) => [
    bottom ? 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 px-2 py-1.5 text-xs font-bold transition-colors' : (block.config?.icon_only ? 'inline-flex h-10 w-10 items-center justify-center text-sm font-bold transition-colors' : 'inline-flex h-10 items-center justify-center gap-1.5 px-3 text-xs font-bold transition-colors'),
    ctaShapeClass(block),
    ...buttonVariantClass(ctaStyleValue(block)),
]
const blockIconClass = (block: any, fallback = '') => String(block.config?.icon_class || block.config?.button_icon || fallback)
const ctaIconSizeClass = (bottom = false) => bottom ? 'text-xl leading-none' : 'text-[20px] leading-none'
const headerUtilityDisplayStyle = (value: unknown, allowLabel = false) => {
    const style = String(value || (allowLabel ? 'icon_with_label' : 'rounded_soft_bg'))
    const allowed = allowLabel
        ? ['hide', 'icon_only', 'rounded_soft_bg', 'circular_soft_bg', 'light_bg', 'icon_with_label']
        : ['hide', 'icon_only', 'rounded_soft_bg', 'circular_soft_bg', 'light_bg']

    return allowed.includes(style) ? style : (allowLabel ? 'icon_with_label' : 'rounded_soft_bg')
}
const headerUtilityClass = (block: any, bottom = false) => {
    if (bottom) {
        return iconSurfaceClass(block, 'relative flex min-w-0 w-full flex-col items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300')
    }

    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style)
    const roundedClass = ['icon_only', 'circular_soft_bg'].includes(displayStyle) ? 'rounded-full' : 'rounded-lg'
    const iconOnlyClass = displayStyle === 'icon_only' ? 'header-soft-icon-button--icon-only' : ''
    const sizeClass = 'h-9 w-9'
    const toneClass = displayStyle === 'icon_only'
        ? 'border-transparent bg-transparent shadow-none'
        : displayStyle === 'light_bg'
            ? 'border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800'
            : 'border'

    return iconSurfaceClass(block, `header-soft-icon-button ${iconOnlyClass} relative flex ${sizeClass} items-center justify-center ${roundedClass} ${toneClass} text-gray-500 transition-all duration-200 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white`)
}
const notificationButtonClass = (block: any, bottom = false) => headerUtilityClass(block, bottom)
const socialIconButtonClass = (block: any) => {
    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style)
    const sizeOverride = '!h-9 !w-9 !min-w-9'
    return `${headerUtilityClass(block, false).join(' ')} ${sizeOverride} !justify-center !gap-0 !p-0 !shadow-none`
}
const softIconSurfaceStyle = (block: any): HeaderStyle => {
    const style: HeaderStyle = {}
    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style)

    if (isOverlayLightBlock(block)) {
        style.color = '#ffffff'
        style['--header-soft-icon-color'] = '#ffffff'
        style['--header-soft-icon-hover-color'] = '#ffffff'
        style['--header-soft-icon-hover-bg'] = 'rgb(255 255 255 / 0.14)'
        style['--header-soft-icon-hover-bg-dark'] = 'rgb(255 255 255 / 0.18)'
        style['--header-soft-icon-hover-border'] = 'rgb(255 255 255 / 0.28)'

        if (displayStyle !== 'icon_only') {
            style.background = 'rgb(255 255 255 / 0.08)'
            style.borderColor = 'rgb(255 255 255 / 0.18)'
        }

        return style
    }

    const textColor = configString(block.config, 'icon_color') || configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color') || textColor

    style['--header-soft-icon-hover-bg'] = 'var(--color-gray-100)'
    style['--header-soft-icon-hover-bg-dark'] = 'rgb(255 255 255 / 0.08)'
    style['--header-soft-icon-hover-border'] = 'rgb(148 163 184 / 0.18)'

    if (textColor) {
        style.color = textColor
        style['--header-soft-icon-color'] = textColor
        if (!['icon_only', 'light_bg'].includes(displayStyle)) {
            style.background = `color-mix(in srgb, ${textColor} 10%, transparent)`
            style.borderColor = `color-mix(in srgb, ${textColor} 16%, transparent)`
        }
    }

    if (hoverColor) {
        style['--header-soft-icon-hover-color'] = hoverColor
    }

    return style
}
const isIconOnly = (block: any) => Boolean(block.config?.icon_only)
const blockText = (block: any, fallback: string) => String(block.config?.text || fallback)
const blockLabel = (block: any, fallback: string) => String(block.config?.label || fallback)
const blockHint = (block: any, fallback: string) => String(block.config?.hint || fallback)
const showBlockLabel = (block: any) => block.config?.show_label !== false
const languageSwitcherDisplay = (block: any): 'default' | 'icon' | 'icon_label' | 'bottom' => {
    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style, true)
    if (displayStyle === 'icon_with_label') return 'icon_label'
    return 'icon'
}
const languageSwitcherClass = (block: any) => {
    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style, true)
    if (displayStyle === 'icon_with_label') return ''
    const shapeClass = ['icon_only', 'circular_soft_bg'].includes(displayStyle) ? '!rounded-full' : '!rounded-lg'
    const sizeOverride = '!h-9 !w-9 !min-w-9'
    return `${headerUtilityClass(block, false).join(' ')} ${shapeClass} ${sizeOverride} !justify-center !gap-0 !p-0`
}
const languageSwitcherStyle = (block: any) => {
    if (isOverlayLightBlock(block)) {
        const displayStyle = headerUtilityDisplayStyle(block.config?.display_style, true)
        const style: HeaderStyle = {
            color: '#ffffff',
            '--header-control-text-color': '#ffffff',
            '--header-control-hover-color': '#ffffff',
            '--header-control-hover-bg': 'rgb(255 255 255 / 0.14)',
            '--header-control-hover-bg-dark': 'rgb(255 255 255 / 0.18)',
            '--header-action-text-color': '#ffffff',
            '--header-action-hover-color': '#ffffff',
            '--header-action-hover-bg': 'rgb(255 255 255 / 0.14)',
            '--header-action-hover-bg-dark': 'rgb(255 255 255 / 0.18)',
        }

        if (!['icon_with_label', 'icon_only'].includes(displayStyle)) {
            style.background = 'rgb(255 255 255 / 0.08)'
            style.borderColor = 'rgb(255 255 255 / 0.18)'
            style['--header-soft-icon-color'] = '#ffffff'
            style['--header-soft-icon-hover-color'] = '#ffffff'
            style['--header-soft-icon-hover-bg'] = 'rgb(255 255 255 / 0.14)'
            style['--header-soft-icon-hover-bg-dark'] = 'rgb(255 255 255 / 0.18)'
        }

        return style
    }

    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style, true)
    return displayStyle === 'icon_with_label' ? headerActionStyle(block) : softIconSurfaceStyle(block)
}
const commandPaletteDisplayStyle = (block: any) => {
    const style = String(block.config?.display_style || 'icon_only')
    return ['icon_only', 'rounded_soft_bg', 'circular_soft_bg', 'search_transparent', 'search_light'].includes(style) ? style : 'icon_only'
}
const commandPaletteButtonClass = (block: any) => {
    const displayStyle = commandPaletteDisplayStyle(block)

    if (['icon_only', 'rounded_soft_bg', 'circular_soft_bg'].includes(displayStyle)) {
        if (displayStyle === 'circular_soft_bg') {
            return `${notificationButtonClass({ ...block, config: { ...block.config, display_style: 'circular_soft_bg' } }).join(' ')}`
        }
        if (displayStyle === 'rounded_soft_bg') {
            return `${notificationButtonClass({ ...block, config: { ...block.config, display_style: 'rounded_soft_bg' } }).join(' ')}`
        }
        return notificationButtonClass(block).join(' ')
    }

    return [
        'group hidden h-10 items-center justify-between gap-3 rounded-xl border px-3 text-sm transition-all md:flex',
        displayStyle === 'search_light'
            ? 'min-w-[180px] bg-gray-50 dark:bg-surface-800'
            : 'min-w-[180px] bg-transparent',
    ].join(' ')
}
const commandPaletteButtonStyle = (block: any): HeaderStyle => {
    if (['icon_only', 'rounded_soft_bg', 'circular_soft_bg'].includes(commandPaletteDisplayStyle(block))) {
        return softIconSurfaceStyle(block)
    }

    const style: HeaderStyle = {}

    if (isOverlayLightBlock(block)) {
        style.color = '#ffffff'
        style.borderColor = 'rgb(255 255 255 / 0.18)'
        style.background = commandPaletteDisplayStyle(block) === 'search_light'
            ? 'rgb(255 255 255 / 0.12)'
            : 'rgb(255 255 255 / 0.04)'
        return style
    }

    const textColor = configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color') || textColor

    if (textColor) {
        style.color = textColor
        style.borderColor = `color-mix(in srgb, ${textColor} 16%, transparent)`
        if (commandPaletteDisplayStyle(block) === 'search_transparent') {
            style.background = `color-mix(in srgb, ${textColor} 5%, transparent)`
        }
    }

    if (hoverColor) {
        style['--header-command-hover-bg'] = `color-mix(in srgb, ${hoverColor} 10%, transparent)`
        style['--header-command-hover-border'] = `color-mix(in srgb, ${hoverColor} 24%, transparent)`
    }

    return style
}
const commandPaletteHintClass = (block: any) => {
    if (isOverlayLightBlock(block)) return 'text-white/70'
    return 'text-gray-400 dark:text-gray-500'
}
const commandPaletteLabelClass = (block: any) => {
    if (isOverlayLightBlock(block)) return 'text-white/90'
    return 'text-gray-500 dark:text-gray-400'
}
const canShowCtaButton = (block: any) => {
    const accessLevel = String(block.config?.access_level || 'all')
    const isLoggedIn = Boolean(user.value)
    const isProUser = user.value?.subscription_status === 'active'

    if (accessLevel === 'guest') return !isLoggedIn
    if (accessLevel === 'auth') return isLoggedIn
    if (accessLevel === 'pro') return isProUser
    if (accessLevel === 'not_pro') return !isProUser

    return true
}

const menuAlignmentClass = (block: any) => {
    const alignment = String(block.config?.alignment || 'center')
    if (alignment === 'left') return 'justify-start'
    if (alignment === 'right') return 'justify-end'
    return 'justify-center'
}
const menuHoverStyleClass = (block: any) => {
    const style = String(block.config?.hover_style || 'underline')
    return ['underline', 'pill', 'box', 'glow'].includes(style) ? `header-menu-hover-${style}` : 'header-menu-hover-underline'
}
const menuStyle = (block: any): CSSProperties => {
    const style: HeaderStyle = {}
    const textColor = configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color')
    if (textColor) style.color = textColor
    if (textColor) style['--header-menu-text-color'] = textColor
    if (hoverColor) style['--header-menu-hover-color'] = hoverColor
    if (hoverColor) {
        style['--header-menu-hover-bg'] = `color-mix(in srgb, ${hoverColor} 12%, transparent)`
        style['--header-menu-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 18%, transparent)`
        style['--header-menu-hover-shadow'] = `0 0 0 1px color-mix(in srgb, ${hoverColor} 16%, transparent), 0 8px 18px color-mix(in srgb, ${hoverColor} 14%, transparent)`
    }
    return style
}
const submenuStyle = (block: any): CSSProperties => {
    const style: HeaderStyle = {}
    const bgColor = configString(block.config, 'submenu_bg_color')
    const textColor = configString(block.config, 'submenu_text_color') || configString(block.config, 'text_color')
    if (bgColor) style.backgroundColor = bgColor
    if (textColor) style['--header-submenu-text-color'] = textColor
    return style
}
const headerActionStyle = (block: any): HeaderStyle => {
    const style: HeaderStyle = {}
    const textColor = configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color')

    if (textColor) {
        style.color = textColor
        style['--header-action-text-color'] = textColor
        style['--header-control-text-color'] = textColor
    }
    if (hoverColor) {
        style['--header-action-hover-color'] = hoverColor
        style['--header-action-hover-bg'] = `color-mix(in srgb, ${hoverColor} 10%, transparent)`
        style['--header-action-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 14%, transparent)`
        style['--header-control-hover-color'] = hoverColor
        style['--header-control-hover-bg'] = style['--header-action-hover-bg']
        style['--header-control-hover-bg-dark'] = style['--header-action-hover-bg-dark']
    }

    return style
}
const blockVisualStyle = (block: any): CSSProperties => {
    const style: CSSProperties = {}
    if (isOverlayLightBlock(block)) {
        style.color = '#ffffff'
        return style
    }
    const iconColor = configString(block.config, 'icon_color')
    const bgColor = configString(block.config, 'bg_color')
    const textColor = configString(block.config, 'text_color')
    const ctaStyle = ctaStyleValue(block)
    const isCustomCta = block.type === 'cta_button' && ctaStyle === 'custom'
    if (iconColor) style.color = iconColor
    if (bgColor && (block.config?.bg_style === 'custom' || isCustomCta)) style.backgroundColor = bgColor
    if (block.type === 'cta_button') {
        if (textColor) {
            style.color = textColor
        } else if (['primary', 'filled', 'dark', 'danger', 'success', 'green', 'warning', 'purple', 'gradient', 'gradient_sunset', 'gradient_ocean', 'gradient_royal'].includes(ctaStyle)) {
            style.color = '#ffffff'
        } else if (ctaStyle === 'bg_light') {
            style.color = 'var(--color-gray-600)'
        } else if (ctaStyle === 'outline' || ctaStyle === 'ghost') {
            style.color = 'var(--color-primary-600)'
        }
    } else if (textColor) {
        style.color = textColor
    }
    return style
}
const iconSurfaceClass = (block: any, baseClass: string) => [
    baseClass,
    block.config?.bg_style === 'transparent' ? 'bg-transparent dark:bg-transparent' : '',
    block.config?.bg_style === 'filled' ? 'btn-primary hover:text-white dark:bg-primary-600 dark:text-white dark:hover:bg-primary-600' : '',
    block.config?.bg_style === 'custom' ? 'hover:opacity-90' : '',
]

const openCommandPalette = () => {
    window.dispatchEvent(new CustomEvent('palette:open'))
}

const userIconHref = computed(() => user.value ? route('user.dashboard') : '/login')
const userIconLabel = computed(() => user.value ? t('Dashboard') : t('Sign In'))
const guestActionMode = (block: any) => {
    const mode = String(block.config?.guest_mode || 'login_only')
    return ['login_only', 'register_only', 'both'].includes(mode) ? mode : 'login_only'
}
const authDisplayMode = (block: any) => String(block.config?.auth_display || 'avatar_name') === 'avatar_only' ? 'avatar_only' : 'avatar_name'
const authAvatarShapeClass = (block: any) => String(block.config?.avatar_shape || 'rounded') === 'circle' ? 'rounded-full' : 'rounded-lg'
const userMenuTriggerClass = (block: any) => {
    if (authDisplayMode(block) === 'avatar_only') {
        return `header-action-link flex h-9 w-9 items-center justify-center p-0 ${authAvatarShapeClass(block)} transition-colors`
    }

    return 'header-action-link flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors'
}
const userMenuAvatarClass = (block: any) => {
    const shapeClass = authDisplayMode(block) === 'avatar_only' ? authAvatarShapeClass(block) : 'rounded-lg'
    const sizeClass = authDisplayMode(block) === 'avatar_only' ? 'h-full w-full' : 'h-8 w-8'
    return `flex ${sizeClass} items-center justify-center bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white shrink-0 ${shapeClass}`
}
const showUserMenuArrow = (block: any) => block.config?.show_arrow_icon !== false && authDisplayMode(block) !== 'avatar_only'
const authButtonStyle = (value: unknown) => {
    return sharedButtonStyleValue(value)
}
const authButtonShape = (block: any) => buttonShapeClass(block.config?.guest_button_shape)
const authActionButtonStyle = (styleValue: string): CSSProperties => {
    if (['primary', 'filled', 'dark', 'danger', 'success', 'green', 'warning', 'purple', 'gradient', 'gradient_sunset', 'gradient_ocean', 'gradient_royal'].includes(styleValue)) {
        return { color: '#ffffff' }
    }

    if (styleValue === 'bg_light') {
        return { color: 'var(--color-gray-600)' }
    }

    if (styleValue === 'outline' || styleValue === 'ghost') {
        return { color: 'var(--color-primary-600)' }
    }

    return {}
}
const authActionButtonClass = (style: string, shape: string, mobile = false) => [
    mobile ? `flex h-10 w-10 items-center justify-center ${shape} transition-all shrink-0` : `inline-flex items-center justify-center gap-2 ${shape} px-4 py-2 text-sm font-semibold transition-all shrink-0`,
    ...buttonVariantClass(style),
]
const guestLoginHref = '/login'
const guestRegisterHref = '/register'
const guestLoginIconClass = (block: any) => String(block.config?.guest_login_icon_class || 'ti ti-login-2')
const guestRegisterIconClass = (block: any) => String(block.config?.guest_register_icon_class || 'ti ti-user-plus')
const guestLoginText = (block: any) => String(block.config?.guest_login_text || t('Login'))
const guestRegisterText = (block: any) => String(block.config?.guest_register_text || t('Register'))
const userMenuInitial = computed(() => String(user.value?.name || 'U').trim().charAt(0).toUpperCase() || 'U')
const closeMobileMenu = () => { mobileMenuOpen.value = false }

const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const getLogoImage = () => isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)
const logoAltText = computed(() => siteName.value)
const logoInitial = computed(() => siteName.value.trim().charAt(0).toUpperCase() || 'A')

const updateScrollState = () => {
    const currentY = Math.max(window.scrollY, 0)
    scrollDirection.value = currentY >= lastScrollY.value ? 'down' : 'up'
    scrollY.value = currentY
    lastScrollY.value = currentY
    const scrollable = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1)
    scrollProgress.value = Math.min(100, Math.max(0, (currentY / scrollable) * 100))
}

const toggleUserMenu = (menu: 'main') => {
    activeUserMenu.value = activeUserMenu.value === menu ? null : menu
}
const isUserMenuOpen = (menu: 'main') => activeUserMenu.value === menu
const close = () => { activeUserMenu.value = null }
const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeMobileMenu()
    }
}
const handleKeydown = (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault()
        openCommandPalette()
    }
}

watch(mobileMenuOpen, (open) => {
    if (typeof document === 'undefined') return
    document.documentElement.classList.toggle('overflow-hidden', open)
})

onMounted(() => {
    document.addEventListener('click', close)
    document.addEventListener('keydown', closeOnEscape)
    document.addEventListener('keydown', handleKeydown)
    updateScrollState()
    window.addEventListener('scroll', updateScrollState, { passive: true })
    void bindHeroOverlayState()
})
onUnmounted(() => {
    document.removeEventListener('click', close)
    document.removeEventListener('keydown', closeOnEscape)
    document.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('scroll', updateScrollState)
    document.documentElement.classList.remove('overflow-hidden')
})
</script>

<template>
    <CommandPalette />
    <!-- Custom CSS injection -->
    <component
        v-for="(_, sectionKey) in { main: headerConfig, mobile: mobileHeaderConfig, mobile_bottom: mobileBottomHeaderConfig }"
        :is="'style'"
        v-if="(_ as any)?.custom_css"
        data-header-custom-css
    >
        {{ (_ as any).custom_css }}
    </component>

    <!-- Main Header -->
    <header :class="[
        'hidden w-full shrink-0 md:block header-section-overlay',
        sectionPositionClass(headerConfig),
        sectionTransitionClass(headerConfig),
        isTransparentMainHeaderActive ? '' : sectionShadowClass(headerConfig),
        sectionVisibilityClass(headerConfig),
        isTransparentMainHeaderActive
            ? `absolute bg-transparent shadow-none header-overlay-light ${sectionBorderClass(headerConfig, 'bottom', true)}`
            : `backdrop-blur-md ${sectionBorderClass(headerConfig)} ${hasCustomBackground(headerConfig) ? '' : 'bg-white/90 dark:bg-surface-900/80'}`,
    ]" :style="{ ...mainHeaderSectionStyle, ...mainHeaderBackgroundStyle, ...sectionAccentStyle(headerConfig) }">
        <div v-if="isStackedCenteredMainHeader" class="flex min-h-full flex-col items-center justify-center" :class="[containerClass(headerConfig), mainRowGapClass, mainRowLayoutClass]">
            <div class="flex items-center justify-center">
                <template v-for="block in centeredMainLogoBlocks" :key="block.id">
                    <Link v-if="block.type === 'logo'" href="/" class="flex items-center justify-center gap-2.5 text-center group">
                        <img v-if="getLogoImage()" :src="getLogoImage()" :alt="logoAltText" class="h-10 w-auto max-w-40 shrink-0 object-contain" />
                        <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white shadow-lg shadow-primary-500/20 transition-transform group-hover:scale-105">
                            {{ logoInitial }}
                        </div>
                        <span v-if="!getLogoImage()" class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ siteName }}</span>
                    </Link>
                </template>
            </div>
            <div class="flex min-w-0 items-center justify-center">
                <template v-for="block in centeredMainNavBlocks" :key="block.id">
                    <nav v-if="block.type === 'navigation'" :class="[...mainNavClass('center'), menuAlignmentClass(block), menuHoverStyleClass(block), mainCenterNavClass]" :style="menuStyle(block)">
                        <template v-if="getMenu(block.config.menu_slug)">
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative">
                                <a :href="menuItemHref(item)" :target="item.target" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    {{ item.label || item.title }}
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="invisible absolute inset-inline-start-0 top-full z-50 mt-3 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <a v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" :href="menuItemHref(child)" :target="child.target" class="header-submenu-link block rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20" :style="menuStyle(block)">{{ child.label || child.title }}</a>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="invisible absolute left-0 top-full z-50 mt-3 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                    v-html="item.mega_menu_content"
                                ></div>
                            </div>
                        </template>
                        <div v-else class="flex items-center gap-2 px-3 py-2 text-xs text-gray-400 italic">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            {{ t('Menu "') }}{{ block.config.menu_slug }}{{ t('" not found.') }}
                        </div>
                    </nav>
                </template>
            </div>
            <div class="flex items-center justify-center" :class="[mainColumnGroupClass('right'), mainColFlexClass('right')]">
                <template v-for="block in centeredMainActionBlocks" :key="block.id">
                    <LanguageSwitcher v-if="block.type === 'language_switcher'" :display="languageSwitcherDisplay(block)" :ui="{ buttonClass: languageSwitcherClass(block), buttonStyle: languageSwitcherStyle(block), iconStyle: blockVisualStyle(block) }" />
                    <NotificationBell v-else-if="block.type === 'notification_bell'" context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    <SocialFollow v-else-if="block.type === 'social_icons'" display-mode="icons" :icon-use-platform-surface="false" :icon-use-platform-color="false" :icon-item-class="socialIconButtonClass(block)" icon-inner-class="text-[18px] leading-none" :icon-item-style="softIconSurfaceStyle(block)" :icon-inner-style="blockVisualStyle(block)" />
                    <button v-else-if="block.type === 'command_palette'" type="button" :class="commandPaletteButtonClass(block)" :style="commandPaletteButtonStyle(block)" :aria-label="t('Open command palette')" @click="openCommandPalette()">
                        <span class="inline-flex items-center gap-2 min-w-0">
                            <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" :style="blockVisualStyle(block)" aria-hidden="true" />
                            <span v-if="commandPaletteDisplayStyle(block) !== 'icon_only'" :class="commandPaletteLabelClass(block)" class="truncate text-sm font-medium">{{ blockText(block, t('Search')) }}</span>
                        </span>
                        <span v-if="commandPaletteDisplayStyle(block) !== 'icon_only'" :class="commandPaletteHintClass(block)" class="rounded-md border border-current/10 px-2 py-1 text-[11px] font-semibold leading-none">{{ blockHint(block, t('Ctrl + K')) }}</span>
                    </button>
                    <button v-else-if="block.type === 'dark_mode'" @click="toggleDark()" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)">
                        <svg v-if="isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" class="text-sm font-bold transition-all whitespace-nowrap shrink-0" :style="blockVisualStyle(block)" :class="[ctaShapeClass(block), isIconOnly(block) ? 'flex h-10 w-10 items-center justify-center' : 'px-5 py-2', ...buttonVariantClass(ctaStyleValue(block))]">
                        <span class="inline-flex items-center gap-1.5">
                            <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), ctaIconSizeClass()]" aria-hidden="true" />
                            <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                        </span>
                    </Link>
                    <template v-else-if="block.type === 'user_menu'">
                        <div v-if="user" class="relative flex items-center" @click.stop>
                            <button @click="toggleUserMenu('main')" :class="userMenuTriggerClass(block)" :style="headerActionStyle(block)">
                                <div :class="userMenuAvatarClass(block)">{{ userMenuInitial }}</div>
                                <span v-if="authDisplayMode(block) === 'avatar_name'" class="hidden sm:block text-sm font-semibold">{{ user.name }}</span>
                                <svg v-if="showUserMenuArrow(block)" class="w-4 h-4 text-gray-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="isUserMenuOpen('main')" class="absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                    <div class="px-4 py-2.5 border-b border-gray-100 dark:border-white/5">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                                    </div>
                                    <Link :href="route('user.dashboard')" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                        {{ t('Dashboard') }}
                                    </Link>
                                    <Link v-if="affiliateEnabled" :href="route('user.dashboard.affiliate')" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m0-12l-3 3m3-3l-3-3m0 15l3-3m-3 3l3 3M15.75 9h3A2.25 2.25 0 0121 11.25v1.5A2.25 2.25 0 0118.75 15h-3" /></svg>
                                        {{ t('Affiliate') }}
                                    </Link>
                                    <button @click="logout" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                        {{ t('Sign Out') }}
                                    </button>
                                </div>
                            </Transition>
                        </div>
                        <div v-else class="flex items-center justify-center gap-2.5">
                            <Link v-if="guestActionMode(block) === 'login_only' || guestActionMode(block) === 'both'" :href="guestLoginHref" :class="authActionButtonClass(authButtonStyle(block.config?.guest_login_style), authButtonShape(block))" :style="authActionButtonStyle(authButtonStyle(block.config?.guest_login_style))">
                                <i v-if="guestLoginIconClass(block)" :class="[guestLoginIconClass(block), 'text-base leading-none']" aria-hidden="true" />
                                <span>{{ guestLoginText(block) }}</span>
                            </Link>
                            <Link v-if="guestActionMode(block) === 'register_only' || guestActionMode(block) === 'both'" :href="guestRegisterHref" :class="authActionButtonClass(authButtonStyle(block.config?.guest_register_style), authButtonShape(block))" :style="authActionButtonStyle(authButtonStyle(block.config?.guest_register_style))">
                                <i v-if="guestRegisterIconClass(block)" :class="[guestRegisterIconClass(block), 'text-base leading-none']" aria-hidden="true" />
                                <span>{{ guestRegisterText(block) }}</span>
                            </Link>
                        </div>
                    </template>
                </template>
            </div>
        </div>
        <div v-else class="flex h-full items-center" :class="[containerClass(headerConfig), mainRowGapClass, mainRowLayoutClass]">
            <!-- Left Column -->
            <div class="flex items-center" :class="[mainColumnGroupClass('left'), mainColFlexClass('left')]">
                <template v-for="block in mainLeftBlocks" :key="block.id">
                    <!-- LOGO -->
                    <Link v-if="block.type === 'logo'" href="/" class="flex items-center gap-2.5 group">
                        <img v-if="getLogoImage()" :src="getLogoImage()" :alt="logoAltText" class="h-9 w-auto max-w-36 shrink-0 object-contain" />
                        <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white shadow-lg shadow-primary-500/20 transition-transform group-hover:scale-105">
                            {{ logoInitial }}
                        </div>
                        <span v-if="!getLogoImage()" class="hidden whitespace-nowrap text-lg font-bold tracking-tight text-gray-900 sm:block dark:text-white">{{ siteName }}</span>
                    </Link>
                    <nav v-else-if="block.type === 'navigation'" :class="[...mainNavClass('left'), menuAlignmentClass(block), menuHoverStyleClass(block)]" :style="menuStyle(block)">
                        <template v-if="getMenu(block.config.menu_slug)">
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative">
                                <a :href="menuItemHref(item)" :target="item.target" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    {{ item.label || item.title }}
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="invisible absolute inset-inline-start-0 top-full z-50 mt-3 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <a v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" :href="menuItemHref(child)" :target="child.target" class="header-submenu-link block rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20" :style="menuStyle(block)">{{ child.label || child.title }}</a>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="invisible absolute left-0 top-full z-50 mt-3 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                    v-html="item.mega_menu_content"
                                ></div>
                            </div>
                        </template>
                        <div v-else class="flex items-center gap-2 px-3 py-2 text-xs text-gray-400 italic">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            {{ t('Menu "') }}{{ block.config.menu_slug }}{{ t('" not found.') }}
                        </div>
                    </nav>
                </template>
            </div>
            <!-- Center Column -->
            <div class="flex items-center" :class="[mainColumnGroupClass('center'), mainColFlexClass('center')]">
                <template v-for="block in mainCenterBlocks" :key="block.id">
                    <!-- NAVIGATION -->
                    <nav v-if="block.type === 'navigation'" :class="[...mainNavClass('center'), menuAlignmentClass(block), menuHoverStyleClass(block), mainCenterNavClass]" :style="menuStyle(block)">
                        <template v-if="getMenu(block.config.menu_slug)">
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative">
                                <a :href="menuItemHref(item)" :target="item.target" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    {{ item.label || item.title }}
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="invisible absolute inset-inline-start-0 top-full z-50 mt-3 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <a v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" :href="menuItemHref(child)" :target="child.target" class="header-submenu-link block rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20" :style="menuStyle(block)">{{ child.label || child.title }}</a>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="invisible absolute left-0 top-full z-50 mt-3 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                    v-html="item.mega_menu_content"
                                ></div>
                            </div>
                        </template>
                        <div v-else class="flex items-center gap-2 px-3 py-2 text-xs text-gray-400 italic">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            {{ t('Menu "') }}{{ block.config.menu_slug }}{{ t('" not found.') }}
                        </div>
                    </nav>

                </template>
            </div>
            <!-- Right Column -->
            <div class="flex items-center" :class="[mainColumnGroupClass('right'), mainColFlexClass('right')]">
                <template v-for="block in mainRightBlocks" :key="block.id">
                    <Link v-if="block.type === 'logo'" href="/" class="flex items-center gap-2.5 group">
                        <img v-if="getLogoImage()" :src="getLogoImage()" :alt="logoAltText" class="h-9 w-auto max-w-36 shrink-0 object-contain" />
                        <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white shadow-lg shadow-primary-500/20 transition-transform group-hover:scale-105">
                            {{ logoInitial }}
                        </div>
                        <span v-if="!getLogoImage()" class="hidden whitespace-nowrap text-lg font-bold tracking-tight text-gray-900 sm:block dark:text-white">{{ siteName }}</span>
                    </Link>
                    <LanguageSwitcher v-else-if="block.type === 'language_switcher'" :display="languageSwitcherDisplay(block)" :ui="{ buttonClass: languageSwitcherClass(block), buttonStyle: languageSwitcherStyle(block), iconStyle: blockVisualStyle(block) }" />
                    <NotificationBell v-else-if="block.type === 'notification_bell'" context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    <SocialFollow v-else-if="block.type === 'social_icons'" display-mode="icons" :icon-use-platform-surface="false" :icon-use-platform-color="false" :icon-item-class="socialIconButtonClass(block)" icon-inner-class="text-[18px] leading-none" :icon-item-style="softIconSurfaceStyle(block)" :icon-inner-style="blockVisualStyle(block)" />
                    <button v-else-if="block.type === 'command_palette'" type="button" :class="commandPaletteButtonClass(block)" :style="commandPaletteButtonStyle(block)" :aria-label="t('Open command palette')" @click="openCommandPalette()">
                        <span class="inline-flex items-center gap-2 min-w-0">
                            <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" :style="blockVisualStyle(block)" aria-hidden="true" />
                            <span v-if="commandPaletteDisplayStyle(block) !== 'icon_only'" :class="commandPaletteLabelClass(block)" class="truncate text-sm font-medium">{{ blockText(block, t('Search')) }}</span>
                        </span>
                        <span v-if="commandPaletteDisplayStyle(block) !== 'icon_only'" :class="commandPaletteHintClass(block)" class="rounded-md border border-current/10 px-2 py-1 text-[11px] font-semibold leading-none">{{ blockHint(block, t('Ctrl + K')) }}</span>
                    </button>

                    <button v-else-if="block.type === 'dark_mode'" @click="toggleDark()" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)">
                        <svg v-if="isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>

                    <!-- CTA BUTTON -->
                    <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" class="text-sm font-bold transition-all whitespace-nowrap shrink-0" :style="blockVisualStyle(block)" :class="[
                        ctaShapeClass(block),
                        isIconOnly(block) ? 'flex h-10 w-10 items-center justify-center' : 'px-5 py-2',
                        ...buttonVariantClass(ctaStyleValue(block)),
                    ]">
                        <span class="inline-flex items-center gap-1.5">
                            <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), ctaIconSizeClass()]" aria-hidden="true" />
                            <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                        </span>
                    </Link>

                    <!-- USER MENU -->
                    <template v-else-if="block.type === 'user_menu'">
                        <div v-if="user" class="relative flex items-center" @click.stop>
                            <button @click="toggleUserMenu('main')" :class="userMenuTriggerClass(block)" :style="headerActionStyle(block)">
                                <div :class="userMenuAvatarClass(block)">{{ userMenuInitial }}</div>
                                <span v-if="authDisplayMode(block) === 'avatar_name'" class="hidden sm:block text-sm font-semibold">{{ user.name }}</span>
                                <svg v-if="showUserMenuArrow(block)" class="w-4 h-4 text-gray-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="isUserMenuOpen('main')" class="absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                    <div class="px-4 py-2.5 border-b border-gray-100 dark:border-white/5">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                                    </div>
                                    <Link :href="route('user.dashboard')" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                        {{ t('Dashboard') }}
                                    </Link>
                                    <Link v-if="affiliateEnabled" :href="route('user.dashboard.affiliate')" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m0-12l-3 3m3-3l-3-3m0 15l3-3m-3 3l3 3M15.75 9h3A2.25 2.25 0 0121 11.25v1.5A2.25 2.25 0 0118.75 15h-3" /></svg>
                                        {{ t('Affiliate') }}
                                    </Link>
                                    <button @click="logout" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                        {{ t('Sign Out') }}
                                    </button>
                                </div>
                            </Transition>
                        </div>
                        <div v-else class="flex items-center gap-2.5">
                            <Link v-if="guestActionMode(block) === 'login_only' || guestActionMode(block) === 'both'" :href="guestLoginHref" :class="authActionButtonClass(authButtonStyle(block.config?.guest_login_style), authButtonShape(block))" :style="authActionButtonStyle(authButtonStyle(block.config?.guest_login_style))">
                                <i v-if="guestLoginIconClass(block)" :class="[guestLoginIconClass(block), 'text-base leading-none']" aria-hidden="true" />
                                <span>{{ guestLoginText(block) }}</span>
                            </Link>
                            <Link v-if="guestActionMode(block) === 'register_only' || guestActionMode(block) === 'both'" :href="guestRegisterHref" :class="authActionButtonClass(authButtonStyle(block.config?.guest_register_style), authButtonShape(block))" :style="authActionButtonStyle(authButtonStyle(block.config?.guest_register_style))">
                                <i v-if="guestRegisterIconClass(block)" :class="[guestRegisterIconClass(block), 'text-base leading-none']" aria-hidden="true" />
                                <span>{{ guestRegisterText(block) }}</span>
                            </Link>
                        </div>
                    </template>

                </template>
            </div>
        </div>
        <div v-if="headerConfig?.progressbar" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </header>

    <!-- Mobile Header -->
    <header
        v-if="mobileHeaderConfig?.enabled"
        :class="[sectionPositionClass(mobileHeaderConfig), sectionTransitionClass(mobileHeaderConfig), sectionShadowClass(mobileHeaderConfig), sectionVisibilityClass(mobileHeaderConfig)]"
        :style="{ ...sectionStyle(mobileHeaderConfig, 'mobile', 64), ...sectionBackgroundStyle(mobileHeaderConfig) }"
        class="w-full border-b border-gray-200 bg-white/95 backdrop-blur-md dark:border-white/5 dark:bg-surface-900/90 md:hidden header-section-overlay"
    >
        <div class="flex h-full items-center justify-between gap-3" :class="[containerClass({ ...mobileHeaderConfig, container_width: '1280px' }, true), isCenteredMobileTop ? 'relative' : '']">
            <div class="flex items-center gap-2" :class="[mobileColFlexClass('left'), mobileTopSideClass]">
                <template v-for="block in mobileLeftBlocks" :key="block.id">
                    <button v-if="block.type === 'hamburger'" type="button" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="t('Open menu')" @click="mobileMenuOpen = !mobileMenuOpen">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'logo'" href="/" class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-white" :class="mobileTopLogoClass">
                        <img v-if="getLogoImage()" :src="getLogoImage()" :alt="logoAltText" class="h-9 w-auto max-w-32 object-contain" />
                        <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white shadow-lg shadow-primary-500/20">
                            {{ logoInitial }}
                        </div>
                        <span v-if="!getLogoImage()">{{ siteName }}</span>
                    </Link>
                </template>
            </div>
            <div class="flex items-center gap-1" :class="[mobileColFlexClass('right'), mobileTopSideClass]">
                <template v-for="block in mobileRightBlocks" :key="block.id">
                    <NotificationBell v-if="block.type === 'notification_bell'" context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    <button v-else-if="block.type === 'dark_mode'" type="button" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)" @click="toggleDark()">
                        <svg v-if="isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'user_menu_icon'" :href="userIconHref" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                    </Link>
                </template>
            </div>
        </div>
        <div v-if="mobileHeaderConfig?.progressbar" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </header>

    <!-- Mobile Menu Drawer -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-[80] md:hidden" role="dialog" aria-modal="true" :aria-label="t('Mobile menu')">
                <button type="button" class="absolute inset-0 h-full w-full bg-gray-950/60 backdrop-blur-sm" :aria-label="t('Close menu')" @click="closeMobileMenu"></button>
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-x-full rtl:translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="-translate-x-full rtl:translate-x-full"
                >
                    <aside class="absolute inset-y-0 left-0 flex w-[min(20rem,calc(100vw-2rem))] max-w-full flex-col border-r border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-surface-900 rtl:left-auto rtl:right-0 rtl:border-l rtl:border-r-0">
                        <div class="flex h-16 items-center justify-between border-b border-gray-100 px-5 dark:border-surface-800">
                            <Link href="/" class="min-w-0 truncate text-base font-bold text-gray-900 dark:text-white" @click="closeMobileMenu">{{ mobileDrawerTitle }}</Link>
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-50 text-gray-500 transition hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" :aria-label="t('Close menu')" @click="closeMobileMenu">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <nav class="flex-1 space-y-1 overflow-y-auto p-4">
                            <template v-if="getMenu(mobileDrawerMenuSlug)">
                                <a
                                    v-for="item in visibleMenuItems(mobileDrawerMenuSlug)"
                                    :key="item.id"
                                    :href="menuItemHref(item)"
                                    :target="item.target"
                                    class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition"
                                    :class="isActive(menuItemHref(item)) ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white'"
                                    @click="closeMobileMenu"
                                >
                                    <span class="truncate">{{ item.label || item.title }}</span>
                                    <svg class="h-4 w-4 shrink-0 text-gray-300 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                </a>
                            </template>
                            <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-700">
                                {{ t('No menu items found.') }}
                            </div>
                        </nav>
                    </aside>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Mobile Bottom Header -->
    <nav
        v-if="mobileBottomHeaderConfig?.enabled && activeMobileBottomBlocks.length > 0"
        :class="[sectionTransitionClass(mobileBottomHeaderConfig), sectionShadowClass(mobileBottomHeaderConfig), bottomSectionVisibilityClass(mobileBottomHeaderConfig), sectionBorderClass(mobileBottomHeaderConfig, 'top')]"
        :style="{ height: `${Number(mobileBottomHeaderConfig?.height ?? 64)}px`, ...sectionBackgroundStyle(mobileBottomHeaderConfig) }"
        class="fixed inset-x-0 bottom-0 z-50 transform-gpu bg-white/95 backdrop-blur-md will-change-transform dark:bg-surface-900/90 md:hidden header-section-overlay"
    >
        <div class="flex h-full items-center justify-around gap-1" :class="containerClass({ ...mobileBottomHeaderConfig, container_width: '1280px' }, true)">
            <template v-for="block in activeMobileBottomBlocks" :key="block.id">
                <Link v-if="block.type === 'home_link'" :href="String(block.config.link || '/')" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Home')">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75v9A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-9" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Home')) }}</span>
                </Link>
                <Link v-else-if="block.type === 'user_menu_icon'" :href="userIconHref" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                    <span v-if="showBlockLabel(block)">{{ user ? blockLabel(block, t('Dashboard')) : String(block.config?.guest_label || blockLabel(block, t('Sign In'))) }}</span>
                </Link>
                <div v-else-if="block.type === 'notification_bell' && user" class="flex min-w-0 flex-1 justify-center">
                    <NotificationBell context="user" :label="showBlockLabel(block) ? blockLabel(block, t('Notifications')) : ''" :ui="{ wrapperClass: 'flex min-w-0 w-full', triggerClass: notificationButtonClass(block, true).join(' '), triggerStyle: softIconSurfaceStyle(block), dropdownClass: 'fixed inset-x-4 bottom-20 z-50 max-h-[70vh] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900', iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                </div>
                <div v-else-if="block.type === 'language_switcher'" class="flex min-w-0 flex-1 justify-center">
                    <LanguageSwitcher display="bottom" />
                </div>
                <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" :class="mobileCtaClass(block, true)" :style="blockVisualStyle(block)">
                    <i v-if="blockIconClass(block, 'ti ti-arrow-right')" :class="[blockIconClass(block, 'ti ti-arrow-right'), ctaIconSizeClass(true)]" aria-hidden="true" />
                    <span v-if="!isIconOnly(block)" class="max-w-full truncate">{{ blockText(block, t('Start')) }}</span>
                </Link>
                <button v-else-if="block.type === 'dark_mode'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Toggle dark mode')" @click="toggleDark()">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <template v-else>
                        <svg v-if="isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </template>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Theme')) }}</span>
                </button>
            </template>
        </div>
        <div v-if="mobileBottomHeaderConfig?.progressbar" class="absolute inset-x-0 top-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </nav>
</template>

<style scoped>
.header-section-overlay {
    isolation: isolate;
    transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease, backdrop-filter 0.5s ease, opacity 0.5s ease;
}
.header-section-overlay::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--header-bg-overlay, transparent);
    pointer-events: none;
    z-index: 1;
    transition: background 0.5s ease, opacity 0.5s ease;
}
.header-section-overlay > * {
    position: relative;
    z-index: 2;
}
.header-overlay-light {
    color: #ffffff;
    --header-menu-text-color: rgba(255, 255, 255, 0.92);
    --header-menu-hover-color: #ffffff;
    --header-menu-hover-bg: rgb(255 255 255 / 0.12);
    --header-menu-hover-bg-dark: rgb(255 255 255 / 0.16);
    --header-action-text-color: #ffffff;
    --header-action-hover-color: #ffffff;
    --header-action-hover-bg: rgb(255 255 255 / 0.12);
    --header-action-hover-bg-dark: rgb(255 255 255 / 0.16);
    --header-control-text-color: #ffffff;
    --header-control-hover-color: #ffffff;
    --header-control-hover-bg: rgb(255 255 255 / 0.12);
    --header-control-hover-bg-dark: rgb(255 255 255 / 0.16);
    --header-soft-icon-color: #ffffff;
    --header-soft-icon-hover-color: #ffffff;
    --header-soft-icon-hover-bg: rgb(255 255 255 / 0.14);
    --header-soft-icon-hover-bg-dark: rgb(255 255 255 / 0.18);
}
.header-overlay-light :is(.header-menu-link, .header-action-link, .language-switcher-button, .header-soft-icon-button) {
    color: #ffffff !important;
}
.header-overlay-light :is(.header-soft-icon-button:not(.header-soft-icon-button--icon-only), .language-switcher-button:not(.header-soft-icon-button--icon-only)) {
    border-color: rgb(255 255 255 / 0.18) !important;
    background: rgb(255 255 255 / 0.08) !important;
}
.header-overlay-light .header-action-link {
    border-color: rgb(255 255 255 / 0.22) !important;
}
.header-overlay-light :is(a, button, svg, i, span) {
    color: inherit;
}
.header-overlay-light :is(.header-menu-link:hover, .header-menu-link-active, .header-action-link:hover, .language-switcher-button:hover, .header-soft-icon-button:hover) {
    color: #ffffff !important;
}
.header-overlay-light :is(.header-menu-hover-pill .header-menu-link:hover, .header-menu-hover-pill .header-menu-link-active, .header-menu-hover-box .header-menu-link:hover, .header-menu-hover-box .header-menu-link-active, .header-menu-hover-glow .header-menu-link:hover, .header-menu-hover-glow .header-menu-link-active) {
    background: rgb(255 255 255 / 0.12) !important;
    box-shadow: none !important;
}
.header-overlay-light .header-menu-hover-underline .header-menu-link::after {
    background: #ffffff;
}
.header-overlay-light :is([class*="border-primary-"], [class*="text-primary-"]) {
    color: #ffffff !important;
    border-color: rgb(255 255 255 / 0.28) !important;
}
.header-overlay-light :is([class*="bg-gray-50"], [class*="bg-primary-50"], [class*="bg-surface-800"]) {
    background: rgb(255 255 255 / 0.08) !important;
}
.header-menu-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    color: var(--header-menu-text-color, var(--color-gray-500));
}
.dark .header-menu-link { color: var(--header-menu-text-color, var(--color-gray-400)); }
.header-menu-link:hover, .header-menu-link-active { color: var(--header-menu-hover-color, var(--color-primary-600)); }
.dark .header-menu-link:hover, .dark .header-menu-link-active { color: var(--header-menu-hover-color, var(--color-primary-400)); }

.header-menu-hover-underline .header-menu-link::after {
    position: absolute;
    inset-inline: 0.875rem;
    bottom: 0.25rem;
    height: 2px;
    content: "";
    background: var(--header-menu-hover-color, var(--color-primary-500));
    border-radius: var(--radius-full);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.18s ease;
}
.header-menu-hover-underline .header-menu-link:hover::after,
.header-menu-hover-underline .header-menu-link-active::after { transform: scaleX(1); }
.header-menu-hover-pill .header-menu-link:hover,
.header-menu-hover-pill .header-menu-link-active { background: var(--header-menu-hover-bg, var(--color-primary-50)); }
.dark .header-menu-hover-pill .header-menu-link:hover,
.dark .header-menu-hover-pill .header-menu-link-active { background: var(--header-menu-hover-bg-dark, rgb(16 185 129 / 0.14)); }
.header-menu-hover-box .header-menu-link:hover,
.header-menu-hover-box .header-menu-link-active { background: var(--header-menu-hover-bg, var(--surface-card)); box-shadow: var(--shadow-sm); transform: translateY(-1px); }
.header-menu-hover-glow .header-menu-link:hover,
.header-menu-hover-glow .header-menu-link-active { background: var(--header-menu-hover-bg, rgb(16 185 129 / 0.08)); box-shadow: var(--header-menu-hover-shadow, 0 0 0 1px rgb(16 185 129 / 0.14), 0 8px 18px rgb(16 185 129 / 0.14)); }
.header-submenu-link { color: var(--header-submenu-text-color, var(--color-gray-700)); }
.dark .header-submenu-link { color: var(--header-submenu-text-color, var(--color-gray-200)); }
.header-submenu-link:hover {
    color: var(--header-menu-hover-color, var(--color-primary-600));
    background: var(--header-menu-hover-bg, var(--color-primary-50));
}
.dark .header-submenu-link:hover {
    color: var(--header-menu-hover-color, var(--color-primary-400));
    background: var(--header-menu-hover-bg-dark, rgb(16 185 129 / 0.14));
}
.header-action-link {
    color: var(--header-action-text-color, inherit);
}
.header-action-link:hover {
    color: var(--header-action-hover-color, inherit);
    background: var(--header-action-hover-bg, var(--color-gray-100));
}
.dark .header-action-link:hover {
    color: var(--header-action-hover-color, inherit);
    background: var(--header-action-hover-bg-dark, rgb(255 255 255 / 0.05));
}
.header-soft-icon-button {
    background: var(--header-soft-icon-bg, var(--surface-card));
    border-color: var(--header-soft-icon-border, transparent);
}
.header-soft-icon-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-soft-icon-color, inherit));
    background: var(--header-soft-icon-hover-bg, var(--color-primary-50));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg, var(--color-gray-100)) !important;
    border-color: transparent !important;
}
.dark .header-soft-icon-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-soft-icon-color, inherit));
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.dark .header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08)) !important;
    border-color: transparent !important;
}
</style>
