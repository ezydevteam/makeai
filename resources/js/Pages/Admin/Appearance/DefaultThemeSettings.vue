<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { FONT_FAMILY_SELECT_OPTIONS } from '@/config/fontFamilies'

defineOptions({ layout: AdminLayout })

type ThemeConfig = {
    name: string
    slug: string
    version?: string
}

type ThemePresetSettings = {
    theme_default_mode?: string
    theme_allow_user_toggle?: boolean
    page_loading_animation?: string
    smooth_scroll?: boolean
    show_back_to_top?: boolean
    primary_color?: string
    secondary_color?: string
    accent_color?: string
    bg_color?: string
    bg_image?: string
    heading_color?: string
    body_text_color?: string
    muted_text_color?: string
    border_color?: string
    gradient_scheme_enabled?: boolean
    gradient_palette?: string
    gradient_start_color?: string
    gradient_end_color?: string
    bg_gradient_direction?: string
    font_body?: string
    heading_font?: string
    base_font_size?: string
    heading_weight?: string
    line_height?: string
    letter_spacing?: string
    border_radius?: string
    container_width?: string
}

type HeaderDesktopSettings = {
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

type HeaderMobileTopSettings = {
    enabled?: boolean
    layout?: string
    sticky?: boolean
    show_logo?: boolean
    show_hamburger?: boolean
    show_dark_mode_toggle?: boolean
}

type HeaderMobileBottomSettings = {
    enabled?: boolean
    layout?: string
    show_home?: boolean
    show_tools?: boolean
    show_dashboard?: boolean
    show_profile?: boolean
}

type HeaderPresetSettings = {
    desktop?: HeaderDesktopSettings
    mobile_top?: HeaderMobileTopSettings
    mobile_bottom?: HeaderMobileBottomSettings
}

type FooterPresetSettings = {
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

type HomepagePresetSettings = {
    hero_variant?: string
    show_social_proof?: boolean
    show_features?: boolean
    show_tools?: boolean
    show_steps?: boolean
    show_pricing?: boolean
    show_testimonials?: boolean
    show_faq?: boolean
    show_cta?: boolean
    show_blog?: boolean
    show_newsletter?: boolean
    show_custom_html?: boolean
    show_richtext?: boolean
    show_ad_slot?: boolean
}

type CustomCodeSettings = {
    custom_css?: string
    custom_header_code?: string
    custom_footer_code?: string
}

type MenuOption = {
    id?: number
    name?: string
    slug?: string
}

const props = defineProps<{
    theme: ThemeConfig
    settings: Record<string, string>
    menus: MenuOption[]
    frontendThemeSettings: Partial<ThemePresetSettings>
    frontendHeaderSettings: Partial<HeaderPresetSettings>
    frontendFooterSettings: Partial<FooterPresetSettings>
    frontendHomepageSettings: Partial<HomepagePresetSettings>
    frontendHomepageConfig?: Record<string, unknown>
    frontendCustomCodeSettings: Partial<CustomCodeSettings>
}>()

const { t } = useTranslate()
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const tabs = [
    { id: 'general', label: 'General', icon: 'ti ti-settings' },
    { id: 'header', label: 'Header', icon: 'ti ti-layout-navbar' },
    { id: 'footer', label: 'Footer', icon: 'ti ti-layout-bottombar' },
    { id: 'homepage', label: 'Homepage', icon: 'ti ti-home' },
    { id: 'colors', label: 'Colors', icon: 'ti ti-palette' },
    { id: 'typography', label: 'Typography', icon: 'ti ti-typography' },
    { id: 'custom_code', label: 'Custom Code', icon: 'ti ti-code' },
] as const

type TabId = typeof tabs[number]['id']

const activeTab = ref<TabId>('general')
const menuOptions = computed(() => props.menus.map((menu) => ({
    value: menu.slug ?? '',
    label: menu.name ?? menu.slug ?? '',
})).filter((menu) => menu.value))
const headerButtonStyleOptions = computed(() => [
    { value: 'primary', label: t('Primary') },
    { value: 'dark', label: t('Dark') },
    { value: 'danger', label: t('Danger') },
    { value: 'success', label: t('Success') },
    { value: 'warning', label: t('Warning') },
    { value: 'purple', label: t('Purple') },
    { value: 'gradient_sunset', label: t('Gradient Sunset') },
    { value: 'gradient_ocean', label: t('Gradient Ocean') },
    { value: 'gradient_royal', label: t('Gradient Royal') },
    { value: 'outline', label: t('Outline') },
    { value: 'ghost', label: t('Ghost') },
])
const headerButtonShapeOptions = computed(() => [
    { value: 'sharp', label: t('Sharp') },
    { value: 'rounded', label: t('Rounded') },
    { value: 'rounded_xl', label: t('Rounded XL') },
    { value: 'pill', label: t('Pill') },
])
const headerShadowStyleOptions = computed(() => [
    { value: 'none', label: t('None') },
    { value: 'small', label: t('Small Shadow') },
    { value: 'medium', label: t('Medium Shadow') },
    { value: 'large', label: t('Large Shadow') },
    { value: 'border_small', label: t('Border Small (1px)') },
    { value: 'border_large', label: t('Border Large (2px)') },
])
const headerActionItemStyleOptions = computed(() => [
    { value: 'hide', label: t('Hide') },
    { value: 'icon_only', label: t('Icon Only') },
    { value: 'rounded_soft_bg', label: t('Rounded Soft BG') },
    { value: 'circular_soft_bg', label: t('Circular Soft BG') },
    { value: 'light_bg', label: t('Light BG') },
])
const headerLanguageSwitcherStyleOptions = computed(() => [
    ...headerActionItemStyleOptions.value,
    { value: 'icon_with_label', label: t('Icon With Label (Active Lang)') },
])
const headerCommandPaletteStyleOptions = computed(() => [
    { value: 'hidden', label: t('Hide') },
    { value: 'icon_only', label: t('Icon Only') },
    { value: 'rounded_soft_bg', label: t('Rounded Soft BG') },
    { value: 'circular_soft_bg', label: t('Circular Soft BG') },
    { value: 'search_transparent', label: t('Search Box (Transparent BG)') },
    { value: 'search_light', label: t('Search Box (Light BG)') },
])
const headerAccountAvatarStyleOptions = computed(() => [
    { value: 'avatar_only_rounded', label: t('Only Avatar (Rounded)') },
    { value: 'avatar_only_circle', label: t('Only Avatar (Circle)') },
    { value: 'avatar_name', label: t('Avatar + Username') },
    { value: 'avatar_name_arrow', label: t('Avatar + Username + Dropdown Icon') },
])
const accessLevelOptions = computed(() => {
    const options = [
        { value: 'all', label: t('Everyone') },
        { value: 'guest', label: t('Guests Only') },
        { value: 'auth', label: t('Logged In Users') },
        { value: 'not_pro', label: t('Not Premium Users') },
    ]

    if (isProAvailable.value) {
        options.push({ value: 'pro', label: t('Premium Users') })
    }

    return options
})
const desktopHeaderLayoutOptions = computed(() => [
    { value: 'classic', label: t('Classic') },
    { value: 'centered', label: t('Centered') },
    { value: 'minimal', label: t('Minimal') },
    { value: 'saas', label: t('SaaS') },
    { value: 'compact', label: t('Compact') },
])
const stickyBehaviorOptions = computed(() => [
    { value: 'none', label: t('None') },
    { value: 'always', label: t('Always') },
    { value: 'upscroll', label: t('Scroll Up') },
    { value: 'downscroll', label: t('Scroll Down') },
])
const footerLayoutOptions = computed(() => [
    {
        value: 'simple',
        label: t('Quick Links'),
        description: t('A compact two-column footer for a short brand intro and essential navigation.'),
        icon: 'ti ti-columns-2',
    },
    {
        value: 'columns',
        label: t('Balanced Columns'),
        description: t('A polished four-column layout for brand content, link groups, and utility items.'),
        icon: 'ti ti-layout-columns',
    },
    {
        value: 'company',
        label: t('Brand Focus'),
        description: t('Highlights your brand and contact details while keeping supporting links easy to scan.'),
        icon: 'ti ti-building-skyscraper',
    },
    {
        value: 'newsletter',
        label: t('Lead Capture'),
        description: t('Adds a stronger email signup area alongside your main footer navigation.'),
        icon: 'ti ti-mail-star',
    },
    {
        value: 'minimal',
        label: t('Minimal Bar'),
        description: t('A clean single-column footer for lightweight sites and simple landing pages.'),
        icon: 'ti ti-minus-path',
    },
    {
        value: 'stacked',
        label: t('Content Stack'),
        description: t('Groups navigation into a tighter stacked layout with a separate marketing column.'),
        icon: 'ti ti-layout-grid',
    },
])

const fontSizeOptions = ['12px', '13px', '14px', '15px', '16px', '18px', '20px'].map((value) => ({ value, label: value }))
const headingWeightOptions = ['400', '500', '600', '700', '800'].map((value) => ({ value, label: value }))
const lineHeightOptions = ['1.25', '1.375', '1.5', '1.625', '1.75', '2'].map((value) => ({ value, label: value }))
const letterSpacingOptions = [
    { value: 'tighter', label: 'Tighter' },
    { value: 'tight', label: 'Tight' },
    { value: 'normal', label: 'Normal' },
    { value: 'wide', label: 'Wide' },
    { value: 'wider', label: 'Wider' },
]
const borderRadiusOptions = [
    { value: '0px', label: 'Sharp' },
    { value: '8px', label: 'Subtle' },
    { value: '12px', label: 'Balanced' },
    { value: '16px', label: 'Soft' },
    { value: '20px', label: 'Rounded' },
    { value: '999px', label: 'Pill' },
]
const containerWidthOptions = [
    { value: '1280px', label: 'Default' },
    { value: 'full', label: 'Full Width' },
    { value: '1080px', label: 'Boxed' },
    { value: '1536px', label: 'Stretched' },
]
const gradientDirectionOptions = [
    { value: 'to bottom', label: 'Top to Bottom' },
    { value: 'to right', label: 'Left to Right' },
    { value: 'to bottom right', label: 'Top Left to Bottom Right' },
    { value: 'to bottom left', label: 'Top Right to Bottom Left' },
]
const gradientPaletteOptions = [
    { value: 'aurora', label: 'Aurora Glow' },
    { value: 'sunset', label: 'Sunset Pop' },
    { value: 'royal', label: 'Royal Pulse' },
    { value: 'mint_fire', label: 'Mint Fire' },
    { value: 'neon_night', label: 'Neon Night' },
    { value: 'gold_rush', label: 'Gold Rush' },
    { value: 'light_glow', label: 'Light Glow' },
    { value: 'light_warm', label: 'Light Warm' },
]

const currentSettingString = (key: string, fallback: string): string => props.settings[key] ?? fallback
const currentSettingBoolean = (key: string, fallback: boolean): boolean => {
    const value = props.settings[key]
    if (value === undefined || value === null || value === '') return fallback
    return value === '1' || value === 'true'
}

const resolvedThemeDefaults = computed<ThemePresetSettings>(() => ({
    ...props.frontendThemeSettings,
}))

const resolvedHeaderDefaults = computed<HeaderPresetSettings>(() => ({
    ...props.frontendHeaderSettings,
}))

const resolvedFooterDefaults = computed<FooterPresetSettings>(() => ({
    ...props.frontendFooterSettings,
}))

function normalizePaymentIcon(value: string | string[] | undefined): string {
    if (typeof value === 'string') {
        return value
    }

    if (Array.isArray(value)) {
        return value.find(Boolean) ?? ''
    }

    return ''
}

const resolvedHomepageDefaults = computed<HomepagePresetSettings>(() => ({
    ...props.frontendHomepageSettings,
}))

const resolvedCustomCodeDefaults = computed<CustomCodeSettings>(() => ({
    ...props.frontendCustomCodeSettings,
}))

const logoLightFile = ref<File | null>(null)
const logoDarkFile = ref<File | null>(null)
const faviconIcoFile = ref<File | null>(null)
const faviconPngFile = ref<File | null>(null)
const ogImageFile = ref<File | null>(null)
const bodyBgImageFile = ref<File | null>(null)
const heroBackgroundFile = ref<File | null>(null)
const paymentIconFile = ref<File | null>(null)
const paymentIconPreviewUrl = ref('')

const themeForm = useForm({
    section: 'theme',
    settings: {
        site_logo_light: currentSettingString('site_logo_light', ''),
        site_logo_dark: currentSettingString('site_logo_dark', ''),
        site_favicon_ico: currentSettingString('site_favicon_ico', ''),
        site_favicon_png: currentSettingString('site_favicon_png', ''),
        site_og_image: currentSettingString('site_og_image', ''),
        theme_default_mode: resolvedThemeDefaults.value.theme_default_mode ?? currentSettingString('theme_default_mode', 'light'),
        theme_allow_user_toggle: resolvedThemeDefaults.value.theme_allow_user_toggle ?? currentSettingBoolean('theme_allow_user_toggle', true),
        page_loading_animation: resolvedThemeDefaults.value.page_loading_animation ?? currentSettingString('page_loading_animation', 'none'),
        smooth_scroll: resolvedThemeDefaults.value.smooth_scroll ?? currentSettingBoolean('smooth_scroll', true),
        show_back_to_top: resolvedThemeDefaults.value.show_back_to_top ?? currentSettingBoolean('show_back_to_top', true),
        primary_color: resolvedThemeDefaults.value.primary_color ?? currentSettingString('primary_color', '#10b981'),
        secondary_color: resolvedThemeDefaults.value.secondary_color ?? currentSettingString('secondary_color', '#3b82f6'),
        accent_color: resolvedThemeDefaults.value.accent_color ?? currentSettingString('accent_color', '#8b5cf6'),
        bg_color: resolvedThemeDefaults.value.bg_color ?? currentSettingString('bg_color', '#f0fdf8'),
        bg_image: resolvedThemeDefaults.value.bg_image ?? currentSettingString('bg_image', ''),
        heading_color: resolvedThemeDefaults.value.heading_color ?? currentSettingString('heading_color', '#111827'),
        body_text_color: resolvedThemeDefaults.value.body_text_color ?? currentSettingString('body_text_color', '#374151'),
        muted_text_color: resolvedThemeDefaults.value.muted_text_color ?? currentSettingString('muted_text_color', '#6b7280'),
        border_color: resolvedThemeDefaults.value.border_color ?? currentSettingString('border_color', '#dbe4ea'),
        gradient_scheme_enabled: resolvedThemeDefaults.value.gradient_scheme_enabled ?? currentSettingBoolean('gradient_scheme_enabled', false),
        gradient_palette: resolvedThemeDefaults.value.gradient_palette ?? currentSettingString('gradient_palette', 'aurora'),
        gradient_start_color: resolvedThemeDefaults.value.gradient_start_color ?? currentSettingString('gradient_start_color', '#10b981'),
        gradient_end_color: resolvedThemeDefaults.value.gradient_end_color ?? currentSettingString('gradient_end_color', '#3b82f6'),
        bg_gradient_direction: resolvedThemeDefaults.value.bg_gradient_direction ?? currentSettingString('bg_gradient_direction', 'to right'),
        font_body: resolvedThemeDefaults.value.font_body ?? currentSettingString('font_body', 'Inter'),
        heading_font: resolvedThemeDefaults.value.heading_font ?? currentSettingString('heading_font', 'Plus Jakarta Sans'),
        base_font_size: resolvedThemeDefaults.value.base_font_size ?? currentSettingString('base_font_size', '15px'),
        heading_weight: resolvedThemeDefaults.value.heading_weight ?? currentSettingString('heading_weight', '700'),
        line_height: resolvedThemeDefaults.value.line_height ?? currentSettingString('line_height', '1.5'),
        letter_spacing: resolvedThemeDefaults.value.letter_spacing ?? currentSettingString('letter_spacing', 'normal'),
        border_radius: resolvedThemeDefaults.value.border_radius ?? currentSettingString('border_radius', '12px'),
        container_width: resolvedThemeDefaults.value.container_width ?? currentSettingString('container_width', '1280px'),
    },
})

const headerForm = useForm({
    section: 'header',
    settings: {
        desktop: {
            layout: resolvedHeaderDefaults.value.desktop?.layout ?? 'classic',
            sticky: resolvedHeaderDefaults.value.desktop?.sticky ?? true,
            sticky_behavior: resolvedHeaderDefaults.value.desktop?.sticky_behavior ?? (resolvedHeaderDefaults.value.desktop?.sticky === false ? 'none' : 'always'),
            shadow_style: resolvedHeaderDefaults.value.desktop?.shadow_style ?? 'border_small',
            show_notification_bell: resolvedHeaderDefaults.value.desktop?.show_notification_bell ?? true,
            notification_button_style: resolvedHeaderDefaults.value.desktop?.show_notification_bell === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.notification_button_style ?? 'rounded_soft_bg'),
            show_social_icons: resolvedHeaderDefaults.value.desktop?.show_social_icons ?? false,
            social_icon_style: resolvedHeaderDefaults.value.desktop?.show_social_icons === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.social_icon_style ?? 'rounded_soft_bg'),
            show_language_switcher: resolvedHeaderDefaults.value.desktop?.show_language_switcher ?? true,
            language_switcher_style: resolvedHeaderDefaults.value.desktop?.show_language_switcher === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.language_switcher_style ?? 'icon_with_label'),
            show_dark_mode_toggle: resolvedHeaderDefaults.value.desktop?.show_dark_mode_toggle ?? true,
            dark_mode_toggle_style: resolvedHeaderDefaults.value.desktop?.show_dark_mode_toggle === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.dark_mode_toggle_style ?? 'rounded_soft_bg'),
            command_palette_style: resolvedHeaderDefaults.value.desktop?.command_palette_style ?? 'hidden',
            auth_mode: resolvedHeaderDefaults.value.desktop?.auth_mode ?? 'login_register',
            guest_login_text: resolvedHeaderDefaults.value.desktop?.guest_login_text ?? 'Login',
            guest_login_icon_class: resolvedHeaderDefaults.value.desktop?.guest_login_icon_class ?? 'ti ti-login-2',
            guest_login_style: resolvedHeaderDefaults.value.desktop?.guest_login_style ?? 'primary',
            guest_register_text: resolvedHeaderDefaults.value.desktop?.guest_register_text ?? 'Register',
            guest_register_icon_class: resolvedHeaderDefaults.value.desktop?.guest_register_icon_class ?? 'ti ti-user-plus',
            guest_register_style: resolvedHeaderDefaults.value.desktop?.guest_register_style ?? 'dark',
            guest_button_shape: resolvedHeaderDefaults.value.desktop?.guest_button_shape ?? 'rounded_xl',
            account_avatar_style: resolvedHeaderDefaults.value.desktop?.account_avatar_style ?? 'avatar_name_arrow',
            show_cta_button: resolvedHeaderDefaults.value.desktop?.show_cta_button ?? true,
            cta_text: resolvedHeaderDefaults.value.desktop?.cta_text ?? 'Get Started',
            cta_link: resolvedHeaderDefaults.value.desktop?.cta_link ?? '/register',
            cta_icon: resolvedHeaderDefaults.value.desktop?.cta_icon ?? 'ti ti-rocket',
            cta_style: resolvedHeaderDefaults.value.desktop?.cta_style ?? 'primary',
            cta_shape: resolvedHeaderDefaults.value.desktop?.cta_shape ?? 'rounded_xl',
            cta_access_level: resolvedHeaderDefaults.value.desktop?.cta_access_level ?? 'all',
            menu_source: resolvedHeaderDefaults.value.desktop?.menu_source ?? 'primary',
            height: resolvedHeaderDefaults.value.desktop?.height ?? 72,
            container_width: resolvedHeaderDefaults.value.desktop?.container_width ?? '1280px',
            bg_color: resolvedHeaderDefaults.value.desktop?.bg_color ?? '',
            transparent_on_hero: resolvedHeaderDefaults.value.desktop?.transparent_on_hero ?? false,
            text_color: resolvedHeaderDefaults.value.desktop?.text_color ?? '',
            menu_hover_color: resolvedHeaderDefaults.value.desktop?.menu_hover_color ?? '',
            show_border: resolvedHeaderDefaults.value.desktop?.show_border ?? true,
            show_shadow: resolvedHeaderDefaults.value.desktop?.show_shadow ?? false,
        },
        mobile_top: {
            enabled: resolvedHeaderDefaults.value.mobile_top?.enabled ?? true,
            layout: resolvedHeaderDefaults.value.mobile_top?.layout ?? 'compact',
            sticky: resolvedHeaderDefaults.value.mobile_top?.sticky ?? true,
            show_logo: resolvedHeaderDefaults.value.mobile_top?.show_logo ?? true,
            show_hamburger: resolvedHeaderDefaults.value.mobile_top?.show_hamburger ?? true,
            show_dark_mode_toggle: resolvedHeaderDefaults.value.mobile_top?.show_dark_mode_toggle ?? true,
        },
        mobile_bottom: {
            enabled: resolvedHeaderDefaults.value.mobile_bottom?.enabled ?? false,
            layout: resolvedHeaderDefaults.value.mobile_bottom?.layout ?? 'tabs',
            show_home: resolvedHeaderDefaults.value.mobile_bottom?.show_home ?? true,
            show_tools: resolvedHeaderDefaults.value.mobile_bottom?.show_tools ?? true,
            show_dashboard: resolvedHeaderDefaults.value.mobile_bottom?.show_dashboard ?? true,
            show_profile: resolvedHeaderDefaults.value.mobile_bottom?.show_profile ?? true,
        },
    },
})

