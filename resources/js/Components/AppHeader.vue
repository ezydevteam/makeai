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
    avatar?: string | null
    credits?: number
    is_pro?: boolean
    plan_id?: number | null
    subscription_status?: string | null
}
type SimpleDesktopHeaderSettings = {
    sticky?: boolean
    sticky_behavior?: string
    shadow_style?: string
    menu_position?: string
    menu_hover_style?: string
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
    sticky?: boolean
    layout?: string
    height?: number
    bg_color?: string
    text_color?: string
    show_shadow?: string
    sticky_behavior?: string
    show_logo?: boolean
    show_hamburger?: boolean
    show_dark_mode_toggle?: boolean
    show_notification_bell?: boolean
    show_search_icon?: boolean
    show_language_switcher?: boolean
    show_login?: boolean
    show_cta_button?: boolean
}
type SimpleMobileBottomHeaderSettings = {
    enabled?: boolean
    hide_menu_labels?: boolean
    show_glassmorphism?: boolean
    show_home?: boolean
    show_search_icon?: boolean
    show_tools?: boolean
    show_notification_bell?: boolean
    show_hamburger?: boolean
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
interface HeaderBlock {
    id: string
    type: string
    enabled: boolean
    config: Record<string, any>
}

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
    const desktopLayout = 'classic'
    const mobileTopLayout = mobileTop.layout || 'compact'
    const mobileBottomLayout = 'tabs'
    const desktopHeight = Number(desktop.height ?? 72)
    const mobileTopHeight = mobileTopLayout === 'centered' ? 72 : 64
    const desktopTextColor = desktop.text_color || ''
    const desktopHoverColor = desktop.menu_hover_color || desktop.text_color || ''
    const mobileTopTextColor = mobileTop.text_color || ''
    const mobileTopHoverColor = mobileTop.text_color || ''
    const desktopStickyBehavior = desktop.sticky_behavior || (desktop.sticky === false ? 'none' : 'always')
    const menuPosition = ['hide', 'left', 'center', 'right'].includes(String(desktop.menu_position || 'center'))
        ? String(desktop.menu_position || 'center')
        : 'center'
    const navBlockAlign = menuPosition === 'hide' ? 'center' : menuPosition
    const logoBlockAlign = 'left'
    const desktopNavHoverStyle = ['bottom_border', 'rounded_soft_bg', 'pill_soft_bg', 'simple'].includes(String(desktop.menu_hover_style || 'rounded_soft_bg'))
        ? String(desktop.menu_hover_style || 'rounded_soft_bg')
        : 'rounded_soft_bg'
    const mainRightBlocks: HeaderBlock[] = []
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

    const mobileBlocks: HeaderBlock[] = []
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
                text_color: mobileTopTextColor,
                icon_color: mobileTopTextColor,
                hover_color: mobileTopHoverColor,
            },
        })
    }
    if (mobileTop.show_logo !== false) {
        mobileBlocks.push({ id: 'simple_mobile_logo', type: 'logo', enabled: true, config: { block_align: 'left' } })
    }

    if (mobileTop.show_notification_bell === true) {
        mobileBlocks.push({
            id: 'simple_mobile_notify',
            type: 'notification_bell',
            enabled: true,
            config: {
                block_align: 'right',
                display_style: 'icon_only',
                text_color: mobileTopTextColor,
                icon_color: mobileTopTextColor,
                hover_color: mobileTopHoverColor,
            },
        })
    }

    if (mobileTop.show_search_icon === true) {
        mobileBlocks.push({
            id: 'simple_mobile_search',
            type: 'command_palette',
            enabled: true,
            config: {
                block_align: 'right',
                display_style: 'icon_only',
                icon_class: 'ti ti-search',
                label: t('Search'),
                text_color: mobileTopTextColor,
                icon_color: mobileTopTextColor,
                hover_color: mobileTopHoverColor,
            },
        })
    }

    if (mobileTop.show_language_switcher === true) {
        mobileBlocks.push({
            id: 'simple_mobile_lang',
            type: 'language_switcher',
            enabled: true,
            config: {
                block_align: 'right',
                display_style: 'icon_only',
                text_color: mobileTopTextColor,
                icon_color: mobileTopTextColor,
                hover_color: mobileTopHoverColor,
            },
        })
    }

    if (mobileTop.show_dark_mode_toggle !== false) {
        mobileBlocks.push({
            id: 'simple_mobile_dark',
            type: 'dark_mode',
            enabled: true,
            config: {
                block_align: 'right',
                display_style: 'icon_only',
                text_color: mobileTopTextColor,
                icon_color: mobileTopTextColor,
                hover_color: mobileTopHoverColor,
            },
        })
    }

    if (mobileTop.show_login === true) {
        mobileBlocks.push({
            id: 'simple_mobile_user',
            type: 'user_menu_icon',
            enabled: true,
            config: {
                block_align: 'right',
                icon_class: 'ti ti-user',
                text_color: mobileTopTextColor,
                icon_color: mobileTopTextColor,
                hover_color: mobileTopHoverColor,
            },
        })
    }

    if (mobileTop.show_cta_button === true) {
        mobileBlocks.push({
            id: 'simple_mobile_cta',
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

    const showLabels = mobileBottom.hide_menu_labels !== true
    const mobileBottomBlocks: HeaderBlock[] = []
    if (mobileBottom.show_home !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_home', type: 'home_link', enabled: true, config: { link: '/', label: t('Home'), icon_class: 'ti ti-home', show_label: showLabels } })
    }
    if (mobileBottom.show_search_icon === true) {
        mobileBottomBlocks.push({ id: 'simple_bottom_search', type: 'command_palette', enabled: true, config: { label: t('Search'), icon_class: 'ti ti-search', show_label: showLabels } })
    }
    if (mobileBottom.show_tools !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_tools', type: 'home_link', enabled: true, config: { link: '/ai-tools', label: t('Tools'), icon_class: 'ti ti-sparkles', show_label: showLabels } })
    }
    if (mobileBottom.show_notification_bell === true) {
        mobileBottomBlocks.push({ id: 'simple_bottom_notify', type: 'notification_bell', enabled: true, config: { label: t('Notifications'), icon_class: 'ti ti-bell', show_label: showLabels } })
    }
    if (mobileBottom.show_profile !== false) {
        mobileBottomBlocks.push({ id: 'simple_bottom_profile', type: 'user_menu_icon', enabled: true, config: { label: t('Account'), guest_label: t('Sign In'), icon_class: 'ti ti-user', show_label: showLabels } })
    }
    if (mobileBottom.show_hamburger === true) {
        mobileBottomBlocks.push({
            id: 'simple_bottom_hamburger',
            type: 'hamburger',
            enabled: true,
            config: {
                block_align: 'center',
                menu_slug: resolveFrontendMenuSlug(desktop.menu_source, 'mobile'),
                label: t('Menu'),
                show_label: showLabels,
                icon_class: 'ti ti-menu-2',
            },
        })
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
                ...(menuPosition === 'hide'
                    ? []
                    : [{ id: 'simple_nav', type: 'navigation', enabled: true, config: { menu_slug: resolveFrontendMenuSlug(desktop.menu_source, 'main'), alignment: navBlockAlign, block_align: navBlockAlign, hover_style: desktopNavHoverStyle, text_color: desktopTextColor, hover_color: desktopHoverColor } }]),
                ...mainRightBlocks,
            ],
        },
        mobile: {
            enabled: mobileTop.enabled !== false,
            sticky: mobileTop.sticky_behavior !== 'none',
            height: Number(mobileTop.height ?? (mobileTopLayout === 'centered' ? 72 : 64)),
            layout: mobileTopLayout,
            container_width: '1280px',
            sticky_behavior: mobileTop.sticky_behavior || (mobileTop.sticky === false ? 'none' : 'always'),
            shadow: ['small', 'medium', 'large'].includes(String(mobileTop.show_shadow)),
            shadow_style: mobileTop.show_shadow || 'none',
            show_border: mobileTop.show_shadow === 'border_small',
            progressbar: false,
            background: { color: mobileTop.bg_color || '', image_url: '', overlay_opacity: 0 },
            text_color: mobileTop.text_color || '',
            custom_css: '',
            column_flex: 'default',
            blocks: mobileBlocks,
        },
        mobile_bottom: {
            enabled: mobileBottom.enabled === true,
            sticky: true,
            height: mobileBottom.hide_menu_labels === true ? 48 : 60,
            layout: mobileBottomLayout,
            show_glassmorphism: mobileBottom.show_glassmorphism !== false,
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
const desktopTransparentOnHero = computed(() => String(frontendHeaderSettings.value.desktop?.transparent_on_hero) === 'true')
const isHeroOverlayActive = ref(typeof window !== 'undefined' ? window.scrollY <= 2 : true)
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
const isLeftClusterMainHeader = computed(() => ['saas', 'compact'].includes(mainHeaderLayout.value))
const isMinimalMainHeader = computed(() => mainHeaderLayout.value === 'minimal')
const homepageHeroSection = computed(() => {
    const config = page.props.frontendHomepageConfig as any
    if (!config || !config.sections) return null
    return config.sections.find((s: any) => s.type === 'hero')
})

const isHeroBgEnabled = computed(() => {
    const bg = homepageHeroSection.value?.config?.show_hero_background
    if (bg === undefined || bg === null || bg === '') return false
    if (typeof bg === 'boolean') return bg
    if (typeof bg === 'number') return bg !== 0
    if (typeof bg === 'string') {
        const lower = bg.toLowerCase().trim()
        if (lower === '1' || lower === 'true') return true
        if (lower === '0' || lower === 'false') return false
    }
    return false
})

const isHeroGradEnabled = computed(() => {
    const grad = homepageHeroSection.value?.config?.hero_gradient_enabled
    if (grad === undefined || grad === null || grad === '') return true
    if (typeof grad === 'boolean') return grad
    if (typeof grad === 'number') return grad !== 0
    if (typeof grad === 'string') {
        const lower = grad.toLowerCase().trim()
        if (lower === '1' || lower === 'true') return true
        if (lower === '0' || lower === 'false') return false
    }
    return true
})

const isLightGradient = computed(() => {
    const palette = homepageHeroSection.value?.config?.hero_gradient_palette
    if (typeof palette !== 'string') return false
    return ['light_glow', 'light_warm'].includes(palette.trim())
})

const supportsTransparentHeroHeader = computed(() => {
    if (page.component !== 'Welcome') {
        return false
    }
    if (!isHeroBgEnabled.value && !isHeroGradEnabled.value) {
        return false
    }
    if (!isHeroBgEnabled.value && isHeroGradEnabled.value && isLightGradient.value) {
        return false
    }
    return desktopTransparentOnHero.value || (headerConfig.value as any)?.transparent_homepage === true
})
const isTransparentDesktopHeaderActive = computed(() => supportsTransparentHeroHeader.value && isHeroOverlayActive.value)
const isTransparentMainHeaderActive = computed(() => isTransparentDesktopHeaderActive.value)
const mainHeaderBackgroundStyle = computed(() => isTransparentMainHeaderActive.value ? {} : sectionBackgroundStyle(headerConfig.value))

const isOverlayLightBlock = (block: any) => {
    if (block?.id && String(block.id).startsWith('simple_bottom_')) {
        return false
    }
    return isTransparentMainHeaderActive.value
}
const mainHeaderSectionStyle = computed<CSSProperties>(() => {
    const style = { ...sectionStyle(headerConfig.value, 'main', 72) }
    const isSticky = stickyBehavior(headerConfig.value) !== 'none'
    if (supportsTransparentHeroHeader.value && isSticky) {
        style.position = 'fixed'
        style.top = stickyTop('main')
        style.left = '0px'
        style.right = '0px'
    } else if (isTransparentMainHeaderActive.value) {
        style.position = isSticky ? 'fixed' : 'absolute'
        style.top = stickyTop('main')
        style.left = '0px'
        style.right = '0px'
    }
    return style
})
const mobileHeaderSectionStyle = computed<CSSProperties>(() => {
    const style = { ...sectionStyle(mobileHeaderConfig.value, 'mobile', 64) }
    const isSticky = mobileHeaderConfig.value?.sticky !== false
    if (supportsTransparentHeroHeader.value && isSticky) {
        style.position = 'fixed'
        style.top = '0px'
        style.left = '0px'
        style.right = '0px'
    } else if (isTransparentMainHeaderActive.value) {
        style.position = isSticky ? 'fixed' : 'absolute'
        style.top = '0px'
        style.left = '0px'
        style.right = '0px'
    }
    return style
})
const mobileHeaderBackgroundStyle = computed(() => isTransparentMainHeaderActive.value ? {} : sectionBackgroundStyle(mobileHeaderConfig.value))
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
const mainNavClass = (zone: 'left' | 'center' | 'right') => {
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

    if (zone === 'right') {
        classes.push('md:flex', 'min-w-0', 'gap-1')
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
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const hasPremiumAccess = computed(() => {
    const status = String(user.value?.subscription_status || '').trim().toLowerCase()

    if (Boolean(user.value?.is_pro)) {
        return true
    }

    return ['active', 'trialing'].includes(status)
})

const getMenu = (slug: string) => globalMenus.value.find((m: any) => m.slug === slug)

const visibleMenuItems = (slug: string) => {
    const menu = getMenu(slug)
    const items = menu?.items?.filter((item: any) => item.is_active !== false) || []
    const loggedIn = Boolean(page.props.auth?.user)
    const isPro = hasPremiumAccess.value

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
const menuItemLabel = (item: any) => String(item.label || item.title || '')
const menuItemIcon = (item: any) => String(item.icon || '').trim()
const menuItemBadgeText = (item: any) => String(item.badge_text || '').trim()
const menuItemBadgeColor = (item: any) => {
    const color = String(item.badge_color || 'gray')
    return ['green', 'blue', 'violet', 'amber', 'red', 'gray'].includes(color) ? color : 'gray'
}
const menuItemTarget = (item: any) => item?.target === '_blank' ? '_blank' : '_self'
const menuItemRel = (item: any) => menuItemTarget(item) === '_blank' ? 'noopener noreferrer' : undefined
const menuParentId = (item: any) => item.parent_id ?? item.parentId ?? null
const topMenuItems = (slug: string) => visibleMenuItems(slug).filter((item: any) => !menuParentId(item))
const submenuItems = (slug: string, parentId: string | number) => visibleMenuItems(slug).filter((item: any) => String(menuParentId(item)) === String(parentId))
const hasSubmenuItems = (slug: string, parentId: string | number) => submenuItems(slug, parentId).length > 0

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
    if (isDark.value) {
        return {}
    }
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
    if (isDark.value) {
        return {}
    }
    const style: HeaderStyle = {}
    const textColor = typeof config?.text_color === 'string' ? config.text_color : ''
    const hoverColor = typeof config?.menu_hover_color === 'string' && config.menu_hover_color
        ? config.menu_hover_color
        : textColor

    if (textColor) {
        style.color = textColor
        style['--header-menu-text-color'] = textColor
        style['--header-control-text-color'] = textColor
        style['--header-action-text-color'] = textColor
        style['--header-soft-icon-color'] = textColor
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
        style['--header-soft-icon-hover-color'] = hoverColor
        style['--header-soft-icon-hover-bg'] = style['--header-control-hover-bg']
        style['--header-soft-icon-hover-bg-dark'] = style['--header-control-hover-bg-dark']
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

watch(
    () => mobileBottomHeaderConfig.value,
    (config) => {
        if (typeof window !== 'undefined') {
            const height = config?.enabled ? Number(config.height || 60) : 0
            document.documentElement.style.setProperty('--mobile-bottom-height', `${height}px`)
        }
    },
    { immediate: true, deep: true }
)

watch(
    () => mobileHeaderConfig.value,
    (config) => {
        if (typeof window !== 'undefined') {
            const height = config?.enabled ? Number(config.height || 64) : 0
            document.documentElement.style.setProperty('--header-height', `${height}px`)
            document.documentElement.style.setProperty('--mobile-top-height', `${height}px`)
        }
    },
    { immediate: true, deep: true }
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


const mobileIconButtonClass = 'flex h-10 w-10 items-center justify-center rounded-xl bg-transparent transition-colors hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
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
    styleValue === 'primary' || styleValue === 'filled' ? 'btn-primary-admin shadow-lg shadow-primary-600/20' : '',
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
    const roundedClass = ['icon_only', 'circular_soft_bg', 'light_bg'].includes(displayStyle) ? 'rounded-full' : 'rounded-lg'
    const iconOnlyClass = displayStyle === 'icon_only' ? 'header-soft-icon-button--icon-only' : ''
    const sizeClass = 'h-9 w-9'
    const toneClass = displayStyle === 'icon_only'
        ? 'border-transparent bg-transparent shadow-none'
        : displayStyle === 'light_bg'
            ? 'border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800'
            : 'border'

    const isMobileBlock = block?.id && String(block.id).startsWith('simple_mobile_')
    const hasMobileColor = isMobileBlock && mobileHeaderConfig.value?.text_color
    const colorClass = hasMobileColor ? 'text-current' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'

    return iconSurfaceClass(block, `header-soft-icon-button ${iconOnlyClass} relative flex ${sizeClass} items-center justify-center ${roundedClass} ${toneClass} ${colorClass} transition-all duration-200`)
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

        style.background = 'rgb(255 255 255 / 0.08)'
        style.borderColor = 'rgb(255 255 255 / 0.18)'

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
    const shapeClass = ['icon_only', 'circular_soft_bg', 'light_bg'].includes(displayStyle) ? '!rounded-full' : '!rounded-lg'
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

        style.background = 'rgb(255 255 255 / 0.08)'
        style.borderColor = 'rgb(255 255 255 / 0.18)'
        style['--header-soft-icon-color'] = '#ffffff'
        style['--header-soft-icon-hover-color'] = '#ffffff'
        style['--header-soft-icon-hover-bg'] = 'rgb(255 255 255 / 0.14)'
        style['--header-soft-icon-hover-bg-dark'] = 'rgb(255 255 255 / 0.18)'

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
    return 'text-current opacity-75'
}
const showCommandPaletteText = (block: any) => ['search_transparent', 'search_light'].includes(commandPaletteDisplayStyle(block))
const commandPaletteLabelClass = (block: any) => {
    return 'text-current opacity-75'
}
const canShowCtaButton = (block: any) => {
    const accessLevel = String(block.config?.access_level || 'all')
    const isLoggedIn = Boolean(user.value)
    const isProUser = hasPremiumAccess.value

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
    const style = String(block.config?.hover_style || 'rounded_soft_bg')
    if (style === 'bottom_border') return 'header-menu-hover-bottom-border'
    if (style === 'pill_soft_bg') return 'header-menu-hover-pill-soft-bg'
    if (style === 'simple') return 'header-menu-hover-simple'
    return 'header-menu-hover-rounded-soft-bg'
}
const menuStyle = (block: any): CSSProperties => {
    const style: HeaderStyle = {}

    if (isOverlayLightBlock(block)) {
        style.color = '#ffffff'
        style['--header-menu-text-color'] = '#ffffff'
        style['--header-menu-hover-color'] = '#ffffff'
        style['--header-menu-hover-bg'] = 'rgb(255 255 255 / 0.12)'
        style['--header-menu-hover-bg-dark'] = 'rgb(255 255 255 / 0.16)'
        style['--header-menu-hover-shadow'] = 'none'
        return style
    }

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
    const textColor = configString(block.config, 'submenu_text_color')
    if (bgColor) style.backgroundColor = bgColor
    style.color = textColor || 'rgb(31 41 55)'
    style['--header-submenu-text-color'] = textColor || 'rgb(31 41 55)'
    return style
}
const headerActionStyle = (block: any): HeaderStyle => {
    const style: HeaderStyle = {}
    const textColor = configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color') || textColor

    if (textColor) {
        style.color = textColor
        style['--header-action-text-color'] = textColor
        style['--header-control-text-color'] = textColor
        style['--header-soft-icon-color'] = textColor
    }
    if (hoverColor) {
        style['--header-action-hover-color'] = hoverColor
        style['--header-action-hover-bg'] = `color-mix(in srgb, ${hoverColor} 10%, transparent)`
        style['--header-action-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 14%, transparent)`
        style['--header-control-hover-color'] = hoverColor
        style['--header-control-hover-bg'] = style['--header-action-hover-bg']
        style['--header-control-hover-bg-dark'] = style['--header-action-hover-bg-dark']
        style['--header-soft-icon-hover-color'] = hoverColor
        style['--header-soft-icon-hover-bg'] = style['--header-action-hover-bg']
        style['--header-soft-icon-hover-bg-dark'] = style['--header-action-hover-bg-dark']
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
    const utilityDisplayStyle = headerUtilityDisplayStyle(block.config?.display_style, block.type === 'language_switcher')
    const isLightBgUtilityIcon = ['notification_bell', 'dark_mode', 'social_icons', 'language_switcher'].includes(block.type) && utilityDisplayStyle === 'light_bg'
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
    if (isLightBgUtilityIcon) {
        style.color = 'rgb(31 41 55)'
    }
    return style
}
const iconSurfaceClass = (block: any, baseClass: string) => [
    baseClass,
    block.config?.bg_style === 'transparent' ? 'bg-transparent dark:bg-transparent' : '',
    block.config?.bg_style === 'filled' ? 'btn-primary-admin text-white hover:text-white dark:text-white' : '',
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
const resolveUserAvatarUrl = (path?: string | null): string => {
    const avatar = String(path || '').trim()

    if (!avatar) {
        return ''
    }

    if (
        avatar.startsWith('http://')
        || avatar.startsWith('https://')
        || avatar.startsWith('/')
        || avatar.startsWith('data:')
        || avatar.startsWith('blob:')
    ) {
        return avatar
    }

    return `/storage/${avatar}`
}
const userMenuAvatarUrl = computed(() => {
    return resolveUserAvatarUrl(user.value?.avatar)
})
const userMenuLinks = computed(() => {
    const links = [
        { href: route('user.dashboard'), label: t('Dashboard'), iconClass: 'ti ti-layout-dashboard', tone: 'default' },
        { href: route('user.dashboard.profile'), label: t('My Profile'), iconClass: 'ti ti-user-circle', tone: 'default' },
        { href: route('user.dashboard.favorites.index'), label: t('My Favorites'), iconClass: 'ti ti-heart', tone: 'default' },
        { href: route('user.dashboard.history.index'), label: t('History'), iconClass: 'ti ti-history', tone: 'default' },
    ]

    if (affiliateEnabled.value) {
        links.push({ href: route('user.dashboard.affiliate'), label: t('Affiliate'), iconClass: 'ti ti-affiliate', tone: 'default' })
    }

    if (isProAvailable.value && !hasPremiumAccess.value) {
        links.push({ href: route('user.dashboard.billing'), label: t('Upgrade'), iconClass: 'ti ti-rocket', tone: 'success' })
    }

    if (isProAvailable.value && hasPremiumAccess.value) {
        links.push({ href: route('user.dashboard.credit-topup'), label: t('Buy Credits'), iconClass: 'ti ti-coins', tone: 'success' })
    }

    return links
})
const userMenuLinkToneClass = (tone: string) => {
    if (tone === 'success') {
        return 'header-user-dropdown-link--success'
    }

    if (tone === 'danger') {
        return 'header-user-dropdown-link--danger'
    }

    return 'header-user-dropdown-link--default'
}

const activeMobileSubmenus = ref<Record<string | number, boolean>>({})
const toggleMobileSubmenu = (itemId: string | number) => {
    activeMobileSubmenus.value[itemId] = !activeMobileSubmenus.value[itemId]
}
const isMobileSubmenuOpen = (itemId: string | number) => {
    return !!activeMobileSubmenus.value[itemId]
}
const closeMobileMenu = () => { mobileMenuOpen.value = false }

const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const getLogoImage = () => {
    if (isTransparentMainHeaderActive.value) {
        return logoDark.value || logoLight.value
    }

    return isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)
}
const drawerLogo = computed(() => {
    return isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)
})
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

const userFirstName = computed(() => {
    const fullName = user.value?.name?.trim() ?? ''

    if (!fullName) {
        return ''
    }

    return fullName.split(/\s+/)[0] ?? ''
})

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
    if (!open) {
        activeMobileSubmenus.value = {}
    }
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
    <template v-for="(secConfig, sectionKey) in { main: headerConfig, mobile: mobileHeaderConfig, mobile_bottom: mobileBottomHeaderConfig }" :key="sectionKey">
        <component
            v-if="secConfig?.custom_css"
            :is="'style'"
            data-header-custom-css
        >
            {{ secConfig.custom_css }}
        </component>
    </template>

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
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-start-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(child) }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span v-if="menuItemBadgeText(child)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(child)}`">{{ menuItemBadgeText(child) }}</span>
                                                <svg v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))" class="h-4 w-4 shrink-0 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                            </span>
                                        </a>
                                        <div
                                            v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))"
                                            class="header-submenu-panel header-submenu-flyout invisible absolute z-50 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition dark:border-surface-700 dark:bg-surface-900"
                                            :style="submenuStyle(block)"
                                        >
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="header-submenu-panel invisible absolute left-0 top-full z-50 mt-0 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
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
                            <span v-if="showCommandPaletteText(block)" :class="commandPaletteLabelClass(block)" class="truncate text-sm font-medium">{{ blockText(block, t('Search')) }}</span>
                        </span>
                            <span v-if="showCommandPaletteText(block)" :class="commandPaletteHintClass(block)" class="rounded-md border border-current/10 px-2 py-1 text-[11px] font-semibold leading-none">{{ blockHint(block, t('Ctrl + K')) }}</span>
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
                                <svg v-if="showUserMenuArrow(block)" class="hidden h-4 w-4 text-current sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="isUserMenuOpen('main')" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                    <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white">
                                            <img v-if="userMenuAvatarUrl" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" />
                                            <span v-else>{{ userMenuInitial }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                        </div>
                                    </div>
                                    <Link v-for="menuLink in userMenuLinks" :key="menuLink.href" :href="menuLink.href" :class="['header-user-dropdown-link flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm transition-colors rtl:text-right', userMenuLinkToneClass(menuLink.tone)]">
                                        <i :class="[menuLink.iconClass, 'text-base leading-none']" aria-hidden="true" />
                                        {{ menuLink.label }}
                                    </Link>
                                    <Link :href="route('logout')" class="header-user-dropdown-link header-user-dropdown-link--danger w-full border-t border-gray-200 text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 transition-colors dark:border-white/10 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                        {{ t('Sign Out') }}
                                    </Link>
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
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-start-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(child) }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span v-if="menuItemBadgeText(child)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(child)}`">{{ menuItemBadgeText(child) }}</span>
                                                <svg v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))" class="h-4 w-4 shrink-0 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                            </span>
                                        </a>
                                        <div
                                            v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))"
                                            class="header-submenu-panel header-submenu-flyout invisible absolute z-50 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition dark:border-surface-700 dark:bg-surface-900"
                                            :style="submenuStyle(block)"
                                        >
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="header-submenu-panel invisible absolute left-0 top-full z-50 mt-0 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
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
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-start-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(child) }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span v-if="menuItemBadgeText(child)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(child)}`">{{ menuItemBadgeText(child) }}</span>
                                                <svg v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))" class="h-4 w-4 shrink-0 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                            </span>
                                        </a>
                                        <div
                                            v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))"
                                            class="header-submenu-panel header-submenu-flyout invisible absolute z-50 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition dark:border-surface-700 dark:bg-surface-900"
                                            :style="submenuStyle(block)"
                                        >
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="header-submenu-panel invisible absolute left-0 top-full z-50 mt-0 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
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
                    <nav v-else-if="block.type === 'navigation'" :class="[...mainNavClass('right'), menuAlignmentClass(block), menuHoverStyleClass(block)]" :style="menuStyle(block)">
                        <template v-if="getMenu(block.config.menu_slug)">
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative">
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-end-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(child) }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span v-if="menuItemBadgeText(child)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(child)}`">{{ menuItemBadgeText(child) }}</span>
                                                <svg v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))" class="h-4 w-4 shrink-0 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                            </span>
                                        </a>
                                        <div
                                            v-if="hasSubmenuItems(block.config.menu_slug, menuItemId(child))"
                                            class="header-submenu-panel header-submenu-flyout invisible absolute z-50 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition dark:border-surface-700 dark:bg-surface-900"
                                            :style="submenuStyle(block)"
                                        >
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else-if="item.mega_menu && item.mega_menu_content"
                                    class="header-submenu-panel invisible absolute right-0 top-full z-50 mt-0 min-w-[480px] rounded-xl border border-gray-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
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
                    <LanguageSwitcher v-else-if="block.type === 'language_switcher'" :display="languageSwitcherDisplay(block)" :ui="{ buttonClass: languageSwitcherClass(block), buttonStyle: languageSwitcherStyle(block), iconStyle: blockVisualStyle(block) }" />
                    <NotificationBell v-else-if="block.type === 'notification_bell'" context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    <SocialFollow v-else-if="block.type === 'social_icons'" display-mode="icons" :icon-use-platform-surface="false" :icon-use-platform-color="false" :icon-item-class="socialIconButtonClass(block)" icon-inner-class="text-[18px] leading-none" :icon-item-style="softIconSurfaceStyle(block)" :icon-inner-style="blockVisualStyle(block)" />
                    <button v-else-if="block.type === 'command_palette'" type="button" :class="commandPaletteButtonClass(block)" :style="commandPaletteButtonStyle(block)" :aria-label="t('Open command palette')" @click="openCommandPalette()">
                        <span class="inline-flex items-center gap-2 min-w-0">
                            <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" :style="blockVisualStyle(block)" aria-hidden="true" />
                            <span v-if="showCommandPaletteText(block)" :class="commandPaletteLabelClass(block)" class="truncate text-sm font-medium">{{ blockText(block, t('Search')) }}</span>
                        </span>
                        <span v-if="showCommandPaletteText(block)" :class="commandPaletteHintClass(block)" class="rounded-md border border-current/10 px-2 py-1 text-[11px] font-semibold leading-none">{{ blockHint(block, t('Ctrl + K')) }}</span>
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
                                <svg v-if="showUserMenuArrow(block)" class="hidden h-4 w-4 text-current sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="isUserMenuOpen('main')" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                    <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white">
                                            <img v-if="userMenuAvatarUrl" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" />
                                            <span v-else>{{ userMenuInitial }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                        </div>
                                    </div>
                                    <Link v-for="menuLink in userMenuLinks" :key="menuLink.href" :href="menuLink.href" :class="['header-user-dropdown-link flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm transition-colors rtl:text-right', userMenuLinkToneClass(menuLink.tone)]">
                                        <i :class="[menuLink.iconClass, 'text-base leading-none']" aria-hidden="true" />
                                        {{ menuLink.label }}
                                    </Link>
                                    <button @click="logout" class="header-user-dropdown-link header-user-dropdown-link--danger w-full border-t border-gray-200 text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 transition-colors dark:border-white/10 flex items-center gap-2">
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
        :class="[
            'w-full md:hidden header-section-overlay',
            sectionPositionClass(mobileHeaderConfig),
            sectionTransitionClass(mobileHeaderConfig),
            isTransparentMainHeaderActive ? '' : sectionShadowClass(mobileHeaderConfig),
            sectionVisibilityClass(mobileHeaderConfig),
            isTransparentMainHeaderActive
                ? `absolute bg-transparent shadow-none header-overlay-light ${sectionBorderClass(mobileHeaderConfig, 'bottom', true)}`
                : `backdrop-blur-md ${sectionBorderClass(mobileHeaderConfig)} ${hasCustomBackground(mobileHeaderConfig) ? '' : 'bg-white/95 dark:bg-surface-900/90'}`,
        ]"
        :style="{ ...mobileHeaderSectionStyle, ...mobileHeaderBackgroundStyle, ...sectionAccentStyle(mobileHeaderConfig) }"
    >
        <div class="flex h-full items-center justify-between gap-3" :class="[containerClass({ ...mobileHeaderConfig, container_width: '1280px' }, true), isCenteredMobileTop ? 'relative' : '']">
            <div class="flex items-center gap-2" :class="[mobileColFlexClass('left'), mobileTopSideClass]">
                <template v-for="block in mobileLeftBlocks" :key="block.id">
                    <button v-if="block.type === 'hamburger'" type="button" class="mobile-header-utility-btn" :class="iconSurfaceClass(block, [mobileIconButtonClass, mobileHeaderConfig?.text_color ? 'text-current' : 'text-gray-600 dark:text-gray-300'].join(' '))" :style="blockVisualStyle(block)" :aria-label="t('Open menu')" @click="mobileMenuOpen = !mobileMenuOpen">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'logo'" href="/" class="flex items-center gap-2 text-base font-bold" :class="[mobileTopLogoClass, mobileHeaderConfig?.text_color ? '' : 'text-gray-900 dark:text-white']">
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
                    <Link v-else-if="block.type === 'user_menu_icon'" class="mobile-header-utility-btn" :href="userIconHref" :class="iconSurfaceClass(block, [mobileIconButtonClass, mobileHeaderConfig?.text_color ? 'text-current' : 'text-gray-600 dark:text-gray-300'].join(' '))" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                        <span v-if="user" class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-xs font-bold text-white">
                            <img v-if="userMenuAvatarUrl" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" />
                            <span v-else>{{ userMenuInitial }}</span>
                        </span>
                        <template v-else>
                            <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                        </template>
                    </Link>
                    <button v-else-if="block.type === 'command_palette'" type="button" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)" :aria-label="t('Open search')" @click="openCommandPalette()">
                        <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" aria-hidden="true" />
                    </button>
                    <LanguageSwitcher v-else-if="block.type === 'language_switcher'" display="icon" :ui="{ buttonClass: notificationButtonClass(block).join(' '), buttonStyle: softIconSurfaceStyle(block), iconStyle: blockVisualStyle(block) }" />
                    <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" class="text-xs font-bold transition-all whitespace-nowrap shrink-0 flex items-center justify-center" :style="blockVisualStyle(block)" :class="[
                        ctaShapeClass(block),
                        isIconOnly(block) ? 'h-9 w-9' : 'px-3 h-9',
                        ...buttonVariantClass(ctaStyleValue(block)),
                    ]">
                        <span class="inline-flex items-center gap-1">
                            <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), 'text-base']" aria-hidden="true" />
                            <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                        </span>
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
                            <Link href="/" class="flex min-w-0 items-center gap-2" @click="closeMobileMenu">
                                <img v-if="drawerLogo" :src="drawerLogo" :alt="logoAltText" class="h-9 w-auto max-w-32 object-contain" />
                                <span v-else class="truncate text-base font-bold text-gray-900 dark:text-white">{{ mobileDrawerTitle }}</span>
                            </Link>
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-500 transition hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" :aria-label="t('Close menu')" @click="closeMobileMenu">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <nav class="flex-1 space-y-1 overflow-y-auto px-1 py-2">
                            <template v-if="getMenu(mobileDrawerMenuSlug)">
                                <div v-for="item in topMenuItems(mobileDrawerMenuSlug)" :key="menuItemId(item)" class="space-y-1">
                                    <template v-if="hasSubmenuItems(mobileDrawerMenuSlug, menuItemId(item))">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition text-start"
                                            :class="isActive(menuItemHref(item)) ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white'"
                                            @click="toggleMobileSubmenu(menuItemId(item))"
                                        >
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(item) }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span v-if="menuItemBadgeText(item)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                                <svg
                                                    class="h-4 w-4 transition-transform duration-200"
                                                    :class="[
                                                        isMobileSubmenuOpen(menuItemId(item)) ? 'rotate-90 rtl:-rotate-90 text-gray-500 dark:text-gray-400' : 'text-gray-300 dark:text-gray-600 rtl:rotate-180'
                                                    ]"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                                                </svg>
                                            </span>
                                        </button>
                                    </template>
                                    <template v-else>
                                        <a
                                            :href="menuItemHref(item)"
                                            :target="menuItemTarget(item)"
                                            :rel="menuItemRel(item)"
                                            class="flex items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition"
                                            :class="isActive(menuItemHref(item)) ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white'"
                                            @click="closeMobileMenu"
                                        >
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(item) }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span v-if="menuItemBadgeText(item)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                            </span>
                                        </a>
                                    </template>

                                    <div
                                        v-if="hasSubmenuItems(mobileDrawerMenuSlug, menuItemId(item))"
                                        class="grid transition-[grid-template-rows,opacity] duration-200 ease-in-out"
                                        :class="isMobileSubmenuOpen(menuItemId(item)) ? 'grid-rows-[1fr] opacity-100 mt-1' : 'grid-rows-[0fr] opacity-0 pointer-events-none'"
                                    >
                                        <div class="overflow-hidden">
                                            <div class="space-y-1 ps-4 pb-1">
                                                <div v-for="child in submenuItems(mobileDrawerMenuSlug, menuItemId(item))" :key="menuItemId(child)" class="space-y-1">
                                                    <template v-if="hasSubmenuItems(mobileDrawerMenuSlug, menuItemId(child))">
                                                        <button
                                                            type="button"
                                                            class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-950 text-start"
                                                            @click="toggleMobileSubmenu(menuItemId(child))"
                                                        >
                                                            <span class="flex min-w-0 items-center gap-2">
                                                                <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                                <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                            </span>
                                                            <span class="flex shrink-0 items-center gap-2">
                                                                <span v-if="menuItemBadgeText(child)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(child)}`">{{ menuItemBadgeText(child) }}</span>
                                                                <svg
                                                                    class="h-4 w-4 transition-transform duration-200"
                                                                    :class="[
                                                                        isMobileSubmenuOpen(menuItemId(child)) ? 'rotate-90 rtl:-rotate-90 text-gray-500 dark:text-gray-400' : 'text-gray-300 dark:text-gray-600 rtl:rotate-180'
                                                                    ]"
                                                                    fill="none"
                                                                    viewBox="0 0 24 24"
                                                                    stroke="currentColor"
                                                                    stroke-width="1.8"
                                                                >
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                                                                </svg>
                                                            </span>
                                                        </button>
                                                    </template>
                                                    <template v-else>
                                                        <a
                                                            :href="menuItemHref(child)"
                                                            :target="menuItemTarget(child)"
                                                            :rel="menuItemRel(child)"
                                                            class="flex items-center justify-between gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-950"
                                                            @click="closeMobileMenu"
                                                        >
                                                            <span class="flex min-w-0 items-center gap-2">
                                                                <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                                <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                            </span>
                                                            <span class="flex shrink-0 items-center gap-2">
                                                                <span v-if="menuItemBadgeText(child)" class="header-menu-badge" :class="`header-menu-badge--${menuItemBadgeColor(child)}`">{{ menuItemBadgeText(child) }}</span>
                                                            </span>
                                                        </a>
                                                    </template>

                                                    <div
                                                        v-if="hasSubmenuItems(mobileDrawerMenuSlug, menuItemId(child))"
                                                        class="grid transition-[grid-template-rows,opacity] duration-200 ease-in-out"
                                                        :class="isMobileSubmenuOpen(menuItemId(child)) ? 'grid-rows-[1fr] opacity-100 mt-1' : 'grid-rows-[0fr] opacity-0 pointer-events-none'"
                                                    >
                                                        <div class="overflow-hidden">
                                                            <div class="space-y-1 ps-4 pb-1">
                                                                <a
                                                                    v-for="grandchild in submenuItems(mobileDrawerMenuSlug, menuItemId(child))"
                                                                    :key="menuItemId(grandchild)"
                                                                    :href="menuItemHref(grandchild)"
                                                                    :target="menuItemTarget(grandchild)"
                                                                    :rel="menuItemRel(grandchild)"
                                                                    class="flex items-center justify-between gap-3 rounded-xl px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-gray-950"
                                                                    @click="closeMobileMenu"
                                                                >
                                                                    <span class="flex min-w-0 items-center gap-2">
                                                                        <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                                        <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                                    </span>
                                                                    <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
        :class="[
            sectionTransitionClass(mobileBottomHeaderConfig),
            sectionShadowClass(mobileBottomHeaderConfig),
            bottomSectionVisibilityClass(mobileBottomHeaderConfig),
            sectionBorderClass(mobileBottomHeaderConfig, 'top'),
            mobileBottomHeaderConfig?.show_glassmorphism !== false
                ? 'bg-white/95 backdrop-blur-md dark:bg-surface-900/90'
                : 'bg-white dark:bg-surface-900'
        ]"
        :style="{ height: `${Number(mobileBottomHeaderConfig?.height ?? 60)}px`, ...sectionBackgroundStyle(mobileBottomHeaderConfig) }"
        class="fixed inset-x-0 bottom-0 z-50 transform-gpu will-change-transform md:hidden header-section-overlay"
    >
        <div class="flex h-full items-center justify-between gap-1" :class="containerClass({ ...mobileBottomHeaderConfig, container_width: '1280px' }, true)">
            <template v-for="block in activeMobileBottomBlocks" :key="block.id">
                <Link v-if="block.type === 'home_link'" :href="String(block.config.link || '/')" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Home')">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75v9A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-9" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Home')) }}</span>
                </Link>
                <button v-else-if="block.type === 'command_palette'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Search')" @click="openCommandPalette()">
                    <i :class="[blockIconClass(block, 'ti ti-search'), 'text-xl leading-none']" aria-hidden="true" />
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Search')) }}</span>
                </button>
                <div v-else-if="block.type === 'notification_bell' && user" class="flex min-w-0 flex-1 justify-center">
                    <NotificationBell context="user" :label="showBlockLabel(block) ? blockLabel(block, t('Notifications')) : ''" :ui="{ wrapperClass: 'flex min-w-0 w-full', triggerClass: notificationButtonClass(block, true).join(' '), triggerStyle: softIconSurfaceStyle(block), dropdownClass: 'fixed inset-x-4 bottom-20 z-50 max-h-[70vh] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900', iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                </div>
                <Link v-else-if="block.type === 'user_menu_icon'" :href="userIconHref" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                    <span v-if="user" class="flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-[10px] font-bold text-white">
                        <img v-if="userMenuAvatarUrl" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" />
                        <span v-else>{{ userMenuInitial }}</span>
                    </span>
                    <template v-else>
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                    </template>
                    <span v-if="showBlockLabel(block)">{{ user ? userFirstName : String(block.config?.guest_label || blockLabel(block, t('Sign In'))) }}</span>
                </Link>
                <button v-else-if="block.type === 'hamburger'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Open menu')" @click="mobileMenuOpen = !mobileMenuOpen">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Menu')) }}</span>
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
.header-overlay-light :is(.header-soft-icon-button, .language-switcher-button, :deep(.language-switcher-button), .mobile-header-utility-btn, [class*="h-10"][class*="w-10"]) {
    border-color: rgb(255 255 255 / 0.18) !important;
    background: rgb(255 255 255 / 0.08) !important;
    border-radius: 9999px !important;
}
.header-overlay-light :is(.header-soft-icon-button:hover, .language-switcher-button:hover, :deep(.language-switcher-button:hover), .mobile-header-utility-btn:hover, [class*="h-10"][class*="w-10"]:hover) {
    background: rgb(255 255 255 / 0.16) !important;
    border-color: rgb(255 255 255 / 0.28) !important;
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
.header-overlay-light :is(.header-menu-hover-pill-soft-bg .header-menu-link:hover, .header-menu-hover-pill-soft-bg .header-menu-link-active, .header-menu-hover-rounded-soft-bg .header-menu-link:hover, .header-menu-hover-rounded-soft-bg .header-menu-link-active) {
    background: rgb(255 255 255 / 0.12) !important;
    box-shadow: none !important;
}
.header-overlay-light :is(.header-menu-hover-pill-soft-bg .header-menu-link:hover, .header-menu-hover-pill-soft-bg .header-menu-link-active) {
    border-radius: 9999px !important;
}
.header-overlay-light :is(.header-menu-hover-rounded-soft-bg .header-menu-link:hover, .header-menu-hover-rounded-soft-bg .header-menu-link-active) {
    border-radius: var(--radius-lg) !important;
}
.header-overlay-light .header-menu-hover-bottom-border .header-menu-link::after {
    background: #ffffff;
}
.header-overlay-light :is([class*="border-primary-"], [class*="text-primary-"]) {
    color: #ffffff !important;
    border-color: rgb(255 255 255 / 0.28) !important;
}
.header-overlay-light :is([class*="bg-gray-50"], [class*="bg-primary-50"], [class*="bg-surface-800"]) {
    background: rgb(255 255 255 / 0.08) !important;
}
.header-overlay-light .header-user-dropdown {
    background: #ffffff !important;
    border-color: rgb(229 231 235) !important;
    color: rgb(55 65 81) !important;
}
.dark .header-overlay-light .header-user-dropdown {
    background: rgb(31 41 55) !important;
    border-color: rgb(255 255 255 / 0.1) !important;
    color: rgb(229 231 235) !important;
}
.header-overlay-light .header-user-dropdown :is(a, button, svg, i, span) {
    color: inherit;
}
.header-user-dropdown .header-user-dropdown-link--default {
    color: rgb(55 65 81) !important;
}
.dark .header-user-dropdown .header-user-dropdown-link--default {
    color: rgb(209 213 219) !important;
}
.header-user-dropdown .header-user-dropdown-link--default:hover {
    background: rgb(249 250 251) !important;
}
.dark .header-user-dropdown .header-user-dropdown-link--default:hover {
    background: rgb(255 255 255 / 0.05) !important;
}
.header-user-dropdown .header-user-dropdown-link--danger {
    color: rgb(239 68 68) !important;
}
.header-user-dropdown .header-user-dropdown-link--danger:hover {
    background: rgb(254 242 242) !important;
}
.dark .header-user-dropdown .header-user-dropdown-link--danger:hover {
    background: rgb(127 29 29 / 0.22) !important;
}
.header-overlay-light .header-user-dropdown .header-user-dropdown-link--default {
    color: rgb(55 65 81) !important;
}
.dark .header-overlay-light .header-user-dropdown .header-user-dropdown-link--default {
    color: rgb(209 213 219) !important;
}
.header-overlay-light .header-user-dropdown .header-user-dropdown-link--default:hover {
    background: rgb(249 250 251) !important;
}
.dark .header-overlay-light .header-user-dropdown .header-user-dropdown-link--default:hover {
    background: rgb(255 255 255 / 0.05) !important;
}
.header-overlay-light .header-user-dropdown .header-user-dropdown-link--danger {
    color: rgb(239 68 68) !important;
}
.header-overlay-light .header-user-dropdown .header-user-dropdown-link--danger:hover {
    background: rgb(254 242 242) !important;
}
.dark .header-overlay-light .header-user-dropdown .header-user-dropdown-link--danger:hover {
    background: rgb(127 29 29 / 0.22) !important;
}
.header-user-dropdown .header-user-dropdown-link--success {
    color: rgb(21 128 61) !important;
}
.header-user-dropdown .header-user-dropdown-link--success:hover {
    background: rgb(240 253 244) !important;
    color: rgb(21 128 61) !important;
}
.dark .header-user-dropdown .header-user-dropdown-link--success {
    color: rgb(74 222 128) !important;
}
.dark .header-user-dropdown .header-user-dropdown-link--success:hover {
    background: rgb(20 83 45 / 0.32) !important;
    color: rgb(74 222 128) !important;
}
.header-overlay-light .header-user-dropdown .header-user-dropdown-link--success {
    color: rgb(21 128 61) !important;
}
.dark .header-overlay-light .header-user-dropdown .header-user-dropdown-link--success {
    color: rgb(74 222 128) !important;
}
.header-overlay-light .header-submenu-panel {
    --header-submenu-bg: #ffffff;
    --header-submenu-border: rgb(229 231 235);
    --header-submenu-text: rgb(31 41 55);
    --header-submenu-hover-bg: rgb(243 244 246);
    --header-submenu-hover-text: rgb(31 41 55);
    color: rgb(31 41 55) !important;
    background: #ffffff !important;
}
.header-overlay-light .header-submenu-panel :is(a, button, span, i, svg, p, div, strong, em) {
    color: rgb(31 41 55) !important;
}
.header-overlay-light .header-submenu-panel .header-submenu-link:hover {
    color: rgb(31 41 55) !important;
    background: rgb(243 244 246) !important;
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
.header-menu-label-wrap {
    position: relative;
    padding-inline-end: 0.9rem;
}
.header-menu-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}
.header-menu-badge--floating {
    position: absolute;
    inset-inline-end: -0.425rem;
    top: -0.675rem;
}
.header-menu-badge--green { background: rgb(220 252 231) !important; color: rgb(21 128 61) !important; }
.header-menu-badge--blue { background: rgb(219 234 254) !important; color: rgb(29 78 216) !important; }
.header-menu-badge--violet { background: rgb(237 233 254) !important; color: rgb(109 40 217) !important; }
.header-menu-badge--amber { background: rgb(254 243 199) !important; color: rgb(180 83 9) !important; }
.header-menu-badge--red { background: rgb(254 226 226) !important; color: rgb(220 38 38) !important; }
.header-menu-badge--gray { background: rgb(243 244 246) !important; color: rgb(75 85 99) !important; }

.header-menu-hover-bottom-border .header-menu-link::after {
    position: absolute;
    inset-inline: 0.875rem;
    bottom: 0.25rem;
    height: 2px;
    content: "";
    background: var(--header-menu-hover-color, var(--color-primary-500));
    border-radius: 9999px;
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.18s ease;
}
.header-menu-hover-bottom-border .header-menu-link:hover::after,
.header-menu-hover-bottom-border .header-menu-link-active::after { transform: scaleX(1); }
.header-menu-hover-pill-soft-bg .header-menu-link {
    border-radius: 9999px !important;
}
.header-menu-hover-pill-soft-bg .header-menu-link:hover,
.header-menu-hover-pill-soft-bg .header-menu-link-active { background: var(--header-menu-hover-bg, var(--color-primary-50)); border-radius: 9999px !important; }
.dark .header-menu-hover-pill-soft-bg .header-menu-link:hover,
.dark .header-menu-hover-pill-soft-bg .header-menu-link-active { background: var(--header-menu-hover-bg-dark, rgb(16 185 129 / 0.14)); }
.header-menu-hover-rounded-soft-bg .header-menu-link {
    border-radius: var(--radius-lg);
}
.header-menu-hover-rounded-soft-bg .header-menu-link:hover,
.header-menu-hover-rounded-soft-bg .header-menu-link-active { background: var(--header-menu-hover-bg, var(--surface-card)); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
.dark .header-menu-hover-rounded-soft-bg .header-menu-link:hover,
.dark .header-menu-hover-rounded-soft-bg .header-menu-link-active { background: var(--header-menu-hover-bg-dark, rgb(16 185 129 / 0.14)); }
.header-menu-hover-simple .header-menu-link:hover,
.header-menu-hover-simple .header-menu-link-active {
    color: var(--header-menu-hover-color, var(--color-primary-600)) !important;
    background: transparent !important;
    box-shadow: none !important;
    transform: none !important;
}
.dark .header-menu-hover-simple .header-menu-link:hover,
.dark .header-menu-hover-simple .header-menu-link-active {
    color: var(--header-menu-hover-color, var(--color-primary-400)) !important;
}
.header-submenu-panel {
    --header-submenu-bg: #ffffff;
    --header-submenu-border: rgb(229 231 235);
    --header-submenu-text: rgb(31 41 55);
    --header-submenu-hover-bg: rgb(243 244 246);
    --header-submenu-hover-text: rgb(31 41 55);
    color: var(--header-submenu-text) !important;
    background: var(--header-submenu-bg) !important;
    border-color: var(--header-submenu-border) !important;
}
.dark .header-submenu-panel {
    --header-submenu-bg: rgb(17 24 39);
    --header-submenu-border: rgb(55 65 81 / 0.7);
    --header-submenu-text: rgb(229 231 235);
    --header-submenu-hover-bg: rgb(31 41 55);
    --header-submenu-hover-text: rgb(243 244 246);
    color: var(--header-submenu-text) !important;
}
.header-submenu-panel :is(a, button, span, i, svg, p, div, strong, em) {
    color: inherit;
}
.header-submenu-item:hover > .header-submenu-flyout {
    visibility: visible;
    opacity: 1;
    pointer-events: auto;
}
.header-submenu-flyout {
    inset-inline-start: calc(100% - 0.25rem);
    top: 0;
    pointer-events: none;
}
.header-submenu-link { color: var(--header-submenu-text) !important; }
.dark .header-submenu-link { color: var(--header-submenu-text) !important; }
.header-submenu-link:hover {
    color: var(--header-submenu-hover-text) !important;
    background: var(--header-submenu-hover-bg) !important;
}
.dark .header-submenu-link:hover {
    color: var(--header-submenu-hover-text) !important;
    background: var(--header-submenu-hover-bg) !important;
}
.header-action-link {
    color: var(--header-action-text-color, inherit);
}
.header-action-link:hover {
    color: var(--header-soft-icon-hover-color, var(--header-action-hover-color, inherit));
    background: var(--header-soft-icon-hover-bg, var(--header-action-hover-bg, var(--color-gray-100)));
}
.dark .header-action-link:hover {
    color: var(--header-soft-icon-hover-color, var(--header-action-hover-color, inherit));
    background: var(--header-soft-icon-hover-bg-dark, var(--header-action-hover-bg-dark, rgb(255 255 255 / 0.05)));
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

/* Ensure mobile hamburger and login buttons have transparent backgrounds in the original header */
:not(.header-overlay-light) .mobile-header-utility-btn {
    background-color: transparent !important;
    border-color: transparent !important;
}
:not(.header-overlay-light) .mobile-header-utility-btn:hover {
    background-color: var(--header-control-hover-bg, var(--color-gray-100)) !important;
}
</style>
