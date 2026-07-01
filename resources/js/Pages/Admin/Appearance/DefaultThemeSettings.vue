<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { FONT_FAMILY_SELECT_OPTIONS } from '@/config/fontFamilies'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

defineOptions({ layout: AdminLayout })

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

interface ThemeConfig {
    name: string
    slug: string
    version: string
    author: string
    description: string
    settings?: any[]
}

type HeaderDesktopSettings = {
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

type HeaderMobileTopSettings = {
    enabled?: boolean
    layout?: string
    sticky?: boolean
    sticky_behavior?: string
    show_logo?: boolean
    show_hamburger?: boolean
    show_dark_mode_toggle?: boolean
    show_notification_bell?: boolean
    show_search_icon?: boolean
    show_language_switcher?: boolean
    show_login?: boolean
    show_cta_button?: boolean
    height?: number
    bg_color?: string
    text_color?: string
    show_shadow?: string
}

type HeaderMobileBottomSettings = {
    enabled?: boolean
    layout?: string
    hide_menu_labels?: boolean
    show_glassmorphism?: boolean
    show_home?: boolean
    show_search_icon?: boolean
    show_tools?: boolean
    show_notification_bell?: boolean
    show_hamburger?: boolean
    show_profile?: boolean
    show_dashboard?: boolean
}

type HeaderPresetSettings = {
    desktop?: HeaderDesktopSettings
    mobile_top?: HeaderMobileTopSettings
    mobile_bottom?: HeaderMobileBottomSettings
}

type FooterPresetSettings = {
    layout?: string
    style_columns?: Record<string, Record<string, string | string[]>>
    brand_title?: string
    brand_description?: string
    show_newsletter?: boolean
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
    menu_title_1?: string
    menu_column_1?: string
    menu_title_2?: string
    menu_column_2?: string
    menu_title_3?: string
    menu_column_3?: string
    custom_title_1?: string
    custom_text_1?: string
    custom_title_2?: string
    custom_text_2?: string
    tool_categories_title_1?: string
    tool_categories_items_1?: string[]
    tool_categories_title_2?: string
    tool_categories_items_2?: string[]
    footer_bg_color?: string
    footer_text_color?: string
    footer_heading_color?: string
    footer_heading_text_case?: string
    container_width?: string
    disable_logo_about?: boolean
    disable_card_style?: boolean
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

type HomepagePresetSettings = {
    show_hero?: boolean
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
    show_ad_slot_2?: boolean
    show_ad_slot_3?: boolean
    show_image_carousel?: boolean
}

interface ThemePresetSettings {
    site_logo_light?: string
    site_logo_dark?: string
    site_favicon_ico?: string
    site_favicon_png?: string
    site_og_image?: string
    bg_image?: string
    bg_image_enabled?: boolean
    theme_default_mode?: string
    theme_allow_user_toggle?: boolean
    page_loading_animation?: string
    container_width?: string
    border_radius?: string
    smooth_scroll?: boolean
    show_back_to_top?: boolean
    primary_color?: string
    secondary_color?: string
    accent_color?: string
    bg_color?: string
    heading_color?: string
    body_text_color?: string
    muted_text_color?: string
    border_color?: string
    font_body?: string
    heading_font?: string
    base_font_size?: string
    heading_weight?: string
    line_height?: string
    letter_spacing?: string
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

type CategoryOption = {
    id?: number
    name?: string
    slug?: string
}

type ToolItem = {
    slug: string
    name: string
    description?: string
    icon?: string | null
    color?: string | null
    category?: string | null
}

const props = defineProps<{
    theme: ThemeConfig
    settings: Record<string, string>
    menus: MenuOption[]
    aiCategories?: CategoryOption[]
    frontendThemeSettings: Partial<ThemePresetSettings>
    frontendHeaderSettings: Partial<HeaderPresetSettings>
    frontendFooterSettings: Partial<FooterPresetSettings>
    frontendHomepageSettings: Partial<HomepagePresetSettings>
    frontendHomepageConfig?: Record<string, unknown>
    frontendCustomCodeSettings: Partial<CustomCodeSettings>
    frontendToolPageSettings: Record<string, any>
    allTools?: ToolItem[]
    adZones?: Record<string, string>
}>()

const { t } = useTranslate()
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const tabs = [
    { id: 'general', label: 'General', icon: 'ti ti-settings' },
    { id: 'header', label: 'Header', icon: 'ti ti-layout-navbar' },
    { id: 'footer', label: 'Footer', icon: 'ti ti-layout-bottombar' },
    { id: 'homepage', label: 'Home', icon: 'ti ti-home' },
    { id: 'page', label: 'Page', icon: 'ti ti-layout-grid' },
    { id: 'colors', label: 'Colors', icon: 'ti ti-palette' },
    { id: 'typography', label: 'Typography', icon: 'ti ti-typography' },
    { id: 'custom_code', label: 'Custom Code', icon: 'ti ti-code' },
] as const

type TabId = typeof tabs[number]['id']

const validTabs: TabId[] = tabs.map(t => t.id)
const urlTab = new URLSearchParams(window.location.search).get('tab') as TabId | null
const activeTab = ref<TabId>(validTabs.includes(urlTab as TabId) ? (urlTab as TabId) : 'general')

watch(activeTab, (tab) => {
    const url = new URL(window.location.href)
    if (tab === 'general') url.searchParams.delete('tab')
    else url.searchParams.set('tab', tab)
    history.replaceState(null, '', url.toString())
})

const menuOptions = computed(() => props.menus.map((menu) => ({
    value: menu.slug ?? '',
    label: menu.name ?? menu.slug ?? '',
})).filter((menu) => menu.value))
const aiCategoryOptions = computed(() => (props.aiCategories ?? []).map((category) => ({
    value: category.slug ?? '',
    label: category.name ?? category.slug ?? '',
})).filter((category) => category.value))
const adZoneOptions = computed(() => {
    const zones = props.adZones || {
        'header_banner': 'Header banner (728x90)',
        'sidebar_top': 'Sidebar top (300x250)',
        'sidebar_bottom': 'Sidebar bottom (300x250)',
        'content_top': 'Content top',
        'content_bottom': 'Content bottom',
        'content-injection': 'Content injection',
        'between_posts': 'Between posts',
        'between_ai_tools': 'Between AI tools',
        'tool_page_top': 'Tool page top',
        'tool_page_bottom': 'Tool page bottom',
        'template_page': 'Template page',
        'chat_banner': 'Chat banner',
        'dashboard_top': 'Dashboard top',
        'footer_banner': 'Footer banner',
        'custom_zone_1': 'Custom zone 1',
        'custom_zone_2': 'Custom zone 2',
    }
    return Object.entries(zones).map(([value, label]) => ({
        value,
        label: t(label)
    }))
})
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
const mobileTopShadowOptions = computed(() => [
    { value: 'none', label: t('None') },
    { value: 'small', label: t('Small Shadow') },
    { value: 'large', label: t('Large Shadow') },
    { value: 'border_small', label: t('Border (1px)') },
])
const headerStickyBehaviorOptions = computed(() => [
    { value: 'none', label: t('None') },
    { value: 'always', label: t('Always Sticky') },
    { value: 'upscroll', label: t('Up Scroll') },
    { value: 'downscroll', label: t('Down Scroll') },
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
const stickyBehaviorOptions = computed(() => [
    { value: 'none', label: t('None') },
    { value: 'always', label: t('Always') },
    { value: 'upscroll', label: t('Scroll Up') },
    { value: 'downscroll', label: t('Scroll Down') },
])
const headerMenuPositionOptions = computed(() => [
    { value: 'hide', label: t('Hide Menu') },
    { value: 'left', label: t('Left') },
    { value: 'center', label: t('Center') },
    { value: 'right', label: t('Right') },
])
const headerMenuHoverStyleOptions = computed(() => [
    { value: 'bottom_border', label: t('Bottom Border') },
    { value: 'rounded_soft_bg', label: t('Rounded Soft BG') },
    { value: 'pill_soft_bg', label: t('Pill Soft BG') },
    { value: 'simple', label: t('Simple') },
])
const footerStylePreview = (style: string) => {
    const previews: Record<string, string> = {
        default: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#ECFDF5"/>
                <rect x="18" y="20" width="284" height="52" rx="18" fill="url(#g1)"/>
                <rect x="30" y="36" width="90" height="8" rx="4" fill="#ffffff"/>
                <rect x="30" y="50" width="132" height="6" rx="3" fill="#D1FAE5"/>
                <rect x="196" y="34" width="92" height="24" rx="12" fill="#ffffff"/>
                <rect x="18" y="92" width="62" height="72" rx="16" fill="#ffffff"/>
                <rect x="92" y="92" width="62" height="72" rx="16" fill="#ffffff"/>
                <rect x="166" y="92" width="62" height="72" rx="16" fill="#ffffff"/>
                <rect x="240" y="92" width="62" height="72" rx="16" fill="#ffffff"/>
                <defs>
                    <linearGradient id="g1" x1="18" y1="20" x2="302" y2="72" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#10B981"/>
                        <stop offset="1" stop-color="#34D399"/>
                    </linearGradient>
                </defs>
            </svg>`,
        centered: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F8FAFC"/>
                <rect x="60" y="28" width="200" height="12" rx="6" fill="#0F172A"/>
                <rect x="84" y="50" width="152" height="8" rx="4" fill="#94A3B8"/>
                <rect x="40" y="86" width="240" height="18" rx="9" fill="#E2E8F0"/>
                <rect x="54" y="128" width="64" height="10" rx="5" fill="#CBD5E1"/>
                <rect x="128" y="128" width="64" height="10" rx="5" fill="#CBD5E1"/>
                <rect x="202" y="128" width="64" height="10" rx="5" fill="#CBD5E1"/>
                <circle cx="120" cy="162" r="11" fill="#10B981"/>
                <circle cx="160" cy="162" r="11" fill="#10B981" fill-opacity=".72"/>
                <circle cx="200" cy="162" r="11" fill="#10B981" fill-opacity=".45"/>
            </svg>`,
        spotlight: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#020617"/>
                <rect x="18" y="18" width="284" height="66" rx="20" fill="url(#g2)"/>
                <rect x="36" y="38" width="116" height="10" rx="5" fill="#F8FAFC"/>
                <rect x="36" y="56" width="152" height="7" rx="3.5" fill="#CBD5E1"/>
                <rect x="206" y="36" width="78" height="28" rx="14" fill="#ffffff"/>
                <rect x="18" y="104" width="64" height="74" rx="16" fill="#111827"/>
                <rect x="94" y="104" width="64" height="74" rx="16" fill="#111827"/>
                <rect x="170" y="104" width="64" height="74" rx="16" fill="#111827"/>
                <rect x="246" y="104" width="56" height="74" rx="16" fill="#111827"/>
                <defs>
                    <linearGradient id="g2" x1="18" y1="18" x2="302" y2="84" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#F97316"/>
                        <stop offset="1" stop-color="#8B5CF6"/>
                    </linearGradient>
                </defs>
            </svg>`,
        card_grid: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F8FAFC"/>
                <rect x="22" y="20" width="276" height="62" rx="22" fill="#ffffff" stroke="#C7D2FE"/>
                <rect x="116" y="8" width="88" height="24" rx="12" fill="url(#g3)"/>
                <rect x="38" y="42" width="92" height="9" rx="4.5" fill="#10B981"/>
                <rect x="38" y="58" width="122" height="6" rx="3" fill="#94A3B8"/>
                <rect x="180" y="38" width="100" height="26" rx="13" fill="#E2E8F0"/>
                <rect x="22" y="102" width="44" height="64" rx="14" fill="#ffffff"/>
                <rect x="78" y="102" width="44" height="64" rx="14" fill="#ffffff"/>
                <rect x="134" y="102" width="44" height="64" rx="14" fill="#ffffff"/>
                <rect x="190" y="102" width="44" height="64" rx="14" fill="#ffffff"/>
                <rect x="246" y="102" width="52" height="64" rx="14" fill="#ffffff"/>
                <defs>
                    <linearGradient id="g3" x1="116" y1="8" x2="204" y2="32" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#D1FAE5"/>
                        <stop offset="1" stop-color="#A7F3D0"/>
                    </linearGradient>
                </defs>
            </svg>`,
        split_band: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F8FAFC"/>
                <rect x="18" y="18" width="284" height="58" rx="20" fill="#0F172A"/>
                <circle cx="44" cy="47" r="12" fill="#10B981"/>
                <rect x="64" y="38" width="72" height="8" rx="4" fill="#F8FAFC"/>
                <rect x="64" y="52" width="106" height="6" rx="3" fill="#94A3B8"/>
                <rect x="206" y="34" width="82" height="24" rx="12" fill="#10B981"/>
                <rect x="18" y="98" width="62" height="74" rx="16" fill="#ffffff"/>
                <rect x="92" y="98" width="62" height="74" rx="16" fill="#ffffff"/>
                <rect x="166" y="98" width="62" height="74" rx="16" fill="#ffffff"/>
                <rect x="240" y="98" width="62" height="74" rx="16" fill="#ffffff"/>
            </svg>`,
        floating_panel: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F1F5F9"/>
                <rect x="62" y="10" width="196" height="64" rx="24" fill="#ffffff" fill-opacity=".96" stroke="#CBD5E1"/>
                <rect x="82" y="32" width="88" height="9" rx="4.5" fill="#10B981"/>
                <rect x="82" y="48" width="110" height="6" rx="3" fill="#94A3B8"/>
                <rect x="202" y="30" width="40" height="24" rx="12" fill="#0F172A"/>
                <rect x="18" y="64" width="284" height="116" rx="28" fill="#0F172A"/>
                <rect x="34" y="92" width="56" height="58" rx="16" fill="#1E293B"/>
                <rect x="102" y="92" width="56" height="58" rx="16" fill="#1E293B"/>
                <rect x="170" y="92" width="56" height="58" rx="16" fill="#1E293B"/>
                <rect x="238" y="92" width="48" height="58" rx="16" fill="#1E293B"/>
            </svg>`,
    }

    return `data:image/svg+xml;utf8,${encodeURIComponent(previews[style] ?? previews.default)}`
}

const heroStylePreview = (style: string) => {
    const previews: Record<string, string> = {
        'centered-gradient': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="url(#hero_g1)"/>
                <rect x="110" y="30" width="100" height="14" rx="7" fill="#ffffff" fill-opacity="0.25"/>
                <circle cx="120" cy="37" r="3" fill="#ffffff"/>
                <rect x="128" y="35" width="64" height="4" rx="2" fill="#ffffff"/>
                <rect x="50" y="62" width="220" height="12" rx="6" fill="#ffffff"/>
                <rect x="80" y="80" width="160" height="12" rx="6" fill="#ffffff" fill-opacity="0.8"/>
                <rect x="70" y="106" width="180" height="6" rx="3" fill="#ffffff" fill-opacity="0.5"/>
                <rect x="80" y="126" width="160" height="24" rx="12" fill="#ffffff" fill-opacity="0.9"/>
                <circle cx="94" cy="138" r="4" stroke="#94A3B8" stroke-width="1.5"/>
                <line x1="97" y1="141" x2="101" y2="145" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
                <rect x="106" y="136" width="70" height="4" rx="2" fill="#CBD5E1"/>
                <circle cx="228" cy="138" r="8" fill="#10B981"/>
                <rect x="100" y="162" width="55" height="16" rx="8" fill="#ffffff"/>
                <rect x="165" y="162" width="55" height="16" rx="8" fill="#ffffff" fill-opacity="0.2" stroke="#ffffff" stroke-width="1"/>
                <defs>
                    <linearGradient id="hero_g1" x1="0" y1="0" x2="320" y2="200" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#10B981"/>
                        <stop offset="1" stop-color="#3B82F6"/>
                    </linearGradient>
                </defs>
            </svg>`,
        'tools-grid': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F8FAFC"/>
                <rect x="20" y="35" width="70" height="10" rx="5" fill="#E2E8F0"/>
                <rect x="20" y="55" width="120" height="14" rx="7" fill="#0F172A"/>
                <rect x="20" y="75" width="90" height="14" rx="7" fill="#10B981"/>
                <rect x="20" y="100" width="110" height="6" rx="3" fill="#94A3B8"/>
                <rect x="20" y="112" width="80" height="6" rx="3" fill="#E2E8F0"/>
                <rect x="20" y="132" width="120" height="18" rx="9" fill="#F1F5F9"/>
                <circle cx="30" cy="141" r="4" stroke="#94A3B8" stroke-width="1.5"/>
                <line x1="33" y1="144" x2="37" y2="148" stroke="#94A3B8" stroke-width="1.5"/>
                <rect x="42" y="139" width="50" height="4" rx="2" fill="#CBD5E1"/>
                <rect x="20" y="162" width="60" height="16" rx="8" fill="#10B981"/>
                <rect x="160" y="30" width="65" height="65" rx="12" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <rect x="172" y="42" width="16" height="16" rx="4" fill="#10B981" fill-opacity="0.15"/>
                <circle cx="180" cy="50" r="4" fill="#10B981"/>
                <rect x="172" y="68" width="40" height="5" rx="2" fill="#0F172A"/>
                <rect x="172" y="78" width="30" height="4" rx="2" fill="#94A3B8"/>
                <rect x="235" y="30" width="65" height="65" rx="12" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <rect x="247" y="42" width="16" height="16" rx="4" fill="#3B82F6" fill-opacity="0.15"/>
                <circle cx="255" cy="50" r="4" fill="#3B82F6"/>
                <rect x="247" y="68" width="40" height="5" rx="2" fill="#0F172A"/>
                <rect x="247" y="78" width="30" height="4" rx="2" fill="#94A3B8"/>
                <rect x="160" y="105" width="65" height="65" rx="12" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <rect x="172" y="117" width="16" height="16" rx="4" fill="#8B5CF6" fill-opacity="0.15"/>
                <circle cx="180" cy="125" r="4" fill="#8B5CF6"/>
                <rect x="172" y="143" width="40" height="5" rx="2" fill="#0F172A"/>
                <rect x="172" y="153" width="30" height="4" rx="2" fill="#94A3B8"/>
                <rect x="235" y="105" width="65" height="65" rx="12" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <rect x="247" y="117" width="16" height="16" rx="4" fill="#F59E0B" fill-opacity="0.15"/>
                <circle cx="255" cy="125" r="4" fill="#F59E0B"/>
                <rect x="247" y="143" width="40" height="5" rx="2" fill="#0F172A"/>
                <rect x="247" y="153" width="30" height="4" rx="2" fill="#94A3B8"/>
            </svg>`,
        'split-gradient': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <mask id="round-corners-hero">
                    <rect width="320" height="200" rx="24" fill="#ffffff"/>
                </mask>
                <g mask="url(#round-corners-hero)">
                    <rect width="160" height="200" fill="url(#hero_split_g1)"/>
                    <rect x="160" width="160" height="200" fill="#F0FDF8"/>
                    <rect x="20" y="45" width="120" height="12" rx="6" fill="#ffffff"/>
                    <rect x="20" y="63" width="100" height="12" rx="6" fill="#ffffff" fill-opacity="0.7"/>
                    <rect x="20" y="85" width="110" height="5" rx="2.5" fill="#ffffff" fill-opacity="0.5"/>
                    <rect x="20" y="95" width="80" height="5" rx="2.5" fill="#ffffff" fill-opacity="0.5"/>
                    <rect x="20" y="115" width="120" height="20" rx="10" fill="#ffffff" fill-opacity="0.2"/>
                    <circle cx="30" cy="125" r="3" fill="#ffffff"/>
                    <rect x="38" y="123" width="70" height="4" rx="2" fill="#ffffff"/>
                    <rect x="20" y="145" width="60" height="18" rx="9" fill="#ffffff"/>
                    <rect x="180" y="40" width="120" height="120" rx="16" fill="#ffffff" stroke="#10B981" stroke-width="1.5" stroke-dasharray="3 3"/>
                    <circle cx="240" cy="80" r="18" fill="#10B981" fill-opacity="0.1"/>
                    <path d="M232,84 L238,78 L242,82 L248,74" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="210" y="110" width="60" height="8" rx="4" fill="#0F172A"/>
                    <rect x="200" y="125" width="80" height="5" rx="2.5" fill="#94A3B8"/>
                </g>
                <rect width="320" height="200" rx="24" stroke="#E2E8F0" stroke-width="1" fill="none"/>
                <defs>
                    <linearGradient id="hero_split_g1" x1="0" y1="0" x2="160" y2="200" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#1E293B"/>
                        <stop offset="1" stop-color="#0F172A"/>
                    </linearGradient>
                </defs>
            </svg>`,
        'app-showcase': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F8FAFC"/>
                <rect x="70" y="20" width="180" height="10" rx="5" fill="#0F172A"/>
                <rect x="100" y="35" width="120" height="10" rx="5" fill="#10B981"/>
                <rect x="90" y="50" width="140" height="4" rx="2" fill="#94A3B8"/>
                <rect x="105" y="62" width="50" height="14" rx="7" fill="#10B981"/>
                <rect x="165" y="62" width="50" height="14" rx="7" fill="#E2E8F0"/>
                <rect x="30" y="88" width="260" height="112" rx="10" fill="#ffffff" stroke="#CBD5E1" stroke-width="1"/>
                <path d="M30,98 H290" stroke="#E2E8F0" stroke-width="1"/>
                <circle cx="40" cy="93" r="2.5" fill="#EF4444"/>
                <circle cx="47" cy="93" r="2.5" fill="#F59E0B"/>
                <circle cx="54" cy="93" r="2.5" fill="#10B981"/>
                <rect x="65" y="90" width="190" height="6" rx="3" fill="#F1F5F9"/>
                <rect x="40" y="110" width="50" height="70" rx="6" fill="#F8FAFC"/>
                <rect x="45" y="115" width="40" height="4" rx="2" fill="#E2E8F0"/>
                <rect x="45" y="125" width="30" height="3" rx="1.5" fill="#E2E8F0"/>
                <rect x="45" y="132" width="20" height="3" rx="1.5" fill="#E2E8F0"/>
                <rect x="100" y="110" width="180" height="30" rx="8" fill="#F1F5F9"/>
                <rect x="110" y="120" width="80" height="6" rx="3" fill="#10B981"/>
                <rect x="240" y="118" width="30" height="10" rx="5" fill="#3B82F6"/>
                <rect x="100" y="148" width="85" height="40" rx="8" fill="#ffffff" stroke="#F1F5F9" stroke-width="1"/>
                <rect x="110" y="156" width="30" height="5" rx="2.5" fill="#0F172A"/>
                <rect x="110" y="166" width="60" height="4" rx="2" fill="#94A3B8"/>
                <rect x="195" y="148" width="85" height="40" rx="8" fill="#ffffff" stroke="#F1F5F9" stroke-width="1"/>
                <rect x="205" y="156" width="30" height="5" rx="2.5" fill="#0F172A"/>
                <rect x="205" y="166" width="60" height="4" rx="2" fill="#94A3B8"/>
            </svg>`,
        'enterprise': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#090D16"/>
                <line x1="40" y1="0" x2="40" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="80" y1="0" x2="80" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="120" y1="0" x2="120" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="160" y1="0" x2="160" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="200" y1="0" x2="200" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="240" y1="0" x2="240" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="280" y1="0" x2="280" y2="200" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="0" y1="50" x2="320" y2="50" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="0" y1="100" x2="320" y2="100" stroke="#1F2937" stroke-width="0.5"/>
                <line x1="0" y1="150" x2="320" y2="150" stroke="#1F2937" stroke-width="0.5"/>
                <rect x="20" y="25" width="90" height="12" rx="6" fill="#3B82F6" fill-opacity="0.2" stroke="#3B82F6" stroke-width="0.5"/>
                <circle cx="28" cy="31" r="2" fill="#3B82F6"/>
                <rect x="36" y="29" width="60" height="4" rx="2" fill="#93C5FD"/>
                <rect x="20" y="50" width="180" height="12" rx="6" fill="#ffffff"/>
                <rect x="20" y="68" width="130" height="12" rx="6" fill="#ffffff"/>
                <rect x="20" y="86" width="160" height="12" rx="6" fill="#3B82F6"/>
                <rect x="20" y="112" width="190" height="4" rx="2" fill="#9CA3AF"/>
                <rect x="20" y="122" width="140" height="4" rx="2" fill="#4B5563"/>
                <rect x="20" y="142" width="70" height="16" rx="8" fill="#3B82F6"/>
                <rect x="100" y="142" width="50" height="16" rx="8" fill="none" stroke="#4B5563" stroke-width="1"/>
                <rect x="230" y="55" width="80" height="100" rx="8" fill="#111827" stroke="#1F2937" stroke-width="1"/>
                <rect x="240" y="65" width="60" height="6" rx="3" fill="#1F2937"/>
                <rect x="240" y="78" width="40" height="15" rx="4" fill="#3B82F6" fill-opacity="0.1"/>
                <rect x="245" y="83" width="30" height="5" rx="2.5" fill="#3B82F6"/>
                <circle cx="255" cy="118" r="10" stroke="#1F2937" stroke-width="2" fill="none"/>
                <path d="M255,108 A10,10 0 0,1 265,118" stroke="#10B981" stroke-width="2" stroke-linecap="round" fill="none"/>
                <circle cx="285" cy="118" r="8" fill="#10B981" fill-opacity="0.1"/>
            </svg>`,
        'featured': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" fill="none">
                <rect width="320" height="200" rx="24" fill="#F8FAFC"/>
                <rect x="60" y="16" width="200" height="12" rx="6" fill="#0F172A"/>
                <rect x="90" y="32" width="140" height="8" rx="4" fill="#94A3B8"/>
                <rect x="80" y="50" width="160" height="20" rx="10" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <circle cx="92" cy="60" r="3" stroke="#94A3B8" stroke-width="1"/>
                <rect x="100" y="58" width="80" height="4" rx="2" fill="#E2E8F0"/>
                <rect x="15" y="80" width="65" height="16" rx="8" fill="#10B981" fill-opacity="0.1" stroke="#10B981" stroke-width="0.5"/>
                <circle cx="23" cy="88" r="3" fill="#10B981"/>
                <rect x="31" y="86" width="38" height="4" rx="2" fill="#10B981"/>
                <rect x="90" y="80" width="65" height="16" rx="8" fill="#ffffff" stroke="#E2E8F0" stroke-width="0.5"/>
                <circle cx="98" cy="88" r="3" fill="#3B82F6"/>
                <rect x="106" y="86" width="38" height="4" rx="2" fill="#64748B"/>
                <rect x="165" y="80" width="65" height="16" rx="8" fill="#ffffff" stroke="#E2E8F0" stroke-width="0.5"/>
                <circle cx="173" cy="88" r="3" fill="#8B5CF6"/>
                <rect x="181" y="86" width="38" height="4" rx="2" fill="#64748B"/>
                <rect x="240" y="80" width="65" height="16" rx="8" fill="#ffffff" stroke="#E2E8F0" stroke-width="0.5"/>
                <circle cx="248" cy="88" r="3" fill="#EC4899"/>
                <rect x="256" y="86" width="38" height="4" rx="2" fill="#64748B"/>
                <rect x="15" y="110" width="135" height="74" rx="14" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <rect x="27" y="122" width="24" height="24" rx="6" fill="#10B981" fill-opacity="0.1"/>
                <circle cx="39" cy="134" r="5" fill="#10B981"/>
                <rect x="60" y="124" width="60" height="6" rx="3" fill="#0F172A"/>
                <rect x="60" y="136" width="40" height="4" rx="2" fill="#10B981"/>
                <rect x="27" y="156" width="110" height="4" rx="2" fill="#CBD5E1"/>
                <rect x="27" y="164" width="80" height="4" rx="2" fill="#F1F5F9"/>
                <rect x="165" y="110" width="140" height="74" rx="14" fill="#ffffff" stroke="#E2E8F0" stroke-width="1"/>
                <rect x="177" y="122" width="24" height="24" rx="6" fill="#3B82F6" fill-opacity="0.1"/>
                <circle cx="189" cy="134" r="5" fill="#3B82F6"/>
                <rect x="210" y="124" width="60" height="6" rx="3" fill="#0F172A"/>
                <rect x="210" y="136" width="40" height="4" rx="2" fill="#3B82F6"/>
                <rect x="177" y="156" width="110" height="4" rx="2" fill="#CBD5E1"/>
                <rect x="177" y="164" width="80" height="4" rx="2" fill="#F1F5F9"/>
            </svg>`,
    }

    return `data:image/svg+xml;utf8,${encodeURIComponent(previews[style] ?? previews['centered-gradient'])}`
}
const footerLayoutOptions = computed(() => [
    {
        value: 'default',
        label: t('Default'),
        preview: footerStylePreview('default'),
    },
    {
        value: 'centered',
        label: t('Centered'),
        preview: footerStylePreview('centered'),
    },
    {
        value: 'spotlight',
        label: t('Spotlight'),
        preview: footerStylePreview('spotlight'),
    },
    {
        value: 'card_grid',
        label: t('Card Grid'),
        preview: footerStylePreview('card_grid'),
    },
    {
        value: 'split_band',
        label: t('Split Band'),
        preview: footerStylePreview('split_band'),
    },
    {
        value: 'floating_panel',
        label: t('Floating Panel'),
        preview: footerStylePreview('floating_panel'),
    },
])

const footerContentItemOptions = computed(() => [
    { value: 'about_text', label: t('About Text') },
    { value: 'logo_light', label: t('Logo Light') },
    { value: 'logo_dark', label: t('Logo Dark') },
    { value: 'menu_links_1', label: t('Menu Links 1') },
    { value: 'menu_links_2', label: t('Menu Links 2') },
    { value: 'menu_links_3', label: t('Menu Links 3') },
    { value: 'contact_info', label: t('Contact Info') },
    { value: 'custom_text_1', label: t('Custom Text 1') },
    { value: 'custom_text_2', label: t('Custom Text 2') },
    { value: 'tool_categories_1', label: t('Tool Categories 1') },
    { value: 'tool_categories_2', label: t('Tool Categories 2') },
    { value: 'newsletter', label: t('Newsletter') },
])

const activeFooterContentItemOptions = computed(() => {
    if (['floating_panel', 'card_grid', 'spotlight'].includes(footerForm.settings.layout)) {
        return footerContentItemOptions.value.filter((option) => option.value !== 'newsletter')
    }

    return footerContentItemOptions.value
})

const footerStyleColumnDefinitions = computed<Record<string, Array<{ key: string; label: string }>>>(() => ({
    default: [
        { key: 'col_1', label: t('Column 1') },
        { key: 'col_2', label: t('Column 2') },
        { key: 'col_3', label: t('Column 3') },
        { key: 'col_4', label: t('Column 4') },
    ],
    centered: [
        { key: 'col_1', label: t('Center Block 1') },
        { key: 'col_2', label: t('Center Block 2') },
        { key: 'col_3', label: t('Center Block 3') },
    ],
    spotlight: [
        { key: 'col_1', label: t('Spotlight Column 1') },
        { key: 'col_2', label: t('Spotlight Column 2') },
        { key: 'col_3', label: t('Spotlight Column 3') },
        { key: 'col_4', label: t('Spotlight Column 4') },
    ],
    card_grid: [
        { key: 'col_1', label: t('Grid Card 1') },
        { key: 'col_2', label: t('Grid Card 2') },
        { key: 'col_3', label: t('Grid Card 3') },
        { key: 'col_4', label: t('Grid Card 4') },
        { key: 'col_5', label: t('Grid Card 5') },
    ],
    split_band: [
        { key: 'col_1', label: t('Lower Column 1') },
        { key: 'col_2', label: t('Lower Column 2') },
        { key: 'col_3', label: t('Lower Column 3') },
        { key: 'col_4', label: t('Lower Column 4') },
    ],
    floating_panel: [
        { key: 'col_1', label: t('Floating Card 1') },
        { key: 'col_2', label: t('Floating Card 2') },
        { key: 'col_3', label: t('Floating Card 3') },
        { key: 'col_4', label: t('Floating Card 4') },
    ],
}))

const defaultFooterStyleColumns: Record<string, Record<string, string[]>> = {
    default: { col_1: ['about_text'], col_2: ['menu_links_1'], col_3: ['contact_info'], col_4: ['custom_text_1'] },
    centered: { col_1: ['about_text'], col_2: ['menu_links_1'], col_3: ['contact_info'] },
    spotlight: { col_1: ['menu_links_1'], col_2: ['menu_links_2'], col_3: ['contact_info'], col_4: ['tool_categories_1'] },
    card_grid: { col_1: ['about_text'], col_2: ['menu_links_1'], col_3: ['menu_links_2'], col_4: ['contact_info'], col_5: ['tool_categories_1'] },
    split_band: { col_1: ['menu_links_1'], col_2: ['contact_info'], col_3: ['custom_text_1'], col_4: ['tool_categories_1'] },
    floating_panel: { col_1: ['menu_links_1'], col_2: ['contact_info'], col_3: ['custom_text_1'], col_4: ['tool_categories_1'] },
}

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
const normalizeBooleanValue = (value: unknown, fallback: boolean): boolean => {
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return value !== 0
    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase()
        if (['1', 'true', 'yes', 'on'].includes(normalized)) return true
        if (['0', 'false', 'no', 'off', ''].includes(normalized)) return false
    }
    return fallback
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

const normalizeFooterStyleColumnItems = (value: unknown, fallback: string[]) => {
    if (Array.isArray(value)) {
        const filtered = value.filter((item): item is string => typeof item === 'string' && item.length > 0)
        return filtered.length ? filtered : [...fallback]
    }

    if (typeof value === 'string' && value.length > 0) {
        return [value]
    }

    return [...fallback]
}

const resolvedFooterStyleColumns = computed<Record<string, Record<string, string[]>>>(() => {
    const stored = resolvedFooterDefaults.value.style_columns ?? {}

    return Object.fromEntries(
        Object.entries(defaultFooterStyleColumns).map(([style, defaults]) => [
            style,
            Object.fromEntries(
                Object.entries(defaults).map(([columnKey, fallbackItems]) => [
                    columnKey,
                    normalizeFooterStyleColumnItems(stored[style]?.[columnKey], fallbackItems)
                        .filter((item) => !(style === 'floating_panel' && item === 'newsletter')),
                ]),
            ),
        ]),
    )
})

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

const resolvedToolPageDefaults = computed(() => ({
    ...props.frontendToolPageSettings,
}))

const toolPageForm = useForm({
    section: 'tool_page',
    settings: {
        layout: resolvedToolPageDefaults.value.layout ?? 'default',
        hide_breadcrumbs: resolvedToolPageDefaults.value.hide_breadcrumbs ?? false,
        hide_rating: resolvedToolPageDefaults.value.hide_rating ?? false,
        hide_share: resolvedToolPageDefaults.value.hide_share ?? false,
        hide_favorite: resolvedToolPageDefaults.value.hide_favorite ?? false,
        archive_layout: resolvedToolPageDefaults.value.archive_layout ?? 'default',
        archive_show_breadcrumbs: resolvedToolPageDefaults.value.archive_show_breadcrumbs !== false,
        archive_show_stats: resolvedToolPageDefaults.value.archive_show_stats !== false,
        archive_show_featured: resolvedToolPageDefaults.value.archive_show_featured !== false,
        archive_show_grid_list: resolvedToolPageDefaults.value.archive_show_grid_list !== false,
        archive_show_recently_used: resolvedToolPageDefaults.value.archive_show_recently_used !== false,
        archive_show_open_button: resolvedToolPageDefaults.value.archive_show_open_button !== false,
        archive_pagination: resolvedToolPageDefaults.value.archive_pagination ?? 'numbered',
        category_show_breadcrumbs: resolvedToolPageDefaults.value.category_show_breadcrumbs !== false,
        category_enable_gradient: resolvedToolPageDefaults.value.category_enable_gradient ?? false,
        category_pagination: resolvedToolPageDefaults.value.category_pagination ?? 'numbered',
    },
})

const logoLightFile = ref<File | null>(null)
const logoDarkFile = ref<File | null>(null)
const faviconIcoFile = ref<File | null>(null)
const faviconPngFile = ref<File | null>(null)
const ogImageFile = ref<File | null>(null)
const bodyBgImageFile = ref<File | null>(null)
const heroBackgroundFile = ref<File | null>(null)
const heroSplitImageFile = ref<File | null>(null)
const paymentIconFile = ref<File | null>(null)
const paymentIconPreviewUrl = ref('')
const brandLogoFiles = ref<Record<number, File>>({})
const brandLogoPreviewUrls = ref<Record<number, string>>({})

const themeForm = useForm({
    section: 'theme',
    settings: {
        site_logo_light: currentSettingString('site_logo_light', ''),
        site_logo_dark: currentSettingString('site_logo_dark', ''),
        site_favicon_ico: currentSettingString('site_favicon_ico', ''),
        site_favicon_png: currentSettingString('site_favicon_png', ''),
        site_og_image: currentSettingString('site_og_image', ''),
        theme_default_mode: resolvedThemeDefaults.value.theme_default_mode ?? currentSettingString('theme_default_mode', 'light'),
        theme_allow_user_toggle: normalizeBooleanValue(resolvedThemeDefaults.value.theme_allow_user_toggle, true),
        page_loading_animation: resolvedThemeDefaults.value.page_loading_animation ?? currentSettingString('page_loading_animation', 'none'),
        smooth_scroll: normalizeBooleanValue(resolvedThemeDefaults.value.smooth_scroll, true),
        show_back_to_top: normalizeBooleanValue(resolvedThemeDefaults.value.show_back_to_top, true),
        primary_color: resolvedThemeDefaults.value.primary_color ?? currentSettingString('primary_color', '#10b981'),
        secondary_color: resolvedThemeDefaults.value.secondary_color ?? currentSettingString('secondary_color', '#3b82f6'),
        accent_color: resolvedThemeDefaults.value.accent_color ?? currentSettingString('accent_color', '#8b5cf6'),
        bg_color: resolvedThemeDefaults.value.bg_color ?? currentSettingString('bg_color', '#f0fdf8'),
        bg_image: resolvedThemeDefaults.value.bg_image ?? currentSettingString('bg_image', ''),
        bg_image_enabled: normalizeBooleanValue(resolvedThemeDefaults.value.bg_image_enabled, false),
        heading_color: resolvedThemeDefaults.value.heading_color ?? currentSettingString('heading_color', '#111827'),
        body_text_color: resolvedThemeDefaults.value.body_text_color ?? currentSettingString('body_text_color', '#374151'),
        muted_text_color: resolvedThemeDefaults.value.muted_text_color ?? currentSettingString('muted_text_color', '#6b7280'),
        border_color: resolvedThemeDefaults.value.border_color ?? currentSettingString('border_color', '#dbe4ea'),
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
            sticky: resolvedHeaderDefaults.value.desktop?.sticky ?? true,
            sticky_behavior: resolvedHeaderDefaults.value.desktop?.sticky_behavior ?? (resolvedHeaderDefaults.value.desktop?.sticky === false ? 'none' : 'always'),
            shadow_style: resolvedHeaderDefaults.value.desktop?.shadow_style ?? 'border_small',
            menu_position: resolvedHeaderDefaults.value.desktop?.menu_position ?? 'center',
            menu_hover_style: resolvedHeaderDefaults.value.desktop?.menu_hover_style ?? 'rounded_soft_bg',
            show_notification_bell: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_notification_bell, true),
            notification_button_style: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_notification_bell, true) === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.notification_button_style ?? 'rounded_soft_bg'),
            show_social_icons: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_social_icons, false),
            social_icon_style: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_social_icons, false) === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.social_icon_style ?? 'rounded_soft_bg'),
            show_language_switcher: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_language_switcher, true),
            language_switcher_style: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_language_switcher, true) === false
                ? 'hide'
                : (resolvedHeaderDefaults.value.desktop?.language_switcher_style ?? 'icon_with_label'),
            show_dark_mode_toggle: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_dark_mode_toggle, true),
            dark_mode_toggle_style: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_dark_mode_toggle, true) === false
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
            show_cta_button: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_cta_button, true),
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
            transparent_on_hero: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.transparent_on_hero, false),
            text_color: resolvedHeaderDefaults.value.desktop?.text_color ?? '',
            menu_hover_color: resolvedHeaderDefaults.value.desktop?.menu_hover_color ?? '',
            show_border: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_border, true),
            show_shadow: normalizeBooleanValue(resolvedHeaderDefaults.value.desktop?.show_shadow, false),
        },
        mobile_top: {
            enabled: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.enabled, true),
            layout: resolvedHeaderDefaults.value.mobile_top?.layout ?? 'compact',
            height: Number(resolvedHeaderDefaults.value.mobile_top?.height ?? 64),
            bg_color: resolvedHeaderDefaults.value.mobile_top?.bg_color ?? '',
            text_color: resolvedHeaderDefaults.value.mobile_top?.text_color ?? '',
            show_shadow: resolvedHeaderDefaults.value.mobile_top?.show_shadow ?? 'none',
            sticky_behavior: resolvedHeaderDefaults.value.mobile_top?.sticky_behavior ?? (resolvedHeaderDefaults.value.mobile_top?.sticky === false ? 'none' : 'always'),
            show_logo: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_logo, true),
            show_hamburger: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_hamburger, true),
            show_dark_mode_toggle: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_dark_mode_toggle, true),
            show_notification_bell: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_notification_bell, false),
            show_search_icon: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_search_icon, false),
            show_language_switcher: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_language_switcher, false),
            show_login: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_login, false),
            show_cta_button: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_top?.show_cta_button, false),
        },
        mobile_bottom: {
            enabled: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.enabled, false),
            layout: resolvedHeaderDefaults.value.mobile_bottom?.layout ?? 'tabs',
            hide_menu_labels: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.hide_menu_labels, false),
            show_glassmorphism: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_glassmorphism, true),
            show_home: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_home, true),
            show_search_icon: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_search_icon, false),
            show_tools: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_tools, true),
            show_notification_bell: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_notification_bell, false),
            show_hamburger: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_hamburger, false),
            show_profile: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_profile, true),
            show_dashboard: normalizeBooleanValue(resolvedHeaderDefaults.value.mobile_bottom?.show_dashboard, true),
        },
    },
})