const footerForm = useForm({
    section: 'footer',
    settings: {
        layout: resolvedFooterDefaults.value.layout ?? 'columns',
        brand_title: resolvedFooterDefaults.value.brand_title ?? '',
        brand_description: resolvedFooterDefaults.value.brand_description ?? '',
        show_newsletter: resolvedFooterDefaults.value.show_newsletter ?? false,
        newsletter_title: resolvedFooterDefaults.value.newsletter_title ?? '',
        newsletter_description: resolvedFooterDefaults.value.newsletter_description ?? '',
        newsletter_placeholder: resolvedFooterDefaults.value.newsletter_placeholder ?? '',
        newsletter_button_label: resolvedFooterDefaults.value.newsletter_button_label ?? '',
        show_social_icons: resolvedFooterDefaults.value.show_social_icons ?? true,
        contact_title: resolvedFooterDefaults.value.contact_title ?? '',
        contact_email: resolvedFooterDefaults.value.contact_email ?? '',
        contact_phone: resolvedFooterDefaults.value.contact_phone ?? '',
        contact_address: resolvedFooterDefaults.value.contact_address ?? '',
        contact_details: resolvedFooterDefaults.value.contact_details ?? '',
        show_payment_icons: resolvedFooterDefaults.value.show_payment_icons ?? true,
        payment_icons: normalizePaymentIcon(resolvedFooterDefaults.value.payment_icons),
        show_bottom_social_icons: resolvedFooterDefaults.value.show_bottom_social_icons ?? false,
        show_back_to_top: resolvedFooterDefaults.value.show_back_to_top ?? true,
        back_to_top_label: resolvedFooterDefaults.value.back_to_top_label ?? '',
        back_to_top_icon: resolvedFooterDefaults.value.back_to_top_icon ?? 'ti ti-arrow-up',
        back_to_top_shape: resolvedFooterDefaults.value.back_to_top_shape ?? 'rounded',
        bottom_menu: resolvedFooterDefaults.value.bottom_menu ?? '',
        bottom_bar_show_border: resolvedFooterDefaults.value.bottom_bar_show_border ?? true,
        bottom_bar_border_color: resolvedFooterDefaults.value.bottom_bar_border_color ?? '',
        bottom_bar_border_width: resolvedFooterDefaults.value.bottom_bar_border_width ?? 1,
        bottom_bar_bg_color: resolvedFooterDefaults.value.bottom_bar_bg_color ?? '',
        bottom_bar_text_color: resolvedFooterDefaults.value.bottom_bar_text_color ?? '',
        bottom_bar_padding: resolvedFooterDefaults.value.bottom_bar_padding ?? 32,
        copyright_text: resolvedFooterDefaults.value.copyright_text ?? '',
        menu_title_1: resolvedFooterDefaults.value.menu_title_1 ?? '',
        menu_title_2: resolvedFooterDefaults.value.menu_title_2 ?? '',
        menu_title_3: resolvedFooterDefaults.value.menu_title_3 ?? '',
        menu_column_1: resolvedFooterDefaults.value.menu_column_1 ?? 'footer-company',
        menu_column_2: resolvedFooterDefaults.value.menu_column_2 ?? 'footer-support',
        menu_column_3: resolvedFooterDefaults.value.menu_column_3 ?? 'footer-legal',
    },
})

