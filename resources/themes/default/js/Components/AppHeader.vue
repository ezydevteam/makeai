<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import type { CSSProperties } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'
import LanguageSwitcher from '@/Components/Utility/LanguageSwitcher.vue'
import CommandPalette from '@/Components/Utility/CommandPalette.vue'
import NotificationBell from '@/Components/Utility/NotificationBell.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import SocialFollow from '@themes/default/js/Components/SocialFollow.vue'
import { mediaUrl } from '@/lib/media'

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
    social_button_text?: string
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
    menu_transform?: string
    show_icon_tooltip?: boolean
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
    show_dark_mode_toggle?: boolean
    show_language_switcher?: boolean
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
const siteName = computed(() => String(branding.value?.site_name || page.props.appName || 'MakeAI'))

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
                social_button_text: 'social_button_text' in desktop
                    ? (desktop.social_button_text ?? '')
                    : 'Follow Us',
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
                // `user_menu` is the legacy name for login-only; anything unrecognised falls
                // back to it rather than rendering no guest action at all.
                guest_mode: {
                    login_register: 'both',
                    register_only: 'register_only',
                    user_menu: 'login_only',
                }[String(desktop.auth_mode || 'login_register')] ?? 'login_only',
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
    if (mobileBottom.show_dark_mode_toggle === true) {
        mobileBottomBlocks.push({ id: 'simple_bottom_dark', type: 'dark_mode', enabled: true, config: { label: t('Theme'), show_label: showLabels } })
    }
    if (mobileBottom.show_language_switcher === true) {
        mobileBottomBlocks.push({ id: 'simple_bottom_lang', type: 'language_switcher', enabled: true, config: { label: t('Language'), show_label: showLabels } })
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
            show_icon_tooltip: desktop.show_icon_tooltip !== false,
            progressbar: false,
            background: { color: desktop.bg_color || '', image_url: '', overlay_opacity: 0 },
            text_color: desktopTextColor,
            menu_hover_color: desktopHoverColor,
            custom_css: '',
            blocks: [
                { id: 'simple_logo', type: 'logo', enabled: true, config: { block_align: logoBlockAlign } },
                ...(menuPosition === 'hide'
                    ? []
                    : [{ id: 'simple_nav', type: 'navigation', enabled: true, config: { menu_slug: resolveFrontendMenuSlug(desktop.menu_source, 'main'), alignment: navBlockAlign, block_align: navBlockAlign, hover_style: desktopNavHoverStyle, text_color: desktopTextColor, hover_color: desktopHoverColor, menu_transform: desktop.menu_transform || 'default' } }]),
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
    if (page.component !== 'Home') {
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

/**
 * Colour for the AI Assistant header slot.
 *
 * The assistant button is teleported in by the addon, so it can't reach the per-block
 * colour logic the other header items use. Instead the SLOT carries the colour and the
 * button inherits it — following the same rule as every other block: white while the
 * header is transparent over the hero, otherwise the configured header text colour.
 */
const assistantSlotStyle = computed<CSSProperties>(() => {
    if (isTransparentMainHeaderActive.value) {
        return { color: '#ffffff' }
    }

    if (isDark.value) {
        return {}
    }

    const textColor = frontendHeaderSettings.value.desktop?.text_color
    return textColor ? { color: textColor } : {}
})

// Where the header has to start once it is taken OUT OF FLOW.
//
// The sticky path leaves the header in flow (position: sticky) and everything above it
// pushes it down for free. The transparent-on-hero path does not: it switches to
// position: fixed so the header can overlay the hero, and a fixed box ignores every
// sibling above it. Offsetting by the announcement container alone was not enough —
// AppLayout stacks the demo banner and a header_banner AdSection above #top-sticky-stack,
// neither of which is in any height variable, so the header sat on top of them.
//
// Measured from an anchor left behind in the flow rather than summed from a list of
// known elements: whatever AppLayout puts above the header, the anchor is below it.
const headerFlowAnchor = ref<HTMLElement | null>(null)
const headerAnchorTop = ref(0)
const topStackBottom = ref(0)

const measureHeaderOffsets = () => {
    if (typeof window === 'undefined') return

    // #top-sticky-stack pins to the viewport top, so its bottom edge is the floor the
    // header can never rise above. Falls back to the announcement container on a layout
    // that renders AnnouncementManager without the sticky wrapper.
    const stack = document.getElementById('top-sticky-stack')
        ?? document.getElementById('top-announcement-container')
    topStackBottom.value = stack ? Math.max(0, stack.getBoundingClientRect().bottom) : 0

    headerAnchorTop.value = headerFlowAnchor.value?.getBoundingClientRect().top ?? 0
}

// The header follows the page down until the anchor passes under the pinned banners,
// then holds there. Same shape as the old max(0, height - scrollY), except the floor is
// the real banner stack and the starting point is the header's own place in the flow.
const overlayHeaderTop = computed(() => Math.max(topStackBottom.value, headerAnchorTop.value))

// For the non-sticky overlay, which is `absolute` and so resolved against the document
// rather than the viewport: the anchor's document offset, which does not change as the
// page scrolls (the anchor rises by exactly scrollY).
const anchorDocumentTop = computed(() => Math.max(0, headerAnchorTop.value + scrollY.value))

const isOverlayLightBlock = (block: any) => {
    if (block?.id && String(block.id).startsWith('simple_bottom_')) {
        return false
    }
    return isTransparentMainHeaderActive.value
}
const mainHeaderSectionStyle = computed<CSSProperties>(() => {
    const style = { ...sectionStyle(headerConfig.value, 'main', 72) }
    const isSticky = stickyBehavior(headerConfig.value) !== 'none'
    const topOffset = isSticky ? overlayHeaderTop.value : anchorDocumentTop.value

    if (supportsTransparentHeroHeader.value && isSticky) {
        style.position = 'fixed'
        style.top = `${topOffset}px`
        style.left = '0px'
        style.right = '0px'
    } else if (isTransparentMainHeaderActive.value) {
        style.position = isSticky ? 'fixed' : 'absolute'
        style.top = `${topOffset}px`
        style.left = '0px'
        style.right = '0px'
    }
    return style
})
const mobileHeaderSectionStyle = computed<CSSProperties>(() => {
    const style = { ...sectionStyle(mobileHeaderConfig.value, 'mobile', 64) }
    const isSticky = mobileHeaderConfig.value?.sticky !== false
    const topOffset = isSticky ? overlayHeaderTop.value : anchorDocumentTop.value

    if (supportsTransparentHeroHeader.value && isSticky) {
        style.position = 'fixed'
        style.top = `${topOffset}px`
        style.left = '0px'
        style.right = '0px'
    } else if (isTransparentMainHeaderActive.value) {
        style.position = isSticky ? 'fixed' : 'absolute'
        style.top = `${topOffset}px`
        style.left = '0px'
        style.right = '0px'
    }
    return style
})
const mobileHeaderBackgroundStyle = computed(() => isTransparentMainHeaderActive.value ? {} : sectionBackgroundStyle(mobileHeaderConfig.value))
// Auto-shift for the centered desktop menu — measured in measureDesktopMenuFit().
//
// A centered menu wants the middle of the row and the action cluster is shrink-0, so a
// header carrying a search box, social icons, a language switcher, an account button and a
// CTA runs out of row before the menu has anywhere to be: the cluster is pushed onto a
// second line and the bar comes out half again as tall (94px against a configured 72 on a
// 1440 viewport, with the actions sitting under the logo). Rather than reserve the middle
// at that price, the menu gives up centering once it stops fitting and packs in beside the
// logo, and whatever still does not fit collapses into a "More" dropdown. The row then
// holds one line at every width.
const desktopMenuIsCompact = ref(false)
const desktopNavVisibleCount = ref(Number.POSITIVE_INFINITY)
const mainRowGapClass = computed(() => {
    return 'gap-x-2.5 gap-y-1.5'
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
    // The action cluster keeps its items at full size and wraps onto another line when it
    // runs out of room, instead of squeezing them into each other on a narrow viewport.
    return col === 'right'
        ? 'gap-x-2.5 gap-y-1.5 shrink-0 flex-wrap justify-end [&>*]:shrink-0'
        : 'gap-x-2.5 gap-y-1.5 flex-wrap'
}
const mainNavClass = (zone: 'left' | 'center' | 'right') => {
    // flex-wrap lets a long menu break onto a second line rather than running over the
    // logo / action cluster on a narrow viewport. The shifted centered menu is the one
    // exception: it has been measured down to the links that fit on one line, with the rest
    // in the "More" panel, so wrapping there would only undo the fit just calculated.
    const isCompactCenter = zone === 'center' && desktopMenuIsCompact.value
    const classes = ['hidden', 'items-center', isCompactCenter ? 'flex-nowrap' : 'flex-wrap']

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

    classes.push('md:flex', 'min-w-0', 'max-w-full', isCompactCenter ? 'justify-start' : 'justify-center', 'gap-1')
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

    // flex-auto (basis auto), not flex-1 (basis 0), for the nav column: it still grows to
    // fill the row, but it is measured at its real width, so a header that no longer fits
    // wraps the action cluster onto a second line instead of squeezing the menu into a
    // narrow column of stacked links.
    if (isMinimalMainHeader.value) {
        if (col === 'center') return 'flex-auto min-w-0 justify-center'
        return col === 'right' ? 'shrink-0 justify-end' : 'shrink-0'
    }

    if (col === 'left') return 'shrink-0'
    // flex-auto (basis auto) is what pushes the action cluster onto a second line: flex line
    // breaking measures this column at the menu's full width, so the row is judged too long
    // before anything is allowed to give. The shifted state switches to basis 0 — the column
    // no longer claims a width of its own, the three columns always share one line, and the
    // menu takes whatever is left between the logo and the actions.
    if (col === 'center') {
        return desktopMenuIsCompact.value ? 'flex-1 min-w-0 justify-start' : 'flex-auto min-w-0 justify-center'
    }
    return 'shrink-0 justify-end'
}
const mobileColFlexClass = (col: 'left' | 'right') => {
    // Side columns size to their own content in the centered layout; the logo cell takes
    // whatever is left. Giving both sides an equal `flex-1` share instead pinned the logo
    // to the exact middle of the row no matter how wide the right side got — and since a
    // `min-w-0` side column is allowed to shrink under its content, a long CTA label spilled
    // out of its own cell leftwards and printed straight over the logo. Sizing to content
    // means the logo simply drifts left as the right side grows, and never collides.
    if (isCenteredMobileTop.value) return 'shrink-0'

    const flex = (mobileHeaderConfig.value?.column_flex ?? 'default') as string
    if (flex === col || (flex === 'default' && col === 'left')) return 'flex-1 min-w-0'
    return ''
}
const mobileLeftBlocks = computed(() => activeMobileBlocks.value.filter((block: any) => {
    if (isCenteredMobileTop.value && block.type === 'logo') return false
    return (block.config?.block_align || 'left') === 'left'
}))
// Centered layout renders the logo as its own in-flow middle cell. It used to be
// absolutely positioned over the row, which overlapped the side icons as soon as enough
// mobile blocks were enabled.
const mobileCenterBlocks = computed(() => isCenteredMobileTop.value
    ? activeMobileBlocks.value.filter((block: any) => block.type === 'logo')
    : [])
const mobileRightBlocks = computed(() => activeMobileBlocks.value.filter((block: any) => block.config?.block_align === 'right'))
const mobileHamburgerBlock = computed(() => activeMobileBlocks.value.find((block: any) => block.type === 'hamburger'))
const mobileDrawerMenuSlug = computed(() => mobileHamburgerBlock.value?.config?.menu_slug || 'mobile')
const mobileDrawerTitle = computed(() => mobileHamburgerBlock.value?.config?.drawer_title || page.props.appName)
const mobileTopLayout = computed(() => String(mobileHeaderConfig.value?.layout || 'compact'))
const isCenteredMobileTop = computed(() => mobileTopLayout.value === 'centered')
const mobileTopSideClass = computed(() => isCenteredMobileTop.value ? 'relative z-10' : '')

const activeUserMenu = ref<'main' | 'mobile_top' | 'mobile_bottom' | null>(null)
const mobileMenuOpen = ref(false)
const lastScrollY = ref(0)
const scrollY = ref(0)
const scrollDirection = ref<'up' | 'down'>('down')
const scrollProgress = ref(0)

const logout = () => router.post(route('logout'))

const globalMenus = computed(() => page.props.globalMenus as Array<any> || [])
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const kbEnabled = computed(() => Boolean(page.props.kbEnabled))
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const hasPremiumAccess = computed(() => {
    // Without billing (Regular licence, or subscriptions switched off) nobody is premium.
    // is_pro already carries that gate from the server, but the subscription_status fallback
    // below did not — so a row still marked 'active' from when subscriptions were on kept
    // granting premium-gated header CTAs and menu items.
    if (! isProAvailable.value) {
        return false
    }

    if (Boolean(user.value?.is_pro)) {
        return true
    }

    const status = String(user.value?.subscription_status || '').trim().toLowerCase()

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
const menuItemDescription = (item: any) => String(item.description || '').trim()
const menuItemIsMega = (item: any) => Boolean(item?.mega_menu)
const submenuPanelExtraClass = (item: any) => menuItemIsMega(item) ? 'header-mega-panel' : ''
// Mega-menu: each direct child becomes a column heading, capped at 4 columns.
const MEGA_MAX_COLUMNS = 4
const megaColumns = (slug: string, parentId: string | number) => submenuItems(slug, parentId).slice(0, MEGA_MAX_COLUMNS)

// The centered desktop menu, split into the links that fit on the row and the ones that do
// not. See desktopMenuIsCompact for why the split exists.
const MORE_MENU_KEY = '__header_more__'
const mainRowEl = ref<HTMLElement | null>(null)
const centeredNavBlock = computed<any>(() => mainCenterBlocks.value.find((block: any) => block.type === 'navigation') ?? null)
const centeredNavItems = computed(() => centeredNavBlock.value ? topMenuItems(centeredNavBlock.value.config.menu_slug) : [])
const centeredNavInlineItems = computed(() => centeredNavItems.value.slice(0, desktopNavVisibleCount.value))
const centeredNavOverflowItems = computed(() => centeredNavItems.value.slice(desktopNavVisibleCount.value))

// Natural width of each top-level link, keyed by menu item and kept once read. A link that
// has been moved into the "More" panel has no width left to measure, and the next
// measurement still has to plan the row around the width it would take if it came back.
const desktopNavItemWidths = new Map<string, number>()
const desktopNavMoreWidth = ref(0)
let desktopNavRemeasureQueued = false
let desktopMenuResizeObserver: ResizeObserver | null = null

const measureDesktopMenuFit = () => {
    if (typeof window === 'undefined') return

    const row = mainRowEl.value
    if (! row) return

    const reset = () => {
        desktopMenuIsCompact.value = false
        desktopNavVisibleCount.value = Number.POSITIVE_INFINITY
    }

    // Under md the desktop nav is display:none and every width here reads 0; the stacked
    // layouts give the menu a row to itself, where nothing is competing for the space.
    if (! window.matchMedia('(min-width: 768px)').matches || isStackedCenteredMainHeader.value) {
        reset()

        return
    }

    const [logoColumn, menuColumn, actionColumn] = Array.from(row.children) as HTMLElement[]
    const nav = menuColumn?.querySelector('nav')
    if (! nav || ! logoColumn || ! actionColumn || ! centeredNavItems.value.length) {
        reset()

        return
    }

    nav.querySelectorAll<HTMLElement>('[data-menu-item]').forEach((el) => {
        if (el.dataset.menuItem) {
            desktopNavItemWidths.set(el.dataset.menuItem, el.getBoundingClientRect().width)
        }
    })

    const moreButton = nav.querySelector<HTMLElement>('[data-menu-more]')
    if (moreButton) {
        desktopNavMoreWidth.value = moreButton.getBoundingClientRect().width
    }

    const widths = centeredNavItems.value.map((item: any) => desktopNavItemWidths.get(String(menuItemId(item))) ?? 0)

    // A link with nothing recorded — the menu was edited, the language changed, or the whole
    // header is hidden at the moment of measuring. Put every link back on the row and read it
    // again next tick.
    //
    // The guard is cleared on the way OUT of a successful measurement, not on the way in to
    // the retry: a retry that clears it first and then measures zero again re-arms itself, and
    // that is an unbroken chain of nextTicks that never yields — a hidden header froze the tab.
    // Left set, a second failure simply stops, with every link inline, which is the right
    // answer for a menu whose width cannot be known.
    if (widths.some((width: number) => width === 0)) {
        desktopMenuIsCompact.value = false
        desktopNavVisibleCount.value = Number.POSITIVE_INFINITY

        if (! desktopNavRemeasureQueued) {
            desktopNavRemeasureQueued = true
            nextTick(() => measureDesktopMenuFit())
        }

        return
    }

    desktopNavRemeasureQueued = false

    const rowStyle = getComputedStyle(row)
    const rowGap = parseFloat(rowStyle.columnGap) || 0
    const navGap = parseFloat(getComputedStyle(nav).columnGap) || 0
    const rowInner = row.clientWidth - (parseFloat(rowStyle.paddingLeft) || 0) - (parseFloat(rowStyle.paddingRight) || 0)

    // Both side columns are shrink-0, so what they measure is what they insist on, on
    // whichever line they have landed on — including the second line this exists to undo.
    // That keeps the two inputs below independent of the state being decided, so a
    // measurement never argues with the one before it.
    const spare = rowInner - logoColumn.getBoundingClientRect().width - actionColumn.getBoundingClientRect().width - (rowGap * 2)
    const naturalNavWidth = widths.reduce((total: number, width: number, index: number) => total + width + (index ? navGap : 0), 0)

    if (naturalNavWidth <= spare) {
        reset()

        return
    }

    // Room for every link is gone, so the "More" button is certain to be there. Charge for
    // it before fitting anything, instead of filling space it is about to take back. The
    // fallback covers the first pass, when it has not been rendered to measure yet.
    const budget = spare - (desktopNavMoreWidth.value || 92) - navGap
    let used = 0
    let visible = 0

    for (const width of widths) {
        const next = used + (visible ? navGap : 0) + width
        if (next > budget) break
        used = next
        visible++
    }

    desktopMenuIsCompact.value = true
    desktopNavVisibleCount.value = visible
}

// Keep a mega-menu panel fully on screen: center it under its trigger, then
// clamp horizontally to the viewport so the rightmost item never clips.
const positionMegaPanel = (event: MouseEvent) => {
    const trigger = event.currentTarget as HTMLElement | null
    const panel = trigger?.querySelector('.header-mega-panel') as HTMLElement | null
    if (!trigger || !panel) return

    const margin = 8
    const triggerRect = trigger.getBoundingClientRect()
    const panelWidth = panel.offsetWidth

    let left = triggerRect.left + (triggerRect.width / 2) - (panelWidth / 2)
    left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin))

    panel.style.left = `${Math.round(left - triggerRect.left)}px`
    panel.style.right = 'auto'
}

// Track which top-level item's dropdown is currently open (hovered) so the
// parent link keeps its active style while the submenu / mega panel is showing.
const openDropdownKey = ref<string | number | null>(null)
const onMenuItemEnter = (item: any, event: MouseEvent) => {
    openDropdownKey.value = menuItemId(item)
    positionMegaPanel(event)
}
const isDropdownOpen = (slug: string, item: any) =>
    openDropdownKey.value !== null
    && String(openDropdownKey.value) === String(menuItemId(item))
    && hasSubmenuItems(slug, menuItemId(item))

const isActive = (url: string) => {
    if (!url) return false

    let parsed: URL
    try {
        parsed = new URL(url, window.location.origin)
    } catch {
        return false
    }

    // Home / site root is never highlighted as active.
    if (parsed.pathname === '/' && !parsed.hash) return false

    // Section-anchor links (e.g. /#features) should only be active when the
    // hash actually matches the current one — not on every page that shares the path.
    if (parsed.hash) {
        return window.location.pathname === parsed.pathname && window.location.hash === parsed.hash
    }

    return window.location.pathname === parsed.pathname
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
    return mobile ? 'mx-auto w-full px-4' : 'mx-auto w-full px-4 sm:px-6'
}
const containerStyle = (config: any) => {
    const cw = config?.container_width ?? '1280px'
    if (cw === 'full') return {}
    return { maxWidth: cw === 'boxed' ? '1080px' : cw === 'stretched' ? '1536px' : cw }
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

    if (['none', 'small', 'medium', 'large'].includes(style)) {
        return style
    }

    if (config?.shadow) return 'medium'

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
    const edgeClass = edge === 'top' ? 'border-t' : 'border-b'

    if (forceLight) {
        if (config?.show_border === false) return 'border-none'
        return `${edgeClass} border-white/18`
    }

    if (config?.show_border !== false) {
        return `${edgeClass} border-gray-200 dark:border-white/10`
    }

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
    measureHeaderOffsets()

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

// The bar can be taller than its configured height once its blocks wrap on a narrow
// screen, so publish the measured height. Layouts offset their sticky content by this
// var and would slide under a wrapped bar if it stayed pinned to the configured value.
const mobileHeaderEl = ref<HTMLElement | null>(null)
let mobileHeaderResizeObserver: ResizeObserver | null = null
const publishMobileHeaderHeight = () => {
    if (typeof window === 'undefined') return

    const config = mobileHeaderConfig.value
    const configured = config?.enabled ? Number(config.height || 64) : 0
    const measured = config?.enabled ? (mobileHeaderEl.value?.offsetHeight ?? 0) : 0
    const height = Math.max(configured, measured)

    document.documentElement.style.setProperty('--header-height', `${height}px`)
    document.documentElement.style.setProperty('--mobile-top-height', `${height}px`)
}

watch(
    () => mobileHeaderConfig.value,
    () => {
        publishMobileHeaderHeight()
        void nextTick(publishMobileHeaderHeight)
    },
    { immediate: true, deep: true }
)

watch(mobileHeaderEl, (el) => {
    mobileHeaderResizeObserver?.disconnect()
    mobileHeaderResizeObserver = null

    if (!el || typeof ResizeObserver === 'undefined') {
        publishMobileHeaderHeight()
        return
    }

    mobileHeaderResizeObserver = new ResizeObserver(() => publishMobileHeaderHeight())
    mobileHeaderResizeObserver.observe(el)
    publishMobileHeaderHeight()
})

onUnmounted(() => {
    removeHeroOverlayListeners()
    mobileHeaderResizeObserver?.disconnect()
    mobileHeaderResizeObserver = null
})
const bottomSectionVisibilityClass = (config: any) => isBottomHeaderVisible(config)
    ? 'translate-y-0 opacity-100'
    : 'translate-y-full opacity-0 pointer-events-none'

// Top-sticking sections sit below the pinned announcement/coupon banners (whose
// combined height AnnouncementManager publishes as --top-banners-height), so the
// header offsets down instead of overlapping them. The bottom nav is unaffected.
const stickyTop = (section: 'main' | 'mobile' | 'mobile_bottom') =>
    section === 'mobile_bottom' ? '0px' : 'var(--top-banners-height, 0px)'

const sectionStyle = (config: any, section: 'main' | 'mobile' | 'mobile_bottom', defaultHeight: number): CSSProperties => {
    const resolvedHeight = Number(config?.height ?? defaultHeight)

    if (section === 'main' && ['centered', 'landing'].includes(String(config?.layout || 'classic'))) {
        return {
            minHeight: `${Math.max(resolvedHeight, 148)}px`,
            top: stickyTop(section),
        }
    }

    // The top bars carry a min-height, not a fixed height: on a narrow viewport their
    // blocks wrap onto a second row, and the bar has to grow with them instead of letting
    // them spill over the page. Identical rendering whenever a single row fits.
    if (section === 'main' || section === 'mobile') {
        return {
            minHeight: `${resolvedHeight}px`,
            top: stickyTop(section),
        }
    }

    return {
        height: `${resolvedHeight}px`,
        top: stickyTop(section),
    }
}


const mobileIconButtonClass = 'flex h-10 w-10 items-center justify-center rounded-xl bg-transparent transition-colors hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
const mobileBottomItemClass = 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
const configString = (config: Record<string, unknown> | undefined, key: string, fallback = '') => typeof config?.[key] === 'string' ? (config[key] as string) : fallback
const sharedButtonStyleValue = (value: unknown) => {
    const style = String(value || 'primary')
    return ['primary', 'dark', 'danger', 'success', 'warning', 'purple', 'gradient_sunset', 'gradient_ocean', 'gradient_royal', 'outline', 'ghost', 'bg_light', 'custom', 'filled', 'green', 'gradient'].includes(style) ? style : 'primary'
}
const ctaStyleValue = (block: any) => {
    return sharedButtonStyleValue(block.config?.style)
}
// Important, or the shape is silently ignored by whichever button is set to `primary`
// or `filled`. buttonVariantClass() gives those `btn-primary`, which sets its own
// min-height (2.5rem) and padding — so every other variant has to declare a matching
// height explicitly, or the primary button ends up taller than its neighbours.
// border-radius in app.css — and that rule is UNLAYERED while Tailwind's utilities live
// in a cascade layer, so it wins regardless of source order and despite the identical
// specificity. Nothing short of !important reaches it.
//
// So "pill" applied to one guest button and not the other purely because they were on
// different styles, and the same silence hit any cta_button on a primary style.
const buttonShapeClass = (shapeValue: unknown) => {
    const shape = String(shapeValue || 'rounded_xl')
    if (shape === 'sharp') return '!rounded-none'
    if (shape === 'rounded') return '!rounded-md'
    if (shape === 'pill') return '!rounded-full'
    return '!rounded-xl'
}
const ctaShapeClass = (block: any) => buttonShapeClass(block.config?.shape)
const buttonVariantClass = (styleValue: string) => [
    // Solid fills are 500->600 gradients that reverse on hover, matching the section
    // buttons. `dark` keeps its own weights — a gray-500 "dark" button would not be dark.
    styleValue === 'primary' || styleValue === 'filled' ? 'btn-primary bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-500 shadow-lg shadow-primary-600/20' : '',
    styleValue === 'dark' ? 'bg-gradient-to-r from-gray-800 to-gray-900 text-white hover:from-gray-900 hover:to-gray-800 dark:from-surface-600 dark:to-surface-700 dark:hover:from-surface-700 dark:hover:to-surface-600' : '',
    styleValue === 'danger' ? 'bg-gradient-to-r from-danger-500 to-danger-600 text-white hover:from-danger-600 hover:to-danger-500 shadow-lg shadow-danger-600/20' : '',
    styleValue === 'success' || styleValue === 'green' ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-emerald-500 shadow-lg shadow-emerald-600/20' : '',
    styleValue === 'warning' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-white hover:from-amber-600 hover:to-amber-500 shadow-lg shadow-amber-500/20' : '',
    styleValue === 'purple' ? 'bg-gradient-to-r from-violet-500 to-violet-600 text-white hover:from-violet-600 hover:to-violet-500 shadow-lg shadow-violet-600/20' : '',
    styleValue === 'gradient' || styleValue === 'gradient_sunset' ? 'bg-gradient-to-r from-orange-500 via-rose-500 to-pink-500 text-white hover:opacity-95 shadow-lg shadow-rose-500/25' : '',
    styleValue === 'gradient_ocean' ? 'bg-gradient-to-r from-cyan-500 via-sky-500 to-blue-600 text-white hover:opacity-95 shadow-lg shadow-sky-500/25' : '',
    styleValue === 'gradient_royal' ? 'bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-500 text-white hover:opacity-95 shadow-lg shadow-violet-500/25' : '',
    styleValue === 'outline' ? 'border border-primary-600 text-primary-600 transition-colors hover:bg-primary-600 hover:!text-white dark:border-primary-900/80' : '',
    styleValue === 'ghost' ? 'border border-white/5 bg-white/10 shadow-sm backdrop-blur text-primary-600 hover:bg-primary-100/30 dark:bg-surface-700 dark:border-surface-800 dark:hover:bg-primary-900/20' : '',
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
        return iconSurfaceClass(block, 'relative flex min-w-0 w-full flex-col items-center justify-center gap-1 rounded-xl px-2 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300')
    }

    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style)
    // Important for the same reason as buttonShapeClass: iconSurfaceClass() below adds
    // btn-primary on bg_style `filled`, and its unlayered border-radius would
    // otherwise square off a button the operator asked to be circular.
    const roundedClass = ['icon_only', 'circular_soft_bg', 'light_bg'].includes(displayStyle) ? '!rounded-full' : '!rounded-xl'
    const iconOnlyClass = displayStyle === 'icon_only' ? 'header-soft-icon-button--icon-only' : ''
    const sizeClass = 'h-9 w-9'
    // light_bg is the one tone that draws a raised white card rather than a wash of the
    // header behind it, so it carries the lift to match.
    const toneClass = displayStyle === 'icon_only'
        ? 'border-transparent bg-transparent shadow-none'
        : displayStyle === 'light_bg'
            ? 'border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-700/50'
            : 'border'
    const hoverClass = utilityHoverClass(displayStyle)

    const isMobileBlock = block?.id && String(block.id).startsWith('simple_mobile_')
    const hasMobileColor = isMobileBlock && mobileHeaderConfig.value?.text_color
    const colorClass = hasMobileColor ? 'text-current' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'

    return iconSurfaceClass(block, `header-soft-icon-button ${iconOnlyClass} relative flex ${sizeClass} items-center justify-center ${roundedClass} ${toneClass} ${hoverClass} ${colorClass} transition-all duration-200`)
}
const notificationButtonClass = (block: any, bottom = false) => headerUtilityClass(block, bottom)
const socialIconButtonClass = (block: any) => {
    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style)
    const sizeOverride = '!h-9 !w-9 !min-w-9'
    // The blanket !shadow-none flattens whatever tone headerUtilityClass picked, which
    // would leave a light_bg social icon as the one raised control in the row without its
    // lift. Every other tone is flat anyway, so scoping it costs nothing.
    const shadowOverride = displayStyle === 'light_bg' ? '' : '!shadow-none'
    return `${headerUtilityClass(block, false).join(' ')} ${sizeOverride} !justify-center !gap-0 !p-0 ${shadowOverride}`
}
const socialButtonWithTextClass = (block: any) => {
    const displayStyle = headerUtilityDisplayStyle(block.config?.display_style)
    const base = 'header-soft-icon-button flex items-center gap-2 px-3 py-1.5 text-sm font-semibold transition-all duration-200'

    // Important — see headerUtilityClass above; this one also ends in iconSurfaceClass.
    let shapeClass = '!rounded-xl'
    if (displayStyle === 'circular_soft_bg' || displayStyle === 'icon_only') {
        shapeClass = '!rounded-full'
    }

    let toneClass = 'border'
    if (displayStyle === 'icon_only') {
        toneClass = 'border-transparent bg-transparent shadow-none'
    } else if (displayStyle === 'light_bg') {
        toneClass = 'border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-700/50'
    }

    const isMobileBlock = block?.id && String(block.id).startsWith('simple_mobile_')
    const hasMobileColor = isMobileBlock && mobileHeaderConfig.value?.text_color
    const colorClass = hasMobileColor ? 'text-current' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'

    return iconSurfaceClass(block, `${base} ${shapeClass} ${toneClass} ${utilityHoverClass(displayStyle)} ${colorClass}`)
}
// The neutral border every bordered header control shares when the operator has not set
// a colour of their own. Named rather than repeated, because Tailwind v4 made the default
// border colour `currentColor`: any control carrying a bare `border` class and no explicit
// colour draws its border in the TEXT colour. That is what set the search box apart —
// a hard gray-700 outline standing next to everything else's 8% black.
const softControlBorder = (dark: boolean) => dark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.08)'
const softControlSurface = (dark: boolean) => dark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)'