const footerForm = useForm({
    section: 'footer',
    settings: {
        layout: resolvedFooterDefaults.value.layout ?? 'default',
        style_columns: resolvedFooterStyleColumns.value,
        brand_title: resolvedFooterDefaults.value.brand_title ?? '',
        brand_description: resolvedFooterDefaults.value.brand_description ?? '',
        show_newsletter: normalizeBooleanValue(resolvedFooterDefaults.value.show_newsletter, true),
        newsletter_title: resolvedFooterDefaults.value.newsletter_title ?? '',
        newsletter_description: resolvedFooterDefaults.value.newsletter_description ?? '',
        newsletter_placeholder: resolvedFooterDefaults.value.newsletter_placeholder ?? '',
        newsletter_button_label: resolvedFooterDefaults.value.newsletter_button_label ?? '',
        newsletter_button_style: resolvedFooterDefaults.value.newsletter_button_style ?? 'primary',
        show_social_icons: normalizeBooleanValue(resolvedFooterDefaults.value.show_social_icons, true),
        contact_title: resolvedFooterDefaults.value.contact_title ?? '',
        contact_email: resolvedFooterDefaults.value.contact_email ?? '',
        contact_phone: resolvedFooterDefaults.value.contact_phone ?? '',
        contact_address: resolvedFooterDefaults.value.contact_address ?? '',
        contact_details: resolvedFooterDefaults.value.contact_details ?? '',
        menu_title_1: resolvedFooterDefaults.value.menu_title_1 ?? '',
        menu_column_1: resolvedFooterDefaults.value.menu_column_1 ?? 'footer-company',
        menu_title_2: resolvedFooterDefaults.value.menu_title_2 ?? '',
        menu_column_2: resolvedFooterDefaults.value.menu_column_2 ?? 'footer-support',
        menu_title_3: resolvedFooterDefaults.value.menu_title_3 ?? '',
        menu_column_3: resolvedFooterDefaults.value.menu_column_3 ?? 'footer-legal',
        custom_title_1: resolvedFooterDefaults.value.custom_title_1 ?? '',
        custom_text_1: resolvedFooterDefaults.value.custom_text_1 ?? '',
        custom_title_2: resolvedFooterDefaults.value.custom_title_2 ?? '',
        custom_text_2: resolvedFooterDefaults.value.custom_text_2 ?? '',
        tool_categories_title_1: resolvedFooterDefaults.value.tool_categories_title_1 ?? '',
        tool_categories_items_1: resolvedFooterDefaults.value.tool_categories_items_1 ?? [],
        tool_categories_title_2: resolvedFooterDefaults.value.tool_categories_title_2 ?? '',
        tool_categories_items_2: resolvedFooterDefaults.value.tool_categories_items_2 ?? [],
        footer_bg_color: resolvedFooterDefaults.value.footer_bg_color ?? '',
        footer_text_color: resolvedFooterDefaults.value.footer_text_color ?? '',
        footer_heading_color: resolvedFooterDefaults.value.footer_heading_color ?? '',
        footer_heading_text_case: resolvedFooterDefaults.value.footer_heading_text_case ?? 'capitalize',
        container_width: resolvedFooterDefaults.value.container_width ?? '1280px',
        disable_logo_about: normalizeBooleanValue(resolvedFooterDefaults.value.disable_logo_about, false),
        disable_card_style: normalizeBooleanValue(resolvedFooterDefaults.value.disable_card_style, false),
        footer_vertical_padding: resolvedFooterDefaults.value.footer_vertical_padding ?? 56,
        show_payment_icons: normalizeBooleanValue(resolvedFooterDefaults.value.show_payment_icons, true),
        payment_icons: normalizePaymentIcon(resolvedFooterDefaults.value.payment_icons),
        show_bottom_social_icons: normalizeBooleanValue(resolvedFooterDefaults.value.show_bottom_social_icons, false),
        show_bottom_language_selector: normalizeBooleanValue(resolvedFooterDefaults.value.show_bottom_language_selector, false),
        show_back_to_top: normalizeBooleanValue(resolvedFooterDefaults.value.show_back_to_top, true),
        back_to_top_label: resolvedFooterDefaults.value.back_to_top_label ?? '',
        back_to_top_icon: resolvedFooterDefaults.value.back_to_top_icon ?? 'ti ti-arrow-up',
        back_to_top_shape: resolvedFooterDefaults.value.back_to_top_shape ?? 'rounded',
        bottom_menu: resolvedFooterDefaults.value.bottom_menu ?? '',
        bottom_bar_show_border: normalizeBooleanValue(resolvedFooterDefaults.value.bottom_bar_show_border, true),
        bottom_bar_border_color: resolvedFooterDefaults.value.bottom_bar_border_color ?? '',
        bottom_bar_border_width: resolvedFooterDefaults.value.bottom_bar_border_width ?? 1,
        bottom_bar_bg_color: resolvedFooterDefaults.value.bottom_bar_bg_color ?? '',
        bottom_bar_text_color: resolvedFooterDefaults.value.bottom_bar_text_color ?? '',
        bottom_bar_padding: resolvedFooterDefaults.value.bottom_bar_padding ?? 32,
        bottom_bar_centered: normalizeBooleanValue(resolvedFooterDefaults.value.bottom_bar_centered, false),
        copyright_text: resolvedFooterDefaults.value.copyright_text ?? '',
    },
})