watch(() => headerForm.settings.desktop.notification_button_style, (value) => {
    headerForm.settings.desktop.show_notification_bell = value !== 'hide'
})

watch(() => headerForm.settings.desktop.social_icon_style, (value) => {
    headerForm.settings.desktop.show_social_icons = value !== 'hide'
})

watch(() => headerForm.settings.desktop.language_switcher_style, (value) => {
    headerForm.settings.desktop.show_language_switcher = value !== 'hide'
})

watch(() => headerForm.settings.desktop.dark_mode_toggle_style, (value) => {
    headerForm.settings.desktop.show_dark_mode_toggle = value !== 'hide'
})

const resolvedHomepageConfig = computed(() => {
    const raw = (props.frontendHomepageConfig ?? {}) as Record<string, unknown>
    const sections = (raw.sections ?? []) as Array<{ type: string; config: Record<string, unknown> }>
    const sectionMap: Record<string, Record<string, unknown>> = {}
    for (const s of sections) sectionMap[s.type] = { ...s.config } as Record<string, unknown>
    return {
        settings: (raw.settings ?? {}) as Record<string, unknown>,
        sections: sectionMap,
    }
})

const homepageForm = useForm({
    section: 'homepage',
    settings: {
        show_hero: resolvedHomepageDefaults.value.show_hero ?? true,
        hero_variant: resolvedHomepageDefaults.value.hero_variant ?? 'centered-gradient',
        show_social_proof: resolvedHomepageDefaults.value.show_social_proof ?? true,
        show_features: resolvedHomepageDefaults.value.show_features ?? true,
        show_tools: resolvedHomepageDefaults.value.show_tools ?? true,
        show_steps: resolvedHomepageDefaults.value.show_steps ?? true,
        show_pricing: resolvedHomepageDefaults.value.show_pricing ?? false,
        show_testimonials: resolvedHomepageDefaults.value.show_testimonials ?? true,
        show_faq: resolvedHomepageDefaults.value.show_faq ?? true,
        show_cta: resolvedHomepageDefaults.value.show_cta ?? true,
        show_blog: resolvedHomepageDefaults.value.show_blog ?? true,
        show_newsletter: resolvedHomepageDefaults.value.show_newsletter ?? true,
        show_custom_html: resolvedHomepageDefaults.value.show_custom_html ?? false,
        show_richtext: resolvedHomepageDefaults.value.show_richtext ?? false,
        show_ad_slot: resolvedHomepageDefaults.value.show_ad_slot ?? false,
    },
    homepage_config: {
        settings: resolvedHomepageConfig.value.settings,
        sections: resolvedHomepageConfig.value.sections,
    },
})

const secCfg = (type: string) => {
    const d = homepageForm.homepage_config?.sections as Record<string, Record<string, unknown>> | undefined
    if (!d?.[type]) return {} as Record<string, unknown>
    return d[type]
}

const collapsedSections = ref<Set<string>>(new Set())

function toggleCollapsed(type: string): void {
    const s = new Set(collapsedSections.value)
    if (s.has(type)) s.delete(type)
    else s.add(type)
    collapsedSections.value = s
}

function toggleVisibility(sec: HomepageSectionDef): void {
    homepageForm.settings[sec.toggleKey] = !homepageForm.settings[sec.toggleKey]
    const s = new Set(collapsedSections.value)
    if (homepageForm.settings[sec.toggleKey]) {
        s.delete(sec.type)
    } else {
        s.add(sec.type)
    }
    collapsedSections.value = s
}

function toggleHeroVisibility(): void {
    homepageForm.settings.show_hero = !homepageForm.settings.show_hero
    const s = new Set(collapsedSections.value)
    if (homepageForm.settings.show_hero) {
        s.delete('hero')
    } else {
        s.add('hero')
    }
    collapsedSections.value = s
}

function addStatsItem(): void {
    const stats = secCfg('hero').stats
    if (!Array.isArray(stats)) {
        secCfg('hero').stats = []
    }
    ;(secCfg('hero').stats as Array<Record<string, string>>).push({ number: '', label: '' })
}

function removeStatsItem(index: number): void {
    const stats = secCfg('hero').stats
    if (Array.isArray(stats)) {
        stats.splice(index, 1)
    }
}

const heroStatsItems = computed<Array<Record<string, string>>>(() => {
    const stats = secCfg('hero').stats
    return Array.isArray(stats) ? (stats as Array<Record<string, string>>) : []
})