// Desktop only, and deliberately: a tooltip needs a pointer that can rest on something,
// which a touch header does not have. Defaults on — most of these controls are a bare
// glyph, and the label is otherwise only in the aria-label.
const showIconTooltip = computed(() => (headerConfig.value as any)?.show_icon_tooltip !== false)

// Empty content makes Tooltip render nothing, so a block with no label degrades quietly
// rather than showing an empty bubble.
const iconTooltipText = (block: any, fallback: string) => {
    if (! showIconTooltip.value) return ''

    return String(block?.config?.tooltip_text || fallback || '').trim()
}

// The motion half of a utility control's hover, chosen per tone — the colour half is the
// :hover rules in the style block below, which every tone already shares.
//
// One effect for all three would read wrong, because they are not the same object at rest:
//   icon_only          bare glyph, no surface at all → nothing to raise or deepen, so it
//                      grows instead, alongside the background wash that fades in.
//   *_soft_bg          a tinted patch of the header → a small grow keeps it feeling part
//                      of the bar. No shadow: socialIconButtonClass forces !shadow-none on
//                      these tones, so one would be silently dropped on social icons.
//   light_bg           a raised white card → it lifts and its shadow deepens, the one
//                      tone where "picked up" is the honest metaphor.
//
// motion-safe so the transform is dropped entirely for prefers-reduced-motion; the colour
// and shadow changes still land there. The controls already carry transition-all
// duration-200, so nothing extra is needed to animate these.
const utilityHoverClass = (displayStyle: string) => {
    if (displayStyle === 'icon_only') return 'motion-safe:hover:scale-110'
    if (displayStyle === 'light_bg') return 'hover:shadow-md motion-safe:hover:-translate-y-0.5'

    return 'motion-safe:hover:scale-105'
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
        style['--header-soft-icon-bg'] = 'rgb(255 255 255 / 0.08)'
        style['--header-soft-icon-border'] = 'rgb(255 255 255 / 0.18)'

        return style
    }

    const textColor = isDark.value ? '' : (configString(block.config, 'icon_color') || configString(block.config, 'text_color'))
    const hoverColor = configString(block.config, 'hover_color') || (isDark.value ? '' : textColor)

    style['--header-soft-icon-hover-bg'] = 'var(--color-gray-100)'
    style['--header-soft-icon-hover-bg-dark'] = 'rgb(255 255 255 / 0.08)'
    style['--header-soft-icon-hover-border'] = 'rgb(148 163 184 / 0.18)'

    if (isDark.value && hoverColor) {
        style['--header-soft-icon-hover-color'] = hoverColor
        style['--header-soft-icon-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 14%, transparent)`
        style['--header-soft-icon-hover-border'] = `color-mix(in srgb, ${hoverColor} 20%, transparent)`
    }

    if (textColor) {
        style.color = textColor
        style['--header-soft-icon-color'] = textColor
        if (!['icon_only', 'light_bg'].includes(displayStyle)) {
            const bgVal = `color-mix(in srgb, ${textColor} 10%, transparent)`
            const borderVal = `color-mix(in srgb, ${textColor} 16%, transparent)`
            style.background = bgVal
            style.borderColor = borderVal
            style['--header-soft-icon-bg'] = bgVal
            style['--header-soft-icon-border'] = borderVal
        } else if (displayStyle === 'light_bg') {
            const bgVal = isDark.value ? 'rgba(30, 30, 46, 0.5)' : '#ffffff'
            const borderVal = isDark.value ? '#1e1e2e' : 'var(--color-gray-200)'
            style.background = bgVal
            style.borderColor = borderVal
            style['--header-soft-icon-bg'] = bgVal
            style['--header-soft-icon-border'] = borderVal
        }
    } else {
        if (!['icon_only', 'light_bg'].includes(displayStyle)) {
            const bgVal = softControlSurface(isDark.value)
            const borderVal = softControlBorder(isDark.value)
            style.background = bgVal
            style.borderColor = borderVal
            style['--header-soft-icon-bg'] = bgVal
            style['--header-soft-icon-border'] = borderVal
        } else if (displayStyle === 'light_bg') {
            const bgVal = isDark.value ? 'rgba(30, 30, 46, 0.5)' : '#ffffff'
            const borderVal = isDark.value ? '#1e1e2e' : 'var(--color-gray-200)'
            style.background = bgVal
            style.borderColor = borderVal
            style['--header-soft-icon-bg'] = bgVal
            style['--header-soft-icon-border'] = borderVal
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
    const shapeClass = ['icon_only', 'circular_soft_bg', 'light_bg'].includes(displayStyle) ? '!rounded-full' : '!rounded-xl'
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
        'group hidden h-10 items-center justify-between gap-3 border px-3 text-sm transition-all md:flex',
        displayStyle === 'search_light'
            ? 'min-w-[180px] rounded-xl bg-white dark:bg-surface-800'
            : 'min-w-[180px] rounded-full bg-transparent',
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
            : 'transparent'
        return style
    }

    const textColor = isDark.value ? '' : configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color') || (isDark.value ? '' : textColor)

    if (isDark.value) {
        style.borderColor = softControlBorder(true)
        style.background = commandPaletteDisplayStyle(block) === 'search_light'
            ? softControlSurface(true)
            : 'transparent'

        if (hoverColor) {
            style['--header-command-hover-bg'] = `color-mix(in srgb, ${hoverColor} 10%, transparent)`
            style['--header-command-hover-border'] = `color-mix(in srgb, ${hoverColor} 24%, transparent)`
        }
        return style
    }

    if (textColor) {
        style.color = textColor
        style.borderColor = `color-mix(in srgb, ${textColor} 16%, transparent)`
        if (commandPaletteDisplayStyle(block) === 'search_transparent') {
            style.background = `color-mix(in srgb, ${textColor} 0%, transparent)`
        }
    } else {
        // The gap this whole helper had: light mode with no operator colour set nothing at
        // all, so the bare `border` class fell through to currentColor. Dark mode above
        // always set it, which is why only the light header showed the mismatch.
        style.borderColor = softControlBorder(false)
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
    // Kept in step with mainNavClass('center'): both land on the same <nav>, and two
    // disagreeing justify- utilities are settled by Tailwind's output order, not by the
    // order they are listed in the class binding.
    return desktopMenuIsCompact.value ? 'justify-start' : 'justify-center'
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

    const transform = String(block.config?.menu_transform || 'default').trim()
    if (transform && transform !== 'default') {
        style.textTransform = transform as any
    }

    if (isOverlayLightBlock(block)) {
        style.color = '#ffffff'
        style['--header-menu-text-color'] = '#ffffff'
        style['--header-menu-hover-color'] = '#ffffff'
        style['--header-menu-hover-bg'] = 'rgb(255 255 255 / 0.12)'
        style['--header-menu-hover-bg-dark'] = 'rgb(255 255 255 / 0.16)'
        style['--header-menu-hover-shadow'] = 'none'
        return style
    }

    const textColor = isDark.value ? '' : configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color') || (isDark.value ? '' : textColor)

    if (isDark.value) {
        if (hoverColor) {
            style['--header-menu-hover-color'] = hoverColor
            style['--header-menu-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 18%, transparent)`
        }
        return style
    }
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
    if (isDark.value) {
        return style
    }
    const bgColor = configString(block.config, 'submenu_bg_color')
    const textColor = configString(block.config, 'submenu_text_color')
    if (bgColor) style.backgroundColor = bgColor
    style.color = textColor || 'rgb(31 41 55)'
    style['--header-submenu-text-color'] = textColor || 'rgb(31 41 55)'
    return style
}
const headerActionStyle = (block: any): HeaderStyle => {
    const style: HeaderStyle = {}
    const textColor = isDark.value ? '' : configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color') || (isDark.value ? '' : textColor)

    if (isDark.value) {
        if (hoverColor) {
            style['--header-action-hover-color'] = hoverColor
            style['--header-control-hover-color'] = hoverColor
            style['--header-soft-icon-hover-color'] = hoverColor
            style['--header-action-hover-bg-dark'] = `color-mix(in srgb, ${hoverColor} 14%, transparent)`
            style['--header-control-hover-bg-dark'] = style['--header-action-hover-bg-dark']
            style['--header-soft-icon-hover-bg-dark'] = style['--header-action-hover-bg-dark']
        }
        return style
    }

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
    const iconColor = isDark.value ? '' : configString(block.config, 'icon_color')
    const bgColor = isDark.value ? '' : configString(block.config, 'bg_color')
    const textColor = isDark.value ? '' : configString(block.config, 'text_color')
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
    if (isLightBgUtilityIcon && !isDark.value) {
        style.color = 'rgb(31 41 55)'
    }
    return style
}
const iconSurfaceClass = (block: any, baseClass: string) => [
    baseClass,
    block.config?.bg_style === 'transparent' ? 'bg-transparent dark:bg-transparent' : '',
    block.config?.bg_style === 'filled' ? 'btn-primary text-white hover:text-white dark:text-white' : '',
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
const authAvatarShapeClass = (block: any) => String(block.config?.avatar_shape || 'rounded') === 'circle' ? 'rounded-full' : 'rounded-xl'
const userMenuTriggerClass = (block: any) => {
    if (authDisplayMode(block) === 'avatar_only') {
        return `header-action-link flex h-9 w-9 items-center justify-center p-0 ${authAvatarShapeClass(block)} transition-colors`
    }

    return 'header-action-link flex items-center gap-2 rounded-xl px-2 py-1.5 transition-colors'
}
const userMenuAvatarClass = (block: any) => {
    const shapeClass = authDisplayMode(block) === 'avatar_only' ? authAvatarShapeClass(block) : 'rounded-xl'
    const sizeClass = authDisplayMode(block) === 'avatar_only' ? 'h-full w-full' : 'h-8 w-8'
    const bgClass = (userMenuAvatarUrl.value && !avatarLoadError.value) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
    return `flex ${sizeClass} items-center justify-center ${bgClass} text-sm font-bold text-white shrink-0 overflow-hidden ${shapeClass}`
}
const showUserMenuArrow = (block: any) => block.config?.show_arrow_icon !== false && authDisplayMode(block) !== 'avatar_only'

// Only the avatar-only styles (rounded and circle alike) get one: those are the two that
// drop the username, leaving a bare initial or photo with no indication of whose account
// it is. The avatar_name styles already print it, so a bubble repeating it is noise —
// empty content makes Tooltip render nothing at all.
const userMenuTooltipText = (block: any) => authDisplayMode(block) === 'avatar_only'
    ? iconTooltipText(block, String(user.value?.name || t('Account')))
    : ''

// The icon-shaped search triggers only. The searchbox styles (search_transparent,
// search_light) render the word "Search" inside the control, so a bubble saying it again
// is noise — same rule the avatar and social triggers follow. Listed explicitly rather
// than inverting showCommandPaletteText(), so a style added later has to opt in.
const searchTooltipText = (block: any) => ['icon_only', 'rounded_soft_bg', 'circular_soft_bg'].includes(commandPaletteDisplayStyle(block))
    ? iconTooltipText(block, t('Search'))
    : ''

// Only when the trigger is a bare icon. With social_button_text set, the button already
// carries that label beside the glyph and a bubble repeating it is noise — the same rule
// the avatar styles follow.
const socialTooltipText = (block: any) => String(block?.config?.social_button_text || '').trim() === ''
    ? iconTooltipText(block, t('Follow Us'))
    : ''
const authButtonStyle = (value: unknown) => {
    return sharedButtonStyleValue(value)
}
const authButtonShape = (block: any) => buttonShapeClass(block.config?.guest_button_shape)
const authActionButtonStyle = (styleValue: string): CSSProperties => {
    if (['primary', 'filled', 'dark', 'danger', 'success', 'green', 'warning', 'purple', 'gradient', 'gradient_sunset', 'gradient_ocean', 'gradient_royal'].includes(styleValue)) {
        return { color: '#ffffff' }
    }

    if (styleValue === 'bg_light') {
        return { color: isDark.value ? 'var(--color-gray-300)' : 'var(--color-gray-600)' }
    }

    if (styleValue === 'outline' || styleValue === 'ghost') {
        return { color: 'var(--color-primary-600)' }
    }

    return {}
}
const authActionButtonClass = (style: string, shape: string, mobile = false) => [
    mobile ? `flex h-10 w-10 items-center justify-center ${shape} transition-all shrink-0` : `inline-flex min-h-10 items-center justify-center gap-2 ${shape} px-4 py-2 text-sm font-semibold transition-all shrink-0`,
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
    return mediaUrl(String(path || '').trim())
}
const userMenuAvatarUrl = computed(() => {
    return resolveUserAvatarUrl(user.value?.avatar)
})
const avatarLoadError = ref(false)
const handleAvatarError = () => {
    avatarLoadError.value = true
}
watch(userMenuAvatarUrl, () => {
    avatarLoadError.value = false
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

    if (kbEnabled.value) {
        links.push({ href: route('addon.kb.public.home'), label: t('Help Center'), iconClass: 'ti ti-help-circle', tone: 'default' })
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

const socialProfiles = computed(() => {
    const follow = page.props.socialFollow as { profiles?: Array<{ platform: string, label: string, url: string }> } | undefined
    return follow?.profiles ?? []
})

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

const activeSocialMenu = ref(false)
const toggleSocialMenu = () => {
    activeSocialMenu.value = !activeSocialMenu.value
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
    measureHeaderOffsets()
}

const userFirstName = computed(() => {
    const fullName = user.value?.name?.trim() ?? ''

    if (!fullName) {
        return ''
    }

    return fullName.split(/\s+/)[0] ?? ''
})

const toggleUserMenu = (menu: 'main' | 'mobile_top' | 'mobile_bottom') => {
    activeUserMenu.value = activeUserMenu.value === menu ? null : menu
}
const isUserMenuOpen = (menu: 'main' | 'mobile_top' | 'mobile_bottom') => activeUserMenu.value === menu
const close = () => {
    activeUserMenu.value = null
    activeSocialMenu.value = false
}
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

// Anything that changes the page's height moves the anchor without a scroll or a resize
// event: dismissing the demo banner, a header_banner ad finishing its load, an
// announcement being closed. An out-of-flow header would keep the stale offset and leave
// a gap. Watching the body covers all of them without naming any.
let bodyResizeObserver: ResizeObserver | null = null

onMounted(() => {
    document.addEventListener('click', close)
    document.addEventListener('keydown', closeOnEscape)
    document.addEventListener('keydown', handleKeydown)
    updateScrollState()
    window.addEventListener('scroll', updateScrollState, { passive: true })
    window.addEventListener('resize', measureHeaderOffsets)
    window.addEventListener('announcement:change', measureHeaderOffsets)
    measureHeaderOffsets()
    void bindHeroOverlayState()

    if (typeof ResizeObserver !== 'undefined') {
        bodyResizeObserver = new ResizeObserver(() => measureHeaderOffsets())
        bodyResizeObserver.observe(document.body)
    }

    measureDesktopMenuFit()

    // The row, not the window: the menu also runs out of room when the row itself narrows —
    // a container_width change, a scrollbar appearing, a zoom step — with the window fixed.
    // The side columns are watched with it, because they can take more of the row without
    // the row changing size at all: signing in swaps a Sign In button for an avatar, credits
    // and a name, and the space left for the menu moves under it with nothing to notice.
    if (typeof ResizeObserver !== 'undefined' && mainRowEl.value) {
        desktopMenuResizeObserver = new ResizeObserver(() => measureDesktopMenuFit())
        desktopMenuResizeObserver.observe(mainRowEl.value)

        const columns = Array.from(mainRowEl.value.children) as HTMLElement[]
        for (const column of [columns[0], columns[columns.length - 1]]) {
            if (column) desktopMenuResizeObserver.observe(column)
        }
    }

    // Links measured against a fallback font are the wrong width once the real one lands,
    // and the difference is easily a whole link's worth across a menu.
    document.fonts?.ready?.then(() => measureDesktopMenuFit()).catch(() => {})
})

// Editing the menu, switching language or turning a header element on or off all change
// what has to fit. Widths are re-read from scratch: the old ones belong to the old labels.
watch(
    [centeredNavItems, () => activeBlocks.value.length],
    () => {
        desktopNavItemWidths.clear()
        nextTick(() => measureDesktopMenuFit())
    },
    { deep: true }
)
onUnmounted(() => {
    document.removeEventListener('click', close)
    document.removeEventListener('keydown', closeOnEscape)
    document.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('scroll', updateScrollState)
    window.removeEventListener('resize', measureHeaderOffsets)
    window.removeEventListener('announcement:change', measureHeaderOffsets)
    bodyResizeObserver?.disconnect()
    bodyResizeObserver = null
    desktopMenuResizeObserver?.disconnect()
    desktopMenuResizeObserver = null
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

    <!-- Marks the header's place in the normal flow. The transparent-on-hero header is
         positioned out of flow and cannot measure where it belongs, so this stays behind
         and reports it — whatever AppLayout stacks above (demo banner, announcements,
         a header_banner ad) is accounted for without this component knowing about any
         of them. Zero height, so it changes nothing when the header is in flow. -->
    <div ref="headerFlowAnchor" aria-hidden="true" class="h-0 w-full shrink-0"></div>

    <!-- Main Header -->
    <header :class="[
        'hidden w-full shrink-0 md:block header-section-overlay',
        sectionPositionClass(headerConfig),
        sectionTransitionClass(headerConfig),
        isTransparentMainHeaderActive ? '' : sectionShadowClass(headerConfig),
        sectionVisibilityClass(headerConfig),
        isTransparentMainHeaderActive
            ? `absolute bg-transparent shadow-none header-overlay-light ${sectionBorderClass(headerConfig, 'bottom', true)}`
            : (desktopTransparentOnHero
                ? `backdrop-blur-md ${sectionBorderClass(headerConfig)} ${(hasCustomBackground(headerConfig) && !isDark) ? '' : 'bg-white/90 dark:bg-surface-900/80'}`
                : `${sectionBorderClass(headerConfig)} ${(hasCustomBackground(headerConfig) && !isDark) ? '' : 'bg-white dark:bg-surface-900'}`),
    ]" :style="{ ...mainHeaderSectionStyle, ...mainHeaderBackgroundStyle, ...sectionAccentStyle(headerConfig) }">
        <div v-if="isStackedCenteredMainHeader" class="flex min-h-full flex-col items-center justify-center" :class="[containerClass(headerConfig), mainRowGapClass, mainRowLayoutClass]" :style="containerStyle(headerConfig)">
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
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative" @mouseenter="onMenuItemEnter(item, $event)" @mouseleave="openDropdownKey = null">
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) || isDropdownOpen(block.config.menu_slug, item) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-start-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :class="submenuPanelExtraClass(item)"
                                    :style="submenuStyle(block)"
                                >
                                    <template v-if="menuItemIsMega(item)">
                                        <div v-for="column in megaColumns(block.config.menu_slug, menuItemId(item))" :key="menuItemId(column)" class="header-mega-col">
                                            <template v-if="submenuItems(block.config.menu_slug, menuItemId(column)).length">
                                                <span class="header-mega-heading">{{ menuItemLabel(column) }}</span>
                                                <a v-for="link in submenuItems(block.config.menu_slug, menuItemId(column))" :key="menuItemId(link)" :href="menuItemHref(link)" :target="menuItemTarget(link)" :rel="menuItemRel(link)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                    <span class="flex min-w-0 flex-col">
                                                        <span class="flex min-w-0 items-center gap-2">
                                                            <i v-if="menuItemIcon(link)" :class="[menuItemIcon(link), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                            <span class="truncate">{{ menuItemLabel(link) }}</span>
                                                        </span>
                                                        <span v-if="menuItemDescription(link)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(link) }}</span>
                                                    </span>
                                                    <span v-if="menuItemBadgeText(link)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(link)}`">{{ menuItemBadgeText(link) }}</span>
                                                </a>
                                            </template>
                                            <a v-else :href="menuItemHref(column)" :target="menuItemTarget(column)" :rel="menuItemRel(column)" class="header-submenu-link flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <i v-if="menuItemIcon(column)" :class="[menuItemIcon(column), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(column) }}</span>
                                            </a>
                                        </div>
                                    </template>
                                    <template v-else>
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 flex-col">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                </span>
                                                <span v-if="menuItemDescription(child)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(child) }}</span>
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
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                    </template>
                                </div>
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
                    <Tooltip v-if="block.type === 'language_switcher'" :content="iconTooltipText(block, t('Language'))" placement="bottom" class="shrink-0">
                        <LanguageSwitcher :display="languageSwitcherDisplay(block)" :ui="{ buttonClass: languageSwitcherClass(block), buttonStyle: languageSwitcherStyle(block), iconStyle: blockVisualStyle(block) }" />
                    </Tooltip>
                    <Tooltip v-else-if="block.type === 'notification_bell'" :content="iconTooltipText(block, t('Notifications'))" placement="bottom" class="shrink-0">
                        <NotificationBell context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    </Tooltip>
                    <!-- SOCIAL ICONS DROPDOWN -->
                    <div v-else-if="block.type === 'social_icons' && socialProfiles.length" class="relative flex items-center" @click.stop>
                        <Tooltip :content="socialTooltipText(block)" placement="bottom" class="shrink-0">
                            <button
                                type="button"
                                :class="[
                                    block.config?.social_button_text?.trim()
                                        ? socialButtonWithTextClass(block)
                                        : socialIconButtonClass(block),
                                    isOverlayLightBlock(block) ? 'text-white' : ''
                                ]"
                                :style="[
                                    block.config?.social_button_text?.trim() ? softIconSurfaceStyle(block) : softIconSurfaceStyle(block),
                                    isOverlayLightBlock(block) ? { color: '#ffffff' } : { color: configString(block.config, 'text_color') || 'inherit' }
                                ]"
                                :aria-label="t('Follow Us')"
                                @click="toggleSocialMenu"
                            >
                                <i class="ti ti-thumb-up text-[18px] leading-none" aria-hidden="true" />
                                <span v-if="block.config?.social_button_text?.trim()" class="hidden sm:block text-sm !font-medium">
                                    {{ block.config.social_button_text }}
                                </span>
                            </button>
                        </Tooltip>
                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="activeSocialMenu" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-72 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl p-2.5 z-[80]">
                                <div class="grid grid-cols-2 gap-2">
                                    <a
                                        v-for="profile in socialProfiles"
                                        :key="profile.platform"
                                        :href="profile.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 transition-colors"
                                    >
                                        <i :class="[socialPlatformIconClass(profile.platform), 'text-lg leading-none shrink-0']" aria-hidden="true" />
                                        <span class="truncate">{{ profile.label || profile.platform }}</span>
                                    </a>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <Tooltip v-else-if="block.type === 'command_palette'" :content="searchTooltipText(block)" placement="bottom" class="shrink-0">
                        <button type="button" :class="commandPaletteButtonClass(block)" :style="commandPaletteButtonStyle(block)" :aria-label="t('Open command palette')" @click="openCommandPalette()">
                            <span class="inline-flex items-center gap-2 min-w-0">
                                <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" :style="blockVisualStyle(block)" aria-hidden="true" />
                                <span v-if="showCommandPaletteText(block)" :class="commandPaletteLabelClass(block)" class="truncate text-sm font-medium">{{ blockText(block, t('Search')) }}</span>
                            </span>
                            <span v-if="showCommandPaletteText(block)" :class="commandPaletteHintClass(block)" class="rounded-md border border-current/10 px-2 py-1 text-[11px] font-semibold leading-none">{{ blockHint(block, t('Ctrl + K')) }}</span>
                        </button>
                    </Tooltip>
                    <Tooltip v-else-if="block.type === 'dark_mode'" :content="iconTooltipText(block, isDark ? t('Light mode') : t('Dark mode'))" placement="bottom" class="shrink-0">
                        <button @click="toggleDark()" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)" :aria-label="isDark ? t('Light mode') : t('Dark mode')">
                            <svg v-if="isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        </button>
                    </Tooltip>
                    <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" class="text-sm font-bold transition-all whitespace-nowrap shrink-0" :style="blockVisualStyle(block)" :class="[ctaShapeClass(block), isIconOnly(block) ? 'flex h-10 w-10 items-center justify-center' : 'min-h-10 px-5 py-2', ...buttonVariantClass(ctaStyleValue(block))]">
                        <span class="inline-flex items-center gap-1.5">
                            <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), ctaIconSizeClass()]" aria-hidden="true" />
                            <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                        </span>
                    </Link>
                    <template v-else-if="block.type === 'user_menu'">
                        <div v-if="user" class="relative flex items-center" @click.stop>
                            <Tooltip :content="userMenuTooltipText(block)" placement="bottom" class="shrink-0">
                                <button @click="toggleUserMenu('main')" :class="userMenuTriggerClass(block)" :style="headerActionStyle(block)">
                                    <div :class="userMenuAvatarClass(block)">
                                        <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
                                        <span v-else>{{ userMenuInitial }}</span>
                                    </div>
                                    <span v-if="authDisplayMode(block) === 'avatar_name'" class="hidden sm:block text-sm font-semibold">{{ user.name }}</span>
                                    <svg v-if="showUserMenuArrow(block)" class="hidden h-4 w-4 text-current sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                            </Tooltip>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="isUserMenuOpen('main')" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                    <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5">
                                        <div :class="[
                                            'flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-bold text-white',
                                            (userMenuAvatarUrl && !avatarLoadError) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
                                        ]">
                                            <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
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
        <div v-else ref="mainRowEl" class="flex min-h-[inherit] flex-wrap items-center py-1.5" :class="[containerClass(headerConfig), mainRowGapClass, mainRowLayoutClass]" :style="containerStyle(headerConfig)">
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
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative" @mouseenter="onMenuItemEnter(item, $event)" @mouseleave="openDropdownKey = null">
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) || isDropdownOpen(block.config.menu_slug, item) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-start-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :class="submenuPanelExtraClass(item)"
                                    :style="submenuStyle(block)"
                                >
                                    <template v-if="menuItemIsMega(item)">
                                        <div v-for="column in megaColumns(block.config.menu_slug, menuItemId(item))" :key="menuItemId(column)" class="header-mega-col">
                                            <template v-if="submenuItems(block.config.menu_slug, menuItemId(column)).length">
                                                <span class="header-mega-heading">{{ menuItemLabel(column) }}</span>
                                                <a v-for="link in submenuItems(block.config.menu_slug, menuItemId(column))" :key="menuItemId(link)" :href="menuItemHref(link)" :target="menuItemTarget(link)" :rel="menuItemRel(link)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                    <span class="flex min-w-0 flex-col">
                                                        <span class="flex min-w-0 items-center gap-2">
                                                            <i v-if="menuItemIcon(link)" :class="[menuItemIcon(link), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                            <span class="truncate">{{ menuItemLabel(link) }}</span>
                                                        </span>
                                                        <span v-if="menuItemDescription(link)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(link) }}</span>
                                                    </span>
                                                    <span v-if="menuItemBadgeText(link)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(link)}`">{{ menuItemBadgeText(link) }}</span>
                                                </a>
                                            </template>
                                            <a v-else :href="menuItemHref(column)" :target="menuItemTarget(column)" :rel="menuItemRel(column)" class="header-submenu-link flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <i v-if="menuItemIcon(column)" :class="[menuItemIcon(column), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(column) }}</span>
                                            </a>
                                        </div>
                                    </template>
                                    <template v-else>
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 flex-col">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                </span>
                                                <span v-if="menuItemDescription(child)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(child) }}</span>
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
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                    </template>
                                </div>
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
                            <div v-for="item in centeredNavInlineItems" :key="menuItemId(item)" :data-menu-item="menuItemId(item)" class="group relative" @mouseenter="onMenuItemEnter(item, $event)" @mouseleave="openDropdownKey = null">
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) || isDropdownOpen(block.config.menu_slug, item) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-start-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :class="submenuPanelExtraClass(item)"
                                    :style="submenuStyle(block)"
                                >
                                    <template v-if="menuItemIsMega(item)">
                                        <div v-for="column in megaColumns(block.config.menu_slug, menuItemId(item))" :key="menuItemId(column)" class="header-mega-col">
                                            <template v-if="submenuItems(block.config.menu_slug, menuItemId(column)).length">
                                                <span class="header-mega-heading">{{ menuItemLabel(column) }}</span>
                                                <a v-for="link in submenuItems(block.config.menu_slug, menuItemId(column))" :key="menuItemId(link)" :href="menuItemHref(link)" :target="menuItemTarget(link)" :rel="menuItemRel(link)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                    <span class="flex min-w-0 flex-col">
                                                        <span class="flex min-w-0 items-center gap-2">
                                                            <i v-if="menuItemIcon(link)" :class="[menuItemIcon(link), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                            <span class="truncate">{{ menuItemLabel(link) }}</span>
                                                        </span>
                                                        <span v-if="menuItemDescription(link)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(link) }}</span>
                                                    </span>
                                                    <span v-if="menuItemBadgeText(link)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(link)}`">{{ menuItemBadgeText(link) }}</span>
                                                </a>
                                            </template>
                                            <a v-else :href="menuItemHref(column)" :target="menuItemTarget(column)" :rel="menuItemRel(column)" class="header-submenu-link flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <i v-if="menuItemIcon(column)" :class="[menuItemIcon(column), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(column) }}</span>
                                            </a>
                                        </div>
                                    </template>
                                    <template v-else>
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 flex-col">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                </span>
                                                <span v-if="menuItemDescription(child)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(child) }}</span>
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
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                    </template>
                                </div>
                            </div>
                            <!-- WHAT DID NOT FIT ON THE ROW -->
                            <!-- Rendered inside the nav so it is measured as part of it, and last so
                                 the links keep the order they were given. -->
                            <div
                                v-if="centeredNavOverflowItems.length"
                                data-menu-more
                                class="group relative"
                                @mouseenter="openDropdownKey = MORE_MENU_KEY"
                                @mouseleave="openDropdownKey = null"
                            >
                                <button
                                    type="button"
                                    :class="{ 'header-menu-link-active': openDropdownKey === MORE_MENU_KEY }"
                                    class="header-menu-link flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap"
                                    :style="menuStyle(block)"
                                    :aria-expanded="openDropdownKey === MORE_MENU_KEY"
                                    aria-haspopup="true"
                                >
                                    <span>{{ t('More') }}</span>
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m5 9 7 7 7-7" /></svg>
                                </button>
                                <!-- Anchored to its own trailing edge: the button sits at the end of a
                                     left-packed menu, so a panel opening the other way runs off screen. -->
                                <div
                                    class="header-submenu-panel invisible absolute inset-inline-end-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :style="submenuStyle(block)"
                                >
                                    <div v-for="item in centeredNavOverflowItems" :key="menuItemId(item)" class="header-submenu-item">
                                        <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(item) }}</span>
                                            </span>
                                            <span v-if="menuItemBadgeText(item)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                        </a>
                                        <!-- Children are listed under their parent rather than behind a
                                             second flyout. This panel is already the fallback for a header
                                             that ran out of room; a submenu opening sideways out of it is
                                             one more thing that has to be kept on screen. A mega item
                                             contributes its column headings here, not the whole grid. -->
                                        <a
                                            v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))"
                                            :key="menuItemId(child)"
                                            :href="menuItemHref(child)"
                                            :target="menuItemTarget(child)"
                                            :rel="menuItemRel(child)"
                                            class="header-submenu-link flex items-center gap-2 rounded-xl py-1.5 pe-3 ps-7 text-xs font-medium opacity-75 transition hover:bg-primary-50 hover:text-primary-600 hover:opacity-100 dark:hover:bg-primary-900/20"
                                        >
                                            <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-sm leading-none shrink-0']" aria-hidden="true" />
                                            <span class="truncate">{{ menuItemLabel(child) }}</span>
                                        </a>
                                    </div>
                                </div>
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
                            <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative" @mouseenter="onMenuItemEnter(item, $event)" @mouseleave="openDropdownKey = null">
                                <a :href="menuItemHref(item)" :target="menuItemTarget(item)" :rel="menuItemRel(item)" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) || isDropdownOpen(block.config.menu_slug, item) }" class="header-menu-link px-3.5 py-2 text-sm font-medium transition-all whitespace-nowrap" :style="menuStyle(block)">
                                    <span class="header-menu-label-wrap inline-flex items-center gap-2">
                                        <i v-if="menuItemIcon(item)" :class="[menuItemIcon(item), 'text-base leading-none']" aria-hidden="true" />
                                        <span>{{ menuItemLabel(item) }}</span>
                                        <span v-if="menuItemBadgeText(item)" class="header-menu-badge header-menu-badge--floating" :class="`header-menu-badge--${menuItemBadgeColor(item)}`">{{ menuItemBadgeText(item) }}</span>
                                    </span>
                                </a>
                                <div
                                    v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                    class="header-submenu-panel invisible absolute inset-inline-end-0 top-full z-50 mt-0 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                    :class="submenuPanelExtraClass(item)"
                                    :style="submenuStyle(block)"
                                >
                                    <template v-if="menuItemIsMega(item)">
                                        <div v-for="column in megaColumns(block.config.menu_slug, menuItemId(item))" :key="menuItemId(column)" class="header-mega-col">
                                            <template v-if="submenuItems(block.config.menu_slug, menuItemId(column)).length">
                                                <span class="header-mega-heading">{{ menuItemLabel(column) }}</span>
                                                <a v-for="link in submenuItems(block.config.menu_slug, menuItemId(column))" :key="menuItemId(link)" :href="menuItemHref(link)" :target="menuItemTarget(link)" :rel="menuItemRel(link)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                    <span class="flex min-w-0 flex-col">
                                                        <span class="flex min-w-0 items-center gap-2">
                                                            <i v-if="menuItemIcon(link)" :class="[menuItemIcon(link), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                            <span class="truncate">{{ menuItemLabel(link) }}</span>
                                                        </span>
                                                        <span v-if="menuItemDescription(link)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(link) }}</span>
                                                    </span>
                                                    <span v-if="menuItemBadgeText(link)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(link)}`">{{ menuItemBadgeText(link) }}</span>
                                                </a>
                                            </template>
                                            <a v-else :href="menuItemHref(column)" :target="menuItemTarget(column)" :rel="menuItemRel(column)" class="header-submenu-link flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <i v-if="menuItemIcon(column)" :class="[menuItemIcon(column), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                <span class="truncate">{{ menuItemLabel(column) }}</span>
                                            </a>
                                        </div>
                                    </template>
                                    <template v-else>
                                    <div v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))" :key="menuItemId(child)" class="header-submenu-item relative">
                                        <a :href="menuItemHref(child)" :target="menuItemTarget(child)" :rel="menuItemRel(child)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                            <span class="flex min-w-0 flex-col">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                </span>
                                                <span v-if="menuItemDescription(child)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(child) }}</span>
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
                                            <a v-for="grandchild in submenuItems(block.config.menu_slug, menuItemId(child))" :key="menuItemId(grandchild)" :href="menuItemHref(grandchild)" :target="menuItemTarget(grandchild)" :rel="menuItemRel(grandchild)" class="header-submenu-link flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <i v-if="menuItemIcon(grandchild)" :class="[menuItemIcon(grandchild), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                    <span class="truncate">{{ menuItemLabel(grandchild) }}</span>
                                                </span>
                                                <span v-if="menuItemBadgeText(grandchild)" class="header-menu-badge shrink-0" :class="`header-menu-badge--${menuItemBadgeColor(grandchild)}`">{{ menuItemBadgeText(grandchild) }}</span>
                                            </a>
                                        </div>
                                    </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex items-center gap-2 px-3 py-2 text-xs text-gray-400 italic">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            {{ t('Menu "') }}{{ block.config.menu_slug }}{{ t('" not found.') }}
                        </div>
                    </nav>
                    <Tooltip v-else-if="block.type === 'language_switcher'" :content="iconTooltipText(block, t('Language'))" placement="bottom" class="shrink-0">
                        <LanguageSwitcher :display="languageSwitcherDisplay(block)" :ui="{ buttonClass: languageSwitcherClass(block), buttonStyle: languageSwitcherStyle(block), iconStyle: blockVisualStyle(block) }" />
                    </Tooltip>
                    <Tooltip v-else-if="block.type === 'notification_bell'" :content="iconTooltipText(block, t('Notifications'))" placement="bottom" class="shrink-0">
                        <NotificationBell context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    </Tooltip>
                    <!-- SOCIAL ICONS DROPDOWN -->
                    <div v-else-if="block.type === 'social_icons' && socialProfiles.length" class="relative flex items-center" @click.stop>
                        <Tooltip :content="socialTooltipText(block)" placement="bottom" class="shrink-0">
                            <button
                                type="button"
                                :class="[
                                    block.config?.social_button_text?.trim()
                                        ? socialButtonWithTextClass(block)
                                        : socialIconButtonClass(block),
                                    isOverlayLightBlock(block) ? 'text-white' : ''
                                ]"
                                :style="[
                                    block.config?.social_button_text?.trim() ? softIconSurfaceStyle(block) : softIconSurfaceStyle(block),
                                    isOverlayLightBlock(block) ? { color: '#ffffff' } : { color: configString(block.config, 'text_color') || 'inherit' }
                                ]"
                                :aria-label="t('Follow Us')"
                                @click="toggleSocialMenu"
                            >
                                <i class="ti ti-thumb-up text-[18px] leading-none" aria-hidden="true" />
                                <span v-if="block.config?.social_button_text?.trim()" class="hidden sm:block text-sm font-semibold">
                                    {{ block.config.social_button_text }}
                                </span>
                            </button>
                        </Tooltip>
                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="activeSocialMenu" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-72 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl p-2.5 z-[80]">
                                <div class="grid grid-cols-2 gap-2">
                                    <a
                                        v-for="profile in socialProfiles"
                                        :key="profile.platform"
                                        :href="profile.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 transition-colors"
                                    >
                                        <i :class="[socialPlatformIconClass(profile.platform), 'text-lg leading-none shrink-0']" aria-hidden="true" />
                                        <span class="truncate">{{ profile.label || profile.platform }}</span>
                                    </a>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <Tooltip v-else-if="block.type === 'command_palette'" :content="searchTooltipText(block)" placement="bottom" class="shrink-0">
                        <button type="button" :class="commandPaletteButtonClass(block)" :style="commandPaletteButtonStyle(block)" :aria-label="t('Open command palette')" @click="openCommandPalette()">
                            <span class="inline-flex items-center gap-2 min-w-0">
                                <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" :style="blockVisualStyle(block)" aria-hidden="true" />
                                <span v-if="showCommandPaletteText(block)" :class="commandPaletteLabelClass(block)" class="truncate text-sm font-medium">{{ blockText(block, t('Search')) }}</span>
                            </span>
                            <span v-if="showCommandPaletteText(block)" :class="commandPaletteHintClass(block)" class="rounded-md border border-current/10 px-2 py-1 text-[11px] font-semibold leading-none">{{ blockHint(block, t('Ctrl + K')) }}</span>
                        </button>
                    </Tooltip>

                    <Tooltip v-else-if="block.type === 'dark_mode'" :content="iconTooltipText(block, isDark ? t('Light mode') : t('Dark mode'))" placement="bottom" class="shrink-0">
                        <button @click="toggleDark()" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)" :aria-label="isDark ? t('Light mode') : t('Dark mode')">
                            <svg v-if="isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        </button>
                    </Tooltip>

                    <!-- CTA BUTTON -->
                    <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" class="text-sm font-bold transition-all whitespace-nowrap shrink-0" :style="blockVisualStyle(block)" :class="[
                        ctaShapeClass(block),
                        isIconOnly(block) ? 'flex h-10 w-10 items-center justify-center' : 'min-h-10 px-5 py-2',
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
                            <Tooltip :content="userMenuTooltipText(block)" placement="bottom" class="shrink-0">
                                <button @click="toggleUserMenu('main')" :class="userMenuTriggerClass(block)" :style="headerActionStyle(block)">
                                    <div :class="userMenuAvatarClass(block)">
                                        <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
                                        <span v-else>{{ userMenuInitial }}</span>
                                    </div>
                                    <span v-if="authDisplayMode(block) === 'avatar_name'" class="hidden sm:block text-sm font-semibold">{{ user.name }}</span>
                                    <svg v-if="showUserMenuArrow(block)" class="hidden h-4 w-4 text-current sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                            </Tooltip>
                            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                                <div v-if="isUserMenuOpen('main')" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                    <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5">
                                        <div :class="[
                                            'flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-bold text-white',
                                            (userMenuAvatarUrl && !avatarLoadError) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
                                        ]">
                                            <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
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

                <!-- Teleport target for the AI Assistant header button (Widget Position =
                     "header-button"). Placed AFTER the configurable blocks so the assistant
                     always sits right-most in the header. Stays empty unless the addon is
                     active and set to that position. The colour set here is what the
                     teleported button inherits. -->
                <div id="ai-assistant-header-slot" class="ms-1 flex items-center" :style="assistantSlotStyle"></div>
            </div>
        </div>
        <div v-if="headerConfig?.progressbar" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </header>

    <!-- Mobile Header -->
    <header
        v-if="mobileHeaderConfig?.enabled"
        ref="mobileHeaderEl"
        :class="[
            'w-full md:hidden header-section-overlay',
            sectionPositionClass(mobileHeaderConfig),
            sectionTransitionClass(mobileHeaderConfig),
            isTransparentMainHeaderActive ? '' : sectionShadowClass(mobileHeaderConfig),
            sectionVisibilityClass(mobileHeaderConfig),
            isTransparentMainHeaderActive
                ? `absolute bg-transparent shadow-none header-overlay-light ${sectionBorderClass(mobileHeaderConfig, 'bottom', true)}`
                : (desktopTransparentOnHero
                    ? `backdrop-blur-md ${sectionBorderClass(mobileHeaderConfig)} ${(hasCustomBackground(mobileHeaderConfig) && !isDark) ? '' : 'bg-white/95 dark:bg-surface-900/90'}`
                    : `${sectionBorderClass(mobileHeaderConfig)} ${(hasCustomBackground(mobileHeaderConfig) && !isDark) ? '' : 'bg-white dark:bg-surface-900'}`),
        ]"
        :style="{ ...mobileHeaderSectionStyle, ...mobileHeaderBackgroundStyle, ...sectionAccentStyle(mobileHeaderConfig) }"
    >
        <div class="flex min-h-[inherit] flex-wrap items-center justify-between gap-x-3 gap-y-1.5 py-1.5" :class="[containerClass({ ...mobileHeaderConfig, container_width: '1280px' }, true), isCenteredMobileTop ? 'relative' : '']">
            <div class="flex min-w-0 flex-wrap items-center gap-2 [&>*]:shrink-0" :class="[mobileColFlexClass('left'), mobileTopSideClass]">
                <template v-for="block in mobileLeftBlocks" :key="block.id">
                    <button v-if="block.type === 'hamburger'" type="button" class="mobile-header-utility-btn" :class="iconSurfaceClass(block, [mobileIconButtonClass, mobileHeaderConfig?.text_color ? 'text-current' : 'text-gray-600 dark:text-gray-300'].join(' '))" :style="blockVisualStyle(block)" :aria-label="t('Open menu')" @click="mobileMenuOpen = !mobileMenuOpen">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'logo'" href="/" class="flex min-w-0 items-center gap-2 text-base font-bold" :class="mobileHeaderConfig?.text_color ? '' : 'text-gray-900 dark:text-white'">
                        <img v-if="getLogoImage()" :src="getLogoImage()" :alt="logoAltText" class="h-9 w-auto max-w-32 object-contain" />
                        <span v-if="!getLogoImage()" class="truncate">{{ siteName }}</span>
                    </Link>
                </template>
            </div>
            <div v-if="mobileCenterBlocks.length" class="flex min-w-0 flex-1 items-center justify-center" :class="mobileTopSideClass">
                <template v-for="block in mobileCenterBlocks" :key="block.id">
                    <Link href="/" class="flex min-w-0 items-center gap-2 text-base font-bold" :class="mobileHeaderConfig?.text_color ? '' : 'text-gray-900 dark:text-white'">
                        <!-- Last resort: if the two side columns alone fill the row, the logo
                             scales down inside object-contain instead of pushing out of its cell. -->
                        <img v-if="getLogoImage()" :src="getLogoImage()" :alt="logoAltText" class="h-9 w-auto min-w-0 max-w-32 shrink object-contain" />
                        <span v-if="!getLogoImage()" class="truncate">{{ siteName }}</span>
                    </Link>
                </template>
            </div>
            <div class="flex min-w-0 flex-wrap items-center justify-end gap-1 [&>*]:shrink-0" :class="[mobileColFlexClass('right'), mobileTopSideClass]">
                <template v-for="block in mobileRightBlocks" :key="block.id">
                    <button v-if="block.type === 'command_palette'" type="button" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)" :aria-label="t('Open search')" @click="openCommandPalette()">
                        <i :class="[blockIconClass(block, 'ti ti-search'), 'text-[18px] leading-none']" aria-hidden="true" />
                    </button>
                    <NotificationBell v-else-if="block.type === 'notification_bell'" context="user" :ui="{ triggerClass: notificationButtonClass(block).join(' '), triggerStyle: softIconSurfaceStyle(block), iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                    <button v-else-if="block.type === 'dark_mode'" type="button" :class="notificationButtonClass(block).join(' ')" :style="softIconSurfaceStyle(block)" :aria-label="isDark ? t('Light mode') : t('Dark mode')" @click="toggleDark()">
                        <svg v-if="isDark" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="blockVisualStyle(block)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>
                    <div v-else-if="block.type === 'user_menu_icon'" class="relative" @click.stop>
                        <button v-if="user" type="button" class="mobile-header-utility-btn flex items-center justify-center" :class="iconSurfaceClass(block, [mobileIconButtonClass, mobileHeaderConfig?.text_color ? 'text-current' : 'text-gray-600 dark:text-gray-300'].join(' '))" :style="blockVisualStyle(block)" :aria-label="userIconLabel" @click="toggleUserMenu('mobile_top')">
                            <span :class="[
                                'flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-bold text-white',
                                (userMenuAvatarUrl && !avatarLoadError) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
                            ]">
                                <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
                                <span v-else>{{ userMenuInitial }}</span>
                            </span>
                        </button>
                        <Link v-else :href="userIconHref" class="mobile-header-utility-btn flex items-center justify-center" :class="iconSurfaceClass(block, [mobileIconButtonClass, mobileHeaderConfig?.text_color ? 'text-current' : 'text-gray-600 dark:text-gray-300'].join(' '))" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                            <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                        </Link>

                        <!-- Dropdown Menu for Mobile Top -->
                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="user && isUserMenuOpen('mobile_top')" class="header-user-dropdown absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]">
                                <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5">
                                    <div :class="[
                                        'flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-bold text-white',
                                        (userMenuAvatarUrl && !avatarLoadError) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
                                    ]">
                                        <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
                                        <span v-else>{{ userMenuInitial }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    </div>
                                </div>
                                <Link v-for="menuLink in userMenuLinks" :key="menuLink.href" :href="menuLink.href" :class="['header-user-dropdown-link flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm transition-colors rtl:text-right', userMenuLinkToneClass(menuLink.tone)]" @click="close">
                                    <i :class="[menuLink.iconClass, 'text-base leading-none']" aria-hidden="true" />
                                    {{ menuLink.label }}
                                </Link>
                                <Link :href="route('logout')" class="header-user-dropdown-link header-user-dropdown-link--danger w-full border-t border-gray-200 text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 transition-colors dark:border-white/10 flex items-center gap-2" @click="close">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                    {{ t('Sign Out') }}
                                </Link>
                            </div>
                        </Transition>
                    </div>
                    <LanguageSwitcher v-else-if="block.type === 'language_switcher'" display="icon" :ui="{ buttonClass: notificationButtonClass(block).join(' '), buttonStyle: softIconSurfaceStyle(block), iconStyle: blockVisualStyle(block) }" />
                    <Link v-else-if="block.type === 'cta_button' && canShowCtaButton(block)" :href="String(block.config.link || '/register')" class="text-xs font-bold transition-all whitespace-nowrap shrink-0 flex items-center justify-center" :style="blockVisualStyle(block)" :class="[
                        ctaShapeClass(block),
                        isIconOnly(block) ? '!min-h-9 h-9 w-9' : '!min-h-9 px-3 h-9',
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
            <div v-if="mobileMenuOpen" class="frontend-theme-vars fixed inset-0 z-[80] md:hidden" role="dialog" aria-modal="true" :aria-label="t('Mobile menu')">
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
                    <aside class="mobile-drawer-surface absolute inset-y-0 left-0 flex w-[min(20rem,calc(100vw-2rem))] max-w-full flex-col border-r border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-surface-900 rtl:left-auto rtl:right-0 rtl:border-l rtl:border-r-0">
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
                                                            <span class="flex min-w-0 flex-col">
                                                                <span class="flex min-w-0 items-center gap-2">
                                                                    <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                                    <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                                </span>
                                                                <span v-if="menuItemDescription(child)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(child) }}</span>
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
                                                            <span class="flex min-w-0 flex-col">
                                                                <span class="flex min-w-0 items-center gap-2">
                                                                    <i v-if="menuItemIcon(child)" :class="[menuItemIcon(child), 'text-base leading-none shrink-0']" aria-hidden="true" />
                                                                    <span class="truncate">{{ menuItemLabel(child) }}</span>
                                                                </span>
                                                                <span v-if="menuItemDescription(child)" class="mt-0.5 truncate text-xs font-normal opacity-60">{{ menuItemDescription(child) }}</span>
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

                        <!-- Same profiles the footer links to (shared socialFollow prop) -->
                        <div v-if="socialProfiles.length" class="shrink-0 border-t border-gray-100 px-5 py-4 dark:border-surface-800">
                            <SocialFollow display-mode="icons" />
                        </div>
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
        :style="{ height: `${Number(mobileBottomHeaderConfig?.height ?? 60) + 2}px`, paddingBottom: '2px', bottom: '-2px', ...sectionBackgroundStyle(mobileBottomHeaderConfig) }"
        class="fixed inset-x-0 z-50 transform-gpu will-change-transform md:hidden header-section-overlay"
    >
        <div class="flex h-full items-center justify-between gap-1" :class="containerClass({ ...mobileBottomHeaderConfig, container_width: '1280px' }, true)">
            <template v-for="block in activeMobileBottomBlocks" :key="block.id">
                <!-- aria-label only when the block renders no visible text. These blocks are
                     admin-relabelled and re-pointed (a "home" block may read "AI Tools"), so a
                     fixed aria-label would override the visible text with a different accessible
                     name — WCAG 2.5.3 Label in Name. With a label shown the text is the name. -->
                <Link v-if="block.type === 'home_link'" :href="String(block.config.link || '/')" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="showBlockLabel(block) ? undefined : blockLabel(block, t('Home'))">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75v9A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-9" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Home')) }}</span>
                </Link>
                <button v-else-if="block.type === 'command_palette'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="showBlockLabel(block) ? undefined : blockLabel(block, t('Search'))" @click="openCommandPalette()">
                    <i :class="[blockIconClass(block, 'ti ti-search'), 'text-xl leading-none']" aria-hidden="true" />
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Search')) }}</span>
                </button>
                <div v-else-if="block.type === 'notification_bell' && user" class="flex min-w-0 flex-1 justify-center">
                    <NotificationBell context="user" :label="showBlockLabel(block) ? blockLabel(block, t('Notifications')) : ''" :ui="{ wrapperClass: 'flex min-w-0 w-full', triggerClass: notificationButtonClass(block, true).join(' '), triggerStyle: blockVisualStyle(block), dropdownClass: 'fixed inset-x-4 bottom-20 z-50 max-h-[70vh] overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-700 shadow-xl dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300', iconClass: blockIconClass(block), iconStyle: blockVisualStyle(block) }" />
                </div>
                <button v-else-if="block.type === 'dark_mode'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="showBlockLabel(block) ? undefined : (isDark ? t('Light mode') : t('Dark mode'))" @click="toggleDark()">
                    <i :class="[isDark ? 'ti ti-sun' : 'ti ti-moon', 'text-xl leading-none']" aria-hidden="true" />
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Theme')) }}</span>
                </button>
                <!-- `bottom` is the switcher's own bar variant: stacked icon/label and a menu
                     that opens upward, centred over the trigger. -->
                <LanguageSwitcher
                    v-else-if="block.type === 'language_switcher'"
                    display="bottom"
                    :ui="{ buttonClass: iconSurfaceClass(block, '').join(' '), buttonStyle: blockVisualStyle(block), iconStyle: blockVisualStyle(block) }"
                />
                <div v-else-if="block.type === 'user_menu_icon'" class="relative flex min-w-0 flex-1 justify-center" @click.stop>
                    <button v-if="user" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="showBlockLabel(block) ? undefined : userIconLabel" @click="toggleUserMenu('mobile_bottom')">
                        <span :class="[
                            'flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full text-[10px] font-bold text-white',
                            (userMenuAvatarUrl && !avatarLoadError) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
                        ]">
                            <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
                            <span v-else>{{ userMenuInitial }}</span>
                        </span>
                        <span v-if="showBlockLabel(block)">{{ userFirstName }}</span>
                    </button>
                    <Link v-else :href="userIconHref" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="showBlockLabel(block) ? undefined : userIconLabel">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                        <span v-if="showBlockLabel(block)">{{ String(block.config?.guest_label || blockLabel(block, t('Sign In'))) }}</span>
                    </Link>

                    <!-- Dropdown Menu for Mobile Bottom -->
                    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                        <div v-if="user && isUserMenuOpen('mobile_bottom')" class="header-user-dropdown fixed right-4 rtl:right-auto rtl:left-4 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-[80]" :style="{ bottom: `${Number(mobileBottomHeaderConfig?.height ?? 60) + 12}px` }">
                            <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5">
                                <div :class="[
                                    'flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-bold text-white',
                                    (userMenuAvatarUrl && !avatarLoadError) ? 'bg-gray-100 dark:bg-surface-800' : 'bg-gradient-to-br from-primary-500 to-accent-500'
                                ]">
                                    <img v-if="userMenuAvatarUrl && !avatarLoadError" :src="userMenuAvatarUrl" :alt="user.name || t('User avatar')" class="h-full w-full object-cover" @error="handleAvatarError" />
                                    <span v-else>{{ userMenuInitial }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                </div>
                            </div>
                            <Link v-for="menuLink in userMenuLinks" :key="menuLink.href" :href="menuLink.href" :class="['header-user-dropdown-link flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm transition-colors rtl:text-right', userMenuLinkToneClass(menuLink.tone)]" @click="close">
                                <i :class="[menuLink.iconClass, 'text-base leading-none']" aria-hidden="true" />
                                {{ menuLink.label }}
                            </Link>
                            <Link :href="route('logout')" class="header-user-dropdown-link header-user-dropdown-link--danger w-full border-t border-gray-200 text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 transition-colors dark:border-white/10 flex items-center gap-2" @click="close">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                {{ t('Sign Out') }}
                            </Link>
                        </div>
                    </Transition>
                </div>
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
/* Mobile drawer surface: a soft wash of the theme's body background over the base
   panel colour, so the drawer reads as part of the page instead of a flat white sheet.
   The drawer is teleported to <body>, so it sits outside .frontend-theme and carries
   the palette through .frontend-theme-vars — which only ships the light values. Light
   mode can therefore tint from --color-bg; dark mode layers a neutral wash instead. */
.mobile-drawer-surface {
    background-image: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-bg, #f8fafc) 18%, transparent) 0%,
        color-mix(in srgb, var(--color-bg, #f8fafc) 68%, transparent) 48%,
        color-mix(in srgb, var(--color-bg, #f8fafc) 96%, transparent) 100%
    );
}
.dark .mobile-drawer-surface {
    background-image: linear-gradient(
        180deg,
        rgb(255 255 255 / 0.05) 0%,
        rgb(255 255 255 / 0.02) 45%,
        rgb(2 6 23 / 0.45) 100%
    );
}
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
/* The three guarded rules below are the white-out that keeps header chrome legible on a
   hero. It reaches into child components whether or not it is asked to: Vue cannot put the
   scope attribute inside `:is()`, so it lands on .header-overlay-light and the rule pierces
   like `:deep()`. The notification and language panels open BELOW the header on their own
   opaque surfaces, where every one of these is wrong, and two match on class SUBSTRINGS:
   `bg-primary-500` (the notification unread dot) contains `bg-primary-50`, and the level
   bubble's `border-primary-200` drags the whole bubble into the text rule, whiting out its
   icon. Excluding those panels beats restating a dozen colors after the fact, which is what
   the account and submenu panels further down still have to do.

   The guard is wrapped in `:where()` so it contributes NO specificity. Bare `:not()` would
   add a class's worth, and the counter-rules those account/submenu panels rely on — several
   of which only just outrank these three — would start losing. */
.header-overlay-light :is(a, button, svg, i, span):not(:where(.notification-panel, .notification-panel *, .language-switcher-panel, .language-switcher-panel *)) {
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
.header-overlay-light :is([class*="border-primary-"], [class*="text-primary-"]):not(:where(.notification-panel, .notification-panel *, .language-switcher-panel, .language-switcher-panel *)) {
    color: #ffffff !important;
    border-color: rgb(255 255 255 / 0.28) !important;
}
.header-overlay-light :is([class*="bg-gray-50"], [class*="bg-primary-50"], [class*="bg-surface-800"]):not(:where(.notification-panel, .notification-panel *, .language-switcher-panel, .language-switcher-panel *)) {
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
/* !important, because the white-out above is itself !important and matches on class
   SUBSTRINGS: `hover:text-primary-600` satisfies [class*="text-primary-"] even though it
   only paints on hover. That is right for chrome sitting on the hero and wrong for a panel
   that opens BELOW the header on its own white background — it painted the social
   dropdown's links white on white. Specificity alone could not win the fight. */
.header-overlay-light .header-user-dropdown :is(a, button, svg, i, span) {
    color: inherit !important;
}
/* Same substring problem, other property: `hover:bg-primary-50` matches
   [class*="bg-primary-50"], so every row in both panels sat on a permanent 8% white wash
   instead of being transparent until hovered. The submenu panel already restates its own
   hover background; the account/social one restates it here. */
.header-overlay-light :is(.header-user-dropdown, .header-submenu-panel) :is(a, button) {
    background: transparent !important;
}
.header-overlay-light .header-user-dropdown :is(a, button):hover {
    background: rgb(249 250 251) !important;
}
.dark .header-overlay-light .header-user-dropdown :is(a, button):hover {
    background: rgb(255 255 255 / 0.05) !important;
}
.header-overlay-light .header-submenu-panel .header-submenu-link:hover {
    background: rgb(243 244 246) !important;
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
/* Mega menu: direct children are columns, grandchildren are the links. */
.header-mega-panel {
    display: flex !important;
    align-items: stretch;
    gap: 0.25rem;
    padding: 0.75rem !important;
    width: max-content;
    max-width: min(56rem, calc(100vw - 2rem));
}
.header-mega-col {
    flex: 1 1 0;
    min-width: 11rem;
}
.header-mega-col + .header-mega-col {
    border-inline-start: 1px solid var(--header-submenu-border);
    padding-inline-start: 0.25rem;
}
.header-mega-heading {
    display: block;
    padding: 0.4rem 0.75rem 0.3rem;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--header-submenu-text) !important;
    opacity: 0.7;
}
.dark .header-mega-heading {
    color: var(--header-submenu-text) !important;
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