const activeFooterStyleColumns = computed(() => footerStyleColumnDefinitions.value[footerForm.settings.layout] ?? footerStyleColumnDefinitions.value.default)
const footerLogoVisibilityToggle = computed<boolean>({
    get: () => footerForm.settings.layout === 'spotlight'
        ? !footerForm.settings.disable_logo_about
        : footerForm.settings.disable_logo_about,
    set: (value: boolean) => {
        footerForm.settings.disable_logo_about = footerForm.settings.layout === 'spotlight'
            ? !value
            : value
    },
})
const footerLogoVisibilityLabel = computed(() => footerForm.settings.layout === 'spotlight'
    ? t('Show Logo')
    : t('Disable Logo & About Text'))

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
    for (const s of sections) {
        const config: Record<string, unknown> = {}
        for (const [key, value] of Object.entries(s.config)) {
            if (typeof value === 'string' && ['true', 'false'].includes(value.trim().toLowerCase())) {
                config[key] = value.trim().toLowerCase() === 'true'
            } else {
                config[key] = value
            }
        }

        // Deduplicate and sanitize image_carousel items without mutating props in place
        if (s.type === 'image_carousel') {
            let items = config.items
            if (!Array.isArray(items)) {
                items = []
            } else {
                const uniqueKeys = new Set<string>()
                items = items.map((item: any) => {
                    if (!item) return { _key: 'key_' + Math.random().toString(36).substring(2, 9), title: '', description: '', link_url: '', image_url: '' }
                    const resolvedItem = {
                        _key: item._key || 'key_' + Math.random().toString(36).substring(2, 9),
                        title: item.title ?? '',
                        description: item.description ?? '',
                        link_url: item.link_url ?? '',
                        image_url: item.image_url ?? '',
                    }
                    if (uniqueKeys.has(resolvedItem._key)) {
                        resolvedItem._key = 'key_' + Math.random().toString(36).substring(2, 9)
                    }
                    uniqueKeys.add(resolvedItem._key)
                    return resolvedItem
                })
            }
            config.items = items
        }

        sectionMap[s.type] = config as Record<string, unknown>
    }
    return {
        settings: (raw.settings ?? {}) as Record<string, unknown>,
        sections: sectionMap,
    }
})

const homepageForm = useForm({
    section: 'homepage',
    settings: {
        show_hero: normalizeBooleanValue(resolvedHomepageDefaults.value.show_hero, true),
        hero_variant: resolvedHomepageDefaults.value.hero_variant ?? 'centered-gradient',
        show_social_proof: normalizeBooleanValue(resolvedHomepageDefaults.value.show_social_proof, true),
        show_features: normalizeBooleanValue(resolvedHomepageDefaults.value.show_features, true),
        show_tools: normalizeBooleanValue(resolvedHomepageDefaults.value.show_tools, true),
        show_steps: normalizeBooleanValue(resolvedHomepageDefaults.value.show_steps, true),
        show_pricing: normalizeBooleanValue(resolvedHomepageDefaults.value.show_pricing, false),
        show_testimonials: normalizeBooleanValue(resolvedHomepageDefaults.value.show_testimonials, true),
        show_faq: normalizeBooleanValue(resolvedHomepageDefaults.value.show_faq, true),
        show_cta: normalizeBooleanValue(resolvedHomepageDefaults.value.show_cta, true),
        show_blog: normalizeBooleanValue(resolvedHomepageDefaults.value.show_blog, true),
        show_newsletter: normalizeBooleanValue(resolvedHomepageDefaults.value.show_newsletter, true),
        show_custom_html: normalizeBooleanValue(resolvedHomepageDefaults.value.show_custom_html, false),
        show_richtext: normalizeBooleanValue(resolvedHomepageDefaults.value.show_richtext, false),
        show_ad_slot: normalizeBooleanValue(resolvedHomepageDefaults.value.show_ad_slot, false),
        show_ad_slot_2: normalizeBooleanValue(resolvedHomepageDefaults.value.show_ad_slot_2, false),
        show_ad_slot_3: normalizeBooleanValue(resolvedHomepageDefaults.value.show_ad_slot_3, false),
        show_image_carousel: normalizeBooleanValue(resolvedHomepageDefaults.value.show_image_carousel, false),
    },
    homepage_config: {
        settings: JSON.parse(JSON.stringify(resolvedHomepageConfig.value.settings)),
        sections: JSON.parse(JSON.stringify(resolvedHomepageConfig.value.sections)),
    },
})

interface SectionItem {
    _key?: string
    icon?: string
    title?: string
    description?: string
    link_url?: string
    image_url?: string
    number?: string
    label?: string
    name?: string
    image?: string
}

interface SectionConfig {
    section_bg?: string
    title_align?: string
    title_size?: string
    title_color?: string
    card_bg_style?: string
    vertical_padding?: string
    disable_card_style?: boolean
    newsletter_style?: string
    button_style?: string
    headline?: string
    show_primary_cta?: boolean
    primary_cta_text?: string
    primary_cta_link?: string
    primary_cta_style?: string
    primary_cta_shape?: string
    primary_cta_size?: string
    primary_cta_icon?: string
    primary_cta_icon_position?: string
    primary_cta_access_level?: string
    show_secondary_cta?: boolean
    secondary_cta_text?: string
    secondary_cta_link?: string
    secondary_cta_style?: string
    secondary_cta_shape?: string
    secondary_cta_size?: string
    secondary_cta_icon?: string
    secondary_cta_icon_position?: string
    secondary_cta_access_level?: string
    show_stats?: boolean
    show_brands?: boolean
    stats?: SectionItem[]
    brands?: SectionItem[]
    items?: SectionItem[]
    tools_grid_tool_slugs?: string[]
    display_categories?: string[]
    display_tool_slugs?: string[]
    primary_text?: string
    primary_link?: string
    primary_style?: string
    primary_shape?: string
    primary_icon?: string
    primary_access_level?: string
    secondary_text?: string
    secondary_link?: string
    secondary_style?: string
    secondary_shape?: string
    secondary_icon?: string
    secondary_access_level?: string
    carousel_layout?: string
    carousel_autoplay?: string
    carousel_time?: string | number
    show_description?: boolean
    show_read_more_btn?: boolean
    show_button?: boolean
    sort_order?: number
    [key: string]: string | number | boolean | SectionItem[] | string[] | null | undefined
}