type HomepageSectionDef = {
    toggleKey: string
    type: string
    label: string
    icon: string
    fields: string[]
}

const orderedSections = ref<HomepageSectionDef[]>([
    { toggleKey: 'show_features', type: 'features', label: t('Features'), icon: 'ti ti-layout-grid', fields: ['title', 'subtitle'] },
    { toggleKey: 'show_tools', type: 'tools_showcase', label: t('Tools Showcase'), icon: 'ti ti-tool', fields: ['title', 'subtitle', 'primary_text', 'primary_link'] },
    { toggleKey: 'show_steps', type: 'how_it_works', label: t('How It Works'), icon: 'ti ti-route', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_pricing', type: 'pricing', label: t('Pricing'), icon: 'ti ti-credit-card', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_testimonials', type: 'testimonials', label: t('Testimonials'), icon: 'ti ti-message-2-heart', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_faq', type: 'faq', label: t('FAQ'), icon: 'ti ti-help-circle', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_social_proof', type: 'stats_bar', label: t('Social Proof'), icon: 'ti ti-chart-bar', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_cta', type: 'cta_banner', label: t('CTA Banner'), icon: 'ti ti-banner', fields: ['headline', 'subheadline', 'primary_text', 'primary_link', 'secondary_text', 'secondary_link'] },
    { toggleKey: 'show_blog', type: 'latest_posts', label: t('Latest Posts'), icon: 'ti ti-article', fields: ['title', 'subtitle', 'button_text', 'button_link'] },
    { toggleKey: 'show_newsletter', type: 'newsletter', label: t('Newsletter'), icon: 'ti ti-mail-star', fields: ['heading', 'subheading', 'button_text', 'placeholder_text'] },
    { toggleKey: 'show_custom_html', type: 'custom_html', label: t('Custom HTML'), icon: 'ti ti-code', fields: ['content'] },
    { toggleKey: 'show_richtext', type: 'richtext', label: t('Rich Text'), icon: 'ti ti-align-left', fields: ['title', 'subtitle', 'content'] },
    { toggleKey: 'show_ad_slot', type: 'ad_slot', label: t('Ad Slot'), icon: 'ti ti-ad', fields: ['title', 'subtitle', 'zone'] },
])

function onSectionReordered(): void {
    // Update sort_order in form data to match new position
    orderedSections.value.forEach((sec, i) => {
        const cfg = secCfg(sec.type)
        if (cfg) cfg.sort_order = i + 1
    })
}

const customCodeForm = useForm({
    section: 'custom_code',
    settings: {
        custom_css: resolvedCustomCodeDefaults.value.custom_css ?? '',
        custom_header_code: resolvedCustomCodeDefaults.value.custom_header_code ?? '',
        custom_footer_code: resolvedCustomCodeDefaults.value.custom_footer_code ?? '',
    },
})

function fileUrl(path: string): string {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) return path
    return `/storage/${path}`
}

function isImagePath(path: string): boolean {
    return /^(https?:\/\/|\/)/.test(path) || /[\\/].+\.[a-z0-9]{2,5}$/i.test(path) || /^[^,]+\.[a-z0-9]{2,5}$/i.test(path)
}

function paymentIconPreviewSrc(): string {
    if (paymentIconPreviewUrl.value) return paymentIconPreviewUrl.value
    return fileUrl(footerForm.settings.payment_icons || '')
}

function onLogoLightInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    logoLightFile.value = target?.files?.[0] ?? null
}

function onLogoDarkInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    logoDarkFile.value = target?.files?.[0] ?? null
}

function onFaviconIcoInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    faviconIcoFile.value = target?.files?.[0] ?? null
}

function onFaviconPngInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    faviconPngFile.value = target?.files?.[0] ?? null
}

function onOgImageInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    ogImageFile.value = target?.files?.[0] ?? null
}

function onBodyBgImageInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    bodyBgImageFile.value = target?.files?.[0] ?? null
}

function onHeroBackgroundInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    heroBackgroundFile.value = target?.files?.[0] ?? null
}

function onPaymentIconsInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    paymentIconFile.value = target?.files?.[0] ?? null
    if (paymentIconPreviewUrl.value) {
        URL.revokeObjectURL(paymentIconPreviewUrl.value)
        paymentIconPreviewUrl.value = ''
    }
    if (paymentIconFile.value) {
        paymentIconPreviewUrl.value = URL.createObjectURL(paymentIconFile.value)
    }
}

function clearPaymentIconSelection(): void {
    footerForm.settings.payment_icons = ''
    paymentIconFile.value = null
    if (paymentIconPreviewUrl.value) {
        URL.revokeObjectURL(paymentIconPreviewUrl.value)
        paymentIconPreviewUrl.value = ''
    }
}

function saveThemeSettings(): void {
    themeForm.transform((data) => ({
        ...data,
        site_logo_light_file: logoLightFile.value,
        site_logo_dark_file: logoDarkFile.value,
        site_favicon_ico_file: faviconIcoFile.value,
        site_favicon_png_file: faviconPngFile.value,
        site_og_image_file: ogImageFile.value,
        bg_image_file: bodyBgImageFile.value,
    })).post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), {
        forceFormData: true,
        preserveScroll: true,
    })
}

function saveHeaderSettings(): void {
    headerForm.post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), { preserveScroll: true })
}

function saveFooterSettings(): void {
    footerForm.transform((data) => ({
        ...data,
        payment_icon_file: paymentIconFile.value,
    })).post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), {
        forceFormData: true,
        preserveScroll: true,
    })
}

function saveHomepageSettings(): void {
    const sections: Record<string, Record<string, unknown>> = homepageForm.homepage_config?.sections ?? {}
    const postOptions: Record<string, unknown> = { preserveScroll: true }
    if (heroBackgroundFile.value) {
        postOptions.forceFormData = true
    }
    homepageForm.transform((data) => ({
        ...data,
        ...(heroBackgroundFile.value ? { hero_background_file: heroBackgroundFile.value } : {}),
        homepage_config: {
            ...(homepageForm.homepage_config as Record<string, unknown>),
            sections: Object.keys(sections).map((type) => ({ type, config: sections[type] })),
        },
    })).post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), postOptions)
}

function saveCustomCodeSettings(): void {
    customCodeForm.post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), { preserveScroll: true })
}

function saveActiveTab(): void {
    if (activeTab.value === 'general' || activeTab.value === 'colors' || activeTab.value === 'typography') {
        saveThemeSettings()
        return
    }

    if (activeTab.value === 'header') {
        saveHeaderSettings()
        return
    }

    if (activeTab.value === 'footer') {
        saveFooterSettings()
        return
    }

    if (activeTab.value === 'homepage') {
        saveHomepageSettings()
        return
    }

    saveCustomCodeSettings()
}

const tabSectionMap: Record<string, string> = {
    general: 'theme',
    colors: 'theme',
    typography: 'theme',
    header: 'header',
    footer: 'footer',
    homepage: 'homepage',
    custom_code: 'custom_code',
}

function restoreDefaults(): void {
    const section = tabSectionMap[activeTab.value]
    if (!section) return

    if (!confirm(t('Are you sure you want to restore default :section settings? This cannot be undone.', { section: t(section) }))) {
        return
    }

    router.post(route('admin.themes.settings.restore-defaults', { slug: props.theme.slug }), {
        section: section,
    }, {
        preserveScroll: true,
    })
}

const isSaving = computed(() => {
    if (activeTab.value === 'general' || activeTab.value === 'colors' || activeTab.value === 'typography') {
        return themeForm.processing
    }

    if (activeTab.value === 'header') return headerForm.processing
    if (activeTab.value === 'footer') return footerForm.processing
    if (activeTab.value === 'homepage') return homepageForm.processing

    return customCodeForm.processing
})
</script>

<template>
    <Head :title="t('Theme Settings')" />

    <div class="w-full px-4 sm:px-5 lg:px-5 xl:px-6 2xl:px-6">
        <div class="mb-5 flex flex-col gap-4 border-b border-gray-100 pb-4 dark:border-surface-800 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Appearance Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ props.theme.name }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage simple frontend presets and buyer-friendly appearance settings.') }}</p>
            </div>
            <div class="flex items-center gap-3 self-start">
                <Link
                    :href="route('admin.themes')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-900"
                >
                    <i class="ti ti-arrow-left mr-1"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-danger-200 bg-white px-4 py-2.5 text-sm font-medium text-danger-600 transition-colors hover:bg-danger-50 dark:border-danger-800 dark:bg-danger-950/20 dark:text-danger-400 dark:hover:bg-danger-950/40"
                    @click="restoreDefaults"
                >
                    <i class="ti ti-restore text-base"></i>
                    {{ t('Restore Defaults') }}
                </button>
                <button
                    type="button"
                    :disabled="isSaving"
                    class="btn-primary inline-flex items-center gap-2 rounded-lg disabled:opacity-60"
                    @click="saveActiveTab"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ isSaving ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
            <aside class="w-full shrink-0 lg:w-64">
                <div class="sticky top-5 flex flex-row overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-surface-800 dark:bg-surface-900 lg:flex-col lg:overflow-x-visible">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="flex items-center gap-2.5 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium transition-all lg:w-full"
                        :class="activeTab === tab.id
                            ? 'bg-violet-50 font-semibold text-violet-700 shadow-sm dark:bg-violet-950/40 dark:text-violet-300'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-white'"
                        @click="activeTab = tab.id"
                    >
                        <i :class="[tab.icon, 'text-lg']"></i>
                        <span>{{ t(tab.label) }}</span>
                    </button>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <div v-if="activeTab === 'general'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">{{ t('Site Identity') }}</h2>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Logo Light') }}</h3>
                                <div v-if="themeForm.settings.site_logo_light" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_logo_light)" alt="Light logo" class="h-10 max-w-[160px] object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_logo_light = ''; logoLightFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="onLogoLightInput" />
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Logo Dark') }}</h3>
                                <div v-if="themeForm.settings.site_logo_dark" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_logo_dark)" alt="Dark logo" class="h-10 max-w-[160px] object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_logo_dark = ''; logoDarkFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="onLogoDarkInput" />
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Favicon ICO') }}</h3>
                                <div v-if="themeForm.settings.site_favicon_ico" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_favicon_ico)" alt="Favicon ICO" class="h-8 w-8 object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_favicon_ico = ''; faviconIcoFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept=".ico,image/x-icon" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="onFaviconIcoInput" />
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Favicon PNG') }}</h3>
                                <div v-if="themeForm.settings.site_favicon_png" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_favicon_png)" alt="Favicon PNG" class="h-8 w-8 object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_favicon_png = ''; faviconPngFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="onFaviconPngInput" />
                            </div>
                        </div>
                        <div class="mt-5 grid gap-5">
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('OG Image') }}</h3>
                                <div v-if="themeForm.settings.site_og_image" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_og_image)" alt="OG Image" class="h-12 max-w-[200px] rounded-lg object-cover" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_og_image = ''; ogImageFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png,image/jpeg,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="onOgImageInput" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-indigo-700 dark:text-indigo-300">{{ t('Theme Mode & Experience') }}</h2>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <AppSelect
                                v-model="themeForm.settings.theme_default_mode"
                                :label="t('Default color scheme')"
                                :options="[
                                    { value: 'light', label: t('Light') },
                                    { value: 'dark', label: t('Dark') },
                                ]"
                            />
                            <AppSelect
                                v-model="themeForm.settings.page_loading_animation"
                                :label="t('Page loading animation')"
                                :options="[
                                    { value: 'none', label: t('None') },
                                    { value: 'spinner', label: t('Spinner') },
                                    { value: 'skeleton', label: t('Skeleton') },
                                ]"
                            />
                            <AppSelect
                                v-model="themeForm.settings.container_width"
                                :label="t('Container width')"
                                :options="containerWidthOptions"
                            />
                            <AppSelect
                                v-model="themeForm.settings.border_radius"
                                :label="t('Card border radius')"
                                :options="borderRadiusOptions"
                            />
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                <button type="button" role="switch" :aria-checked="String(themeForm.settings.smooth_scroll)" class="app-switch" @click="themeForm.settings.smooth_scroll = !themeForm.settings.smooth_scroll">
                                    <span class="app-switch__thumb"></span>
                                </button>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Smooth scroll') }}</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                <button type="button" role="switch" :aria-checked="String(themeForm.settings.show_back_to_top)" class="app-switch" @click="themeForm.settings.show_back_to_top = !themeForm.settings.show_back_to_top">
                                    <span class="app-switch__thumb"></span>
                                </button>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show back to top') }}</span>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else-if="activeTab === 'colors'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Theme Colors') }}</h2>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <AppColorPicker v-model="themeForm.settings.primary_color" :label="t('Primary')" />
                            <AppColorPicker v-model="themeForm.settings.secondary_color" :label="t('Secondary')" />
                            <AppColorPicker v-model="themeForm.settings.accent_color" :label="t('Accent')" />
                            <AppColorPicker v-model="themeForm.settings.bg_color" :label="t('Body BG')" />
                            <AppColorPicker v-model="themeForm.settings.heading_color" :label="t('Heading Color')" />
                            <AppColorPicker v-model="themeForm.settings.body_text_color" :label="t('Body Text')" />
                            <AppColorPicker v-model="themeForm.settings.muted_text_color" :label="t('Muted Text')" />
                            <AppColorPicker v-model="themeForm.settings.border_color" :label="t('Border Color')" />
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">{{ t('Body Background') }}</h2>
                        <div class="space-y-4">
                            <div v-if="themeForm.settings.bg_image" class="overflow-hidden rounded-xl border border-dashed border-gray-200 bg-white dark:border-surface-700 dark:bg-surface-900/80">
                                <img :src="fileUrl(themeForm.settings.bg_image)" :alt="t('Body background preview')" class="h-40 w-full object-cover" />
                            </div>
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/avif" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="onBodyBgImageInput" />
                            <button type="button" class="text-sm font-medium text-danger-500 hover:underline" @click="themeForm.settings.bg_image = ''; bodyBgImageFile = null">{{ t('Remove background image') }}</button>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">{{ t('Gradient Scheme') }}</h2>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800 sm:col-span-2">
                                <button type="button" role="switch" :aria-checked="String(themeForm.settings.gradient_scheme_enabled)" class="app-switch" @click="themeForm.settings.gradient_scheme_enabled = !themeForm.settings.gradient_scheme_enabled">
                                    <span class="app-switch__thumb"></span>
                                </button>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Enable gradient scheme') }}</span>
                            </div>
                            <AppSelect v-model="themeForm.settings.gradient_palette" :label="t('Gradient palette')" :options="gradientPaletteOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                            <AppSelect v-model="themeForm.settings.bg_gradient_direction" :label="t('Gradient direction')" :options="gradientDirectionOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                            <AppColorPicker v-model="themeForm.settings.gradient_start_color" :label="t('Gradient start')" />
                            <AppColorPicker v-model="themeForm.settings.gradient_end_color" :label="t('Gradient end')" />
                        </div>
                    </section>
                </div>

                <div v-else-if="activeTab === 'typography'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">{{ t('Typography') }}</h2>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <AppSelect v-model="themeForm.settings.font_body" :label="t('Body Font')" :options="FONT_FAMILY_SELECT_OPTIONS.map((option) => ({ value: option.value, label: option.label }))" />
                            <AppSelect v-model="themeForm.settings.heading_font" :label="t('Heading Font')" :options="FONT_FAMILY_SELECT_OPTIONS.map((option) => ({ value: option.value, label: option.label }))" />
                            <AppSelect v-model="themeForm.settings.base_font_size" :label="t('Base Font Size')" :options="fontSizeOptions" />
                            <AppSelect v-model="themeForm.settings.heading_weight" :label="t('Heading Weight')" :options="headingWeightOptions" />
                            <AppSelect v-model="themeForm.settings.line_height" :label="t('Line Height')" :options="lineHeightOptions" />
                            <AppSelect v-model="themeForm.settings.letter_spacing" :label="t('Letter Spacing')" :options="letterSpacingOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                            <AppSelect v-model="themeForm.settings.border_radius" :label="t('Border Radius')" :options="borderRadiusOptions" />
                            <AppSelect v-model="themeForm.settings.container_width" :label="t('Container Width')" :options="containerWidthOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                        </div>
                    </section>
                </div>

                <div v-else-if="activeTab === 'header'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Desktop Header') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Set up the main desktop navigation area that visitors see first.') }}</p>
                        </div>

                        <div class="mt-6 grid gap-6 xl:grid-cols-2">
                            <section class="rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Layout & Size') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Choose how the header is structured and how much space it uses.') }}</p>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <AppSelect v-model="headerForm.settings.desktop.layout" :label="t('Layout')" :options="desktopHeaderLayoutOptions" />
                                    <AppSelect v-model="headerForm.settings.desktop.sticky_behavior" :label="t('Sticky Behavior')" :options="stickyBehaviorOptions" />
                                    <AppSelect v-model="headerForm.settings.desktop.menu_source" :label="t('Menu Source')" :options="menuOptions" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Header Height') }}
                                        <input v-model.number="headerForm.settings.desktop.height" type="number" min="48" max="140" step="1" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                    <AppSelect v-model="headerForm.settings.desktop.shadow_style" :label="t('Header Shadow')" :options="headerShadowStyleOptions" />
                                    <div class="sm:col-span-2">
                                        <AppSelect v-model="headerForm.settings.desktop.container_width" :label="t('Container Width')" :options="containerWidthOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Header Tools') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Choose which quick-access items appear in the desktop header.') }}</p>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <AppSelect v-model="headerForm.settings.desktop.notification_button_style" :label="t('Notification Bell')" :options="headerActionItemStyleOptions" />
                                    </div>
                                    <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <AppSelect v-model="headerForm.settings.desktop.social_icon_style" :label="t('Social Icons')" :options="headerActionItemStyleOptions" />
                                    </div>
                                    <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <AppSelect v-model="headerForm.settings.desktop.language_switcher_style" :label="t('Language Switcher')" :options="headerLanguageSwitcherStyleOptions" />
                                    </div>
                                    <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <AppSelect v-model="headerForm.settings.desktop.dark_mode_toggle_style" :label="t('Dark Mode Toggle')" :options="headerActionItemStyleOptions" />
                                    </div>
                                    <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-800 sm:col-span-2">
                                        <div>
                                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Search Trigger') }}</span>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show a header search trigger that opens the command palette on click or with Ctrl + K.') }}</p>
                                        </div>
                                        <AppSelect v-model="headerForm.settings.desktop.command_palette_style" :label="t('Search Box Style')" :options="headerCommandPaletteStyleOptions" class="mt-4" />
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="mt-6 grid gap-6 xl:grid-cols-2">
                            <section class="rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Account Buttons') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Set up the guest sign-in buttons and the logged-in profile button.') }}</p>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <AppSelect
                                            v-model="headerForm.settings.desktop.auth_mode"
                                            :label="t('Account Area')"
                                            :options="[
                                                { value: 'none', label: t('None') },
                                                { value: 'login_register', label: t('Login / Register') },
                                                { value: 'user_menu', label: t('User Menu') },
                                            ]"
                                        />
                                    </div>
                                    <template v-if="headerForm.settings.desktop.auth_mode !== 'none'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Login Button Text') }}<input v-model="headerForm.settings.desktop.guest_login_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" /></label>
                                        <IconClassSelect v-model="headerForm.settings.desktop.guest_login_icon_class" :label="t('Login Button Icon')" />
                                        <AppSelect v-model="headerForm.settings.desktop.guest_login_style" :label="t('Login Button Style')" :options="headerButtonStyleOptions" />
                                        <label
                                            v-if="headerForm.settings.desktop.auth_mode === 'login_register'"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >{{ t('Register Button Text') }}<input v-model="headerForm.settings.desktop.guest_register_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" /></label>
                                        <IconClassSelect
                                            v-if="headerForm.settings.desktop.auth_mode === 'login_register'"
                                            v-model="headerForm.settings.desktop.guest_register_icon_class"
                                            :label="t('Register Button Icon')"
                                        />
                                        <AppSelect
                                            v-if="headerForm.settings.desktop.auth_mode === 'login_register'"
                                            v-model="headerForm.settings.desktop.guest_register_style"
                                            :label="t('Register Button Style')"
                                            :options="headerButtonStyleOptions"
                                        />
                                        <AppSelect v-model="headerForm.settings.desktop.guest_button_shape" :label="t('Guest Button Shape')" :options="headerButtonShapeOptions" />
                                        <AppSelect v-model="headerForm.settings.desktop.account_avatar_style" :label="t('Avatar Button Style')" :options="headerAccountAvatarStyleOptions" />
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Colors') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('These colors affect only the main desktop header area.') }}</p>
                                </div>
                                <div class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Transparent Over Hero') }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the header transparent while the homepage hero is visible, then restore this background color after scrolling past it.') }}</p>
                                    </div>
                                    <button type="button" role="switch" :aria-checked="String(headerForm.settings.desktop.transparent_on_hero)" class="app-switch shrink-0" @click="headerForm.settings.desktop.transparent_on_hero = !headerForm.settings.desktop.transparent_on_hero">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                    <AppColorPicker v-model="headerForm.settings.desktop.bg_color" :label="t('Background Color')" />
                                    <AppColorPicker v-model="headerForm.settings.desktop.text_color" :label="t('Text Color')" />
                                    <AppColorPicker v-model="headerForm.settings.desktop.menu_hover_color" :label="t('Menu Hover Color')" />
                                </div>
                            </section>
                        </div>

                        <section class="mt-6 rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('CTA Button') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Highlight one main action such as register, contact, or pricing.') }}</p>
                                </div>
                                <button type="button" role="switch" :aria-checked="String(headerForm.settings.desktop.show_cta_button)" class="app-switch" @click="headerForm.settings.desktop.show_cta_button = !headerForm.settings.desktop.show_cta_button">
                                    <span class="app-switch__thumb"></span>
                                </button>
                            </div>
                            <div v-if="headerForm.settings.desktop.show_cta_button" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('CTA Text') }}<input v-model="headerForm.settings.desktop.cta_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" /></label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('CTA Link') }}<input v-model="headerForm.settings.desktop.cta_link" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" /></label>
                                <IconClassSelect v-model="headerForm.settings.desktop.cta_icon" :label="t('CTA Icon')" />
                                <AppSelect v-model="headerForm.settings.desktop.cta_style" :label="t('CTA Button Style')" :options="headerButtonStyleOptions" />
                                <AppSelect v-model="headerForm.settings.desktop.cta_shape" :label="t('CTA Button Shape')" :options="headerButtonShapeOptions" />
                                <AppSelect v-model="headerForm.settings.desktop.cta_access_level" :label="t('Button Access Level')" :options="accessLevelOptions" />
                            </div>
                            <p v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">{{ t('Enable this to show a main action button in the desktop header.') }}</p>
                        </section>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="mb-6 space-y-1">
                                <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">{{ t('Mobile Top Header') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Configure the compact header shown at the top on mobile devices.') }}</p>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <AppSelect
                                    v-model="headerForm.settings.mobile_top.layout"
                                    :label="t('Layout')"
                                    :options="[
                                        { value: 'compact', label: t('Compact') },
                                        { value: 'centered', label: t('Centered') },
                                    ]"
                                />
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Enable Mobile Top Header') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_top.enabled)" class="app-switch" @click="headerForm.settings.mobile_top.enabled = !headerForm.settings.mobile_top.enabled"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Sticky Mobile Top') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_top.sticky)" class="app-switch" @click="headerForm.settings.mobile_top.sticky = !headerForm.settings.mobile_top.sticky"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Logo') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_top.show_logo)" class="app-switch" @click="headerForm.settings.mobile_top.show_logo = !headerForm.settings.mobile_top.show_logo"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Hamburger') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_top.show_hamburger)" class="app-switch" @click="headerForm.settings.mobile_top.show_hamburger = !headerForm.settings.mobile_top.show_hamburger"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Dark Mode Toggle') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_top.show_dark_mode_toggle)" class="app-switch" @click="headerForm.settings.mobile_top.show_dark_mode_toggle = !headerForm.settings.mobile_top.show_dark_mode_toggle"><span class="app-switch__thumb"></span></button></div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <div class="mb-6 space-y-1">
                                <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">{{ t('Mobile Bottom Header') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Choose which quick navigation items appear in the fixed bottom mobile bar.') }}</p>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <AppSelect
                                    v-model="headerForm.settings.mobile_bottom.layout"
                                    :label="t('Layout')"
                                    :options="[
                                        { value: 'tabs', label: t('Tabs') },
                                    ]"
                                />
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Enable Mobile Bottom Header') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_bottom.enabled)" class="app-switch" @click="headerForm.settings.mobile_bottom.enabled = !headerForm.settings.mobile_bottom.enabled"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Home') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_bottom.show_home)" class="app-switch" @click="headerForm.settings.mobile_bottom.show_home = !headerForm.settings.mobile_bottom.show_home"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Tools') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_bottom.show_tools)" class="app-switch" @click="headerForm.settings.mobile_bottom.show_tools = !headerForm.settings.mobile_bottom.show_tools"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Dashboard') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_bottom.show_dashboard)" class="app-switch" @click="headerForm.settings.mobile_bottom.show_dashboard = !headerForm.settings.mobile_bottom.show_dashboard"><span class="app-switch__thumb"></span></button></div>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Profile') }}</span><button type="button" role="switch" :aria-checked="String(headerForm.settings.mobile_bottom.show_profile)" class="app-switch" @click="headerForm.settings.mobile_bottom.show_profile = !headerForm.settings.mobile_bottom.show_profile"><span class="app-switch__thumb"></span></button></div>
                            </div>
                        </section>
                    </div>
                </div>

                <div v-else-if="activeTab === 'footer'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Layout Style') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Choose a footer presentation that buyers can understand quickly, then fine-tune the content below.') }}</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <button
                                v-for="option in footerLayoutOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-2xl border p-5 text-left transition"
                                :class="footerForm.settings.layout === option.value
                                    ? 'border-primary-300 bg-primary-50 shadow-sm dark:border-primary-500/40 dark:bg-primary-500/10'
                                    : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/50 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-500/30 dark:hover:bg-primary-500/5'"
                                @click="footerForm.settings.layout = option.value"
                            >
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-100 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                                        <i :class="option.icon" class="text-xl"></i>
                                    </span>
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[11px]"
                                        :class="footerForm.settings.layout === option.value
                                            ? 'border-primary-500 bg-primary-500 text-white'
                                            : 'border-gray-300 text-transparent dark:border-surface-600'"
                                    >
                                        <i class="ti ti-check"></i>
                                    </span>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ option.label }}</h3>
                                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ option.description }}</p>
                            </button>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <AppSelect v-model="footerForm.settings.menu_column_1" :label="t('Links Menu One')" :options="menuOptions" />
                            <AppSelect v-model="footerForm.settings.menu_column_2" :label="t('Links Menu Two')" :options="menuOptions" />
                            <AppSelect v-model="footerForm.settings.menu_column_3" :label="t('Links Menu Three')" :options="menuOptions" />
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><button type="button" role="switch" :aria-checked="String(footerForm.settings.show_newsletter)" class="app-switch" @click="footerForm.settings.show_newsletter = !footerForm.settings.show_newsletter"><span class="app-switch__thumb"></span></button><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Enable Email Signup Block') }}</span></div>
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><button type="button" role="switch" :aria-checked="String(footerForm.settings.show_social_icons)" class="app-switch" @click="footerForm.settings.show_social_icons = !footerForm.settings.show_social_icons"><span class="app-switch__thumb"></span></button><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Social Links') }}</span></div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">{{ t('Brand Copy And Link Headings') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Write buyer-friendly brand text and clear navigation headings for each footer link group.') }}</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Brand Heading') }}
                                <input v-model="footerForm.settings.brand_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Trusted AI platform for content, chat, and automation')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Link Group Heading One') }}
                                <input v-model="footerForm.settings.menu_title_1" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Product')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                {{ t('Brand Summary') }}
                                <textarea v-model="footerForm.settings.brand_description" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Help visitors understand what your platform does and why they should trust it.')"></textarea>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Link Group Heading Two') }}
                                <input v-model="footerForm.settings.menu_title_2" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Resources')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Link Group Heading Three') }}
                                <input v-model="footerForm.settings.menu_title_3" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Company')" />
                            </label>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">{{ t('Contact And Email Signup') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Customize the support details and email capture copy shown in your footer utility block.') }}</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Support Block Heading') }}
                                <input v-model="footerForm.settings.contact_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Talk to our team')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Signup Block Heading') }}
                                <input v-model="footerForm.settings.newsletter_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Join our newsletter')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Support Email') }}
                                <input v-model="footerForm.settings.contact_email" type="email" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('support@example.com')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Support Phone') }}
                                <input v-model="footerForm.settings.contact_phone" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('+1 (555) 123-4567')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                {{ t('Support Address') }}
                                <textarea v-model="footerForm.settings.contact_address" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Level 4, 245 Market Street, San Francisco, CA')" ></textarea>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                {{ t('Support Note') }}
                                <textarea v-model="footerForm.settings.contact_details" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Response time, business hours, or onboarding help text.')"></textarea>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                {{ t('Signup Message') }}
                                <textarea v-model="footerForm.settings.newsletter_description" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Offer updates, promotions, or feature releases to encourage signups.')"></textarea>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Email Field Placeholder') }}
                                <input v-model="footerForm.settings.newsletter_placeholder" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Enter your work email')" />
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Signup Button Label') }}
                                <input v-model="footerForm.settings.newsletter_button_label" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Subscribe Now')" />
                            </label>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">{{ t('Bottom Footer Section') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Manage the lower footer row, including trust badges, scroll action, and copyright text.') }}</p>
                        </div>
                        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="String(footerForm.settings.show_bottom_social_icons)" class="app-switch" @click="footerForm.settings.show_bottom_social_icons = !footerForm.settings.show_bottom_social_icons">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Social Icons') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="String(footerForm.settings.show_payment_icons)" class="app-switch" @click="footerForm.settings.show_payment_icons = !footerForm.settings.show_payment_icons">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Payment Methods') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="String(footerForm.settings.show_back_to_top)" class="app-switch" @click="footerForm.settings.show_back_to_top = !footerForm.settings.show_back_to_top">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Scroll To Top') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="String(footerForm.settings.bottom_bar_show_border)" class="app-switch" @click="footerForm.settings.bottom_bar_show_border = !footerForm.settings.bottom_bar_show_border">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Top Border') }}</span>
                                </div>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <AppSelect
                                    v-model="footerForm.settings.bottom_menu"
                                    :label="t('Bottom Footer Menu')"
                                    :options="[{ value: '', label: t('None') }, ...menuOptions]"
                                />
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Vertical Padding') }}
                                    <input v-model.number="footerForm.settings.bottom_bar_padding" type="number" min="12" max="80" step="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                </label>
                                <AppColorPicker v-model="footerForm.settings.bottom_bar_bg_color" :label="t('Background Color')" />
                                <AppColorPicker v-model="footerForm.settings.bottom_bar_text_color" :label="t('Text Color')" />
                                <template v-if="footerForm.settings.bottom_bar_show_border">
                                    <AppColorPicker v-model="footerForm.settings.bottom_bar_border_color" :label="t('Border Color')" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Border Width') }}
                                        <input v-model.number="footerForm.settings.bottom_bar_border_width" type="number" min="1" max="6" step="1" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                </template>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Copyright Line') }}
                                    <input v-model="footerForm.settings.copyright_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('© {year} Your brand. All rights reserved.')" />
                                </label>
                                <label v-if="footerForm.settings.show_payment_icons" class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Payment Methods') }}
                                    <div v-if="footerForm.settings.payment_icons || paymentIconPreviewUrl" class="mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900">
                                        <img v-if="paymentIconPreviewSrc()" :src="paymentIconPreviewSrc()" :alt="t('Payment method preview')" class="h-12 max-w-full object-contain" />
                                        <span v-else class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-gray-50 px-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ footerForm.settings.payment_icons }}</span>
                                    </div>
                                    <input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="onPaymentIconsInput" />
                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <span class="block text-xs text-gray-400">{{ t('Upload one combined payment methods image with the brands you want to display.') }}</span>
                                        <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="clearPaymentIconSelection">{{ t('Remove image') }}</button>
                                    </div>
                                </label>
                                <template v-if="footerForm.settings.show_back_to_top">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Scroll To Top Label') }}
                                        <input v-model="footerForm.settings.back_to_top_label" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Back to top')" />
                                    </label>
                                    <IconClassSelect v-model="footerForm.settings.back_to_top_icon" :label="t('Scroll To Top Icon')" :placeholder="t('Choose an icon')" />
                                    <AppSelect
                                        v-model="footerForm.settings.back_to_top_shape"
                                        :label="t('Scroll To Top Style')"
                                        :options="[
                                            { value: 'rounded', label: t('Rounded') },
                                            { value: 'pill', label: t('Pill') },
                                            { value: 'circle', label: t('Circle') },
                                            { value: 'square', label: t('Square') },
                                        ]"
                                    />
                                </template>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else-if="activeTab === 'homepage'" class="space-y-6">

                    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                <i class="ti ti-rectangle mr-1.5 text-primary-500"></i>{{ t('Hero Section') }}
                            </h3>
                            <div class="flex items-center gap-2">
                                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" @click="toggleCollapsed('hero')">
                                    <i :class="collapsedSections.has('hero') ? 'ti ti-chevron-right' : 'ti ti-chevron-down'" class="text-base"></i>
                                </button>
                                <button type="button" role="switch" :aria-checked="String(homepageForm.settings.show_hero)" class="app-switch" @click="toggleHeroVisibility"><span class="app-switch__thumb"></span></button>
                            </div>
                        </div>
                        <div v-if="!collapsedSections.has('hero')" class="p-5 space-y-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hero Variant') }}</label>
                            <div class="grid grid-cols-3 gap-3">
                                <button type="button"
                                    v-for="variant in [
                                        { value: 'centered-gradient', label: t('Centered Gradient'), desc: 'Centered headline on gradient' },
                                        { value: 'tools-grid', label: t('Tools Grid'), desc: 'Featured tools in grid' },
                                        { value: 'split-gradient', label: t('Split Gradient'), desc: 'Split content with overlay' },
                                        { value: 'app-showcase', label: t('App Showcase'), desc: 'Dashboard + highlights' },
                                        { value: 'enterprise', label: t('Enterprise'), desc: 'Corporate style' },
                                        { value: 'minimal', label: t('Minimal'), desc: 'Clean & minimal' },
                                    ]"
                                    :key="variant.value"
                                    class="group rounded-xl border-2 p-4 text-left transition-all"
                                    :class="homepageForm.settings.hero_variant === variant.value
                                        ? 'border-[var(--primary)] bg-[var(--primary)]/5 shadow-sm'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-surface-700 dark:hover:border-surface-500'"
                                    @click="homepageForm.settings.hero_variant = variant.value"
                                >
                                    <div class="mb-3 h-20 w-full rounded-lg overflow-hidden border border-gray-200/50 dark:border-surface-600/50">
                                        <div v-if="variant.value === 'centered-gradient'" class="flex h-full w-full items-center justify-center" style="background: linear-gradient(135deg, var(--primary), #3b82f6);">
                                            <div class="text-center">
                                                <div class="mx-auto mb-1 h-1.5 w-16 rounded-full bg-white/80"></div>
                                                <div class="mx-auto h-1 w-10 rounded-full bg-white/50"></div>
                                            </div>
                                        </div>
                                        <div v-else-if="variant.value === 'tools-grid'" class="flex h-full w-full items-center justify-center bg-gray-50 p-2 dark:bg-surface-800">
                                            <div class="grid w-full grid-cols-2 gap-1">
                                                <div class="h-3 rounded bg-gray-200 dark:bg-surface-600" style="background: var(--primary); opacity: 0.6;"></div>
                                                <div class="h-3 rounded bg-gray-200 dark:bg-surface-600" style="background: var(--primary); opacity: 0.3;"></div>
                                                <div class="h-3 rounded bg-gray-200 dark:bg-surface-600" style="background: var(--primary); opacity: 0.3;"></div>
                                                <div class="h-3 rounded bg-gray-200 dark:bg-surface-600" style="background: var(--primary); opacity: 0.6;"></div>
                                            </div>
                                        </div>
                                        <div v-else-if="variant.value === 'split-gradient'" class="flex h-full w-full" style="background: linear-gradient(135deg, var(--primary) 50%, #f0fdf8 50%);">
                                            <div class="flex w-1/2 items-center justify-center">
                                                <div class="h-1.5 w-8 rounded-full bg-white/70"></div>
                                            </div>
                                            <div class="flex w-1/2 items-center justify-center">
                                                <div class="h-1.5 w-6 rounded-full" style="background: var(--primary);"></div>
                                            </div>
                                        </div>
                                        <div v-else-if="variant.value === 'app-showcase'" class="flex h-full w-full items-center justify-center bg-gray-50 p-2 dark:bg-surface-800">
                                            <div class="flex w-full gap-1.5">
                                                <div class="w-2/3 space-y-1">
                                                    <div class="h-1.5 w-full rounded" style="background: var(--primary); opacity: 0.7;"></div>
                                                    <div class="h-1 w-3/4 rounded bg-gray-300 dark:bg-surface-600"></div>
                                                </div>
                                                <div class="w-1/3 rounded border border-gray-200 bg-white p-1 dark:border-surface-600 dark:bg-surface-700">
                                                    <div class="h-1 w-full rounded bg-gray-200 dark:bg-surface-600"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else-if="variant.value === 'enterprise'" class="flex h-full w-full flex-col bg-gray-900 p-2">
                                            <div class="mb-1 flex items-center gap-1">
                                                <div class="h-1 w-4 rounded-full bg-white/60"></div>
                                                <div class="h-1 w-3 rounded-full bg-white/30"></div>
                                            </div>
                                            <div class="h-1.5 w-full rounded bg-white/20"></div>
                                        </div>
                                        <div v-else-if="variant.value === 'minimal'" class="flex h-full w-full items-center justify-center bg-white p-2 dark:bg-surface-900">
                                            <div class="space-y-1.5 text-center">
                                                <div class="mx-auto h-1 w-12 rounded-full bg-gray-300 dark:bg-surface-600"></div>
                                                <div class="mx-auto h-1 w-8 rounded-full bg-gray-200 dark:bg-surface-700"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white group-hover:text-[var(--primary)] transition-colors">{{ variant.label }}</div>
                                    <div class="mt-0.5 text-[10px] text-gray-400">{{ variant.desc }}</div>
                                </button>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Headline') }}
                                    <input v-model="secCfg('hero').headline" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    <p class="mt-1 text-xs text-gray-400">{{ t('Use | to enable typewriter effect (e.g. Create with | AI Writer | AI Images)') }}</p>
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Subheadline') }}
                                    <textarea v-model="secCfg('hero').subheadline" rows="2" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-1">
                                    {{ t('Trust Badge Text') }}
                                    <input v-model="secCfg('hero').trust_badge_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                </label>
                                <div class="sm:col-span-1">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Vertical Padding') }}</label>
                                    <AppSelect v-model="secCfg('hero').hero_vertical_padding" :options="[
                                        { value: '24', label: t('Compact') },
                                        { value: '48', label: t('Default') },
                                        { value: '72', label: t('Comfortable') },
                                        { value: '96', label: t('Tall') },
                                        { value: '128', label: t('Extra Tall') },
                                        { value: 'full', label: t('Full Window') },
                                    ]" />
                                </div>
                            </div>

                            <!-- Primary CTA Button Configuration -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Primary CTA Button') }}</h4>
                                    <button type="button" role="switch" :aria-checked="String(secCfg('hero').show_primary_cta ?? true)" class="app-switch" @click="secCfg('hero').show_primary_cta = !secCfg('hero').show_primary_cta"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfg('hero').show_primary_cta !== false && secCfg('hero').show_primary_cta !== 'false'" class="grid gap-4 sm:grid-cols-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                        {{ t('Button Text') }}
                                        <input v-model="secCfg('hero').primary_cta_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                        {{ t('Button Link') }}
                                        <input v-model="secCfg('hero').primary_cta_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                    <AppSelect v-model="secCfg('hero').primary_cta_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                    <AppSelect v-model="secCfg('hero').primary_cta_shape" :label="t('Button Shape')" :options="headerButtonShapeOptions" />
                                    <AppSelect v-model="secCfg('hero').primary_cta_size" :label="t('Button Size')" :options="[{ value: 'sm', label: t('Small') }, { value: 'md', label: t('Medium') }, { value: 'lg', label: t('Large') }, { value: 'xl', label: t('Extra Large') }]" />
                                    <IconClassSelect v-model="secCfg('hero').primary_cta_icon" :label="t('Button Icon')" />
                                    <AppSelect v-model="secCfg('hero').primary_cta_icon_position" :label="t('Icon Position')" :options="[{ value: 'left', label: t('Left') }, { value: 'right', label: t('Right') }]" />
                                    <AppSelect v-model="secCfg('hero').primary_cta_access_level" :label="t('Access Level')" :options="accessLevelOptions" />
                                </div>
                            </div>

                            <!-- Secondary CTA Button Configuration -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Secondary CTA Button') }}</h4>
                                    <button type="button" role="switch" :aria-checked="String(secCfg('hero').show_secondary_cta ?? true)" class="app-switch" @click="secCfg('hero').show_secondary_cta = !secCfg('hero').show_secondary_cta"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfg('hero').show_secondary_cta !== false && secCfg('hero').show_secondary_cta !== 'false'" class="grid gap-4 sm:grid-cols-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                        {{ t('Button Text') }}
                                        <input v-model="secCfg('hero').secondary_cta_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                        {{ t('Button Link') }}
                                        <input v-model="secCfg('hero').secondary_cta_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                    <AppSelect v-model="secCfg('hero').secondary_cta_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                    <AppSelect v-model="secCfg('hero').secondary_cta_shape" :label="t('Button Shape')" :options="headerButtonShapeOptions" />
                                    <AppSelect v-model="secCfg('hero').secondary_cta_size" :label="t('Button Size')" :options="[{ value: 'sm', label: t('Small') }, { value: 'md', label: t('Medium') }, { value: 'lg', label: t('Large') }, { value: 'xl', label: t('Extra Large') }]" />
                                    <IconClassSelect v-model="secCfg('hero').secondary_cta_icon" :label="t('Button Icon')" />
                                    <AppSelect v-model="secCfg('hero').secondary_cta_icon_position" :label="t('Icon Position')" :options="[{ value: 'left', label: t('Left') }, { value: 'right', label: t('Right') }]" />
                                    <AppSelect v-model="secCfg('hero').secondary_cta_access_level" :label="t('Access Level')" :options="accessLevelOptions" />
                                </div>
                            </div>

                            <!-- Background Image / Video -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Background Image / Video') }}</h4>
                                    <button type="button" role="switch" :aria-checked="String(secCfg('hero').show_hero_background ?? false)" class="app-switch" @click="secCfg('hero').show_hero_background = !secCfg('hero').show_hero_background"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfg('hero').show_hero_background && secCfg('hero').show_hero_background !== 'false'" class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="secCfg('hero').hero_background_type" :label="t('Media Type')" :options="[{ value: 'image', label: t('Image') }, { value: 'video', label: t('Video') }]" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Choose File') }}
                                        <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/avif,video/mp4,video/webm,video/ogg" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 dark:file:bg-primary-900/30 dark:file:text-primary-300" @input="onHeroBackgroundInput" />
                                    </label>
                                    <div v-if="secCfg('hero').hero_background_url" class="sm:col-span-2 flex items-center gap-2 rounded-lg border border-dashed border-success-200 bg-success-50 px-3 py-2 text-xs text-success-700 dark:border-success-800 dark:bg-success-900/20 dark:text-success-300">
                                        <i class="ti ti-check"></i>
                                        <span class="flex-1 truncate">{{ secCfg('hero').hero_background_url }}</span>
                                        <button type="button" class="shrink-0 font-medium text-danger-500 hover:underline" @click="secCfg('hero').hero_background_url = ''; heroBackgroundFile = null">{{ t('Remove') }}</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Gradient Colors -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Gradient Colors') }}</h4>
                                    <button type="button" role="switch" :aria-checked="String(secCfg('hero').hero_gradient_enabled ?? true)" class="app-switch" @click="secCfg('hero').hero_gradient_enabled = !secCfg('hero').hero_gradient_enabled"><span class="app-switch__thumb"></span></button>
                                </div>
                                <p class="mb-3 text-xs text-gray-400">{{ t('Applied to centered-gradient and split-gradient layouts. Light gradients keep text dark, dark gradients use white text.') }}</p>
                                <div v-if="secCfg('hero').hero_gradient_enabled !== false && secCfg('hero').hero_gradient_enabled !== 'false'" class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="secCfg('hero').hero_gradient_palette" :label="t('Gradient Palette')" :options="gradientPaletteOptions.map((o) => ({ value: o.value, label: t(o.label) }))" />
                                    <AppSelect v-model="secCfg('hero').hero_gradient_direction" :label="t('Gradient Direction')" :options="gradientDirectionOptions.map((o) => ({ value: o.value, label: t(o.label) }))" />
                                </div>
                            </div>

                            <!-- Stats Cards -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Stats Cards') }}</h4>
                                    <button type="button" role="switch" :aria-checked="String(secCfg('hero').show_stats ?? true)" class="app-switch" @click="secCfg('hero').show_stats = !secCfg('hero').show_stats"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfg('hero').show_stats !== false && secCfg('hero').show_stats !== 'false'" class="space-y-3">
                                    <div v-for="(stat, idx) in heroStatsItems" :key="idx" class="flex items-start gap-3 rounded-xl border border-gray-100 p-3 dark:border-surface-700">
                                        <div class="grid flex-1 gap-3 sm:grid-cols-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Number') }}
                                                <input v-model="stat.number" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Label') }}
                                                <input v-model="stat.label" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                        </div>
                                        <button type="button" class="mt-6 shrink-0 text-danger-500 hover:text-danger-700" @click="removeStatsItem(idx)"><i class="ti ti-trash"></i></button>
                                    </div>
                                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400" @click="addStatsItem">
                                        <i class="ti ti-plus"></i>
                                        {{ t('Add Stat') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <VueDraggable
                        v-model="orderedSections"
                        :animation="180"
                        handle=".drag-handle"
                        ghost-class="opacity-40"
                        @end="onSectionReordered"
                        class="space-y-4"
                    >
                        <section
                            v-for="sec in orderedSections"
                            :key="sec.type"
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
                        >
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                    <i :class="sec.icon" class="mr-1.5 text-primary-500"></i>{{ sec.label }}
                                </h3>
                                <div class="flex items-center gap-2">
                                    <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <i class="ti ti-grip-vertical text-lg"></i>
                                    </span>
                                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" @click="toggleCollapsed(sec.type)">
                                        <i :class="collapsedSections.has(sec.type) ? 'ti ti-chevron-right' : 'ti ti-chevron-down'" class="text-base"></i>
                                    </button>
                                    <button type="button" role="switch" :aria-checked="String(homepageForm.settings[sec.toggleKey])" class="app-switch" @click="toggleVisibility(sec)"><span class="app-switch__thumb"></span></button>
                                </div>
                            </div>

                            <div v-if="!collapsedSections.has(sec.type)" class="p-5 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <template v-for="field in sec.fields" :key="field">
                                        <label v-if="field === 'subheadline' || field === 'subtitle'" :key="field" class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                            {{ t(field.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())) }}
                                            <textarea v-model="secCfg(sec.type)[field]" rows="2" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                                    </label>
                                    <label v-else class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t(field.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())) }}
                                        <input v-model="secCfg(sec.type)[field]" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    </label>
                                </template>
                            </div>
                        </div>
                    </section>
                    </VueDraggable>
                </div>

                <div v-else-if="activeTab === 'custom_code'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">{{ t('Custom Code') }}</h2>
                        <div class="space-y-5">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Custom CSS') }}
                                <textarea v-model="customCodeForm.settings.custom_css" rows="8" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Custom Header Code') }}
                                <textarea v-model="customCodeForm.settings.custom_header_code" rows="8" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Custom Footer Code') }}
                                <textarea v-model="customCodeForm.settings.custom_footer_code" rows="8" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                            </label>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