const secCfg = (type: string): any => {
    const d = homepageForm.homepage_config?.sections as Record<string, SectionConfig> | undefined
    if (!d?.[type]) return {} as SectionConfig
    const cfg = d[type]
    if (type !== 'hero') {
        if (cfg.section_bg === undefined) cfg.section_bg = 'default'
        if (cfg.title_align === undefined) cfg.title_align = 'center'
        if (cfg.title_size === undefined) cfg.title_size = 'md'
        if (cfg.title_color === undefined) cfg.title_color = 'dark'
        if (cfg.card_bg_style === undefined) cfg.card_bg_style = 'default'
        if (cfg.vertical_padding === undefined) cfg.vertical_padding = '96'
        if (cfg.icon_position === undefined) cfg.icon_position = 'top'
        if (cfg.icon_style === undefined) cfg.icon_style = 'primary'
    }
    if (type === 'tools_showcase') {
        if (cfg.source === undefined) cfg.source = 'all'
        if (cfg.max_items === undefined) cfg.max_items = 6
        if (cfg.layout === undefined) cfg.layout = '3-column'
        if (cfg.card_style === undefined) cfg.card_style = 'style-1'
        if (cfg.primary_text === undefined) cfg.primary_text = ''
        if (cfg.primary_link === undefined) cfg.primary_link = ''
        if (cfg.primary_style === undefined) cfg.primary_style = 'primary'
        if (cfg.primary_shape === undefined) cfg.primary_shape = 'rounded_xl'
        if (cfg.primary_icon === undefined) cfg.primary_icon = ''
        if (cfg.show_rating === undefined) cfg.show_rating = true
        if (cfg.show_favorite === undefined) cfg.show_favorite = true
        if (cfg.show_category === undefined) cfg.show_category = true
        if (cfg.show_category_filter === undefined) cfg.show_category_filter = false
        if (cfg.show_search === undefined) cfg.show_search = false
    }
    if (type === 'features') {
        if (cfg.disable_card_style === undefined) cfg.disable_card_style = false
        if (!Array.isArray(cfg.items)) cfg.items = []
    }
    if (type === 'how_it_works') {
        if (!Array.isArray(cfg.items)) cfg.items = []
    }
    if (type === 'testimonials') {
        if (cfg.card_style === undefined) cfg.card_style = 'bordered'
        if (cfg.slider_columns === undefined) cfg.slider_columns = '3'
        if (cfg.hide_controls === undefined) cfg.hide_controls = '0'
        if (cfg.autoplay_enabled === undefined) cfg.autoplay_enabled = '0'
        if (cfg.max_items === undefined) cfg.max_items = 6
        if (cfg.source === undefined) cfg.source = 'all'
    }
    if (type === 'stats_bar') {
        if (cfg.show_stats === undefined) cfg.show_stats = true
        if (cfg.show_brands === undefined) cfg.show_brands = false
        if (!Array.isArray(cfg.stats)) cfg.stats = []
        if (!Array.isArray(cfg.brands)) cfg.brands = []
    }
    if (type === 'cta_banner') {
        if (cfg.background_style === undefined) cfg.background_style = 'gradient-1'
        if (cfg.primary_text === undefined) cfg.primary_text = ''
        if (cfg.primary_link === undefined) cfg.primary_link = ''
        if (cfg.primary_style === undefined) cfg.primary_style = 'primary_filled'
        if (cfg.primary_shape === undefined) cfg.primary_shape = 'rounded_xl'
        if (cfg.primary_icon === undefined) cfg.primary_icon = ''
        if (cfg.primary_access_level === undefined) cfg.primary_access_level = 'all'

        if (cfg.secondary_text === undefined) cfg.secondary_text = ''
        if (cfg.secondary_link === undefined) cfg.secondary_link = ''
        if (cfg.secondary_style === undefined) cfg.secondary_style = 'outline'
        if (cfg.secondary_shape === undefined) cfg.secondary_shape = 'rounded_xl'
        if (cfg.secondary_icon === undefined) cfg.secondary_icon = ''
        if (cfg.secondary_access_level === undefined) cfg.secondary_access_level = 'all'
    }
    if (type === 'latest_posts') {
        if (cfg.max_items === undefined) cfg.max_items = 3
        if (cfg.show_button === undefined) cfg.show_button = true
        if (cfg.show_description === undefined) cfg.show_description = true
        if (cfg.show_read_more_btn === undefined) cfg.show_read_more_btn = true
        if (cfg.button_text === undefined) cfg.button_text = ''
        if (cfg.button_link === undefined) cfg.button_link = ''
        if (cfg.button_style === undefined) cfg.button_style = 'outline'
        if (cfg.button_icon === undefined) cfg.button_icon = ''
    }
    if (type === 'newsletter') {
        if (cfg.background_style === undefined) cfg.background_style = 'white'
        if (cfg.newsletter_style === undefined) cfg.newsletter_style = 'inline'
        if (cfg.button_style === undefined) cfg.button_style = 'primary_filled'
    }
    if (type === 'custom_html') {
        if (cfg.heading === undefined) cfg.heading = ''
        if (cfg.subheading === undefined) cfg.subheading = ''
        if (cfg.content === undefined) cfg.content = ''
    }
    if (type === 'richtext') {
        if (cfg.title === undefined) cfg.title = ''
        if (cfg.subtitle === undefined) cfg.subtitle = ''
        if (cfg.content === undefined || cfg.content === null) cfg.content = ''
    }
    if (['ad_slot', 'ad_slot_2', 'ad_slot_3'].includes(type)) {
        if (cfg.title === undefined) cfg.title = ''
        if (cfg.subtitle === undefined) cfg.subtitle = ''
        if (cfg.zone === undefined) cfg.zone = ''
    }
    if (type === 'image_carousel') {
        if (cfg.title === undefined) cfg.title = ''
        if (cfg.subtitle === undefined) cfg.subtitle = ''
        if (cfg.carousel_layout === undefined) cfg.carousel_layout = '3'
        if (cfg.carousel_autoplay === undefined) cfg.carousel_autoplay = '1'
        if (cfg.carousel_time === undefined) cfg.carousel_time = '5'
        if (!Array.isArray(cfg.items)) {
            cfg.items = []
        }
    }
    return cfg
}

const secCfgBool = (type: string, key: string, fallback: boolean): boolean => {
    return normalizeBooleanValue(secCfg(type)[key], fallback)
}

const collapsedSections = ref<Set<string>>(new Set())

function toggleCollapsed(type: string): void {
    const s = new Set(collapsedSections.value)
    if (s.has(type)) s.delete(type)
    else s.add(type)
    collapsedSections.value = s
}

function toggleVisibility(sec: HomepageSectionDef): void {
    const settings = homepageForm.settings as any
    settings[sec.toggleKey] = !settings[sec.toggleKey]
    const s = new Set(collapsedSections.value)
    if (settings[sec.toggleKey]) {
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

const getHomepageSetting = (key: string): any => {
    return (homepageForm.settings as any)[key]
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

// ─── Tools Grid helpers (only for tools-grid variant) ───
const toolsGridSelectedSlugs = computed({
    get: (): string[] => {
        const slugs = secCfg('hero').tools_grid_tool_slugs
        return Array.isArray(slugs) ? (slugs as string[]) : []
    },
    set: (val: string[]) => { secCfg('hero').tools_grid_tool_slugs = val },
})

const toolsGridSelectOptions = computed(() => {
    const all = (props.allTools ?? []) as ToolItem[]
    return all.map((t) => ({ value: t.slug, label: t.name }))
})

function getToolBySlug(slug: string): ToolItem | undefined {
    const all = (props.allTools ?? []) as ToolItem[]
    return all.find((t) => t.slug === slug)
}

function addToolsGridTool(): void {
    const slugs = [...toolsGridSelectedSlugs.value]
    if (slugs.length >= 4) return
    const all = (props.allTools ?? []) as ToolItem[]
    const firstUnused = all.find((t) => !slugs.includes(t.slug))
    slugs.push(firstUnused?.slug ?? '')
    toolsGridSelectedSlugs.value = slugs
}

function removeToolsGridTool(index: number): void {
    const slugs = [...toolsGridSelectedSlugs.value]
    slugs.splice(index, 1)
    toolsGridSelectedSlugs.value = slugs
}

function updateToolsGridTool(index: number, value: string): void {
    const slugs = [...toolsGridSelectedSlugs.value]
    slugs[index] = value
    toolsGridSelectedSlugs.value = slugs
}

// ─── Featured variant helpers ───
const featuredCategoriesSlugs = computed({
    get: (): string[] => {
        const slugs = secCfg('hero').display_categories
        return Array.isArray(slugs) ? (slugs as string[]) : []
    },
    set: (val: string[]) => { secCfg('hero').display_categories = val },
})

const categorySelectOptions = computed(() => {
    return (props.aiCategories ?? []).map((cat) => ({
        value: cat.name ?? cat.slug ?? '',
        label: cat.name ?? cat.slug ?? '',
    })).filter((cat) => cat.value)
})

function getCategoryInfo(name: string): { label: string; icon: string; color: string } {
    const all = (props.allTools ?? []) as ToolItem[]
    const firstTool = all.find((t) => t.category === name)
    return { label: name, icon: firstTool?.icon || 'ti ti-folder', color: firstTool?.color || 'var(--primary)' }
}

function addFeaturedCategory(): void {
    const slugs = [...featuredCategoriesSlugs.value]
    if (slugs.length >= 4) return
    const unused = categorySelectOptions.value.find((c) => !slugs.includes(c.value as string))
    if (unused) slugs.push(unused.value as string)
    featuredCategoriesSlugs.value = slugs
}

function removeFeaturedCategory(index: number): void {
    const slugs = [...featuredCategoriesSlugs.value]
    slugs.splice(index, 1)
    featuredCategoriesSlugs.value = slugs
}

function updateFeaturedCategory(index: number, value: string): void {
    const categories = [...featuredCategoriesSlugs.value]
    categories[index] = value
    featuredCategoriesSlugs.value = categories
}

const featuredToolSlugs = computed({
    get: (): string[] => {
        const slugs = secCfg('hero').display_tool_slugs
        return Array.isArray(slugs) ? (slugs as string[]) : []
    },
    set: (val: string[]) => { secCfg('hero').display_tool_slugs = val },
})

function addFeatureItem(): void {
    const items = secCfg('features').items
    if (!Array.isArray(items)) {
        secCfg('features').items = []
    }
    ;(secCfg('features').items as Array<Record<string, string>>).push({ icon: 'ti ti-sparkles', title: '', description: '' })
}

function removeFeatureItem(index: number): void {
    const items = secCfg('features').items
    if (Array.isArray(items)) {
        items.splice(index, 1)
    }
}

function addHowItWorksStep(): void {
    const items = secCfg('how_it_works').items
    if (!Array.isArray(items)) {
        secCfg('how_it_works').items = []
    }
    ;(secCfg('how_it_works').items as Array<Record<string, string>>).push({ icon: 'ti ti-sparkles', title: '', description: '' })
}

function removeHowItWorksStep(index: number): void {
    const items = secCfg('how_it_works').items
    if (Array.isArray(items)) {
        items.splice(index, 1)
    }
}

function addStatsBarStat(): void {
    const stats = secCfg('stats_bar').stats
    if (!Array.isArray(stats)) {
        secCfg('stats_bar').stats = []
    }
    ;(secCfg('stats_bar').stats as Array<Record<string, string>>).push({ number: '', label: '' })
}

function removeStatsBarStat(index: number): void {
    const stats = secCfg('stats_bar').stats
    if (Array.isArray(stats)) {
        stats.splice(index, 1)
    }
}

function addStatsBarBrand(): void {
    const brands = secCfg('stats_bar').brands
    if (!Array.isArray(brands)) {
        secCfg('stats_bar').brands = []
    }
    ;(secCfg('stats_bar').brands as Array<Record<string, string>>).push({ name: '', image: '' })
}

function removeStatsBarBrand(index: number): void {
    const brands = secCfg('stats_bar').brands
    if (Array.isArray(brands)) {
        brands.splice(index, 1)
    }
    const newFiles: Record<number, File> = {}
    const newPreviews: Record<number, string> = {}

    Object.keys(brandLogoFiles.value).forEach((key) => {
        const k = Number(key)
        if (k < index) {
            newFiles[k] = brandLogoFiles.value[k]
        } else if (k > index) {
            newFiles[k - 1] = brandLogoFiles.value[k]
        }
    })

    Object.keys(brandLogoPreviewUrls.value).forEach((key) => {
        const k = Number(key)
        if (k < index) {
            newPreviews[k] = brandLogoPreviewUrls.value[k]
        } else if (k > index) {
            newPreviews[k - 1] = brandLogoPreviewUrls.value[k]
        } else {
            URL.revokeObjectURL(brandLogoPreviewUrls.value[k])
        }
    })

    brandLogoFiles.value = newFiles
    brandLogoPreviewUrls.value = newPreviews
}

function onBrandLogoInput(index: number, event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    const file = target?.files?.[0] ?? null
    if (file) {
        brandLogoFiles.value[index] = file
        if (brandLogoPreviewUrls.value[index]) {
            URL.revokeObjectURL(brandLogoPreviewUrls.value[index])
        }
        brandLogoPreviewUrls.value[index] = URL.createObjectURL(file)
    }
}

function clearBrandLogoSelection(index: number): void {
    const cfg = secCfg('stats_bar')
    if (cfg.brands?.[index]) {
        cfg.brands[index].image = ''
    }
    delete brandLogoFiles.value[index]
    if (brandLogoPreviewUrls.value[index]) {
        URL.revokeObjectURL(brandLogoPreviewUrls.value[index])
        delete brandLogoPreviewUrls.value[index]
    }
}

const carouselItemFiles = ref<Record<string, File>>({})
const carouselItemPreviewUrls = ref<Record<string, string>>({})

function addCarouselItem(): void {
    const items = secCfg('image_carousel').items
    if (!Array.isArray(items)) {
        secCfg('image_carousel').items = []
    }
    const key = 'key_' + Math.random().toString(36).substring(2, 9)
    ;(secCfg('image_carousel').items as Array<Record<string, any>>).push({ _key: key, title: '', description: '', link_url: '', image_url: '' })
}

function removeCarouselItem(item: any, index: number): void {
    const items = secCfg('image_carousel').items
    if (Array.isArray(items)) {
        items.splice(index, 1)
    }
    if (item._key) {
        delete carouselItemFiles.value[item._key]
        if (carouselItemPreviewUrls.value[item._key]) {
            URL.revokeObjectURL(carouselItemPreviewUrls.value[item._key])
            delete carouselItemPreviewUrls.value[item._key]
        }
    }
}

function onCarouselItemImageInput(item: any, event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    const file = target?.files?.[0] ?? null
    if (file && item._key) {
        carouselItemFiles.value[item._key] = file
        if (carouselItemPreviewUrls.value[item._key]) {
            URL.revokeObjectURL(carouselItemPreviewUrls.value[item._key])
        }
        carouselItemPreviewUrls.value[item._key] = URL.createObjectURL(file)
    }
}

function clearCarouselItemImageSelection(item: any, index: number): void {
    const cfg = secCfg('image_carousel')
    if (cfg.items?.[index]) {
        cfg.items[index].image_url = ''
    }
    if (item._key) {
        delete carouselItemFiles.value[item._key]
        if (carouselItemPreviewUrls.value[item._key]) {
            URL.revokeObjectURL(carouselItemPreviewUrls.value[item._key])
            delete carouselItemPreviewUrls.value[item._key]
        }
    }
}


type HomepageSectionDef = {
    toggleKey: string
    type: string
    label: string
    icon: string
    fields: string[]
}

const orderedSections = ref<HomepageSectionDef[]>([
    { toggleKey: 'show_features', type: 'features', label: t('Features'), icon: 'ti ti-layout-grid', fields: ['title', 'subtitle'] },
    { toggleKey: 'show_tools', type: 'tools_showcase', label: t('Tools Showcase'), icon: 'ti ti-tool', fields: ['title', 'subtitle'] },
    { toggleKey: 'show_steps', type: 'how_it_works', label: t('How It Works'), icon: 'ti ti-route', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_pricing', type: 'pricing', label: t('Pricing'), icon: 'ti ti-credit-card', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_testimonials', type: 'testimonials', label: t('Testimonials'), icon: 'ti ti-message-2-heart', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_faq', type: 'faq', label: t('FAQ'), icon: 'ti ti-help-circle', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_social_proof', type: 'stats_bar', label: t('Social Proof'), icon: 'ti ti-chart-bar', fields: ['heading', 'subheading'] },
    { toggleKey: 'show_cta', type: 'cta_banner', label: t('CTA Banner'), icon: 'ti ti-banner', fields: ['headline', 'subheadline'] },
    { toggleKey: 'show_blog', type: 'latest_posts', label: t('Latest Blog Posts'), icon: 'ti ti-article', fields: ['title', 'subtitle'] },
    { toggleKey: 'show_newsletter', type: 'newsletter', label: t('Newsletter'), icon: 'ti ti-mail-star', fields: ['heading', 'subheading', 'button_text', 'placeholder_text'] },
    { toggleKey: 'show_custom_html', type: 'custom_html', label: t('Custom HTML'), icon: 'ti ti-code', fields: ['heading', 'subheading', 'content'] },
    { toggleKey: 'show_richtext', type: 'richtext', label: t('Rich Text'), icon: 'ti ti-align-left', fields: ['title', 'subtitle', 'content'] },
    { toggleKey: 'show_image_carousel', type: 'image_carousel', label: t('Image Carousel'), icon: 'ti ti-photo-sensor', fields: ['title', 'subtitle'] },
    { toggleKey: 'show_ad_slot', type: 'ad_slot', label: t('Ad Slot 1'), icon: 'ti ti-ad', fields: ['title', 'subtitle', 'zone'] },
    { toggleKey: 'show_ad_slot_2', type: 'ad_slot_2', label: t('Ad Slot 2'), icon: 'ti ti-ad', fields: ['title', 'subtitle', 'zone'] },
    { toggleKey: 'show_ad_slot_3', type: 'ad_slot_3', label: t('Ad Slot 3'), icon: 'ti ti-ad', fields: ['title', 'subtitle', 'zone'] },
])

function onSectionReordered(): void {
    // Update sort_order in form data to match new position
    orderedSections.value.forEach((sec, i) => {
        const cfg = secCfg(sec.type)
        if (cfg) cfg.sort_order = i + 1
    })
}

// Initialize collapsed sections from saved toggle state so disabled sections
// are collapsed on load instead of always showing expanded.
;(() => {
    const initial: string[] = []
    if (!homepageForm.settings?.show_hero) initial.push('hero')
    for (const sec of orderedSections.value) {
        if (!(homepageForm.settings as any)?.[sec.toggleKey]) initial.push(sec.type)
    }
    collapsedSections.value = new Set(initial)
})()

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

function onHeroSplitImageInput(event: Event): void {
    const target = event.currentTarget as HTMLInputElement | null
    heroSplitImageFile.value = target?.files?.[0] ?? null
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
        onSuccess: () => {
            logoLightFile.value = null
            logoDarkFile.value = null
            faviconIcoFile.value = null
            faviconPngFile.value = null
            ogImageFile.value = null
            bodyBgImageFile.value = null
        }
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
        onSuccess: () => {
            paymentIconFile.value = null
        }
    })
}

function saveHomepageSettings(): void {
    const sections: Record<string, Record<string, unknown>> = homepageForm.homepage_config?.sections ?? {}
    const postOptions: Record<string, any> = {
        preserveScroll: true,
        onSuccess: () => {
            heroBackgroundFile.value = null
            heroSplitImageFile.value = null
            brandLogoFiles.value = {}
            brandLogoPreviewUrls.value = {}
            carouselItemFiles.value = {}
            carouselItemPreviewUrls.value = {}
        }
    }
    if (heroBackgroundFile.value || heroSplitImageFile.value || Object.keys(brandLogoFiles.value).length > 0 || Object.keys(carouselItemFiles.value).length > 0) {
        postOptions.forceFormData = true
    }
    homepageForm.transform((data) => {
        const mappedSections = Object.keys(sections).map((type) => ({ type, config: sections[type] }))
        // Ensure featured config keys are always present in the hero section
        for (const sec of mappedSections) {
            if (sec.type === 'hero' && sec.config) {
                sec.config.display_categories = sec.config.display_categories ?? []
                sec.config.display_tool_slugs = sec.config.display_tool_slugs ?? []
            }
        }

        const filesPayload: Record<string, File> = {}
        Object.keys(brandLogoFiles.value).forEach((key) => {
            const idx = Number(key)
            if (brandLogoFiles.value[idx]) {
                filesPayload[`brand_logo_${idx}`] = brandLogoFiles.value[idx]
            }
        })
        const items = secCfg('image_carousel').items
        if (Array.isArray(items)) {
            items.forEach((item: any, idx: number) => {
                if (item._key && carouselItemFiles.value[item._key]) {
                    filesPayload[`carousel_item_image_${idx}`] = carouselItemFiles.value[item._key]
                }
            })
        }

        return {
            ...data,
            ...filesPayload,
            ...(heroBackgroundFile.value ? { hero_background_file: heroBackgroundFile.value } : {}),
            ...(heroSplitImageFile.value ? { hero_split_image_file: heroSplitImageFile.value } : {}),
            homepage_config: {
                ...(homepageForm.homepage_config as Record<string, unknown>),
                sections: mappedSections,
            },
        }
    }).post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), postOptions)
}

function saveCustomCodeSettings(): void {
    customCodeForm.post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), { preserveScroll: true })
}

function saveToolPageSettings(): void {
    toolPageForm.post(route('admin.themes.settings.simple.save', { slug: props.theme.slug }), { preserveScroll: true })
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

    if (activeTab.value === 'page') {
        saveToolPageSettings()
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
    page: 'tool_page',
    custom_code: 'custom_code',
}

const isRestoreModalOpen = ref(false)
const sectionToRestore = ref('')
const activeTabLabel = computed(() => {
    const currentTab = tabs.find(tab => tab.id === activeTab.value)
    return currentTab ? t(currentTab.label) : ''
})

function confirmRestoreDefaults(): void {
    sectionToRestore.value = tabSectionMap[activeTab.value] || 'theme'
    isRestoreModalOpen.value = true
}

function handleConfirmRestore(): void {
    isRestoreModalOpen.value = false
    router.post(route('admin.themes.settings.restore-defaults', { slug: props.theme.slug }), {
        section: sectionToRestore.value,
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
    if (activeTab.value === 'page') return toolPageForm.processing

    return customCodeForm.processing
})

const pageScrollY = ref(0)
const showFloatingSaveButton = computed(() => pageScrollY.value > 180)

function syncPageScroll(): void {
    if (typeof window === 'undefined') return
    pageScrollY.value = window.scrollY || window.pageYOffset || 0
}

onMounted(() => {
    syncPageScroll()
    window.addEventListener('scroll', syncPageScroll, { passive: true })
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') return
    window.removeEventListener('scroll', syncPageScroll)
})

watch(() => [
    props.settings.site_logo_light,
    props.settings.site_logo_dark,
    props.settings.site_favicon_ico,
    props.settings.site_favicon_png,
    props.settings.site_og_image,
    props.settings.bg_image,
], () => {
    themeForm.settings.site_logo_light = props.settings.site_logo_light ?? ''
    themeForm.settings.site_logo_dark = props.settings.site_logo_dark ?? ''
    themeForm.settings.site_favicon_ico = props.settings.site_favicon_ico ?? ''
    themeForm.settings.site_favicon_png = props.settings.site_favicon_png ?? ''
    themeForm.settings.site_og_image = props.settings.site_og_image ?? ''
    themeForm.settings.bg_image = props.settings.bg_image ?? ''
})

watch(() => props.frontendFooterSettings.payment_icons, (newVal) => {
    footerForm.settings.payment_icons = normalizePaymentIcon(newVal)
})

watch(() => [props.frontendHomepageConfig, props.frontendHomepageSettings], () => {
    homepageForm.homepage_config = {
        settings: JSON.parse(JSON.stringify(resolvedHomepageConfig.value.settings)),
        sections: JSON.parse(JSON.stringify(resolvedHomepageConfig.value.sections)),
    }

    const defaults = resolvedHomepageDefaults.value
    homepageForm.settings.show_hero = normalizeBooleanValue(defaults.show_hero, true)
    homepageForm.settings.hero_variant = defaults.hero_variant ?? 'centered-gradient'
    homepageForm.settings.show_social_proof = normalizeBooleanValue(defaults.show_social_proof, true)
    homepageForm.settings.show_features = normalizeBooleanValue(defaults.show_features, true)
    homepageForm.settings.show_tools = normalizeBooleanValue(defaults.show_tools, true)
    homepageForm.settings.show_steps = normalizeBooleanValue(defaults.show_steps, true)
    homepageForm.settings.show_pricing = normalizeBooleanValue(defaults.show_pricing, false)
    homepageForm.settings.show_testimonials = normalizeBooleanValue(defaults.show_testimonials, true)
    homepageForm.settings.show_faq = normalizeBooleanValue(defaults.show_faq, true)
    homepageForm.settings.show_cta = normalizeBooleanValue(defaults.show_cta, true)
    homepageForm.settings.show_blog = normalizeBooleanValue(defaults.show_blog, true)
    homepageForm.settings.show_newsletter = normalizeBooleanValue(defaults.show_newsletter, true)
    homepageForm.settings.show_custom_html = normalizeBooleanValue(defaults.show_custom_html, false)
    homepageForm.settings.show_richtext = normalizeBooleanValue(defaults.show_richtext, false)
    homepageForm.settings.show_ad_slot = normalizeBooleanValue(defaults.show_ad_slot, false)
    homepageForm.settings.show_ad_slot_2 = normalizeBooleanValue(defaults.show_ad_slot_2, false)
    homepageForm.settings.show_ad_slot_3 = normalizeBooleanValue(defaults.show_ad_slot_3, false)
    homepageForm.settings.show_image_carousel = normalizeBooleanValue(defaults.show_image_carousel, false)
}, { deep: true })

watch(() => [
    homepageForm.homepage_config?.sections?.hero?.show_hero_background,
    homepageForm.homepage_config?.sections?.hero?.hero_gradient_enabled,
    homepageForm.homepage_config?.sections?.hero?.hero_gradient_palette
], ([bg, grad, palette]) => {
    const isBgEnabled = bg !== undefined ? normalizeBooleanValue(bg, false) : false
    const isGradEnabled = grad !== undefined ? normalizeBooleanValue(grad, true) : true
    const isLightGradient = typeof palette === 'string' && ['light_glow', 'light_warm'].includes(palette.trim())
    if ((!isBgEnabled && !isGradEnabled) || (!isBgEnabled && isGradEnabled && isLightGradient)) {
        if (headerForm.settings?.desktop) {
            headerForm.settings.desktop.transparent_on_hero = false
        }
    }
})
</script>

<template>
    <Head :title="t('Theme Settings')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-4 border-b border-gray-100 pb-4 dark:border-surface-800 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Theme Settings') }}</h1>
                    <span class="rounded-full bg-primary-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ props.theme.name }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage frontend site appearance and basic settings.') }}</p>
            </div>
            <div class="flex items-center gap-3 self-start">
                <Link
                    :href="route('admin.themes')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-900"
                >
                    <i class="ti ti-arrow-left mr-1"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-danger-200 bg-white px-4 py-2 text-sm font-medium text-danger-600 transition-colors hover:bg-danger-50 dark:border-danger-800 dark:bg-danger-950/20 dark:text-danger-400 dark:hover:bg-danger-950/40"
                    @click="confirmRestoreDefaults"
                    :title="t('Restore defaults')"
                >
                    <i class="ti ti-restore text-base"></i>
                    {{ t('Restore') }}
                </button>
                <button
                    type="button"
                    :disabled="isSaving"
                    class="btn-primary inline-flex items-center gap-2 rounded-lg disabled:opacity-60"
                    @click="saveActiveTab"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ isSaving ? t('Saving...') : t('Save') }}
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
                            ? 'bg-primary-50 font-semibold text-primary-700 shadow-sm dark:bg-primary-950/40 dark:text-primary-300'
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
                                <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onLogoLightInput" />
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Logo Dark') }}</h3>
                                <div v-if="themeForm.settings.site_logo_dark" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_logo_dark)" alt="Dark logo" class="h-10 max-w-[160px] object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_logo_dark = ''; logoDarkFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onLogoDarkInput" />
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Favicon ICO') }}</h3>
                                <div v-if="themeForm.settings.site_favicon_ico" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_favicon_ico)" alt="Favicon ICO" class="h-8 w-8 object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_favicon_ico = ''; faviconIcoFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept=".ico,image/x-icon" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onFaviconIcoInput" />
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Favicon PNG') }}</h3>
                                <div v-if="themeForm.settings.site_favicon_png" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_favicon_png)" alt="Favicon PNG" class="h-8 w-8 object-contain" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_favicon_png = ''; faviconPngFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onFaviconPngInput" />
                            </div>
                        </div>
                        <div class="mt-5 grid gap-5">
                            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Open Graph Image') }}</h3>
                                <div v-if="themeForm.settings.site_og_image" class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.site_og_image)" alt="OG Image" class="h-12 max-w-[200px] rounded-lg object-cover" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.site_og_image = ''; ogImageFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png,image/jpeg,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onOgImageInput" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Theme Mode & Experience') }}</h2>
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
                                <button type="button" role="switch" :aria-checked="themeForm.settings.smooth_scroll" class="app-switch" @click="themeForm.settings.smooth_scroll = !themeForm.settings.smooth_scroll">
                                    <span class="app-switch__thumb"></span>
                                </button>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Smooth scroll') }}</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                <button type="button" role="switch" :aria-checked="themeForm.settings.show_back_to_top" class="app-switch" @click="themeForm.settings.show_back_to_top = !themeForm.settings.show_back_to_top">
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
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200/60 p-4 dark:border-surface-800">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Use background image') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Enable to use a custom background image on frontend pages.') }}</p>
                                </div>
                                <button type="button" role="switch" :aria-checked="themeForm.settings.bg_image_enabled" class="app-switch shrink-0" @click="themeForm.settings.bg_image_enabled = !themeForm.settings.bg_image_enabled">
                                    <span class="app-switch__thumb"></span>
                                </button>
                            </div>

                            <div v-if="themeForm.settings.bg_image_enabled" class="space-y-4">
                                <div v-if="themeForm.settings.bg_image" class="flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <img :src="fileUrl(themeForm.settings.bg_image)" :alt="t('Body background preview')" class="h-12 w-full rounded-lg object-cover" />
                                    <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="themeForm.settings.bg_image = ''; bodyBgImageFile = null">{{ t('Remove') }}</button>
                                </div>
                                <input type="file" accept="image/png,image/jpeg,image/webp,image/avif" class="w-full rounded-lg border border-gray-200/60 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onBodyBgImageInput" />
                            </div>
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
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Choose the menu placement, hover behavior, and overall header sizing.') }}</p>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <AppSelect v-model="headerForm.settings.desktop.menu_position" :label="t('Menu Position')" :options="headerMenuPositionOptions" />
                                    <AppSelect v-model="headerForm.settings.desktop.menu_hover_style" :label="t('Menu Hover Style')" :options="headerMenuHoverStyleOptions" />
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
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Colors') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('These colors affect only the main desktop header area.') }}</p>
                                </div>
                                <div class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Transparent Over Hero') }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the header transparent while the homepage hero is visible, then restore this background color after scrolling past it.') }}</p>
                                    </div>
                                    <button type="button" role="switch" :aria-checked="headerForm.settings.desktop.transparent_on_hero" class="app-switch shrink-0" @click="headerForm.settings.desktop.transparent_on_hero = !headerForm.settings.desktop.transparent_on_hero">
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

                        <div class="mt-6 grid gap-6 xl:grid-cols-2">
                            <section class="rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Header Elements') }}</h3>
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
                        </div>

                        <section class="mt-6 rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Custom Button') }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Highlight one main action such as register, contact, or pricing.') }}</p>
                                </div>
                                <button type="button" role="switch" :aria-checked="headerForm.settings.desktop.show_cta_button" class="app-switch" @click="headerForm.settings.desktop.show_cta_button = !headerForm.settings.desktop.show_cta_button">
                                    <span class="app-switch__thumb"></span>
                                </button>
                            </div>
                            <div v-if="headerForm.settings.desktop.show_cta_button" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom Text') }}<input v-model="headerForm.settings.desktop.cta_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" /></label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom Link') }}<input v-model="headerForm.settings.desktop.cta_link" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" /></label>
                                <IconClassSelect v-model="headerForm.settings.desktop.cta_icon" :label="t('Custom Icon')" />
                                <AppSelect v-model="headerForm.settings.desktop.cta_style" :label="t('Custom Button Style')" :options="headerButtonStyleOptions" />
                                <AppSelect v-model="headerForm.settings.desktop.cta_shape" :label="t('Custom Button Shape')" :options="headerButtonShapeOptions" />
                                <AppSelect v-model="headerForm.settings.desktop.cta_access_level" :label="t('Button Access Level')" :options="accessLevelOptions" />
                            </div>
                            <p v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">{{ t('Enable this to show a main action button in the desktop header.') }}</p>
                        </section>
                    </section>

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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Header Height (px)') }}
                                <input v-model.number="headerForm.settings.mobile_top.height" type="number" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <AppSelect v-model="headerForm.settings.mobile_top.show_shadow" :label="t('Show Shadow')" :options="mobileTopShadowOptions" />
                            <AppSelect v-model="headerForm.settings.mobile_top.sticky_behavior" :label="t('Sticky Behavior')" :options="headerStickyBehaviorOptions" />
                            <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2">
                                <AppColorPicker v-model="headerForm.settings.mobile_top.bg_color" :label="t('Background Color')" />
                                <AppColorPicker v-model="headerForm.settings.mobile_top.text_color" :label="t('Text Color')" />
                            </div>
                            <div class="sm:col-span-2 border-t border-gray-100 pt-4 dark:border-surface-800">
                                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Top Header Elements') }}</h3>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Logo') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_logo" class="app-switch" @click="headerForm.settings.mobile_top.show_logo = !headerForm.settings.mobile_top.show_logo"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Hamburger') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_hamburger" class="app-switch" @click="headerForm.settings.mobile_top.show_hamburger = !headerForm.settings.mobile_top.show_hamburger"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Dark Mode Toggle') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_dark_mode_toggle" class="app-switch" @click="headerForm.settings.mobile_top.show_dark_mode_toggle = !headerForm.settings.mobile_top.show_dark_mode_toggle"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Notification Bell') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_notification_bell" class="app-switch" @click="headerForm.settings.mobile_top.show_notification_bell = !headerForm.settings.mobile_top.show_notification_bell"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Search Icon') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_search_icon" class="app-switch" @click="headerForm.settings.mobile_top.show_search_icon = !headerForm.settings.mobile_top.show_search_icon"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Language Switcher') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_language_switcher" class="app-switch" @click="headerForm.settings.mobile_top.show_language_switcher = !headerForm.settings.mobile_top.show_language_switcher"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Login Button') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_login" class="app-switch" @click="headerForm.settings.mobile_top.show_login = !headerForm.settings.mobile_top.show_login"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show CTA Button') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_top.show_cta_button" class="app-switch" @click="headerForm.settings.mobile_top.show_cta_button = !headerForm.settings.mobile_top.show_cta_button"><span class="app-switch__thumb"></span></button></div>
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
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Enable Mobile Bottom Header') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.enabled" class="app-switch" @click="headerForm.settings.mobile_bottom.enabled = !headerForm.settings.mobile_bottom.enabled"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Hide Menu Labels') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.hide_menu_labels" class="app-switch" @click="headerForm.settings.mobile_bottom.hide_menu_labels = !headerForm.settings.mobile_bottom.hide_menu_labels"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Glassmorphism Effect') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_glassmorphism" class="app-switch" @click="headerForm.settings.mobile_bottom.show_glassmorphism = !headerForm.settings.mobile_bottom.show_glassmorphism"><span class="app-switch__thumb"></span></button></div>

                            <div class="sm:col-span-2 border-t border-gray-100 pt-4 dark:border-surface-800">
                                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Bottom Header Elements') }}</h3>
                            </div>

                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Home') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_home" class="app-switch" @click="headerForm.settings.mobile_bottom.show_home = !headerForm.settings.mobile_bottom.show_home"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Search Icon') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_search_icon" class="app-switch" @click="headerForm.settings.mobile_bottom.show_search_icon = !headerForm.settings.mobile_bottom.show_search_icon"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Tools') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_tools" class="app-switch" @click="headerForm.settings.mobile_bottom.show_tools = !headerForm.settings.mobile_bottom.show_tools"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Notification Bell') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_notification_bell" class="app-switch" @click="headerForm.settings.mobile_bottom.show_notification_bell = !headerForm.settings.mobile_bottom.show_notification_bell"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Hamburger') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_hamburger" class="app-switch" @click="headerForm.settings.mobile_bottom.show_hamburger = !headerForm.settings.mobile_bottom.show_hamburger"><span class="app-switch__thumb"></span></button></div>
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800"><span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Login/Dashboard') }}</span><button type="button" role="switch" :aria-checked="headerForm.settings.mobile_bottom.show_profile" class="app-switch" @click="headerForm.settings.mobile_bottom.show_profile = !headerForm.settings.mobile_bottom.show_profile"><span class="app-switch__thumb"></span></button></div>
                        </div>
                    </section>
                </div>

            <div v-else-if="activeTab === 'footer'" class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Footer Style') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Choose one ready-made footer style, then control its content and appearance from the settings below.') }}</p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                            <button
                                v-for="option in footerLayoutOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-2xl border p-3 text-left transition"
                                :class="footerForm.settings.layout === option.value
                                    ? 'border-primary-300 bg-primary-50 shadow-sm dark:border-primary-500/40 dark:bg-primary-500/10'
                                    : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/50 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-500/30 dark:hover:bg-primary-500/5'"
                                @click="footerForm.settings.layout = option.value"
                            >
                                <div class="mb-3 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-950">
                                    <img :src="option.preview" :alt="option.label" class="h-24 w-full object-cover" />
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ option.label }}</h3>
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[11px]"
                                        :class="footerForm.settings.layout === option.value
                                            ? 'border-primary-500 bg-primary-500 text-white'
                                            : 'border-gray-300 text-transparent dark:border-surface-600'"
                                    >
                                        <i class="ti ti-check"></i>
                                    </span>
                                </div>
                            </button>
                        </div>
                        <div class="mt-6 grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.show_newsletter" class="app-switch" @click="footerForm.settings.show_newsletter = !footerForm.settings.show_newsletter">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Newsletter Section') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.show_social_icons" class="app-switch" @click="footerForm.settings.show_social_icons = !footerForm.settings.show_social_icons">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Social Links') }}</span>
                                </div>
                                <div v-if="footerForm.settings.layout !== 'default' && footerForm.settings.layout !== 'floating_panel' && footerForm.settings.layout !== 'card_grid'" class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerLogoVisibilityToggle" class="app-switch" @click="footerLogoVisibilityToggle = !footerLogoVisibilityToggle">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ footerLogoVisibilityLabel }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.disable_card_style" class="app-switch" @click="footerForm.settings.disable_card_style = !footerForm.settings.disable_card_style">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Disable Card Style') }}</span>
                                </div>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <AppSelect v-model="footerForm.settings.container_width" :label="t('Container Width')" :options="containerWidthOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                    <p v-if="footerForm.settings.layout === 'card_grid'" class="mt-1 text-xs leading-6 text-gray-400">
                                        {{ t('Card Grid applies this width to the main footer only. The newsletter section always uses the default container width.') }}
                                    </p>
                                </div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Vertical Padding') }}
                                    <input v-model.number="footerForm.settings.footer_vertical_padding" type="number" min="24" max="120" step="4" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                </label>
                                <AppColorPicker v-model="footerForm.settings.footer_bg_color" :label="t('Footer Main Background')" />
                                <AppColorPicker v-model="footerForm.settings.footer_text_color" :label="t('Text Color')" />
                                <AppColorPicker v-model="footerForm.settings.footer_heading_color" :label="t('Heading Color')" />
                                <AppSelect
                                    v-model="footerForm.settings.footer_heading_text_case"
                                    :label="t('Heading Text Case')"
                                    :options="[
                                        { value: '', label: t('Default') },
                                        { value: 'lowercase', label: t('lowercase') },
                                        { value: 'uppercase', label: t('UPPERCASE') },
                                        { value: 'capitalize', label: t('Capitalize') },
                                    ]"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">{{ t('Style Column Items') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('For the selected footer style, choose which reusable content block should appear in each column or card.') }}</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                            <div v-for="column in activeFooterStyleColumns" :key="column.key" class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <AppSelect
                                    v-model="footerForm.settings.style_columns[footerForm.settings.layout][column.key]"
                                    :label="column.label"
                                    :options="activeFooterContentItemOptions"
                                    :multiple="true"
                                    :compact-multiple="true"
                                    :live-search="true"
                                />
                                <p class="mt-3 text-xs leading-6 text-gray-400">{{ t('Choose one or more content blocks to stack in this area for the selected footer style.') }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="mb-6 space-y-1">
                            <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Reusable Footer Content') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('These content cards are shared across all footer styles. Pick them in the style column section above wherever you want them to appear.') }}</p>
                        </div>
                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('About Text') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to the About Text block.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('About Heading') }}
                                        <input v-model="footerForm.settings.brand_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Trusted AI platform for content, chat, and automation')" />
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('About Text') }}
                                        <textarea v-model="footerForm.settings.brand_description" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Help visitors understand what your platform does and why they should trust it.')"></textarea>
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Menu Links 1') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Menu Links 1.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <AppSelect v-model="footerForm.settings.menu_column_1" :label="t('Menu')" :options="menuOptions" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Menu Heading') }}
                                        <input v-model="footerForm.settings.menu_title_1" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Explore')" />
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Menu Links 2') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Menu Links 2.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <AppSelect v-model="footerForm.settings.menu_column_2" :label="t('Menu')" :options="menuOptions" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Menu Heading') }}
                                        <input v-model="footerForm.settings.menu_title_2" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Resources')" />
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Menu Links 3') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Menu Links 3.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <AppSelect v-model="footerForm.settings.menu_column_3" :label="t('Menu')" :options="menuOptions" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Menu Heading') }}
                                        <input v-model="footerForm.settings.menu_title_3" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Company')" />
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Contact Info') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Contact Info.') }}</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Contact Heading') }}
                                        <input v-model="footerForm.settings.contact_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Talk to our team')" />
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
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Newsletter') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a style shows the newsletter band or a column is assigned to Newsletter.') }}</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Newsletter Heading') }}
                                        <input v-model="footerForm.settings.newsletter_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Join our newsletter')" />
                                    </label>
                                    <AppSelect v-model="footerForm.settings.newsletter_button_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                        {{ t('Newsletter Text') }}
                                        <textarea v-model="footerForm.settings.newsletter_description" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Offer updates, promotions, or feature releases to encourage signups.')"></textarea>
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Email Field Placeholder') }}
                                        <input v-model="footerForm.settings.newsletter_placeholder" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Enter your work email')" />
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Button Text') }}
                                        <input v-model="footerForm.settings.newsletter_button_label" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Subscribe Now')" />
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Custom Text 1') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Custom Text 1.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Custom Heading') }}
                                        <input v-model="footerForm.settings.custom_title_1" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Why buyers choose us')" />
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Custom Text') }}
                                        <textarea v-model="footerForm.settings.custom_text_1" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Add a short trust-building message, offer summary, or extra product guidance for visitors.')"></textarea>
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Custom Text 2') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Custom Text 2.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Custom Heading') }}
                                        <input v-model="footerForm.settings.custom_title_2" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('More reasons to choose us')" />
                                    </label>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Custom Text') }}
                                        <textarea v-model="footerForm.settings.custom_text_2" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Add another trust-building note, support message, or product highlight.')"></textarea>
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Tool Categories 1') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Tool Categories 1.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Block Heading') }}
                                        <input v-model="footerForm.settings.tool_categories_title_1" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Popular tool categories')" />
                                    </label>
                                    <AppSelect
                                        v-model="footerForm.settings.tool_categories_items_1"
                                        :label="t('Categories')"
                                        :options="aiCategoryOptions"
                                        :multiple="true"
                                        :compact-multiple="true"
                                        :live-search="true"
                                    />
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-700">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Tool Categories 2') }}</h3>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">{{ t('Used when a column is assigned to Tool Categories 2.') }}</p>
                                </div>
                                <div class="grid gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Block Heading') }}
                                        <input v-model="footerForm.settings.tool_categories_title_2" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Explore AI categories')" />
                                    </label>
                                    <AppSelect
                                        v-model="footerForm.settings.tool_categories_items_2"
                                        :label="t('Categories')"
                                        :options="aiCategoryOptions"
                                        :multiple="true"
                                        :compact-multiple="true"
                                        :live-search="true"
                                    />
                                </div>
                            </div>
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
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.show_bottom_social_icons" class="app-switch" @click="footerForm.settings.show_bottom_social_icons = !footerForm.settings.show_bottom_social_icons">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Social Icons') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.show_bottom_language_selector" class="app-switch" @click="footerForm.settings.show_bottom_language_selector = !footerForm.settings.show_bottom_language_selector">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Language Selector') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.show_payment_icons" class="app-switch" @click="footerForm.settings.show_payment_icons = !footerForm.settings.show_payment_icons">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Payment Methods') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.show_back_to_top" class="app-switch" @click="footerForm.settings.show_back_to_top = !footerForm.settings.show_back_to_top">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Scroll To Top') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.bottom_bar_show_border" class="app-switch" @click="footerForm.settings.bottom_bar_show_border = !footerForm.settings.bottom_bar_show_border">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Top Border') }}</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                    <button type="button" role="switch" :aria-checked="footerForm.settings.bottom_bar_centered" class="app-switch" @click="footerForm.settings.bottom_bar_centered = !footerForm.settings.bottom_bar_centered">
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Center Align Bottom Bar') }}</span>
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
                                    <p class="mt-2 text-xs leading-6 text-gray-400">{{ t('Available shortcodes: {copyright}, {year}, {site_name}. Example: {copyright} {year} {site_name}. All rights reserved.') }}</p>
                                </label>
                                <label v-if="footerForm.settings.show_payment_icons" class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Payment Methods') }}
                                    <div v-if="footerForm.settings.payment_icons || paymentIconPreviewUrl" class="mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900">
                                        <img v-if="paymentIconPreviewSrc()" :src="paymentIconPreviewSrc()" :alt="t('Payment method preview')" class="h-12 max-w-full object-contain" />
                                        <span v-else class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-gray-50 px-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ footerForm.settings.payment_icons }}</span>
                                    </div>
                                    <input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/avif" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onPaymentIconsInput" />
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
                                <button type="button" role="switch" :aria-checked="homepageForm.settings.show_hero" class="app-switch" @click="toggleHeroVisibility"><span class="app-switch__thumb"></span></button>
                            </div>
                        </div>
                        <div v-if="!collapsedSections.has('hero')" class="p-5 space-y-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Select Style') }}</label>
                            <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 2xl:grid-cols-6">
                                <button type="button"
                                    v-for="variant in [
                                        { value: 'centered-gradient', label: t('Centered'), desc: t('Centered headline on gradient') },
                                        { value: 'tools-grid', label: t('Tools Grid'), desc: t('Featured tools in grid') },
                                        { value: 'split-gradient', label: t('Split'), desc: t('Split content with overlay') },
                                        { value: 'app-showcase', label: t('Showcase'), desc: t('Dashboard preview with app mockup') },
                                        { value: 'enterprise', label: t('Enterprise'), desc: t('Corporate style') },
                                        { value: 'featured', label: t('Featured'), desc: t('Category grid with featured tools carousel') },
                                    ]"
                                    :key="variant.value"
                                    class="rounded-2xl border p-3 text-left transition"
                                    :class="homepageForm.settings.hero_variant === variant.value
                                        ? 'border-primary-300 bg-primary-50 shadow-sm dark:border-primary-500/40 dark:bg-primary-500/10'
                                        : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/50 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-500/30 dark:hover:bg-primary-500/5'"
                                    @click="homepageForm.settings.hero_variant = variant.value"
                                >
                                    <div class="mb-3 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-950">
                                        <img :src="heroStylePreview(variant.value)" :alt="variant.label" class="h-24 w-full object-cover" />
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ variant.label }}</h3>
                                        <span
                                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[11px]"
                                            :class="homepageForm.settings.hero_variant === variant.value
                                                ? 'border-primary-500 bg-primary-500 text-white'
                                                : 'border-gray-300 text-transparent dark:border-surface-600'"
                                        >
                                            <i class="ti ti-check"></i>
                                        </span>
                                    </div>
                                    <div class="mt-1 text-[10px] text-gray-400 leading-normal">{{ variant.desc }}</div>
                                </button>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Headline') }}
                                    <input v-model="secCfg('hero').headline" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                    <p class="mt-1 text-xs text-gray-400">{{ t('Use | to enable typewriter effect (e.g. Create with | AI Writer | AI Images) & Use / to newline.') }}</p>
                                </label>
                            </div>

                            <!-- Primary CTA Button Configuration -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Primary CTA Button') }}</h4>
                                    <button type="button" role="switch" :aria-checked="secCfgBool('hero', 'show_primary_cta', true)" class="app-switch" @click="secCfg('hero').show_primary_cta = !secCfgBool('hero', 'show_primary_cta', true)"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfgBool('hero', 'show_primary_cta', true)">
                                    <div class="mb-4 grid gap-4 grid-cols-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-3">
                                                {{ t('Button Text') }}
                                            </label>
                                            <input v-model="secCfg('hero').primary_cta_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-3">
                                                {{ t('Button Link') }}
                                            </label>
                                            <input v-model="secCfg('hero').primary_cta_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            <p class="mt-1 text-xs text-gray-400">{{ t('Paste a YouTube URL or video ID to open a video popup instead of navigating') }}</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <AppSelect v-model="secCfg('hero').primary_cta_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                        <AppSelect v-model="secCfg('hero').primary_cta_shape" :label="t('Button Shape')" :options="headerButtonShapeOptions" />
                                        <AppSelect v-model="secCfg('hero').primary_cta_size" :label="t('Button Size')" :options="[{ value: 'sm', label: t('Small') }, { value: 'md', label: t('Medium') }, { value: 'lg', label: t('Large') }, { value: 'xl', label: t('Extra Large') }]" />
                                        <IconClassSelect v-model="secCfg('hero').primary_cta_icon" :label="t('Button Icon')" />
                                        <AppSelect v-model="secCfg('hero').primary_cta_icon_position" :label="t('Icon Position')" :options="[{ value: 'left', label: t('Left') }, { value: 'right', label: t('Right') }]" />
                                        <AppSelect v-model="secCfg('hero').primary_cta_access_level" :label="t('Access Level')" :options="accessLevelOptions" />
                                    </div>
                                </div>
                            </div>

                            <!-- Secondary CTA Button Configuration -->
                            <div v-if="homepageForm.settings.hero_variant !== 'tools-grid'" class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Secondary CTA Button') }}</h4>
                                    <button type="button" role="switch" :aria-checked="secCfgBool('hero', 'show_secondary_cta', true)" class="app-switch" @click="secCfg('hero').show_secondary_cta = !secCfgBool('hero', 'show_secondary_cta', true)"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfgBool('hero', 'show_secondary_cta', true)">
                                    <div class="mb-4 grid gap-4 grid-cols-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-3">
                                                {{ t('Button Text') }}
                                            </label>
                                            <input v-model="secCfg('hero').secondary_cta_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-3">
                                                {{ t('Button Link') }}
                                            </label>
                                            <input v-model="secCfg('hero').secondary_cta_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                        </div>
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <AppSelect v-model="secCfg('hero').secondary_cta_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                        <AppSelect v-model="secCfg('hero').secondary_cta_shape" :label="t('Button Shape')" :options="headerButtonShapeOptions" />
                                        <AppSelect v-model="secCfg('hero').secondary_cta_size" :label="t('Button Size')" :options="[{ value: 'sm', label: t('Small') }, { value: 'md', label: t('Medium') }, { value: 'lg', label: t('Large') }, { value: 'xl', label: t('Extra Large') }]" />
                                        <IconClassSelect v-model="secCfg('hero').secondary_cta_icon" :label="t('Button Icon')" />
                                        <AppSelect v-model="secCfg('hero').secondary_cta_icon_position" :label="t('Icon Position')" :options="[{ value: 'left', label: t('Left') }, { value: 'right', label: t('Right') }]" />
                                        <AppSelect v-model="secCfg('hero').secondary_cta_access_level" :label="t('Access Level')" :options="accessLevelOptions" />
                                    </div>
                                </div>
                            </div>

                            <!-- Search Box -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Search Box') }}</h4>
                                    <button type="button" role="switch" :aria-checked="secCfgBool('hero', 'show_search_box', true)" class="app-switch" @click="secCfg('hero').show_search_box = !secCfgBool('hero', 'show_search_box', true)"><span class="app-switch__thumb"></span></button>
                                </div>
                                <p class="text-xs text-gray-400">{{ t('Show a pill-style search button that opens the command palette.') }}</p>
                            </div>

                            <!-- Background Image / Video -->
                            <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Background Image / Video') }}</h4>
                                    <button type="button" role="switch" :aria-checked="secCfgBool('hero', 'show_hero_background', false)" class="app-switch" @click="secCfg('hero').show_hero_background = !secCfgBool('hero', 'show_hero_background', false)"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfgBool('hero', 'show_hero_background', false)" class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="secCfg('hero').hero_background_type" :label="t('Media Type')" :options="[{ value: 'image', label: t('Image') }, { value: 'video', label: t('Video') }]" />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Choose File') }}
                                        <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/avif,video/mp4,video/webm,video/ogg" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onHeroBackgroundInput" />
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
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Gradient Colors') }}</h4>
                                    <button type="button" role="switch" :aria-checked="secCfgBool('hero', 'hero_gradient_enabled', true)" class="app-switch" @click="secCfg('hero').hero_gradient_enabled = !secCfgBool('hero', 'hero_gradient_enabled', true)"><span class="app-switch__thumb"></span></button>
                                </div>
                                <p class="mb-3 text-xs text-gray-400">{{ t('Applied to centered-gradient, tools-grid, split-gradient, app-showcase, enterprise, and featured layouts. Light gradients keep text dark, dark gradients use white text.') }}</p>
                                <div v-if="secCfgBool('hero', 'hero_gradient_enabled', true)" class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="secCfg('hero').hero_gradient_palette" :label="t('Gradient Palette')" :options="gradientPaletteOptions.map((o) => ({ value: o.value, label: t(o.label) }))" />
                                    <AppSelect v-model="secCfg('hero').hero_gradient_direction" :label="t('Gradient Direction')" :options="gradientDirectionOptions.map((o) => ({ value: o.value, label: t(o.label) }))" />
                                </div>
                            </div>

                            <!-- Visual Image (for split-gradient and app-showcase) -->
                            <div v-if="homepageForm.settings.hero_variant === 'split-gradient' || homepageForm.settings.hero_variant === 'app-showcase'" class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ homepageForm.settings.hero_variant === 'app-showcase' ? t('Dashboard Preview Image') : t('Right Side Image') }}</h4>
                                </div>
                                <p class="mb-3 text-xs text-gray-400">{{ homepageForm.settings.hero_variant === 'app-showcase' ? t('Upload a dashboard screenshot to display in the mockup browser frame. Replaces the built-in dashboard preview.') : t('Upload an image to display on the right side of the split-gradient hero. Replaces the stats card.') }}</p>
                                <div class="grid gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Choose Image') }}
                                        <input type="file" accept="image/png,image/svg+xml" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onHeroSplitImageInput" />
                                    </label>
                                    <div v-if="secCfg('hero').hero_split_image_url" class="flex items-center gap-2 rounded-lg border border-dashed border-success-200 bg-success-50 px-3 py-2 text-xs text-success-700 dark:border-success-800 dark:bg-success-900/20 dark:text-success-300">
                                        <i class="ti ti-check"></i>
                                        <span class="flex-1 truncate">{{ secCfg('hero').hero_split_image_url }}</span>
                                        <button type="button" class="shrink-0 font-medium text-danger-500 hover:underline" @click="secCfg('hero').hero_split_image_url = ''; heroSplitImageFile = null">{{ t('Remove') }}</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Featured — Categories & Tools (only for featured variant) -->
                            <div v-if="homepageForm.settings.hero_variant === 'featured'" class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Featured Categories') }}</h4>
                                </div>
                                <p class="mb-3 text-xs text-gray-400">{{ t('Select up to 4 categories to show in the grid. If none selected, categories are derived from your tools.') }}</p>
                                <div class="space-y-2">
                                    <div v-for="(slug, idx) in featuredCategoriesSlugs" :key="idx" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                            :style="getCategoryInfo(slug).color && getCategoryInfo(slug).color !== 'var(--primary)' ? { background: getCategoryInfo(slug).color + '20', color: getCategoryInfo(slug).color } : { background: 'color-mix(in srgb, var(--primary) 20%, transparent)', color: 'var(--primary)' }"
                                        >
                                            <i :class="[getCategoryInfo(slug).icon, 'text-sm']"></i>
                                        </div>
                                        <AppSelect :model-value="slug" @update:model-value="(val) => updateFeaturedCategory(idx, String(val ?? ''))" :options="categorySelectOptions" class="flex-1" />
                                        <button type="button" class="shrink-0 text-danger-500 hover:text-danger-700" @click="removeFeaturedCategory(idx)">
                                            <i class="ti ti-trash text-sm"></i>
                                        </button>
                                    </div>
                                    <button v-if="featuredCategoriesSlugs.length < 4" type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400" @click="addFeaturedCategory">
                                        <i class="ti ti-plus"></i>
                                        {{ t('Add Category') }}
                                    </button>
                                </div>
                                <div class="mt-4 border-t border-gray-100 pt-4 dark:border-surface-700">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Featured Tools Carousel') }}</h4>
                                    </div>
                                    <p class="mb-3 text-xs text-gray-400">{{ t('Select tools to show in the auto-scroll carousel. If none selected, featured tools from the database are used.') }}</p>
                                    <AppSelect v-model="featuredToolSlugs" :options="toolsGridSelectOptions" :multiple="true" :compact-multiple="true" :live-search="true" />
                                </div>
                            </div>

                            <!-- Stats Cards -->
                            <div v-if="homepageForm.settings.hero_variant !== 'tools-grid'" class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Stats Cards') }}</h4>
                                    <button type="button" role="switch" :aria-checked="secCfgBool('hero', 'show_stats', true)" class="app-switch" @click="secCfg('hero').show_stats = !secCfgBool('hero', 'show_stats', true)"><span class="app-switch__thumb"></span></button>
                                </div>
                                <div v-if="secCfgBool('hero', 'show_stats', true)" class="space-y-3">
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
                                    <button type="button" class="flex items-center justify-center gap-2 w-full rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400" @click="addStatsItem">
                                        <i class="ti ti-plus"></i>
                                        {{ t('Add Stat') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Tools Grid — Tool Selection (only for tools-grid variant) -->
                            <div v-if="homepageForm.settings.hero_variant === 'tools-grid'" class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Tools Grid — Select Tools') }}</h4>
                                </div>
                                <p class="mb-3 text-xs text-gray-400">{{ t('Choose up to 4 tools to display in the tools grid.') }}</p>
                                <div class="space-y-2">
                                    <div v-for="(slug, idx) in toolsGridSelectedSlugs" :key="idx" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                            :style="{ background: getToolBySlug(slug)?.color || 'var(--primary)', color: '#fff' }"
                                        >
                                            <i :class="[getToolBySlug(slug)?.icon || 'ti ti-apps', 'text-sm']"></i>
                                        </div>
                                        <AppSelect :model-value="slug" @update:model-value="(val) => updateToolsGridTool(idx, String(val ?? ''))" :options="toolsGridSelectOptions" class="flex-1" />
                                        <button type="button" class="shrink-0 text-danger-500 hover:text-danger-700" @click="removeToolsGridTool(idx)">
                                            <i class="ti ti-trash text-sm"></i>
                                        </button>
                                    </div>
                                    <button v-if="toolsGridSelectedSlugs.length < 4" type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400" @click="addToolsGridTool">
                                        <i class="ti ti-plus"></i>
                                        {{ t('Add Tool') }}
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
                                    <button type="button" role="switch" :aria-checked="!!getHomepageSetting(sec.toggleKey)" class="app-switch" @click="toggleVisibility(sec)"><span class="app-switch__thumb"></span></button>
                                </div>
                            </div>

                            <div v-if="!collapsedSections.has(sec.type)" class="p-5 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <template v-for="field in sec.fields" :key="field">
                                        <label v-if="field === 'title' || field === 'heading' || field === 'headline'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t(field.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())) }}
                                            <input v-model="secCfg(sec.type)[field]" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                        </label>
                                        <label v-else-if="field === 'subheadline' || field === 'subtitle' || field === 'subheading'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t(field.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())) }}
                                            <input v-model="secCfg(sec.type)[field]" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                        </label>
                                        <div v-else-if="field === 'content'" class="block text-sm font-medium text-gray-700 dark:text-gray-300 sm:col-span-2">
                                            <span class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t(field.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())) }}
                                            </span>
                                            <RichEditor v-if="sec.type === 'richtext'" :model-value="secCfg(sec.type)[field] || ''" @update:model-value="(val: any) => secCfg(sec.type)[field] = val" class="mt-1.5" />
                                            <textarea v-else v-model="secCfg(sec.type)[field]" rows="12" :class="[sec.type === 'custom_html' ? 'font-mono text-xs' : 'text-sm']" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="sec.type === 'custom_html' ? '<!-- Enter custom HTML/CSS/JS here -->' : ''"></textarea>
                                        </div>
                                        <div v-else-if="field === 'zone'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            <span class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Ad Zone') }}
                                            </span>
                                            <AppSelect
                                                v-model="secCfg(sec.type)[field]"
                                                :options="adZoneOptions"
                                            />
                                        </div>
                                        <label v-else class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t(field.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())) }}
                                            <input v-model="secCfg(sec.type)[field]" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                        </label>
                                    </template>
                                </div>

                                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-surface-800">
                                    <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                        {{ t('Section Style & Layout') }}
                                    </h4>
                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Section Background') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).section_bg"
                                                :options="[
                                                    { value: 'default', label: t('Default') },
                                                    { value: 'light', label: t('Light') },
                                                    { value: 'primary-light', label: t('Primary Light') },
                                                    { value: 'green-light', label: t('Success Light') },
                                                    { value: 'red-light', label: t('Danger Light') },
                                                    { value: 'warning-light', label: t('Warning Light') },
                                                    { value: 'gradient1', label: t('Theme Gradient (Primary/Secondary)') },
                                                    { value: 'gradient2', label: t('Purple/Pink Gradient') },
                                                    { value: 'gradient3', label: t('Blue/Cyan Gradient') },
                                                    { value: 'gradient4', label: t('Blue/Purple Gradient') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Title Alignment') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).title_align"
                                                :options="[
                                                    { value: 'center', label: t('Center') },
                                                    { value: 'left', label: t('Left') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Title Size') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).title_size"
                                                :options="[
                                                    { value: 'sm', label: t('Small') },
                                                    { value: 'md', label: t('Medium') },
                                                    { value: 'lg', label: t('Large') },
                                                    { value: 'xl', label: t('Extra Large') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Title Color') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).title_color"
                                                :options="[
                                                    { value: 'dark', label: t('Dark') },
                                                    { value: 'primary', label: t('Primary') },
                                                    { value: 'success', label: t('Success') },
                                                    { value: 'danger', label: t('Danger') },
                                                    { value: 'warning', label: t('Warning') },
                                                    { value: 'gradient1', label: t('Primary/Secondary') },
                                                    { value: 'gradient2', label: t('Purple/Pink Gradient') },
                                                    { value: 'gradient3', label: t('Blue/Cyan Gradient') },
                                                    { value: 'gradient4', label: t('Blue/Purple Gradient') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Card Background Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).card_bg_style"
                                                :options="['tools_showcase', 'cta_banner', 'newsletter'].includes(sec.type) ? [
                                                    { value: 'default', label: t('Default') },
                                                    { value: 'transparent', label: t('Border only') },
                                                    { value: 'white', label: t('Solid White/Slate') },
                                                    { value: 'light', label: t('Gray/Surface Light') },
                                                    { value: 'primary_light', label: t('Primary Light') },
                                                    { value: 'gradient-1', label: t('Gradient Blue/Violet') },
                                                    { value: 'gradient-2', label: t('Gradient Emerald/Teal') },
                                                    { value: 'gradient-3', label: t('Gradient Sky/Indigo') },
                                                    { value: 'gradient-4', label: t('Gradient Sunset') },
                                                ] : [
                                                    { value: 'default', label: t('Default') },
                                                    { value: 'transparent', label: t('Border only') },
                                                    { value: 'white', label: t('Solid White/Slate') },
                                                    { value: 'light', label: t('Gray/Surface Light') },
                                                    { value: 'primary_light', label: t('Primary Light') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Vertical Padding') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).vertical_padding"
                                                :options="[
                                                    { value: '24', label: t('Compact') },
                                                    { value: '48', label: t('Small') },
                                                    { value: '96', label: t('Default') },
                                                    { value: '128', label: t('Tall') },
                                                    { value: '160', label: t('Extra Tall') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <IconClassSelect v-model="secCfg(sec.type).icon" :label="t('Icon')" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Icon Position') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).icon_position"
                                                :options="[
                                                    { value: 'top', label: t('Top') },
                                                    { value: 'left', label: t('Left') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Icon Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).icon_style"
                                                :options="[
                                                    { value: 'primary', label: t('Primary Light') },
                                                    { value: 'dark', label: t('Dark Light') },
                                                    { value: 'light', label: t('White/Light') },
                                                    { value: 'success', label: t('Success Light') },
                                                    { value: 'danger', label: t('Danger Light') },
                                                    { value: 'warning', label: t('Warning Light') },
                                                    { value: 'gradient-1', label: t('Blue/Purple Gradient') },
                                                    { value: 'gradient-4', label: t('Sunset Gradient') },
                                                ]"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'image_carousel'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Carousel Columns') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).carousel_layout"
                                                :options="[
                                                    { value: '1', label: t('1 Column') },
                                                    { value: '2', label: t('2 Columns') },
                                                    { value: '3', label: t('3 Columns') },
                                                    { value: '4', label: t('4 Columns') },
                                                ]"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'image_carousel'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Auto Change Slides') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).carousel_autoplay"
                                                :options="[
                                                    { value: '1', label: t('Enabled') },
                                                    { value: '0', label: t('Disabled') },
                                                ]"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'image_carousel'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Carousel Time (seconds)') }}
                                            </label>
                                            <input
                                                v-model="secCfg(sec.type).carousel_time"
                                                type="number"
                                                min="1"
                                                max="60"
                                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'testimonials' && secCfg(sec.type).slider_columns !== '1'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Card Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).card_style"
                                                :options="[
                                                    { value: 'simple', label: t('Simple') },
                                                    { value: 'bordered', label: t('Bordered') },
                                                    { value: 'spotlight', label: t('Spotlight') },
                                                ]"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'testimonials'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Slider Columns (Desktop)') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).slider_columns"
                                                :options="[
                                                    { value: '1', label: t('1 Column') },
                                                    { value: '2', label: t('2 Columns') },
                                                    { value: '3', label: t('3 Columns') },
                                                ]"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'testimonials'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Navigation Controls') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).hide_controls"
                                                :options="[
                                                    { value: '0', label: t('Show') },
                                                    { value: '1', label: t('Hide') },
                                                ]"
                                            />
                                        </div>
                                        <div v-if="sec.type === 'testimonials'">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Auto Slider') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg(sec.type).autoplay_enabled"
                                                :options="[
                                                    { value: '0', label: t('Disabled') },
                                                    { value: '1', label: t('Enabled') },
                                                ]"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Custom Configuration for Tools Showcase Section -->
                                <div v-if="sec.type === 'tools_showcase'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-4">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Showcase Options') }}</h4>
                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Tools Source') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg('tools_showcase').source"
                                                :options="[
                                                    { value: 'all', label: t('All Tools') },
                                                    { value: 'featured', label: t('Featured Tools') },
                                                    { value: 'popular', label: t('Popular Tools') },
                                                    { value: 'recent', label: t('Recent Tools') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Max Items') }}
                                            </label>
                                            <input
                                                v-model="secCfg('tools_showcase').max_items"
                                                type="number"
                                                min="1"
                                                max="48"
                                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Grid Columns') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg('tools_showcase').layout"
                                                :options="[
                                                    { value: '2-column', label: t('2 Columns') },
                                                    { value: '3-column', label: t('3 Columns') },
                                                    { value: '4-column', label: t('4 Columns') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Card Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg('tools_showcase').card_style"
                                                :options="[
                                                    { value: 'style-1', label: t('Default') },
                                                    { value: 'style-2', label: t('Glassmorphism') },
                                                    { value: 'style-3', label: t('Minimal') },
                                                ]"
                                            />
                                        </div>
                                    </div>

                                    <!-- Toggle Options -->
                                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-surface-800">
                                        <h5 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Display Toggles') }}</h5>
                                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                            <div class="flex items-center justify-between rounded-xl border border-gray-100 p-3 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Rating') }}</span>
                                                <button type="button" role="switch" :aria-checked="secCfgBool('tools_showcase', 'show_rating', true)" class="app-switch" @click="secCfg('tools_showcase').show_rating = !secCfgBool('tools_showcase', 'show_rating', true)"><span class="app-switch__thumb"></span></button>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border border-gray-100 p-3 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Favorite Button') }}</span>
                                                <button type="button" role="switch" :aria-checked="secCfgBool('tools_showcase', 'show_favorite', true)" class="app-switch" @click="secCfg('tools_showcase').show_favorite = !secCfgBool('tools_showcase', 'show_favorite', true)"><span class="app-switch__thumb"></span></button>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border border-gray-100 p-3 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Tool Category') }}</span>
                                                <button type="button" role="switch" :aria-checked="secCfgBool('tools_showcase', 'show_category', true)" class="app-switch" @click="secCfg('tools_showcase').show_category = !secCfgBool('tools_showcase', 'show_category', true)"><span class="app-switch__thumb"></span></button>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border border-gray-100 p-3 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Category Filter') }}</span>
                                                <button type="button" role="switch" :aria-checked="secCfgBool('tools_showcase', 'show_category_filter', false)" class="app-switch" @click="secCfg('tools_showcase').show_category_filter = !secCfgBool('tools_showcase', 'show_category_filter', false)"><span class="app-switch__thumb"></span></button>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border border-gray-100 p-3 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Search Box') }}</span>
                                                <button type="button" role="switch" :aria-checked="secCfgBool('tools_showcase', 'show_search', false)" class="app-switch" @click="secCfg('tools_showcase').show_search = !secCfgBool('tools_showcase', 'show_search', false)"><span class="app-switch__thumb"></span></button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Button Customization -->
                                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-surface-800">
                                        <h5 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Call To Action Button') }}</h5>
                                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                    {{ t('Button Text') }}
                                                </label>
                                                <input
                                                    v-model="secCfg('tools_showcase').primary_text"
                                                    type="text"
                                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                                    :placeholder="t('e.g. View All Tools')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                    {{ t('Button Link') }}
                                                </label>
                                                <input
                                                    v-model="secCfg('tools_showcase').primary_link"
                                                    type="text"
                                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                                    placeholder="/ai-tools"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                    {{ t('Button Style') }}
                                                </label>
                                                <AppSelect
                                                    v-model="secCfg('tools_showcase').primary_style"
                                                    :options="headerButtonStyleOptions"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                    {{ t('Button Shape') }}
                                                </label>
                                                <AppSelect
                                                    v-model="secCfg('tools_showcase').primary_shape"
                                                    :options="headerButtonShapeOptions"
                                                />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                    {{ t('Button Icon') }}
                                                </label>
                                                <IconClassSelect v-model="secCfg('tools_showcase').primary_icon" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Custom List Editor for Features Section -->
                                <div v-if="sec.type === 'features'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Feature Items') }}</h4>
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Disable Card Style') }}</span>
                                            <button type="button" role="switch" :aria-checked="!!secCfg('features').disable_card_style" class="app-switch" @click="secCfg('features').disable_card_style = !secCfg('features').disable_card_style"><span class="app-switch__thumb"></span></button>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-for="(item, idx) in (secCfg('features').items || [])" :key="idx" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 space-y-3 relative group">
                                            <button type="button" class="absolute top-4 right-4 text-danger-500 hover:text-danger-700 transition-colors" @click="removeFeatureItem(Number(idx))">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                            <div class="grid gap-3 sm:grid-cols-3 pr-8">
                                                <div class="sm:col-span-1">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Icon') }}</label>
                                                    <IconClassSelect v-model="item.icon" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Label / Title') }}</label>
                                                    <input v-model="item.title" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Description') }}</label>
                                                    <textarea v-model="item.description" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400 w-full justify-center transition-colors" @click="addFeatureItem">
                                            <i class="ti ti-plus"></i>
                                            {{ t('Add Feature Item') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Custom List Editor for How It Works Section -->
                                <div v-if="sec.type === 'how_it_works'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('How It Works Steps') }}</h4>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-for="(item, idx) in (secCfg('how_it_works').items || [])" :key="idx" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 space-y-3 relative group">
                                            <button type="button" class="absolute top-4 right-4 text-danger-500 hover:text-danger-700 transition-colors" @click="removeHowItWorksStep(Number(idx))">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                            <div class="grid gap-3 sm:grid-cols-3 pr-8">
                                                <div class="sm:col-span-1">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Icon') }}</label>
                                                    <IconClassSelect v-model="item.icon" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Title') }}</label>
                                                    <input v-model="item.title" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Description') }}</label>
                                                    <textarea v-model="item.description" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400 w-full justify-center transition-colors" @click="addHowItWorksStep">
                                            <i class="ti ti-plus"></i>
                                            {{ t('Add Step') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Custom Configuration for Social Proof (Stats Bar) Section -->
                                <div v-if="sec.type === 'stats_bar'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-6">
                                          <!-- Toggle: Show Stats -->
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Stats Settings') }}</h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('Display numbers/stats on the social proof bar.') }}</p>
                                        </div>
                                        <button type="button" role="switch" :aria-checked="secCfgBool('stats_bar', 'show_stats', true)" class="app-switch" @click="secCfg('stats_bar').show_stats = !secCfgBool('stats_bar', 'show_stats', true)">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                    </div>

                                    <!-- Stats List Editor (visible only if show_stats is enabled) -->
                                    <div v-if="secCfgBool('stats_bar', 'show_stats', true)" class="space-y-4">
                                        <div v-for="(stat, idx) in (secCfg('stats_bar').stats || [])" :key="idx" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 space-y-3 relative group">
                                            <button type="button" class="absolute top-4 right-4 text-danger-500 hover:text-danger-700 transition-colors" @click="removeStatsBarStat(Number(idx))">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                            <div class="grid gap-3 sm:grid-cols-2 pr-8">
                                                <div>
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Stat Value (e.g. 50K+)') }}</label>
                                                    <input v-model="stat.number" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Label (e.g. Users Trusted)') }}</label>
                                                    <input v-model="stat.label" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400 w-full justify-center transition-colors" @click="addStatsBarStat">
                                            <i class="ti ti-plus"></i>
                                            {{ t('Add Stat') }}
                                        </button>
                                    </div>

                                    <hr class="border-gray-100 dark:border-surface-800" />

                                    <!-- Toggle: Show Brands Logos -->
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Brand Logos Settings') }}</h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('Display logos of supported brands in a marquee scrolling style.') }}</p>
                                        </div>
                                        <button type="button" role="switch" :aria-checked="secCfgBool('stats_bar', 'show_brands', false)" class="app-switch" @click="secCfg('stats_bar').show_brands = !secCfgBool('stats_bar', 'show_brands', false)">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                    </div>

                                    <!-- Brands List Editor (visible only if show_brands is enabled) -->
                                    <div v-if="secCfgBool('stats_bar', 'show_brands', false)" class="space-y-4">
                                        <div v-for="(brand, idx) in (secCfg('stats_bar').brands || [])" :key="idx" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 space-y-3 relative group">
                                            <button type="button" class="absolute top-4 right-4 text-danger-500 hover:text-danger-700 transition-colors" @click="removeStatsBarBrand(Number(idx))">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                            <div class="grid gap-3 sm:grid-cols-3 pr-8">
                                                <div class="sm:col-span-1">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Brand Name') }}</label>
                                                    <input v-model="brand.name" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Upload Brand Logo') }}</label>
                                                    <input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/avif" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onBrandLogoInput(Number(idx), $event)" />

                                                    <!-- Logo Preview -->
                                                    <div v-if="brand.image || brandLogoPreviewUrls[Number(idx)]" class="mt-2 flex items-center gap-3">
                                                        <div class="h-8 max-w-[120px] rounded border border-gray-200 bg-white p-1 flex items-center justify-center dark:border-surface-700 dark:bg-surface-900">
                                                            <img :src="brandLogoPreviewUrls[Number(idx)] || `/storage/${brand.image}`" class="h-full object-contain" />
                                                        </div>
                                                        <button v-if="brand.image" type="button" class="text-xs text-danger-500 hover:underline" @click="clearBrandLogoSelection(Number(idx))">{{ t('Remove logo') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400 w-full justify-center transition-colors" @click="addStatsBarBrand">
                                            <i class="ti ti-plus"></i>
                                            {{ t('Add Brand') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Custom Configuration for CTA Banner Section -->
                                <div v-if="sec.type === 'cta_banner'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-6">
                                    <!-- Background Style -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ t('Background Style') }}
                                        </label>
                                        <AppSelect
                                            v-model="secCfg('cta_banner').background_style"
                                            :options="[
                                                { value: 'transparent', label: t('Border only') },
                                                { value: 'gradient-1', label: t('Gradient Blue/Violet') },
                                                { value: 'gradient-2', label: t('Gradient Emerald/Teal') },
                                                { value: 'gradient-3', label: t('Gradient Sky/Indigo') },
                                                { value: 'gradient-4', label: t('Gradient Sunset') },
                                                { value: 'primary_light', label: t('Primary Light') },
                                                { value: 'green_light', label: t('Success Light') },
                                                { value: 'white', label: t('White') },
                                                { value: 'light', label: t('Light') },
                                            ]"
                                        />
                                    </div>


                                    <!-- Primary CTA Button Configuration -->
                                    <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">{{ t('Primary Button') }}</h4>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Button Text') }}
                                                <input v-model="secCfg('cta_banner').primary_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Button Link') }}
                                                <input v-model="secCfg('cta_banner').primary_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <AppSelect v-model="secCfg('cta_banner').primary_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                            <AppSelect v-model="secCfg('cta_banner').primary_shape" :label="t('Button Shape')" :options="headerButtonShapeOptions" />
                                            <IconClassSelect v-model="secCfg('cta_banner').primary_icon" :label="t('Button Icon')" />
                                            <AppSelect v-model="secCfg('cta_banner').primary_access_level" :label="t('Access Level')" :options="accessLevelOptions" />
                                        </div>
                                    </div>

                                    <!-- Secondary CTA Button Configuration -->
                                    <div class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">{{ t('Secondary Button') }}</h4>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Button Text') }}
                                                <input v-model="secCfg('cta_banner').secondary_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Button Link') }}
                                                <input v-model="secCfg('cta_banner').secondary_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <AppSelect v-model="secCfg('cta_banner').secondary_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                            <AppSelect v-model="secCfg('cta_banner').secondary_shape" :label="t('Button Shape')" :options="headerButtonShapeOptions" />
                                            <IconClassSelect v-model="secCfg('cta_banner').secondary_icon" :label="t('Button Icon')" />
                                            <AppSelect v-model="secCfg('cta_banner').secondary_access_level" :label="t('Access Level')" :options="accessLevelOptions" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Custom Configuration for Latest Posts Section -->
                                <div v-if="sec.type === 'latest_posts'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-6">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <!-- Max Items / Count -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Number of Posts to Display') }}
                                                <input v-model.number="secCfg('latest_posts').max_items" type="number" min="1" max="12" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                        </div>

                                        <!-- Toggles -->
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Show Description') }}</h4>
                                                <p class="text-xs text-gray-400 mt-1">{{ t('Show the post excerpt/description on the card.') }}</p>
                                            </div>
                                            <button type="button" role="switch" :aria-checked="secCfgBool('latest_posts', 'show_description', true)" class="app-switch" @click="secCfg('latest_posts').show_description = !secCfgBool('latest_posts', 'show_description', true)"><span class="app-switch__thumb"></span></button>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Show Read More Link') }}</h4>
                                                <p class="text-xs text-gray-400 mt-1">{{ t('Show a "Read More" link on the blog post cards.') }}</p>
                                            </div>
                                            <button type="button" role="switch" :aria-checked="secCfgBool('latest_posts', 'show_read_more_btn', true)" class="app-switch" @click="secCfg('latest_posts').show_read_more_btn = !secCfgBool('latest_posts', 'show_read_more_btn', true)"><span class="app-switch__thumb"></span></button>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Show Visit Blog Button') }}</h4>
                                                <p class="text-xs text-gray-400 mt-1">{{ t('Show the main CTA button at the bottom of the section.') }}</p>
                                            </div>
                                            <button type="button" role="switch" :aria-checked="secCfgBool('latest_posts', 'show_button', true)" class="app-switch" @click="secCfg('latest_posts').show_button = !secCfgBool('latest_posts', 'show_button', true)"><span class="app-switch__thumb"></span></button>
                                        </div>
                                    </div>

                                    <!-- Button Config -->
                                    <div v-if="secCfgBool('latest_posts', 'show_button', true)" class="border-t border-gray-100 pt-4 dark:border-surface-700">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">{{ t('Blog Link Button Settings') }}</h4>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Button Text') }}
                                                <input v-model="secCfg('latest_posts').button_text" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ t('Button Link') }}
                                                <input v-model="secCfg('latest_posts').button_link" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                            </label>
                                            <AppSelect v-model="secCfg('latest_posts').button_style" :label="t('Button Style')" :options="headerButtonStyleOptions" />
                                            <IconClassSelect v-model="secCfg('latest_posts').button_icon" :label="t('Button Icon')" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Custom Configuration for Newsletter Section -->
                                <div v-if="sec.type === 'newsletter'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-6">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Card Background Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg('newsletter').background_style"
                                                :options="[
                                                    { value: 'transparent', label: t('Border only') },
                                                    { value: 'white', label: t('Solid White/Slate') },
                                                    { value: 'light', label: t('Gray/Surface Light') },
                                                    { value: 'primary_light', label: t('Primary Light') },
                                                    { value: 'gradient-1', label: t('Gradient Blue/Violet') },
                                                    { value: 'gradient-2', label: t('Gradient Emerald/Teal') },
                                                    { value: 'gradient-3', label: t('Gradient Sky/Indigo') },
                                                    { value: 'gradient-4', label: t('Gradient Sunset') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Newsletter Layout Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg('newsletter').newsletter_style"
                                                :options="[
                                                    { value: 'inline', label: t('Inline (Rounded Card)') },
                                                    { value: 'inline_pill', label: t('Inline Pill (Rounded Full)') },
                                                    { value: 'stacked', label: t('Stacked Layout') },
                                                ]"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                {{ t('Button Style') }}
                                            </label>
                                            <AppSelect
                                                v-model="secCfg('newsletter').button_style"
                                                :options="headerButtonStyleOptions"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Custom List Editor for Image Carousel Section -->
                                <div v-if="sec.type === 'image_carousel'" class="mt-6 pt-6 border-t border-gray-100 dark:border-surface-800 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Carousel Items') }}</h4>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-for="(item, idx) in (secCfg('image_carousel').items || [])" :key="item._key || idx" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 space-y-3 relative group">
                                            <button type="button" class="absolute top-4 right-4 text-danger-500 hover:text-danger-700 transition-colors" @click="removeCarouselItem(item, Number(idx))">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                            <div class="grid gap-3 sm:grid-cols-3 pr-8">
                                                <div class="sm:col-span-1">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Title') }}</label>
                                                    <input v-model="item.title" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Link URL') }}</label>
                                                    <input v-model="item.link_url" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Description') }}</label>
                                                    <textarea v-model="item.description" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"></textarea>
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{{ t('Upload Image') }}</label>
                                                    <input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/avif" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-500 hover:file:bg-primary-100/80 dark:file:bg-primary-950/40 dark:file:text-primary-400" @input="onCarouselItemImageInput(item, $event)" />

                                                    <!-- Image Preview -->
                                                    <div v-if="item.image_url || carouselItemPreviewUrls[item._key || '']" class="mt-2 flex items-center gap-3">
                                                        <div class="h-12 w-20 rounded border border-gray-200 bg-white p-1 flex items-center justify-center dark:border-surface-700 dark:bg-surface-900">
                                                            <img :src="carouselItemPreviewUrls[item._key || ''] || `/storage/${item.image_url}`" class="h-full w-full object-cover rounded" />
                                                        </div>
                                                        <button v-if="item.image_url" type="button" class="text-xs text-danger-500 hover:underline" @click="clearCarouselItemImageSelection(item, Number(idx))">{{ t('Remove image') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:text-gray-400 w-full justify-center transition-colors" @click="addCarouselItem">
                                            <i class="ti ti-plus"></i>
                                            {{ t('Add Carousel Item') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </VueDraggable>
                </div>

                <div v-else-if="activeTab === 'page'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Tool Single Page') }}</h2>

                        <div class="space-y-6">
                            <!-- Page Layout Section -->
                            <div class="grid gap-5 sm:grid-cols-2">
                                <AppSelect
                                    v-model="toolPageForm.settings.layout"
                                    :label="t('Page Layout')"
                                    :options="[
                                        { value: 'default', label: t('Default') },
                                        { value: 'creative', label: t('Creative') },
                                        { value: 'modern', label: t('Modern') },
                                        { value: 'minimalist', label: t('Minimalist') },
                                    ]"
                                />
                            </div>

                            <hr class="border-gray-100 dark:border-surface-850" />

                            <!-- Visibility Options Section -->
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ t('Visibility Settings') }}</h3>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.hide_breadcrumbs" class="app-switch" @click="toolPageForm.settings.hide_breadcrumbs = !toolPageForm.settings.hide_breadcrumbs">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hide Breadcrumbs') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Do not display navigational breadcrumbs on the tool details page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.hide_rating" class="app-switch" @click="toolPageForm.settings.hide_rating = !toolPageForm.settings.hide_rating">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hide Rating') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Do not display ratings and reviews on the tool page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.hide_share" class="app-switch" @click="toolPageForm.settings.hide_share = !toolPageForm.settings.hide_share">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hide Share') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Do not display the share options button on the tool page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.hide_favorite" class="app-switch" @click="toolPageForm.settings.hide_favorite = !toolPageForm.settings.hide_favorite">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hide Favorite') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Do not display the add-to-favorites button on the tool page.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Tool Archive Page') }}</h2>

                        <div class="space-y-6">
                            <!-- Page Layout Section -->
                            <div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <AppSelect
                                        v-model="toolPageForm.settings.archive_layout"
                                        :label="t('Page Layout')"
                                        :options="[
                                            { value: 'default', label: t('Default') },
                                            { value: 'modern', label: t('Modern') },
                                            { value: 'minimal', label: t('Minimal') },
                                        ]"
                                    />
                                    <AppSelect
                                        v-model="toolPageForm.settings.archive_pagination"
                                        :label="t('Pagination Style')"
                                        :options="[
                                            { value: 'none', label: t('No Pagination') },
                                            { value: 'numbered', label: t('Numbered') },
                                            { value: 'load_more', label: t('Load More') },
                                        ]"
                                    />
                                </div>
                            </div>

                            <hr class="border-gray-100 dark:border-surface-850" />

                            <!-- Visibility Options Section -->
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ t('Visibility Settings') }}</h3>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.archive_show_breadcrumbs" class="app-switch" @click="toolPageForm.settings.archive_show_breadcrumbs = !toolPageForm.settings.archive_show_breadcrumbs">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Breadcrumbs') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display navigational breadcrumbs on the tools directory archive page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.archive_show_stats" class="app-switch" @click="toolPageForm.settings.archive_show_stats = !toolPageForm.settings.archive_show_stats">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Stats Cards') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display quick statistics summary cards on the directory page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.archive_show_featured" class="app-switch" @click="toolPageForm.settings.archive_show_featured = !toolPageForm.settings.archive_show_featured">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Featured Section') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display the highlighted/featured tools carousel or banner.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.archive_show_grid_list" class="app-switch" @click="toolPageForm.settings.archive_show_grid_list = !toolPageForm.settings.archive_show_grid_list">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Grid/List Button') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display layout toggles for users to switch between grid and list layouts.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.archive_show_recently_used" class="app-switch" @click="toolPageForm.settings.archive_show_recently_used = !toolPageForm.settings.archive_show_recently_used">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Recently Used Section') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display the list of recently used tools in the directory archive page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.archive_show_open_button" class="app-switch" @click="toolPageForm.settings.archive_show_open_button = !toolPageForm.settings.archive_show_open_button">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Open Tool Button') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display an open action button or link inside the tool list cards.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Tool Category Page') }}</h2>

                        <div class="space-y-6">
                            <!-- Pagination Options -->
                            <div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <AppSelect
                                        v-model="toolPageForm.settings.category_pagination"
                                        :label="t('Pagination Style')"
                                        :options="[
                                            { value: 'none', label: t('No Pagination') },
                                            { value: 'numbered', label: t('Numbered') },
                                            { value: 'load_more', label: t('Load More') },
                                        ]"
                                    />
                                </div>
                            </div>

                            <hr class="border-gray-100 dark:border-surface-850" />

                            <!-- Visibility and Style Options -->
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ t('Visibility & Style Settings') }}</h3>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.category_show_breadcrumbs" class="app-switch" @click="toolPageForm.settings.category_show_breadcrumbs = !toolPageForm.settings.category_show_breadcrumbs">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Breadcrumbs') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Display navigational breadcrumbs on the category tool list page.') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                                        <button type="button" role="switch" :aria-checked="toolPageForm.settings.category_enable_gradient" class="app-switch" @click="toolPageForm.settings.category_enable_gradient = !toolPageForm.settings.category_enable_gradient">
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable Gradient Scheme') }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('Use a gradient theme for headers, tool titles, and action buttons.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else-if="activeTab === 'custom_code'" class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Custom Code') }}</h2>
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

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-4 opacity-0"
        >
            <div
                v-if="showFloatingSaveButton"
                class="pointer-events-none fixed inset-x-0 top-20 z-40 flex justify-end px-4 sm:px-6 lg:px-8"
            >
                <button
                    type="button"
                    :disabled="isSaving"
                    class="pointer-events-auto inline-flex items-center gap-2 whitespace-nowrap rounded-full bg-linear-to-r from-primary-500 to-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-500/25 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary-500/30 disabled:opacity-60"
                    @click="saveActiveTab"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ isSaving ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </Transition>

        <ActionConfirmModal
            :open="isRestoreModalOpen"
            :title="t('Restore default settings?')"
            :message="t('Are you sure you want to restore the default settings for the \'{tab}\' tab? Any custom changes made to this section will be reset back to their factory default values and cannot be undone.', { tab: activeTabLabel })"
            :confirm-label="t('Restore defaults')"
            variant="danger"
            @confirm="handleConfirmRestore"
            @cancel="isRestoreModalOpen = false"
        />
    </div>
</template>
