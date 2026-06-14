<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppIconSelect from '@/Components/IconClassSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'integrations' | 'custom_html' | 'template_grid' | 'all_tools' | 'richtext' | 'image_carousel' | 'ad_slot' | 'announcement'

declare const route: (name: string, params?: string | number | Record<string, string | number>) => string

type SectionItem = Record<string, string | number | boolean>
type SectionConfigValue = string | number | boolean | string[] | SectionItem[]

type SectionConfig = Record<string, SectionConfigValue>
type AdZone = 'header_banner' | 'sidebar_top' | 'sidebar_bottom' | 'content_top' | 'content_bottom' | 'content-injection' | 'between_posts' | 'between_ai_tools' | 'tool_page_top' | 'tool_page_bottom' | 'template_page' | 'chat_banner' | 'dashboard_top' | 'footer_banner' | 'custom_zone_1' | 'custom_zone_2'
type SettingsTab = 'general' | 'items' | 'style' | 'visibility' | 'advanced'

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
    scroll_to_top: {
        enabled: boolean
        position: 'left' | 'right'
        show_after_px: number
    }
    chat_widget_embed: string
}

interface HomepageConfig {
    sections: HomepageSection[]
    settings: HomepageSettings
}

interface SectionMeta {
    type: SectionType
    label: string
    description: string
    icon: string
}

interface EditingSection {
    index: number
    data: HomepageSection
}

const props = defineProps<{
    config: HomepageConfig
    sectionTypes: SectionType[]
    activeHomepageTemplate: string
    availableTemplates: Array<{ slug: string; name: string; requires_pro: boolean }>
    gridTemplates: Array<{ slug: string; name: string; requires_pro: boolean }>
}>()

const { t } = useTranslate()
const toast = useToastr()
const isCustomHomepage = computed(() => props.activeHomepageTemplate === 'default')

const homepageTemplateForm = useForm({
    homepage_template: props.activeHomepageTemplate,
})

const normalizeSectionConfig = (section: HomepageSection): HomepageSection => {
    const normalized = JSON.parse(JSON.stringify(section)) as HomepageSection

    if (normalized.type === 'how_it_works') {
        normalizeHowItWorksSection(normalized)
    }

    if (normalized.type === 'testimonials') {
        normalizeTestimonialsSection(normalized)
    }

    if (normalized.type === 'faq') {
        normalizeFaqSection(normalized)
    }

    if (normalized.type === 'stats_bar') {
        normalizeStatsBarSection(normalized)
    }

    if (normalized.type === 'latest_posts') {
        normalizeLatestPostsSection(normalized)
    }

    if (normalized.type === 'newsletter') {
        normalizeNewsletterSection(normalized)
    }

    if (normalized.type === 'integrations') {
        normalizeIntegrationsSection(normalized)
    }

    if (normalized.type === 'announcement') {
        normalizeAnnouncementSection(normalized)
    }

    if (normalized.type === 'template_grid') {
        normalizeTemplateGridSection(normalized)
    }

    if (normalized.type === 'all_tools') {
        normalizeAllToolsSection(normalized)
    }

    if (normalized.type === 'cta_banner') {
        normalizeCtaBannerSection(normalized)
    }

    return normalized
}

const form = useForm<HomepageConfig>({
    sections: props.config.sections.map((section) => normalizeSectionConfig(section)),
    settings: props.config.settings,
})

const sectionCatalog: SectionMeta[] = [
    { type: 'hero', label: t('Hero Section'), description: t('Headline, CTAs, hero media, trust badges, and counters.'), icon: 'ti ti-layout-navbar' },
    { type: 'features', label: t('Features Section'), description: t('Feature cards with icons, images, colors, and a call to action.'), icon: 'ti ti-layout-grid' },
    { type: 'tools_showcase', label: t('AI Tools Showcase'), description: t('Tool grid, carousel, tabs, or masonry showcase.'), icon: 'ti ti-sparkles' },
    { type: 'how_it_works', label: t('How It Works'), description: t('Numbered process steps in horizontal or timeline layout.'), icon: 'ti ti-route' },
    { type: 'pricing', label: t('Pricing Section'), description: t('Database plans or custom static pricing table.'), icon: 'ti ti-credit-card' },
    { type: 'testimonials', label: t('Testimonials'), description: t('Customer quotes from database or manual entries.'), icon: 'ti ti-message-2-heart' },
    { type: 'faq', label: t('FAQ Section'), description: t('Accordion FAQs from database, page, or manual entries.'), icon: 'ti ti-help-circle' },
    { type: 'stats_bar', label: t('Stats / Social Proof'), description: t('Counters and partner logo cloud.'), icon: 'ti ti-chart-bar' },
    { type: 'cta_banner', label: t('CTA Banner'), description: t('Conversion banner with background and buttons.'), icon: 'ti ti-bolt' },
    { type: 'latest_posts', label: t('Blog / Latest Posts'), description: t('Recent posts grid, list, or featured-first layout.'), icon: 'ti ti-article' },
    { type: 'newsletter', label: t('Newsletter Section'), description: t('Newsletter subscription block.'), icon: 'ti ti-mail' },
    { type: 'integrations', label: t('Technology Logos'), description: t('AI model or integration logo ticker/grid.'), icon: 'ti ti-plug-connected' },
    { type: 'richtext', label: t('Rich Text'), description: t('Formatted content block with editor support.'), icon: 'ti ti-text-size' },
    { type: 'image_carousel', label: t('Image Carousel'), description: t('Rotating slides with image, title, and description.'), icon: 'ti ti-carousel-horizontal' },
    { type: 'ad_slot', label: t('Ad Slot'), description: t('Render an existing ad zone from Ads Manager.'), icon: 'ti ti-ad-2' },
    { type: 'announcement', label: t('Announcement'), description: t('Show active announcements from the announcements manager.'), icon: 'ti ti-speakerphone' },
    { type: 'custom_html', label: t('Custom HTML'), description: t('Embed custom HTML, CSS, or scripts.'), icon: 'ti ti-code' },
    { type: 'template_grid', label: t('Template Tool Grid'), description: t('Embed a tool grid from any site template with filters and cards.'), icon: 'ti ti-layout-grid' },
    { type: 'all_tools', label: t('All Tools Browser'), description: t('Searchable tools catalog with category filter, popular, featured, and recent tabs.'), icon: 'ti ti-apps' },
]

const addSectionModalOpen = ref(false)
const sectionModalOpen = ref(false)
const editingSection = ref<EditingSection | null>(null)
const removeTargetIndex = ref<number | null>(null)
const resetConfirmOpen = ref(false)
const importJsonText = ref('')
const showImportModal = ref(false)
const isDragging = ref(false)
const heroBackgroundUploading = ref(false)
const ctaBannerBackgroundUploading = ref(false)
const toolsShowcaseBackgroundUploading = ref(false)
const activeSettingsTab = ref<SettingsTab>('general')

const availableSections = computed(() => sectionCatalog.filter((section) => props.sectionTypes.includes(section.type)))

const availableGridTemplates = computed(() =>
  (props.gridTemplates && props.gridTemplates.length > 0)
    ? props.gridTemplates
    : props.availableTemplates.filter(t => t.slug !== 'ai-chatbot')
)

const hiddenConfigKeys: Record<string, string[]> = {
    hero: [
        'layout',
        'headline',
        'subheadline',
        'primary_cta_text',
        'primary_cta_link',
        'primary_cta_style',
        'primary_cta_icon',
        'primary_cta_icon_position',
        'secondary_cta_text',
        'secondary_cta_link',
        'secondary_cta_style',
        'secondary_cta_icon',
        'secondary_cta_icon_position',
        'hero_background_type',
        'hero_background_url',
        'show_hero_gradient_overlay',
        'show_stats_separator',
        'hero_section_height',
        'hero_vertical_padding',
        'hero_heading_size',
        'hero_heading_color',
        'hero_subheading_color',
        'stats_number_color',
        'stats_label_color',
        'trust_badge_text',
        'background_type',
        'background_value',
        'hero_media_url',
        'typing_phrases',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    features: [
        'title',
        'subtitle',
        'layout',
        'card_style',
        'feature_vertical_padding',
        'heading_color',
        'subheading_color',
        'learn_more_text',
        'button_text',
        'button_link',
        'button_style',
        'button_icon',
        'cta_text',
        'cta_link',
        'cta_style',
        'cta_icon',
        'items',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    how_it_works: [
        'title',
        'subtitle',
        'layout',
        'card_style',
        'heading',
        'subheading',
        'icon',
        'step_layout',
        'step_card_style',
        'section_vertical_padding',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    testimonials: [
        'title',
        'subtitle',
        'heading',
        'subheading',
        'icon',
        'source',
        'card_style',
        'max_items',
        'featured_only',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    stats_bar: [
        'title',
        'subtitle',
        'heading',
        'subheading',
        'icon',
        'show_stats_separator',
        'stats_number_color',
        'stats_label_color',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    latest_posts: [
        'title',
        'subtitle',
        'heading',
        'subheading',
        'icon',
        'layout',
        'source',
        'card_style',
        'max_items',
        'button_text',
        'button_link',
        'button_icon',
        'button_style',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
        'items',
    ],
    newsletter: [
        'title',
        'subtitle',
        'heading',
        'subheading',
        'icon',
        'layout',
        'button_text',
        'button_link',
        'button_icon',
        'button_style',
        'placeholder_text',
        'privacy_text',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    announcement: [
        'title',
        'subtitle',
        'announcement_type',
        'style',
        'max_items',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    integrations: [
        'title',
        'subtitle',
        'heading',
        'subheading',
        'icon',
        'layout',
        'max_items',
        'items',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    pricing: [
        'title',
        'subtitle',
        'heading',
        'subheading',
        'icon',
        'source',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    tools_showcase: [
        'title',
        'subtitle',
        'layout',
        'card_style',
        'section_vertical_padding',
        'source',
        'max_items',
        'heading_color',
        'subheading_color',
        'primary_text',
        'primary_link',
        'primary_icon',
        'primary_style',
        'secondary_text',
        'secondary_link',
        'secondary_icon',
        'secondary_style',
        'background_style',
        'background_image_url',
        'width',
        'items',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    cta_banner: [
        'headline',
        'subheadline',
        'primary_text',
        'primary_link',
        'primary_icon',
        'primary_style',
        'secondary_text',
        'secondary_link',
        'secondary_icon',
        'secondary_style',
        'access',
        'background',
        'background_style',
        'background_image_url',
        'width',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
    template_grid: [
        'title',
        'subtitle',
        'template_slug',
        'max_items',
        'show_filter',
    ],
    all_tools: [
        'title',
        'subtitle',
        'max_items',
        'default_tab',
        'show_search',
        'show_categories',
        'section_style',
        'visibility',
        'section_anchor',
        'custom_class',
        'animation',
        'overlay_opacity',
        'lazy_load_media',
    ],
}

const adZoneOptions: AdZone[] = [
    'header_banner',
    'sidebar_top',
    'sidebar_bottom',
    'content_top',
    'content_bottom',
    'content-injection',
    'between_posts',
    'between_ai_tools',
    'tool_page_top',
    'tool_page_bottom',
    'template_page',
    'chat_banner',
    'dashboard_top',
    'footer_banner',
    'custom_zone_1',
    'custom_zone_2',
]

const heroLayoutOptions = [
    { value: 'left', label: t('Left') },
    { value: 'center', label: t('Center') },
    { value: 'right', label: t('Right') },
]

const heroHeightOptions = [
    { value: 'default', label: t('Small') },
    { value: 'compact', label: t('Medium') },
    { value: 'comfortable', label: t('Large') },
    { value: 'full', label: t('Full Screen') },
]

const toolsShowcaseSourceOptions = [
    { value: 'all', label: t('All Tools') },
    { value: 'featured', label: t('Featured Tools') },
    { value: 'popular', label: t('Popular Tools') },
    { value: 'recent', label: t('Newest Tools') },
]

const pricingSourceOptions = [
    { value: 'all', label: t('All Plans') },
    { value: 'featured', label: t('Featured Plans') },
    { value: 'free', label: t('Free Plans') },
    { value: 'paid', label: t('Paid Plans') },
]

const featureCardStyleOptions = [
    { value: 'simple', label: t('Simple') },
    { value: 'bordered', label: t('Bordered') },
    { value: 'image_focus', label: t('Image Focus') },
]

const howItWorksLayoutOptions = [
    { value: 'cards', label: t('Cards') },
    { value: 'timeline', label: t('Timeline') },
    { value: 'compact', label: t('Compact') },
]

const howItWorksCardStyleOptions = [
    { value: 'simple', label: t('Simple') },
    { value: 'bordered', label: t('Bordered') },
]

const toolsShowcaseCardStyleOptions = [
    { value: 'simple', label: t('Simple') },
    { value: 'bordered', label: t('Bordered') },
    { value: 'image_focus', label: t('Image Focus') },
]

const integrationsLayoutOptions = [
    { value: 'grid', label: t('Grid') },
    { value: 'ticker', label: t('Ticker') },
]

const announcementTypeOptions = [
    { value: 'topbar', label: t('Topbar') },
    { value: 'popup', label: t('Popup') },
    { value: 'notification', label: t('Notification') },
    { value: 'all', label: t('All') },
]

const announcementStyleOptions = [
    { value: 'cards', label: t('Cards') },
    { value: 'compact', label: t('Compact') },
]

const heroButtonStyleOptions = [
    { value: 'primary_filled', label: t('Primary Filled') },
    { value: 'dark', label: t('Dark') },
    { value: 'purple', label: t('Purple') },
    { value: 'gradient', label: t('Gradient') },
    { value: 'red', label: t('Red') },
    { value: 'green', label: t('Green') },
    { value: 'outline', label: t('Outline') },
    { value: 'white', label: t('White') },
    { value: 'light', label: t('Light') },
]

const sectionStylePresetOptions = [
    { value: 'default', label: t('Default') },
    { value: 'left_border', label: t('Left Border') },
    { value: 'bottom_accent', label: t('Bottom Accent') },
    { value: 'right_border', label: t('Right Border') },
    { value: 'bottom_border', label: t('Bottom Border') },
]

const sectionVisibilityOptions = [
    { value: 'all', label: t('Visible on All Devices') },
    { value: 'desktop', label: t('Desktop Only') },
    { value: 'tablet', label: t('Tablet Only') },
    { value: 'mobile', label: t('Mobile Only') },
    { value: 'desktop_tablet', label: t('Desktop + Tablet') },
    { value: 'tablet_mobile', label: t('Tablet + Mobile') },
]

const heroIconPositionOptions = [
    { value: 'left', label: t('Left') },
    { value: 'right', label: t('Right') },
]

const heroHeadingSizeOptions = [
    { value: 'sm', label: t('Small') },
    { value: 'md', label: t('Medium') },
    { value: 'lg', label: t('Large') },
    { value: 'xl', label: t('Extra Large') },
]

const heroHeadingColorOptions = [
    { value: 'primary', label: t('Primary') },
    { value: 'dark', label: t('Dark') },
    { value: 'green', label: t('Green') },
    { value: 'purple', label: t('Purple') },
    { value: 'white', label: t('White') },
    { value: 'light', label: t('Light') },
    { value: 'red', label: t('Red') },
    { value: 'yellow', label: t('Yellow') },
    { value: 'gradient', label: t('Gradient') },
]

const heroSubheadingColorOptions = [
    { value: 'primary', label: t('Primary') },
    { value: 'dark', label: t('Dark') },
    { value: 'green', label: t('Green') },
    { value: 'purple', label: t('Purple') },
    { value: 'white', label: t('White') },
    { value: 'light', label: t('Light') },
    { value: 'red', label: t('Red') },
    { value: 'yellow', label: t('Yellow') },
]

const ctaBannerBackgroundStyleOptions = [
    { value: 'gradient-1', label: t('Gradient 1') },
    { value: 'gradient-2', label: t('Gradient 2') },
    { value: 'gradient-3', label: t('Gradient 3') },
    { value: 'primary_light', label: t('Primary Light') },
    { value: 'green_light', label: t('Green Light') },
    { value: 'white', label: t('White') },
    { value: 'light', label: t('Light') },
    { value: 'dark', label: t('Dark') },
]

const ctaBannerWidthOptions = [
    { value: 'contained', label: t('Contained') },
    { value: 'wide', label: t('Wide') },
    { value: 'full', label: t('Full Width') },
]

const isHiddenConfigKey = (type: SectionType, key: string): boolean => {
  return (hiddenConfigKeys[type] ?? []).includes(key)
}

const isItemFieldKey = (key: string, value: SectionConfigValue): boolean => {
    if (key === 'items' || key === 'stats') return true
    return Array.isArray(value)
}

const isStyleFieldKey = (key: string): boolean => [
    'heading_color',
    'subheading_color',
    'card_style',
    'feature_vertical_padding',
    'section_vertical_padding',
    'hero_vertical_padding',
    'hero_section_height',
    'hero_heading_size',
    'hero_heading_color',
    'hero_subheading_color',
    'stats_number_color',
    'stats_label_color',
    'background_style',
    'background_image_url',
    'width',
    'primary_style',
    'secondary_style',
    'button_style',
    'layout',
    'section_style',
    'padding_top',
    'padding_bottom',
    'margin_top',
    'margin_bottom',
    'background_color',
    'text_align',
    'alignment',
].includes(key)

const isVisibilityFieldKey = (key: string): boolean => key === 'visibility'

const isAdvancedFieldKey = (key: string): boolean => [
    'section_anchor',
    'custom_class',
    'animation',
    'overlay_opacity',
    'lazy_load_media',
].includes(key)

const sectionHasItems = computed(() => {
    if (editingSection.value === null) return false
    if (editingSection.value.data.type === 'pricing') return false
    if (editingSection.value.data.type === 'latest_posts') return false
    if (editingSection.value.data.type === 'newsletter') return false
    return Array.isArray(editingSection.value.data.config.items) || Array.isArray(editingSection.value.data.config.stats)
})

const settingsTabs = computed(() => {
    const tabs = [
        { key: 'general', label: t('General') },
        { key: 'items', label: t('Items') },
        { key: 'style', label: t('Style') },
        { key: 'advanced', label: t('Advanced') },
    ] as const

    return tabs.filter((tab) => tab.key !== 'items' || sectionHasItems.value)
})

const setSettingsTab = (tab: SettingsTab) => {
    activeSettingsTab.value = tab
}

const enabledSectionsCount = computed(() => form.sections.filter((section) => section.enabled).length)

const getSectionMeta = (type: SectionType): SectionMeta => sectionCatalog.find((section) => section.type === type) ?? sectionCatalog[0]
const configLabel = (key: string | number): string => t(String(key).replaceAll('_', ' '))
const titleCase = (value: string): string => value.replace(/\b\w/g, (char) => char.toUpperCase())
const configPlaceholder = (key: string | number): string => {
    const normalizedKey = String(key)
    const label = titleCase(String(configLabel(key)))

    if (normalizedKey.includes('link') || normalizedKey.includes('url')) {
        return t('Enter :field...', { field: label })
    }

    if (normalizedKey.includes('title') || normalizedKey.includes('headline')) {
        return t('Enter :field...', { field: label })
    }

    if (normalizedKey.includes('subtitle') || normalizedKey.includes('subheadline') || normalizedKey.includes('description') || normalizedKey.includes('content')) {
        return t('Write :field...', { field: label.toLowerCase() })
    }

    if (normalizedKey.includes('count') || normalizedKey.includes('max_items') || normalizedKey.includes('interval') || normalizedKey.includes('spacing')) {
        return t('Enter :field...', { field: label })
    }

    return t('Enter :field...', { field: label })
}
const zoneOptionLabel = (zone: AdZone): string => t(titleCase(zone.replaceAll('_', ' ').replaceAll('-', ' ')))
const resolveStoredMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path

    return `/storage/${path}`
}

const cloneSection = (section: HomepageSection): HomepageSection => JSON.parse(JSON.stringify(section)) as HomepageSection

const submit = () => {
    form.post(route('admin.homepage.update'), {
        preserveScroll: true,
    })
}

const openPreview = () => {
    window.open(route('home'), '_blank', 'noopener,noreferrer')
}

const requestResetToDefaults = () => {
    resetConfirmOpen.value = true
}

const resetToDefaults = () => {
    form.sections = props.config.sections.map((section) => normalizeSectionConfig(section))
    form.settings = JSON.parse(JSON.stringify(props.config.settings))
    resetConfirmOpen.value = false
}

const exportConfig = () => {
    const data = {
        sections: form.sections,
        settings: form.settings,
    }
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'homepage-config.json'
    a.click()
    URL.revokeObjectURL(url)
}

const importConfig = () => {
    try {
        const data = JSON.parse(importJsonText.value)
        if (Array.isArray(data.sections)) form.sections = data.sections.map((section: HomepageSection) => normalizeSectionConfig(section))
        if (data.settings) form.settings = { ...form.settings, ...data.settings }
        importJsonText.value = ''
        showImportModal.value = false
    } catch (e) {
        toast.error(t('Invalid JSON format.'))
    }
}

const toggleSection = (index: number) => {
    form.sections[index].enabled = !form.sections[index].enabled
}

const editSection = (index: number) => {
    editingSection.value = {
        index,
        data: cloneSection(form.sections[index]),
    }
    normalizeHowItWorksSection(editingSection.value.data)
    normalizeTestimonialsSection(editingSection.value.data)
    normalizeFaqSection(editingSection.value.data)
    normalizeStatsBarSection(editingSection.value.data)
    normalizeLatestPostsSection(editingSection.value.data)
    normalizeNewsletterSection(editingSection.value.data)
    normalizeIntegrationsSection(editingSection.value.data)
    normalizeAnnouncementSection(editingSection.value.data)
    normalizeTemplateGridSection(editingSection.value.data)
    normalizeAllToolsSection(editingSection.value.data)
    normalizeCtaBannerSection(editingSection.value.data)
    activeSettingsTab.value = 'general'
    sectionModalOpen.value = true
}

const saveSectionSettings = () => {
    if (editingSection.value === null) return
    normalizeHowItWorksSection(editingSection.value.data)
    normalizeTestimonialsSection(editingSection.value.data)
    normalizeFaqSection(editingSection.value.data)
    normalizeStatsBarSection(editingSection.value.data)
    normalizeLatestPostsSection(editingSection.value.data)
    normalizeNewsletterSection(editingSection.value.data)
    normalizeIntegrationsSection(editingSection.value.data)
    normalizeAnnouncementSection(editingSection.value.data)
    normalizeTemplateGridSection(editingSection.value.data)
    normalizeAllToolsSection(editingSection.value.data)
    normalizeCtaBannerSection(editingSection.value.data)
    form.sections[editingSection.value.index] = cloneSection(editingSection.value.data)
    sectionModalOpen.value = false
}

const removeSection = (index: number) => {
    removeTargetIndex.value = index
}

const confirmRemoveSection = () => {
    if (removeTargetIndex.value === null) return
    form.sections.splice(removeTargetIndex.value, 1)
    removeTargetIndex.value = null
}

const addSection = (type: SectionType) => {
    form.sections.push(createSection(type))
    addSectionModalOpen.value = false
}

const createSection = (type: SectionType): HomepageSection => ({
    id: `${type}_${Date.now()}`,
    type,
    enabled: true,
    core: false,
    config: createDefaultConfig(type),
})

const createDefaultConfig = (type: SectionType): SectionConfig => {
    const title = getSectionMeta(type).label

    if (type === 'hero') {
        return {
            layout: 'center',
            headline: t('Create more with {app_name}'),
            subheadline: t('Launch your AI-powered workflow from one polished platform.'),
            primary_cta_text: t('Get Started'),
            primary_cta_link: '/register',
            primary_cta_style: 'primary_filled',
            primary_cta_icon: '',
            primary_cta_icon_position: 'left',
            secondary_cta_text: t('View Pricing'),
            secondary_cta_link: '/pricing',
            secondary_cta_style: 'outline',
            secondary_cta_icon: '',
            secondary_cta_icon_position: 'left',
            hero_background_type: 'image',
            hero_background_url: '',
            show_hero_gradient_overlay: true,
            show_stats_separator: true,
            hero_section_height: 'default',
            hero_vertical_padding: 48,
            hero_heading_size: 'lg',
            hero_heading_color: 'dark',
            hero_subheading_color: 'light',
            stats_number_color: 'dark',
            stats_label_color: 'light',
            trust_badge_text: t('Trusted by creators worldwide'),
            stats: [],
        }
    }

    if (type === 'features') {
        return {
            title,
            subtitle: t('Highlight your platform advantages.'),
            layout: '3-column',
            card_style: 'bordered',
            feature_vertical_padding: 96,
            heading_color: 'dark',
            subheading_color: 'light',
            items: [],
            button_text: '',
            button_link: '',
            button_style: 'primary_filled',
            button_icon: '',
        }
    }

    if (type === 'how_it_works') {
        return {
            heading: title,
            subheading: t('Show each step in a buyer-friendly layout that is easy to edit.'),
            icon: 'ti ti-route',
            step_layout: 'cards',
            step_card_style: 'bordered',
            section_vertical_padding: 96,
            items: [],
        }
    }

    if (type === 'testimonials') {
        return {
            heading: title,
            subheading: t('Show real customer reviews to build trust.'),
            icon: 'ti ti-message-2-heart',
            source: 'all',
            card_style: 'bordered',
            max_items: 6,
        }
    }

    if (type === 'faq') {
        return {
            heading: title,
            subheading: t('Answer common questions in a clean accordion layout.'),
            icon: 'ti ti-help-circle',
            max_items: 8,
        }
    }

    if (type === 'stats_bar') {
        return {
            heading: title,
            subheading: t('Show your best numbers and proof points in a clean row.'),
            icon: 'ti ti-chart-bar',
            show_stats_separator: true,
            stats_number_color: 'dark',
            stats_label_color: 'light',
            stats: [],
        }
    }

    if (type === 'latest_posts') {
        return {
            heading: title,
            subheading: t('Show your latest blog posts in a clean, buyer-friendly layout.'),
            icon: 'ti ti-article',
            layout: 'grid',
            source: 'recent',
            card_style: 'bordered',
            max_items: 3,
            button_text: t('View all posts'),
            button_link: '/blog',
            button_icon: '',
            button_style: 'outline',
        }
    }

    if (type === 'newsletter') {
        return {
            heading: title,
            subheading: t('Collect newsletter subscribers with a clear, buyer-friendly signup form.'),
            icon: 'ti ti-mail',
            layout: 'inline',
            placeholder_text: t('Enter your email'),
            button_text: t('Subscribe'),
            button_link: '/newsletter/subscribe',
            button_icon: '',
            button_style: 'primary_filled',
            privacy_text: t('We respect your inbox. Unsubscribe at any time.'),
        }
    }

    if (type === 'tools_showcase') {
        return {
            title,
            subtitle: t('Show the tools buyers can start using right away.'),
            layout: '3-column',
            card_style: 'bordered',
            section_vertical_padding: 96,
            source: 'all',
            max_items: 6,
            heading_color: 'white',
            subheading_color: 'white',
            primary_text: t('View all tools'),
            primary_link: '/ai-tools',
            primary_icon: '',
            primary_style: 'primary_filled',
            background_style: 'gradient-1',
            background_image_url: '',
            width: 'contained',
        }
    }

    if (type === 'pricing') {
        return {
            heading: title,
            subheading: t('Show your available plans with a clean, buyer-friendly layout.'),
            icon: 'ti ti-credit-card',
            source: 'all',
        }
    }

    if (type === 'custom_html') {
        return {
            title,
            content: '',
        }
    }

    if (type === 'richtext') {
        return {
            title,
            subtitle: '',
            content: `<p>${t('Add your formatted homepage content here.')}</p>`,
        }
    }

    if (type === 'image_carousel') {
        return {
            title,
            subtitle: t('Showcase highlights with rotating visuals.'),
            auto_play: true,
            interval_ms: 5000,
            items: [
                { image_url: '', title: t('Slide title'), description: t('Describe this slide.'), link_url: '' },
            ],
        }
    }

    if (type === 'ad_slot') {
        return {
            title,
            subtitle: '',
            zone: 'content_top',
        }
    }

    if (type === 'announcement') {
        return {
            title,
            subtitle: '',
            announcement_type: 'topbar',
            max_items: 3,
            style: 'cards',
        }
    }

    if (type === 'cta_banner') {
        return {
            headline: t('Ready to build with AI?'),
            subheadline: t('Start creating content, images, and code today.'),
            primary_text: t('Create Account'),
            primary_link: '/register',
            primary_icon: '',
            primary_style: 'primary_filled',
            secondary_text: t('See Pricing'),
            secondary_link: '/pricing',
            secondary_icon: '',
            secondary_style: 'outline',
            access: 'everyone',
            background_style: 'gradient-1',
            background_image_url: '',
            width: 'contained',
        }
    }

    if (type === 'template_grid') {
        return {
            title,
            subtitle: t('Embed a tool grid from any site template with filters and cards.'),
            template_slug: '',
            max_items: 12,
            show_filter: true,
        }
    }

    if (type === 'all_tools') {
        return {
            title,
            subtitle: t('Browse, search, and filter every tool in one place.'),
            default_tab: 'popular',
            show_search: true,
            show_categories: true,
            max_items: 12,
        }
    }

    return {
        title,
        subtitle: '',
        layout: 'grid',
        max_items: 6,
        source: 'manual',
        primary_text: t('Get Started'),
        primary_link: '/register',
        primary_icon: '',
        primary_style: 'primary_filled',
        secondary_text: '',
        secondary_link: '',
        secondary_icon: '',
        secondary_style: 'outline',
        background_style: 'gradient-1',
        background_image_url: '',
        width: 'contained',
        visibility: 'all',
        section_style: 'default',
        section_anchor: '',
        custom_class: '',
        animation: 'none',
        overlay_opacity: 0,
        lazy_load_media: false,
    }
}

const addListItem = (key: string) => {
    if (editingSection.value === null) return
    const value = editingSection.value.data.config[key]
    const item: SectionItem = key === 'stats'
        ? { number: '100K+', label: t('Generated results') }
        : editingSection.value.data.type === 'how_it_works'
            ? { title: t('New step'), icon: 'ti ti-route', description: t('Describe this step.'), link: '' }
        : editingSection.value.data.type === 'integrations'
            ? { title: t('New logo'), image_url: '', link_url: '' }
        : editingSection.value.data.type === 'image_carousel'
            ? { image_url: '', title: t('Slide title'), description: t('Describe this slide.'), link_url: '' }
        : { icon: 'ti ti-sparkles', title: t('New item'), description: t('Describe this item.'), image_url: '', link_url: '', link_open_new_tab: false }

    editingSection.value.data.config[key] = Array.isArray(value) && value.every((entry) => typeof entry !== 'string') ? [...value, item] : [item]
}

function normalizeHowItWorksSection(section: HomepageSection) {
    if (section.type !== 'how_it_works') return
    const items = section.config.items
    if (!Array.isArray(items)) return

    section.config.items = items.map((item, index) => {
        if (typeof item === 'string') {
            return {
                title: item || t('Step :count', { count: String(index + 1).padStart(2, '0') }),
                icon: 'ti ti-route',
                description: '',
                link: '',
            }
        }

        return {
            title: String(item.title ?? item.label ?? item.name ?? t('Step :count', { count: String(index + 1).padStart(2, '0') })),
            icon: String(item.icon ?? 'ti ti-route'),
            description: String(item.description ?? item.text ?? ''),
            link: String(item.link ?? ''),
            ...item,
        }
    }) as SectionItem[]
}

function normalizeTestimonialsSection(section: HomepageSection) {
    if (section.type !== 'testimonials') return

    const source = String(section.config.source ?? '').trim()
    const featuredOnly = section.config.featured_only === true

    if (!section.config.heading && section.config.title) {
        section.config.heading = String(section.config.title)
    }

    if (!section.config.subheading && section.config.subtitle) {
        section.config.subheading = String(section.config.subtitle)
    }

    if (!section.config.heading) {
        section.config.heading = t('What Our Users Say')
    }

    if (!section.config.subheading) {
        section.config.subheading = t('Show real customer feedback to build trust.')
    }

    if (!section.config.icon) {
        section.config.icon = 'ti ti-message-2-heart'
    }

    if (!source && featuredOnly) {
        section.config.source = 'featured'
    }

    if (!section.config.source) {
        section.config.source = 'all'
    }

    if (!section.config.card_style) {
        section.config.card_style = 'bordered'
    }

    if (!section.config.max_items) {
        section.config.max_items = 6
    }

    delete section.config.title
    delete section.config.subtitle
}

function normalizeFaqSection(section: HomepageSection) {
    if (section.type !== 'faq') return

    if (!section.config.heading && section.config.title) {
        section.config.heading = String(section.config.title)
    }

    if (!section.config.subheading && section.config.subtitle) {
        section.config.subheading = String(section.config.subtitle)
    }

    if (!section.config.heading) {
        section.config.heading = t('Frequently Asked Questions')
    }

    if (!section.config.subheading) {
        section.config.subheading = t('Answer common questions in a clean accordion layout.')
    }

    if (!section.config.icon) {
        section.config.icon = 'ti ti-help-circle'
    }

    if (!section.config.max_items) {
        section.config.max_items = 8
    }

    delete section.config.title
    delete section.config.subtitle
}

function normalizeStatsBarSection(section: HomepageSection) {
    if (section.type !== 'stats_bar') return

    if (!section.config.heading && section.config.title) {
        section.config.heading = String(section.config.title)
    }

    if (!section.config.subheading && section.config.subtitle) {
        section.config.subheading = String(section.config.subtitle)
    }

    if (!section.config.heading) {
        section.config.heading = t('Social Proof')
    }

    if (!section.config.subheading) {
        section.config.subheading = t('Show your best numbers and proof points in a clean row.')
    }

    if (!section.config.icon) {
        section.config.icon = 'ti ti-chart-bar'
    }

    if (section.config.show_stats_separator === undefined) {
        section.config.show_stats_separator = true
    }

    if (!section.config.stats_number_color) {
        section.config.stats_number_color = 'dark'
    }

    if (!section.config.stats_label_color) {
        section.config.stats_label_color = 'light'
    }

    delete section.config.title
    delete section.config.subtitle
}

function normalizeTemplateGridSection(section: HomepageSection) {
    if (section.type !== 'template_grid') return

    if (!section.config.title) {
        section.config.title = t('Template Tool Grid')
    }

    if (!section.config.subtitle) {
        section.config.subtitle = t('Embed a tool grid from any site template with filters and cards.')
    }

    if (!section.config.template_slug) {
        section.config.template_slug = ''
    }

    if (!section.config.max_items) {
        section.config.max_items = 12
    }

    if (typeof section.config.show_filter === 'undefined') {
        section.config.show_filter = true
    }
}

function normalizeAllToolsSection(section: HomepageSection) {
    if (section.type !== 'all_tools') return

    if (!section.config.title) {
        section.config.title = t('All Tools Browser')
    }

    if (!section.config.subtitle) {
        section.config.subtitle = t('Browse, search, and filter every tool in one place.')
    }

    if (!section.config.max_items) {
        section.config.max_items = 12
    }

    if (!section.config.default_tab) {
        section.config.default_tab = 'popular'
    }

    if (typeof section.config.show_search === 'undefined') {
        section.config.show_search = true
    }

    if (typeof section.config.show_categories === 'undefined') {
        section.config.show_categories = true
    }
}

function normalizeLatestPostsSection(section: HomepageSection) {
    if (section.type !== 'latest_posts') return

    if (!section.config.heading && section.config.title) {
        section.config.heading = String(section.config.title)
    }

    if (!section.config.subheading && section.config.subtitle) {
        section.config.subheading = String(section.config.subtitle)
    }

    if (!section.config.heading) {
        section.config.heading = t('Latest Posts')
    }

    if (!section.config.subheading) {
        section.config.subheading = t('Show your latest blog posts in a clean, buyer-friendly layout.')
    }

    if (!section.config.icon) {
        section.config.icon = 'ti ti-article'
    }

    if (!section.config.layout) {
        section.config.layout = 'grid'
    }

    if (!section.config.source) {
        section.config.source = 'recent'
    }

    if (!section.config.card_style) {
        section.config.card_style = 'bordered'
    }

    if (!section.config.max_items) {
        section.config.max_items = 3
    }

    if (!section.config.button_text) {
        section.config.button_text = t('View all posts')
    }

    if (!section.config.button_link) {
        section.config.button_link = '/blog'
    }

    if (!section.config.button_style) {
        section.config.button_style = 'outline'
    }

    delete section.config.title
    delete section.config.subtitle
}

function normalizeNewsletterSection(section: HomepageSection) {
    if (section.type !== 'newsletter') return

    if (!section.config.heading && section.config.title) {
        section.config.heading = String(section.config.title)
    }

    if (!section.config.subheading && section.config.subtitle) {
        section.config.subheading = String(section.config.subtitle)
    }

    if (!section.config.heading) {
        section.config.heading = t('Stay in the loop')
    }

    if (!section.config.subheading) {
        section.config.subheading = t('Collect newsletter subscribers with a clear, buyer-friendly signup form.')
    }

    if (!section.config.icon) {
        section.config.icon = 'ti ti-mail'
    }

    if (!section.config.layout) {
        section.config.layout = 'inline'
    }

    if (!section.config.placeholder_text) {
        section.config.placeholder_text = t('Enter your email')
    }

    if (!section.config.button_text) {
        section.config.button_text = t('Subscribe')
    }

    if (!section.config.button_link) {
        section.config.button_link = '/newsletter/subscribe'
    }

    if (!section.config.button_style) {
        section.config.button_style = 'primary_filled'
    }

    if (!section.config.privacy_text) {
        section.config.privacy_text = t('We respect your inbox. Unsubscribe at any time.')
    }

    delete section.config.title
    delete section.config.subtitle
}

function normalizeIntegrationsSection(section: HomepageSection) {
    if (section.type !== 'integrations') return

    if (!section.config.heading && section.config.title) {
        section.config.heading = String(section.config.title)
    }

    if (!section.config.subheading && section.config.subtitle) {
        section.config.subheading = String(section.config.subtitle)
    }

    if (!section.config.heading) {
        section.config.heading = t('Technology Logos')
    }

    if (!section.config.subheading) {
        section.config.subheading = t('Show the platforms, models, and integrations your product works with.')
    }

    if (!section.config.icon) {
        section.config.icon = 'ti ti-plug-connected'
    }

    if (!section.config.layout) {
        section.config.layout = 'grid'
    }

    if (!section.config.max_items) {
        section.config.max_items = 6
    }

    const items = section.config.items
    if (Array.isArray(items)) {
        section.config.items = items.map((item, index) => {
            if (typeof item === 'string') {
                return {
                    title: item || t('Logo :count', { count: String(index + 1).padStart(2, '0') }),
                    image_url: '',
                    link_url: '',
                    link_open_new_tab: false,
                }
            }

            return {
                ...(item as Record<string, unknown>),
                title: String(item.title ?? item.label ?? item.name ?? t('Logo :count', { count: String(index + 1).padStart(2, '0') })),
                image_url: String(item.image_url ?? ''),
                link_url: String(item.link_url ?? ''),
                link_open_new_tab: item.link_open_new_tab === true || item.link_open_new_tab === 'true' || item.link_open_new_tab === '1' || item.link_open_new_tab === 1,
            }
        }) as SectionItem[]
    }

    delete section.config.title
    delete section.config.subtitle
}

function normalizeAnnouncementSection(section: HomepageSection) {
    if (section.type !== 'announcement') return

    if (!section.config.title) {
        section.config.title = t('Announcements')
    }

    if (!section.config.subtitle) {
        section.config.subtitle = t('Show active announcements from the announcements manager.')
    }

    if (section.config.announcement_type !== 'topbar' && section.config.announcement_type !== 'popup' && section.config.announcement_type !== 'notification' && section.config.announcement_type !== 'all') {
        section.config.announcement_type = 'topbar'
    }

    if (section.config.style !== 'cards' && section.config.style !== 'compact') {
        section.config.style = 'cards'
    }

    if (!section.config.max_items) {
        section.config.max_items = 3
    }
}

function normalizeCtaBannerSection(section: HomepageSection) {
    if (section.type !== 'cta_banner') return

    if (section.config.access !== 'everyone' && section.config.access !== 'logged_in' && section.config.access !== 'pro') {
        section.config.access = 'everyone'
    }

    if (!section.config.background_style) {
        section.config.background_style = 'gradient-1'
    }

    if (!section.config.width) {
        section.config.width = 'contained'
    }
}

const removeListItem = (key: string, index: number) => {
    if (editingSection.value === null) return
    const value = editingSection.value.data.config[key]
    if (!Array.isArray(value)) return
    editingSection.value.data.config[key] = value.filter((entry, itemIndex) => typeof entry !== 'string' && itemIndex !== index) as SectionItem[]
}

const setConfigString = (key: string, value: SectionConfigValue) => {
    if (editingSection.value === null) return
    editingSection.value.data.config[key] = value
}

const setItemString = (item: SectionItem, key: string, value: string | number | boolean) => {
    item[key] = value
}

const handleItemImageUpload = async (item: SectionItem, event: Event) => {
    const target = event.target as HTMLInputElement | null
    const file = target?.files?.[0]

    if (!file) {
        return
    }

    try {
        const payload = new FormData()
        payload.append('file', file)
        payload.append('directory', 'homepage')

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        const response = await fetch(route('admin.homepage.upload'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: payload,
        })

        const result = await response.json().catch(() => ({}))

        if (!response.ok || !result.path) {
            throw new Error(result.message || t('Upload failed.'))
        }

        item.image_url = String(result.path)
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('Upload failed.'))
    } finally {
        if (target) {
            target.value = ''
        }
    }
}

const clearCarouselImage = (item: SectionItem) => {
    item.image_url = ''
}

const handleHeroBackgroundUpload = async (event: Event) => {
    if (editingSection.value === null) {
        return
    }

    const target = event.target as HTMLInputElement | null
    const file = target?.files?.[0]

    if (!file) {
        return
    }

    heroBackgroundUploading.value = true

    try {
        const payload = new FormData()
        payload.append('file', file)
        payload.append('directory', 'homepage')

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        const response = await fetch(route('admin.homepage.upload'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: payload,
        })

        const result = await response.json().catch(() => ({}))

        if (!response.ok || !result.path) {
            throw new Error(result.message || t('Upload failed.'))
        }

        const mediaType = file.type.startsWith('video/') ? 'video' : 'image'
        editingSection.value.data.config.hero_background_type = mediaType
        editingSection.value.data.config.hero_background_url = String(result.path)
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('Upload failed.'))
    } finally {
        heroBackgroundUploading.value = false
        if (target) {
            target.value = ''
        }
    }
}

const clearHeroBackgroundMedia = () => {
    if (editingSection.value === null) {
        return
    }

    editingSection.value.data.config.hero_background_url = ''
    editingSection.value.data.config.hero_background_type = 'image'
}

const handleCtaBannerBackgroundUpload = async (event: Event) => {
    if (editingSection.value === null) {
        return
    }

    const target = event.target as HTMLInputElement | null
    const file = target?.files?.[0]

    if (!file) {
        return
    }

    ctaBannerBackgroundUploading.value = true

    try {
        const payload = new FormData()
        payload.append('file', file)
        payload.append('directory', 'homepage')

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        const response = await fetch(route('admin.homepage.upload'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: payload,
        })

        const result = await response.json().catch(() => ({}))

        if (!response.ok || !result.path) {
            throw new Error(result.message || t('Upload failed.'))
        }

        editingSection.value.data.config.background_image_url = String(result.path)
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('Upload failed.'))
    } finally {
        ctaBannerBackgroundUploading.value = false
        if (target) {
            target.value = ''
        }
    }
}

const clearCtaBannerBackgroundMedia = () => {
    if (editingSection.value === null) {
        return
    }

    editingSection.value.data.config.background_image_url = ''
}

const handleToolsShowcaseBackgroundUpload = async (event: Event) => {
    if (editingSection.value === null) {
        return
    }

    const target = event.target as HTMLInputElement | null
    const file = target?.files?.[0]

    if (!file) {
        return
    }

    toolsShowcaseBackgroundUploading.value = true

    try {
        const payload = new FormData()
        payload.append('file', file)
        payload.append('directory', 'homepage')

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        const response = await fetch(route('admin.homepage.upload'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: payload,
        })

        const result = await response.json().catch(() => ({}))

        if (!response.ok || !result.path) {
            throw new Error(result.message || t('Upload failed.'))
        }

        editingSection.value.data.config.background_image_url = String(result.path)
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('Upload failed.'))
    } finally {
        toolsShowcaseBackgroundUploading.value = false
        if (target) {
            target.value = ''
        }
    }
}

const clearToolsShowcaseBackgroundMedia = () => {
    if (editingSection.value === null) {
        return
    }

    editingSection.value.data.config.background_image_url = ''
}

const normalizePhrases = () => {
    if (editingSection.value === null) return
    const phrases = editingSection.value.data.config.typing_phrases
    if (Array.isArray(phrases)) return
    editingSection.value.data.config.typing_phrases = String(phrases).split(',').map((phrase) => phrase.trim()).filter(Boolean)
}

const setHomepageTemplate = (slug: string) => {
    homepageTemplateForm.homepage_template = slug
    homepageTemplateForm.post(route('admin.homepage.set'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Homepage Builder - Admin')" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto px-6 py-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Homepage Builder') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Build the landing page with draggable sections, live preview controls, SEO, and conversion settings.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Tooltip :content="t('Export JSON')" placement="top">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Export JSON')" @click="exportConfig">
                            <i class="ti ti-file-export text-base"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Import JSON')" placement="top">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Import JSON')" @click="showImportModal = true">
                            <i class="ti ti-file-import text-base"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Reset')" placement="top">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Reset')" @click="requestResetToDefaults">
                            <i class="ti ti-restore text-base"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Live Preview')" placement="top">
                        <button @click="openPreview" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Live Preview')">
                            <i class="ti ti-external-link text-base"></i>
                        </button>
                    </Tooltip>
                    <button @click="submit" :disabled="form.processing" class="px-6 py-2.5 btn-primary rounded-xl text-sm font-bold transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50">
                        {{ form.processing ? t('Saving...') : t('Save Homepage') }}
                    </button>
                </div>
            </div>

            <!-- Homepage Selector -->
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6 mb-8">
                <h2 class="text-base font-bold text-gray-900 dark:text-white mb-4">{{ t('Choose Homepage') }}</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <label
                        @click="setHomepageTemplate('default')"
                        :class="isCustomHomepage ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-500' : 'border-gray-200 dark:border-surface-700 hover:border-gray-300 dark:hover:border-surface-600'"
                        class="cursor-pointer rounded-xl border px-5 py-3 transition-all"
                    >
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Custom Homepage') }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ t('Drag & drop builder') }}</p>
                    </label>
                    <label
                        v-for="tpl in props.availableTemplates"
                        :key="tpl.slug"
                        @click="setHomepageTemplate(tpl.slug)"
                        :class="!isCustomHomepage && props.activeHomepageTemplate === tpl.slug ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-500' : 'border-gray-200 dark:border-surface-700 hover:border-gray-300 dark:hover:border-surface-600'"
                        class="cursor-pointer rounded-xl border px-5 py-3 transition-all"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ tpl.name }}</span>
                            <span v-if="tpl.requires_pro" class="inline-flex items-center rounded-full bg-purple-100 dark:bg-purple-900/30 px-2 py-0.5 text-[10px] font-bold text-purple-700 dark:text-purple-400">{{ t('Pro') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ t('Site template') }}</p>
                    </label>
                </div>
                <p v-if="homepageTemplateForm.recentlySuccessful" class="mt-3 text-sm text-green-600 dark:text-green-400 font-medium">{{ t('Homepage updated') }}</p>
            </div>

            <!-- Show section builder only when Custom is selected -->
            <div v-if="isCustomHomepage" class="space-y-6">
                <div class="space-y-6">
                    <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6">
                        <div class="flex items-center justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ t('Homepage Sections') }}</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t(':enabled enabled of :total sections.', { enabled: enabledSectionsCount, total: form.sections.length }) }}</p>
                            </div>
                            <button @click="addSectionModalOpen = true" type="button" class="inline-flex items-center gap-1 px-4 py-2 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 rounded-lg text-sm font-semibold hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors">
                                <i class="ti ti-plus text-sm"></i>
                                {{ t('Add Section') }}
                            </button>
                        </div>

                        <div v-if="form.sections.length === 0" class="border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-2xl p-10 text-center bg-gray-50/50 dark:bg-surface-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1">{{ t('No sections yet') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ t('Add a section to start building your homepage.') }}</p>
                            <button @click="addSectionModalOpen = true" type="button" class="px-4 py-2 btn-primary rounded-lg text-sm font-bold">{{ t('Add first section') }}</button>
                        </div>

                        <VueDraggable v-model="form.sections" handle=".drag-handle" ghostClass="opacity-50" :animation="150" @start="isDragging = true" @end="isDragging = false" class="space-y-2">
                            <div v-for="(section, index) in form.sections" :key="section.id" :class="section.enabled ? 'border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800' : 'border-gray-200 bg-gray-25 dark:border-surface-700 dark:bg-surface-800'" class="group flex items-center gap-4 rounded-2xl border p-4 transition-all hover:shadow-md">
                                <div class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-primary-500 transition-colors shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 :class="section.enabled ? 'text-gray-900 dark:text-white' : 'line-through text-gray-500 dark:text-gray-400'" class="font-bold text-sm truncate">{{ getSectionMeta(section.type).label }}</h3>
                                        <span v-if="section.core" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-surface-800 text-gray-500">{{ t('Core') }}</span>
                                        <span :class="section.enabled ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-surface-800'" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md">{{ section.enabled ? t('Enabled') : t('Disabled') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ getSectionMeta(section.type).description }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Tooltip :content="section.enabled ? t('Disable section') : t('Enable section')" placement="top">
                                        <button @click="toggleSection(index)" type="button" :aria-label="section.enabled ? t('Disable section') : t('Enable section')" :class="section.enabled ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                            <span :class="section.enabled ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                        </button>
                                    </Tooltip>
                                    <Tooltip :content="t('Section settings')" placement="top">
                                        <button @click="editSection(index)" type="button" :aria-label="t('Section settings')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-gray-600 dark:text-gray-400 hover:text-primary-600 hover:border-primary-300 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></button>
                                    </Tooltip>
                                    <Tooltip :content="t('Delete section')" placement="top">
                                        <button @click="removeSection(index)" type="button" :aria-label="t('Delete section')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                    </Tooltip>
                                </div>
                            </div>
                        </VueDraggable>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="addSectionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="addSectionModalOpen = false">
            <div class="w-full max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Add Homepage Section') }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ t('Choose a section to add.') }}</p>
                    </div>
                    <Tooltip :content="t('Close')">
                        <button @click="addSectionModalOpen = false" type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </Tooltip>
                </div>
                <div class="grid max-h-[70vh] grid-cols-1 gap-3 overflow-y-auto p-6 sm:grid-cols-2">
                    <button
                        v-for="section in availableSections"
                        :key="section.type"
                        type="button"
                        class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-800 dark:hover:bg-primary-900/20 rtl:text-right"
                        @click="addSection(section.type)"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                            <i :class="[section.icon, 'text-base']"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-gray-900 dark:text-white">{{ section.label }}</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ section.description }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="sectionModalOpen && editingSection" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="sectionModalOpen = false">
            <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div class="flex items-start justify-between gap-4">
                        <div class="max-w-2xl">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(':section Settings', { section: getSectionMeta(editingSection.data.type).label }) }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ getSectionMeta(editingSection.data.type).description }}</p>
                        </div>
                        <Tooltip :content="t('Close')">
                            <button @click="sectionModalOpen = false" type="button" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </Tooltip>
                    </div>
                </div>
                <div class="space-y-5 overflow-y-auto p-6">
                    <div class="flex flex-wrap gap-2 border-b border-gray-100 pb-4 dark:border-surface-800">
                                <button
                                    v-for="tab in settingsTabs"
                                    :key="tab.key"
                            type="button"
                            class="rounded-full px-4 py-2 text-sm font-semibold transition"
                            :class="activeSettingsTab === tab.key ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700'"
                            @click="setSettingsTab(tab.key as SettingsTab)"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="space-y-6">

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'testimonials'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep testimonial controls simple for non-technical buyers.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                        @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.icon ?? '')"
                                        @update:model-value="setConfigString('icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.source ?? 'all')"
                                    @update:model-value="setConfigString('source', String($event ?? 'all'))"
                                    :label="t('Testimonial Source')"
                                    :placeholder="t('Select Testimonial Source...')"
                                    :options="[
                                        { value: 'all', label: t('All Testimonials') },
                                        { value: 'featured', label: t('Featured Only') },
                                    ]"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.card_style ?? 'bordered')"
                                    @update:model-value="setConfigString('card_style', String($event ?? 'bordered'))"
                                    :label="t('Card Style')"
                                    :placeholder="t('Select Card Style...')"
                                    :options="[
                                        { value: 'simple', label: t('Simple') },
                                        { value: 'bordered', label: t('Bordered') },
                                        { value: 'spotlight', label: t('Spotlight') },
                                    ]"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 6)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value))"
                                        type="number"
                                        min="1"
                                        max="12"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>

                        <a
                            :href="route('admin.testimonials.index')"
                            target="_blank"
                            class="flex items-center gap-3 rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 transition-colors hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/20 dark:hover:bg-primary-900/40"
                        >
                            <svg class="h-4 w-4 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            <div class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-primary-700 dark:text-primary-300">{{ t('Manage Testimonials') }}</span>
                                <span class="block text-xs text-primary-500 dark:text-primary-400">{{ t('Add, edit, and reorder customer reviews used by this section.') }}</span>
                            </div>
                            <svg class="h-4 w-4 shrink-0 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'faq'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep FAQ controls simple for non-technical buyers.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                        @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.icon ?? '')"
                                        @update:model-value="setConfigString('icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 8)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value))"
                                        type="number"
                                        min="1"
                                        max="20"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>

                        <a
                            :href="route('admin.faqs.index')"
                            target="_blank"
                            class="flex items-center gap-3 rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 transition-colors hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/20 dark:hover:bg-primary-900/40"
                        >
                            <svg class="h-4 w-4 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-primary-700 dark:text-primary-300">{{ t('Manage FAQs') }}</span>
                                <span class="block text-xs text-primary-500 dark:text-primary-400">{{ t('Add, edit, and reorder FAQ entries used by this section.') }}</span>
                            </div>
                            <svg class="h-4 w-4 shrink-0 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'stats_bar'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep social proof controls simple for non-technical buyers.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                        @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.icon ?? '')"
                                        @update:model-value="setConfigString('icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <AppSelect
                                    :model-value="Boolean(editingSection.data.config.show_stats_separator) ? '1' : '0'"
                                    @update:model-value="setConfigString('show_stats_separator', String($event ?? '1') === '1')"
                                    :label="t('Show Line Before Stats')"
                                    :placeholder="t('Select...')"
                                    :options="[
                                        { value: '1', label: t('Show') },
                                        { value: '0', label: t('Hide') },
                                    ]"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.stats_number_color ?? 'dark')"
                                    @update:model-value="setConfigString('stats_number_color', String($event ?? 'dark'))"
                                    :label="t('Stats Number Color')"
                                    :placeholder="t('Select Stats Number Color...')"
                                    :options="heroHeadingColorOptions"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.stats_label_color ?? 'light')"
                                    @update:model-value="setConfigString('stats_label_color', String($event ?? 'light'))"
                                    :label="t('Stats Label Color')"
                                    :placeholder="t('Select Stats Label Color...')"
                                    :options="heroSubheadingColorOptions"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'template_grid'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Template Tool Grid Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Pick a template and keep the embedded tool grid easy to edit.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.title ?? '')"
                                        @input="setConfigString('title', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subtitle', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.template_slug ?? '')"
                                    @update:model-value="setConfigString('template_slug', String($event ?? ''))"
                                    :label="t('Template')"
                                    :placeholder="t('Choose a template...')"
                                    :options="availableGridTemplates.map((tpl) => ({ value: tpl.slug, label: tpl.name }))"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 12)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value || 0))"
                                        type="number"
                                        min="1"
                                        max="50"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <AppSelect
                                    :model-value="Boolean(editingSection.data.config.show_filter) ? '1' : '0'"
                                    @update:model-value="setConfigString('show_filter', String($event ?? '1') === '1')"
                                    :label="t('Show Filter')"
                                    :placeholder="t('Select...')"
                                    :options="[
                                        { value: '1', label: t('Show') },
                                        { value: '0', label: t('Hide') },
                                    ]"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'all_tools'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('All Tools Browser Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Control the browser heading, default tab, and lightweight visibility toggles.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.title ?? '')"
                                        @input="setConfigString('title', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subtitle', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.default_tab ?? 'popular')"
                                    @update:model-value="setConfigString('default_tab', String($event ?? 'popular'))"
                                    :label="t('Default Tab')"
                                    :placeholder="t('Select Default Tab...')"
                                    :options="[
                                        { value: 'popular', label: t('Popular') },
                                        { value: 'featured', label: t('Featured') },
                                        { value: 'recent', label: t('Recent') },
                                    ]"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 12)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value || 0))"
                                        type="number"
                                        min="1"
                                        max="50"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <AppSelect
                                    :model-value="Boolean(editingSection.data.config.show_search) ? '1' : '0'"
                                    @update:model-value="setConfigString('show_search', String($event ?? '1') === '1')"
                                    :label="t('Show Search')"
                                    :placeholder="t('Select...')"
                                    :options="[
                                        { value: '1', label: t('Show') },
                                        { value: '0', label: t('Hide') },
                                    ]"
                                />
                                <AppSelect
                                    :model-value="Boolean(editingSection.data.config.show_categories) ? '1' : '0'"
                                    @update:model-value="setConfigString('show_categories', String($event ?? '1') === '1')"
                                    :label="t('Show Categories')"
                                    :placeholder="t('Select...')"
                                    :options="[
                                        { value: '1', label: t('Show') },
                                        { value: '0', label: t('Hide') },
                                    ]"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'ad_slot'" class="mb-2 rounded-xl border border-primary-100 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-900/20">
                        <p class="mb-3 text-sm text-primary-700 dark:text-primary-300">{{ t('Select an ad zone managed from the Ads menu. The active ad in that zone will render on the homepage.') }}</p>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'features'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Use simple controls so non-technical buyers can edit the section quickly.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Learn More Btn Text') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.learn_more_text ?? '')"
                                        @input="setConfigString('learn_more_text', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Learn More Btn Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Text') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.button_text ?? '')"
                                        @input="setConfigString('button_text', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Button Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Link') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.button_link ?? '')"
                                        @input="setConfigString('button_link', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Button Link...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'image_carousel'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the carousel title and playback settings easy to edit.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Title') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.title ?? '')"
                                        @input="setConfigString('title', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Title...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Subtitle') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subtitle', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Subtitle...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <AppSelect
                                    :model-value="Boolean(editingSection.data.config.auto_play) ? '1' : '0'"
                                    @update:model-value="setConfigString('auto_play', String($event ?? '0'))"
                                    :label="t('Auto Play')"
                                    :placeholder="t('Select...')"
                                    :options="[
                                        { value: '0', label: t('Off') },
                                        { value: '1', label: t('On') },
                                    ]"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Interval (ms)') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.interval_ms ?? 5000)"
                                        @input="setConfigString('interval_ms', ($event.target as HTMLInputElement).value)"
                                        type="number"
                                        min="1000"
                                        max="20000"
                                        step="500"
                                        :placeholder="t('Enter Interval...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'tools_showcase'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the tools showcase easy to edit while still letting buyers control the section look.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect
                                    :model-value="String(editingSection.data.config.source ?? 'all')"
                                    @update:model-value="setConfigString('source', String($event ?? 'all'))"
                                    :label="t('Tool Source')"
                                    :placeholder="t('Select Tool Source...')"
                                    :options="toolsShowcaseSourceOptions"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 6)"
                                        @input="setConfigString('max_items', ($event.target as HTMLInputElement).value)"
                                        type="number"
                                        min="1"
                                        max="24"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Call To Action') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Control the main showcase button from one card.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Primary Text') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.primary_text ?? '')"
                                        @input="setConfigString('primary_text', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Primary Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Primary Link') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.primary_link ?? '')"
                                        @input="setConfigString('primary_link', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Primary Link...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Primary Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.primary_icon ?? '')"
                                        @update:model-value="setConfigString('primary_icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'hero'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Headline') }}</label>
                                <input
                                    :value="String(editingSection.data.config.headline ?? '')"
                                    @input="setConfigString('headline', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Headline...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Subheadline') }}</label>
                                <textarea
                                    :value="String(editingSection.data.config.subheadline ?? '')"
                                    @input="setConfigString('subheadline', ($event.target as HTMLTextAreaElement).value)"
                                    rows="4"
                                    :placeholder="t('Write subheadline...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                ></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Trusted Badge Text') }}</label>
                                <input
                                    :value="String(editingSection.data.config.trust_badge_text ?? '')"
                                    @input="setConfigString('trust_badge_text', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Trusted Badge Text...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Primary Button Text') }}</label>
                                <input
                                    :value="String(editingSection.data.config.primary_cta_text ?? '')"
                                    @input="setConfigString('primary_cta_text', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Primary Button Text...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Primary Button Link') }}</label>
                                <input
                                    :value="String(editingSection.data.config.primary_cta_link ?? '')"
                                    @input="setConfigString('primary_cta_link', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Primary Button Link...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Secondary Button Text') }}</label>
                                <input
                                    :value="String(editingSection.data.config.secondary_cta_text ?? '')"
                                    @input="setConfigString('secondary_cta_text', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Secondary Button Text...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Secondary Button Link') }}</label>
                                <input
                                    :value="String(editingSection.data.config.secondary_cta_link ?? '')"
                                    @input="setConfigString('secondary_cta_link', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Secondary Button Link...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'latest_posts'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep blog section controls simple for non-technical buyers.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                        @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.icon ?? '')"
                                        @update:model-value="setConfigString('icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.source ?? 'recent')"
                                    @update:model-value="setConfigString('source', String($event ?? 'recent'))"
                                    :label="t('Post Source')"
                                    :placeholder="t('Select Post Source...')"
                                    :options="[
                                        { value: 'recent', label: t('Recent Posts') },
                                        { value: 'featured', label: t('Featured Only') },
                                    ]"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.layout ?? 'grid')"
                                    @update:model-value="setConfigString('layout', String($event ?? 'grid'))"
                                    :label="t('Layout')"
                                    :placeholder="t('Select Layout...')"
                                    :options="[
                                        { value: 'grid', label: t('Grid') },
                                        { value: 'list', label: t('List') },
                                    ]"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.card_style ?? 'bordered')"
                                    @update:model-value="setConfigString('card_style', String($event ?? 'bordered'))"
                                    :label="t('Card Style')"
                                    :placeholder="t('Select Card Style...')"
                                    :options="[
                                        { value: 'simple', label: t('Simple') },
                                        { value: 'bordered', label: t('Bordered') },
                                        { value: 'image_focus', label: t('Image Focus') },
                                    ]"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 3)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value))"
                                        type="number"
                                        min="1"
                                        max="12"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Text') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.button_text ?? '')"
                                        @input="setConfigString('button_text', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Button Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Link') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.button_link ?? '/blog')"
                                        @input="setConfigString('button_link', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Button Link...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.button_icon ?? '')"
                                        @update:model-value="setConfigString('button_icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.button_style ?? 'outline')"
                                    @update:model-value="setConfigString('button_style', String($event ?? 'outline'))"
                                    :label="t('Button Style')"
                                    :placeholder="t('Select Button Style...')"
                                    :options="heroButtonStyleOptions"
                                />
                            </div>
                        </div>
                        <a
                            :href="route('admin.blog.posts.index')"
                            target="_blank"
                            class="flex items-center gap-3 rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 transition-colors hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/20 dark:hover:bg-primary-900/40"
                        >
                            <svg class="h-4 w-4 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7H5m14 0a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2m14 0V5a2 2 0 00-2-2H7a2 2 0 00-2 2v2"/></svg>
                            <div class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-primary-700 dark:text-primary-300">{{ t('Manage Blog Posts') }}</span>
                                <span class="block text-xs text-primary-500 dark:text-primary-400">{{ t('Create, edit, and publish the posts used by this section.') }}</span>
                            </div>
                            <svg class="h-4 w-4 shrink-0 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'integrations'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep this section focused on logos so buyers can update it fast.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                        @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.icon ?? '')"
                                        @update:model-value="setConfigString('icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 6)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value || 0))"
                                        type="number"
                                        min="1"
                                        max="24"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'announcement'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Announcement Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Choose how active announcements should appear on the homepage.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.title ?? t('Announcements'))"
                                        @input="setConfigString('title', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subtitle ?? t('Show active announcements from the announcements manager.'))"
                                        @input="setConfigString('subtitle', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.announcement_type ?? 'topbar')"
                                    @update:model-value="setConfigString('announcement_type', String($event ?? 'topbar'))"
                                    :label="t('Announcement Type')"
                                    :placeholder="t('Select Announcement Type...')"
                                    :options="announcementTypeOptions"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.style ?? 'cards')"
                                    @update:model-value="setConfigString('style', String($event ?? 'cards'))"
                                    :label="t('Display Style')"
                                    :placeholder="t('Select Display Style...')"
                                    :options="announcementStyleOptions"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Max Items') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.max_items ?? 3)"
                                        @input="setConfigString('max_items', Number(($event.target as HTMLInputElement).value || 0))"
                                        type="number"
                                        min="1"
                                        max="12"
                                        :placeholder="t('Enter Max Items...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'newsletter'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Section Settings') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep newsletter controls simple for non-technical buyers.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                        @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                        @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                        rows="3"
                                        :placeholder="t('Enter Sub Heading...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.icon ?? '')"
                                        @update:model-value="setConfigString('icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.layout ?? 'inline')"
                                    @update:model-value="setConfigString('layout', String($event ?? 'inline'))"
                                    :label="t('Layout')"
                                    :placeholder="t('Select Layout...')"
                                    :options="[
                                        { value: 'inline', label: t('Inline') },
                                        { value: 'stacked', label: t('Stacked') },
                                    ]"
                                />
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Email Placeholder') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.placeholder_text ?? '')"
                                        @input="setConfigString('placeholder_text', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Placeholder Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Text') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.button_text ?? '')"
                                        @input="setConfigString('button_text', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        :placeholder="t('Enter Button Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Icon') }}</label>
                                    <AppIconSelect
                                        :model-value="String(editingSection.data.config.button_icon ?? '')"
                                        @update:model-value="setConfigString('button_icon', String($event ?? ''))"
                                        :placeholder="t('Choose an icon...')"
                                    />
                                </div>
                                <AppSelect
                                    :model-value="String(editingSection.data.config.button_style ?? 'primary_filled')"
                                    @update:model-value="setConfigString('button_style', String($event ?? 'primary_filled'))"
                                    :label="t('Button Style')"
                                    :placeholder="t('Select Button Style...')"
                                    :options="heroButtonStyleOptions"
                                />
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Privacy Text') }}</label>
                                    <textarea
                                        :value="String(editingSection.data.config.privacy_text ?? '')"
                                        @input="setConfigString('privacy_text', ($event.target as HTMLTextAreaElement).value)"
                                        rows="2"
                                        :placeholder="t('Enter Privacy Text...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'cta_banner'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Headline') }}</label>
                                <input
                                    :value="String(editingSection.data.config.headline ?? '')"
                                    @input="setConfigString('headline', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Headline...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Subheadline') }}</label>
                                <textarea
                                    :value="String(editingSection.data.config.subheadline ?? '')"
                                    @input="setConfigString('subheadline', ($event.target as HTMLTextAreaElement).value)"
                                    rows="3"
                                    :placeholder="t('Write subheadline...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                ></textarea>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Text') }}</label>
                                <input
                                    :value="String(editingSection.data.config.primary_text ?? '')"
                                    @input="setConfigString('primary_text', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Button Text...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Link') }}</label>
                                <input
                                    :value="String(editingSection.data.config.primary_link ?? '')"
                                    @input="setConfigString('primary_link', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Button Link...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Button Icon') }}</label>
                                <AppIconSelect
                                    :model-value="String(editingSection.data.config.primary_icon ?? '')"
                                    @update:model-value="setConfigString('primary_icon', String($event ?? ''))"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'pricing'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                                <input
                                    :value="String(editingSection.data.config.heading ?? editingSection.data.config.title ?? '')"
                                    @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                    type="text"
                                    :placeholder="t('Enter Heading...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                                <textarea
                                    :value="String(editingSection.data.config.subheading ?? editingSection.data.config.subtitle ?? '')"
                                    @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                    rows="3"
                                    :placeholder="t('Enter Sub Heading...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                ></textarea>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                                <AppIconSelect
                                    :model-value="String(editingSection.data.config.icon ?? '')"
                                    @update:model-value="setConfigString('icon', String($event ?? ''))"
                                    :placeholder="t('Choose an icon...')"
                                />
                            </div>
                            <AppSelect
                                :model-value="String(editingSection.data.config.source ?? 'all')"
                                @update:model-value="setConfigString('source', String($event ?? 'all'))"
                                :label="t('Source')"
                                :placeholder="t('Select Source...')"
                                :options="pricingSourceOptions"
                            />
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && editingSection.data.type === 'how_it_works'" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="md:col-span-2 rounded-2xl border border-primary-100 bg-primary-50/70 p-4 dark:border-primary-900/30 dark:bg-primary-900/10">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-700 dark:text-primary-300">
                                <i class="ti ti-route text-sm"></i>
                                <span>{{ t('Section Settings') }}</span>
                            </div>
                            <p class="mt-2 text-xs text-primary-700/80 dark:text-primary-300/80">{{ t('Edit the main title, subheading, and section icon only.') }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Heading') }}</label>
                            <input
                                :value="String(editingSection.data.config.heading ?? '')"
                                @input="setConfigString('heading', ($event.target as HTMLInputElement).value)"
                                type="text"
                                :placeholder="t('Enter Heading...')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Sub Heading') }}</label>
                            <textarea
                                :value="String(editingSection.data.config.subheading ?? '')"
                                @input="setConfigString('subheading', ($event.target as HTMLTextAreaElement).value)"
                                rows="3"
                                :placeholder="t('Enter Sub Heading...')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Icon') }}</label>
                            <AppIconSelect
                                :model-value="String(editingSection.data.config.icon ?? '')"
                                @update:model-value="setConfigString('icon', String($event ?? ''))"
                                :placeholder="t('Choose an icon...')"
                            />
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'general' && !['hero', 'features', 'tools_showcase', 'cta_banner', 'image_carousel', 'pricing', 'how_it_works', 'testimonials', 'faq', 'stats_bar', 'integrations', 'announcement', 'template_grid', 'all_tools'].includes(editingSection.data.type)" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div
                            v-for="(value, key) in editingSection.data.config"
                            :key="key"
                            v-show="activeSettingsTab === 'general' && !isHiddenConfigKey(editingSection.data.type, String(key)) && !isStyleFieldKey(String(key)) && !isVisibilityFieldKey(String(key)) && !isAdvancedFieldKey(String(key))"
                            :class="[
                                Array.isArray(value) && !value.every((item) => typeof item === 'string') ? 'md:col-span-2' : '',
                                editingSection.data.type === 'richtext' && String(key) === 'content' ? 'md:col-span-2' : '',
                            ]"
                            class="min-w-0"
                        >
                            <template v-if="editingSection.data.type === 'richtext' && String(key) === 'content'">
                                <div class="w-full md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                    <RichEditor v-model="editingSection.data.config[key]" variant="full" />
                                </div>
                            </template>
                            <template v-else-if="editingSection.data.type === 'ad_slot' && String(key) === 'zone'">
                                <AppSelect
                                    :model-value="String(editingSection.data.config[key] ?? '')"
                                    @update:model-value="setConfigString(String(key), String($event ?? ''))"
                                    :label="configLabel(key)"
                                    :placeholder="t('Select Ad Zone...')"
                                    dropdown-placement="top"
                                    :options="[
                                        { value: '', label: t('Select Ad Zone...') },
                                        ...adZoneOptions.map((zone) => ({
                                            value: zone,
                                            label: zoneOptionLabel(zone),
                                        })),
                                    ]"
                                />
                            </template>
                            <template v-else-if="editingSection.data.type === 'announcement' && String(key) === 'announcement_type'">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <select
                                    :value="String(editingSection.data.config[key] ?? 'topbar')"
                                    @input="setConfigString(String(key), ($event.target as HTMLSelectElement).value)"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                                    <option value="topbar">{{ t('Topbar') }}</option>
                                    <option value="popup">{{ t('Popup') }}</option>
                                    <option value="notification">{{ t('Notification') }}</option>
                                    <option value="all">{{ t('All') }}</option>
                                </select>
                            </template>
                            <template v-else-if="editingSection.data.type === 'announcement' && String(key) === 'style'">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <select
                                    :value="String(editingSection.data.config[key] ?? 'cards')"
                                    @input="setConfigString(String(key), ($event.target as HTMLSelectElement).value)"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                                    <option value="cards">{{ t('Cards') }}</option>
                                    <option value="compact">{{ t('Compact') }}</option>
                                </select>
                            </template>
                            <template v-else-if="typeof value === 'boolean'">
                                <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                                    <span class="text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</span>
                                    <button @click="editingSection.data.config[key] = !value" type="button" :class="value ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                        <span :class="value ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                    </button>
                                </label>
                            </template>
                            <template v-else-if="typeof value === 'number'">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <input
                                    :value="String(editingSection.data.config[key] ?? '')"
                                    @input="setConfigString(String(key), Number(($event.target as HTMLInputElement).value))"
                                    type="number"
                                    :placeholder="configPlaceholder(key)"
                                    class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"
                                >
                            </template>
                            <template v-else-if="Array.isArray(value) && value.every((item) => typeof item === 'string')">
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <input :value="value.join(', ')" @input="editingSection.data.config[key] = ($event.target as HTMLInputElement).value.split(',').map((item) => item.trim()).filter(Boolean)" @blur="normalizePhrases" type="text" :placeholder="configPlaceholder(key)" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                            <template v-else-if="Array.isArray(value)">
                                <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-surface-700 dark:bg-surface-800/60 md:col-span-2">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                        <button @click="addListItem(String(key))" type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30">
                                            <i class="ti ti-plus text-xs"></i>
                                            {{ t('Add Item') }}
                                        </button>
                                    </div>
                                    <div class="space-y-3">
                                        <div v-if="value.length === 0" class="text-xs text-gray-500 dark:text-gray-400">{{ t('No items yet. Add one to display content.') }}</div>
                                        <div v-for="(item, itemIndex) in value" :key="itemIndex" class="space-y-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900">
                                            <div v-if="editingSection.data.type === 'how_it_works'" class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                                                {{ t('Step') }} {{ String(itemIndex + 1).padStart(2, '0') }}
                                            </div>
                                            <div v-for="(itemValue, itemKey) in item" :key="itemKey">
                                                <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ configLabel(itemKey) }}</label>
                                                <template v-if="editingSection.data.type === 'image_carousel' && String(itemKey) === 'image_url'">
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                                        @change="handleItemImageUpload(item, $event)"
                                                    >
                                                    <div v-if="item.image_url" class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                                                        <img :src="String(item.image_url)" :alt="t('Carousel image preview')" class="h-24 w-full rounded-md object-cover">
                                                        <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-danger-500" @click="clearCarouselImage(item)">
                                                            <i class="ti ti-trash text-xs"></i>
                                                            {{ t('Remove image') }}
                                                        </button>
                                                    </div>
                                                </template>
                                                <input v-else :value="String(item[itemKey] ?? '')" @input="setItemString(item, String(itemKey), ($event.target as HTMLInputElement).value)" type="text" :placeholder="configPlaceholder(itemKey)" class="w-full bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white">
                                            </div>
                                            <button @click="removeListItem(String(key), itemIndex)" type="button" class="inline-flex items-center gap-1 text-xs font-bold text-danger-500">
                                                <i class="ti ti-trash text-xs"></i>
                                                {{ t('Remove item') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <textarea v-if="String(key).includes('content') || String(key).includes('embed') || String(key).includes('description') || String(key).includes('subheadline')" :value="String(editingSection.data.config[key] ?? '')" @input="setConfigString(String(key), ($event.target as HTMLTextAreaElement).value)" rows="3" :placeholder="configPlaceholder(key)" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"></textarea>
                                <input v-else :value="String(editingSection.data.config[key] ?? '')" @input="setConfigString(String(key), ($event.target as HTMLInputElement).value)" type="text" :placeholder="configPlaceholder(key)" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                        </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'items'" class="space-y-6">
                        <div v-if="editingSection.data.type === 'integrations'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Technology Logos') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Upload logos, add a label, and optionally link each logo to a page or external site.') }}</p>
                                </div>
                                <button @click="addListItem('items')" type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30">
                                    <i class="ti ti-plus text-xs"></i>
                                    {{ t('Add Logo') }}
                                </button>
                            </div>
                            <div class="mt-5 space-y-4">
                                <div
                                    v-for="(item, itemIndex) in (Array.isArray(editingSection.data.config.items) ? editingSection.data.config.items : [])"
                                    :key="itemIndex"
                                    class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900"
                                >
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="md:col-span-2">
                                            <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Logo Image') }}</label>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                                @change="handleItemImageUpload(item, $event)"
                                            >
                                            <div v-if="String(item.image_url ?? '')" class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                                                <img :src="resolveStoredMediaUrl(String(item.image_url))" :alt="t('Logo preview')" class="h-20 w-full rounded-lg object-contain p-2">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Logo Label') }}</label>
                                            <input
                                                :value="String(item.title ?? '')"
                                                @input="setItemString(item, 'title', ($event.target as HTMLInputElement).value)"
                                                type="text"
                                                :placeholder="t('Enter Logo Label...')"
                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                            >
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Logo Link') }}</label>
                                            <input
                                                :value="String(item.link_url ?? '')"
                                                @input="setItemString(item, 'link_url', ($event.target as HTMLInputElement).value)"
                                                type="text"
                                                :placeholder="t('Enter Logo Link...')"
                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                            >
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Open In') }}</label>
                                            <AppSelect
                                                :model-value="String(item.link_open_new_tab ? '1' : '0')"
                                                @update:model-value="setItemString(item, 'link_open_new_tab', String($event ?? '0') === '1')"
                                                :placeholder="t('Select Open In...')"
                                                :options="[
                                                    { value: '0', label: t('Same Tab') },
                                                    { value: '1', label: t('New Tab') },
                                                ]"
                                            />
                                        </div>
                                    </div>
                                    <button @click="removeListItem('items', itemIndex)" type="button" class="mt-4 inline-flex items-center gap-1 text-xs font-bold text-danger-500">
                                        <i class="ti ti-trash text-xs"></i>
                                        {{ t('Remove logo') }}
                                    </button>
                                </div>
                                <div v-if="!Array.isArray(editingSection.data.config.items) || editingSection.data.config.items.length === 0" class="rounded-xl border border-dashed border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400">
                                    {{ t('No logos yet. Add one to start.') }}
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ editingSection.data.type === 'how_it_works' ? t('Steps') : t('Items') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ editingSection.data.type === 'how_it_works' ? t('Each step has a title, icon, and description. Use clear copy so buyers can update the flow quickly.') : t('Edit repeatable content for cards, stats, slides, FAQs, or similar lists.') }}</p>
                                </div>
                            </div>
                            <div v-if="sectionHasItems && editingSection.data.type !== 'integrations'" class="mt-5 space-y-4">
                                <div
                                    v-for="(value, key) in editingSection.data.config"
                                    :key="key"
                                    v-show="isItemFieldKey(String(key), value)"
                                >
                                    <div v-if="editingSection.data.type !== 'integrations' && Array.isArray(value) && value.length > 0 && value.every((item) => typeof item !== 'string')" class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ t(':count items', { count: value.length }) }}</span>
                                                <button @click="addListItem(String(key))" type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30">
                                                    <i class="ti ti-plus text-xs"></i>
                                                    {{ editingSection.data.type === 'how_it_works' ? t('Add Step') : editingSection.data.type === 'integrations' ? t('Add Logo') : t('Add Item') }}
                                                </button>
                                            </div>
                                        </div>
                                        <div v-for="(item, itemIndex) in value" :key="itemIndex" class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                                            <div v-if="editingSection.data.type === 'how_it_works'" class="mb-3 inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                                                {{ t('Step') }} {{ String(itemIndex + 1).padStart(2, '0') }}
                                            </div>
                                            <template v-if="editingSection.data.type === 'how_it_works'">
                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Step Title') }}</label>
                                                        <input
                                                            :value="String(item.title ?? '')"
                                                            @input="setItemString(item, 'title', ($event.target as HTMLInputElement).value)"
                                                            type="text"
                                                            :placeholder="t('Enter Step Title...')"
                                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                                        >
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Step Icon') }}</label>
                                                        <AppIconSelect :model-value="String(item.icon ?? '')" @update:model-value="setItemString(item, 'icon', String($event ?? ''))" :placeholder="t('Choose an icon...')" />
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Step Description') }}</label>
                                                        <textarea
                                                            :value="String(item.description ?? '')"
                                                            @input="setItemString(item, 'description', ($event.target as HTMLTextAreaElement).value)"
                                                            rows="3"
                                                            :placeholder="t('Write Step Description...')"
                                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                                        ></textarea>
                                                    </div>
                                                    <div v-if="Object.keys(item).some((itemKey) => !['title', 'description', 'icon'].includes(itemKey))" class="md:col-span-2 grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        <div v-for="(itemValue, itemKey) in item" :key="itemKey" v-show="!['title', 'description', 'icon'].includes(String(itemKey))" :class="String(itemKey) === 'link' ? 'md:col-span-2' : ''">
                                                            <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ configLabel(itemKey) }}</label>
                                                            <input
                                                                :value="String(item[itemKey] ?? '')"
                                                                @input="setItemString(item, String(itemKey), ($event.target as HTMLInputElement).value)"
                                                                type="text"
                                                                :placeholder="configPlaceholder(itemKey)"
                                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else-if="editingSection.data.type === 'integrations'">
                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div class="md:col-span-2">
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Logo Image') }}</label>
                                                        <input
                                                            type="file"
                                                            accept="image/*"
                                                            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                                            @change="handleItemImageUpload(item, $event)"
                                                        >
                                                        <div v-if="String(item.image_url ?? '')" class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                                                            <img :src="resolveStoredMediaUrl(String(item.image_url))" :alt="t('Logo preview')" class="h-20 w-full rounded-lg object-contain p-2">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Logo Label') }}</label>
                                                        <input
                                                            :value="String(item.title ?? '')"
                                                            @input="setItemString(item, 'title', ($event.target as HTMLInputElement).value)"
                                                            type="text"
                                                            :placeholder="t('Enter Logo Label...')"
                                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                                        >
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ t('Logo Link') }}</label>
                                                        <input
                                                            :value="String(item.link_url ?? '')"
                                                            @input="setItemString(item, 'link_url', ($event.target as HTMLInputElement).value)"
                                                            type="text"
                                                            :placeholder="t('Enter Logo Link...')"
                                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                                        >
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div v-for="(itemValue, itemKey) in item" :key="itemKey" :class="String(itemKey) === 'description' ? 'md:col-span-2' : ''">
                                                        <label class="mb-1 block text-xs font-medium capitalize text-gray-500 dark:text-gray-400">{{ configLabel(itemKey) }}</label>
                                                        <template v-if="String(itemKey) === 'icon'">
                                                            <AppIconSelect :model-value="String(item.icon ?? '')" @update:model-value="setItemString(item, 'icon', String($event ?? ''))" :placeholder="t('Choose an icon...')" />
                                                        </template>
                                                        <template v-else-if="String(itemKey) === 'image_url'">
                                                            <input type="file" accept="image/*" class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200" @change="handleItemImageUpload(item, $event)">
                                                            <div v-if="String(item.image_url ?? '')" class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                                                                <img :src="resolveStoredMediaUrl(String(item.image_url))" :alt="t('Item image preview')" class="h-24 w-full rounded-lg object-cover">
                                                                <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-danger-500" @click="clearCarouselImage(item)">
                                                                    <i class="ti ti-trash text-xs"></i>
                                                                    {{ t('Remove image') }}
                                                                </button>
                                                            </div>
                                                        </template>
                                                        <template v-else-if="String(itemKey) === 'link_open_new_tab'">
                                                            <AppSelect :model-value="Boolean(item.link_open_new_tab) ? '1' : '0'" @update:model-value="setItemString(item, 'link_open_new_tab', String($event ?? '0'))" :placeholder="t('Select...')" :options="[{ value: '0', label: t('Same Tab') }, { value: '1', label: t('New Tab') }]" />
                                                        </template>
                                                        <textarea v-else-if="typeof itemValue === 'string' && (String(itemKey).includes('description') || String(itemKey).includes('content'))" :value="String(item[itemKey] ?? '')" @input="setItemString(item, String(itemKey), ($event.target as HTMLTextAreaElement).value)" rows="3" :placeholder="configPlaceholder(itemKey)" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                                                        <input v-else :value="String(item[itemKey] ?? '')" @input="setItemString(item, String(itemKey), ($event.target as HTMLInputElement).value)" type="text" :placeholder="configPlaceholder(itemKey)" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                                    </div>
                                                </div>
                                            </template>
                                            <button @click="removeListItem(String(key), itemIndex)" type="button" class="mt-4 inline-flex items-center gap-1 text-xs font-bold text-danger-500">
                                                <i class="ti ti-trash text-xs"></i>
                                                {{ editingSection.data.type === 'how_it_works' ? t('Remove step') : t('Remove item') }}
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else-if="editingSection.data.type !== 'integrations' && Array.isArray(value) && value.every((item) => typeof item === 'string')" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                                        <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                        <input :value="value.join(', ')" @input="editingSection.data.config[key] = ($event.target as HTMLInputElement).value.split(',').map((item) => item.trim()).filter(Boolean)" type="text" :placeholder="configPlaceholder(key)" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                    </div>
                                    <div v-else-if="editingSection.data.type !== 'integrations' && Array.isArray(value) && value.length === 0" class="rounded-xl border border-dashed border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400">
                                        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <span>{{ editingSection.data.type === 'how_it_works' ? t('No steps yet. Add one to start.') : editingSection.data.type === 'integrations' ? t('No logos yet. Add one to start.') : t('No items yet. Add one to start.') }}</span>
                                            <button @click="addListItem(String(key))" type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30">
                                                <i class="ti ti-plus text-xs"></i>
                                                {{ editingSection.data.type === 'how_it_works' ? t('Add Step') : editingSection.data.type === 'integrations' ? t('Add Logo') : t('Add Item') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'style'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Style') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Shared design controls for headings, cards, spacing, backgrounds, and buttons.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect
                                    v-if="editingSection.data.type !== 'integrations'"
                                    :model-value="String(editingSection.data.config.layout ?? 'center')"
                                    @update:model-value="setConfigString('layout', String($event ?? 'center'))"
                                    :label="t('Alignment')"
                                    :placeholder="t('Select Alignment...')"
                                    :options="heroLayoutOptions"
                                />
                                <AppSelect
                                    v-else
                                    :model-value="String(editingSection.data.config.layout ?? 'grid')"
                                    @update:model-value="setConfigString('layout', String($event ?? 'grid'))"
                                    :label="t('Layout')"
                                    :placeholder="t('Select Layout...')"
                                    :options="integrationsLayoutOptions"
                                />
                                <AppSelect :model-value="String(editingSection.data.config.section_style ?? 'default')" @update:model-value="setConfigString('section_style', String($event ?? 'default'))" :label="t('Section Style')" :placeholder="t('Select Section Style...')" :options="sectionStylePresetOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.heading_color ?? editingSection.data.config.hero_heading_color ?? 'dark')" @update:model-value="setConfigString('heading_color', String($event ?? 'dark'))" :label="t('Heading Color')" :placeholder="t('Select Heading Color...')" :options="heroHeadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.subheading_color ?? editingSection.data.config.hero_subheading_color ?? 'light')" @update:model-value="setConfigString('subheading_color', String($event ?? 'light'))" :label="t('Sub Heading Color')" :placeholder="t('Select Sub Heading Color...')" :options="heroSubheadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.background_style ?? 'gradient-1')" @update:model-value="setConfigString('background_style', String($event ?? 'gradient-1'))" :label="t('Background Style')" :placeholder="t('Select Background Style...')" :options="ctaBannerBackgroundStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.width ?? 'contained')" @update:model-value="setConfigString('width', String($event ?? 'contained'))" :label="t('Width')" :placeholder="t('Select Width...')" :options="ctaBannerWidthOptions" />
                                <div v-if="editingSection.data.type !== 'how_it_works'">
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Padding') }}</label>
                                    <input :value="String(editingSection.data.config.section_vertical_padding ?? editingSection.data.config.hero_vertical_padding ?? 96)" @input="setConfigString(editingSection.data.type === 'hero' ? 'hero_vertical_padding' : 'section_vertical_padding', ($event.target as HTMLInputElement).value)" type="number" min="0" max="240" :placeholder="t('Enter Padding...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </div>
                                <AppSelect :model-value="String(editingSection.data.config.button_style ?? editingSection.data.config.primary_style ?? 'primary_filled')" @update:model-value="setConfigString(editingSection.data.type === 'cta_banner' ? 'primary_style' : 'button_style', String($event ?? 'primary_filled'))" :label="t('Button Style')" :placeholder="t('Select Button Style...')" :options="heroButtonStyleOptions" />
                            </div>
                        </div>
                        <div v-if="editingSection.data.type === 'features'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Features Style') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Control feature card visuals and the section CTA button.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect :model-value="String(editingSection.data.config.feature_vertical_padding ?? 96)" @update:model-value="setConfigString('feature_vertical_padding', String($event ?? '96'))" :label="t('Layout Vertical Padding')" :placeholder="t('Select Padding...')" :options="[{ value: '72', label: '72px' }, { value: '96', label: '96px' }, { value: '120', label: '120px' }, { value: '144', label: '144px' }]" />
                                <AppSelect :model-value="String(editingSection.data.config.card_style ?? 'bordered')" @update:model-value="setConfigString('card_style', String($event ?? 'bordered'))" :label="t('Card Style')" :placeholder="t('Select Card Style...')" :options="featureCardStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.heading_color ?? 'dark')" @update:model-value="setConfigString('heading_color', String($event ?? 'dark'))" :label="t('Heading Color')" :placeholder="t('Select Heading Color...')" :options="heroHeadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.subheading_color ?? 'light')" @update:model-value="setConfigString('subheading_color', String($event ?? 'light'))" :label="t('Sub Heading Color')" :placeholder="t('Select Sub Heading Color...')" :options="heroSubheadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.button_style ?? 'primary_filled')" @update:model-value="setConfigString('button_style', String($event ?? 'primary_filled'))" :label="t('Button Style')" :placeholder="t('Select Button Style...')" :options="heroButtonStyleOptions" />
                            </div>
                        </div>
                        <div v-if="editingSection.data.type === 'tools_showcase'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Tools Showcase Style') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Adjust card style, spacing, background, and the showcase CTA.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect :model-value="String(editingSection.data.config.section_vertical_padding ?? 96)" @update:model-value="setConfigString('section_vertical_padding', String($event ?? '96'))" :label="t('Section Vertical Padding')" :placeholder="t('Select Padding...')" :options="[{ value: '72', label: '72px' }, { value: '96', label: '96px' }, { value: '120', label: '120px' }, { value: '144', label: '144px' }]" />
                                <AppSelect :model-value="String(editingSection.data.config.card_style ?? 'bordered')" @update:model-value="setConfigString('card_style', String($event ?? 'bordered'))" :label="t('Card Style')" :placeholder="t('Select Card Style...')" :options="toolsShowcaseCardStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.heading_color ?? 'dark')" @update:model-value="setConfigString('heading_color', String($event ?? 'dark'))" :label="t('Heading Color')" :placeholder="t('Select Heading Color...')" :options="heroHeadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.subheading_color ?? 'light')" @update:model-value="setConfigString('subheading_color', String($event ?? 'light'))" :label="t('Sub Heading Color')" :placeholder="t('Select Sub Heading Color...')" :options="heroSubheadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.background_style ?? 'gradient-1')" @update:model-value="setConfigString('background_style', String($event ?? 'gradient-1'))" :label="t('Background Style')" :placeholder="t('Select Background Style...')" :options="ctaBannerBackgroundStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.width ?? 'contained')" @update:model-value="setConfigString('width', String($event ?? 'contained'))" :label="t('Width')" :placeholder="t('Select Width...')" :options="ctaBannerWidthOptions" />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.access ?? 'everyone')"
                                    @update:model-value="setConfigString('access', String($event ?? 'everyone'))"
                                    :label="t('Access')"
                                    :placeholder="t('Select Access...')"
                                    :options="[
                                        { value: 'everyone', label: t('Everyone') },
                                        { value: 'logged_in', label: t('Logged In') },
                                        { value: 'pro', label: t('Pro') },
                                    ]"
                                />
                                <AppSelect :model-value="String(editingSection.data.config.primary_style ?? 'primary_filled')" @update:model-value="setConfigString('primary_style', String($event ?? 'primary_filled'))" :label="t('Primary Style')" :placeholder="t('Select Primary Style...')" :options="heroButtonStyleOptions" />
                            </div>
                            <div class="mt-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background Image Upload') }}</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    :disabled="toolsShowcaseBackgroundUploading"
                                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-gray-200"
                                    @change="handleToolsShowcaseBackgroundUpload($event)"
                                >
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ toolsShowcaseBackgroundUploading ? t('Uploading background image...') : t('Upload one image for the tools showcase background.') }}
                                </p>
                                <div v-if="editingSection.data.config.background_image_url" class="mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900">
                                    <img
                                        :src="resolveStoredMediaUrl(String(editingSection.data.config.background_image_url))"
                                        :alt="t('Tools showcase background preview')"
                                        class="h-36 w-full rounded-lg object-cover"
                                    >
                                    <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-danger-500" @click="clearToolsShowcaseBackgroundMedia">
                                        <i class="ti ti-trash text-sm"></i>
                                        {{ t('Remove Background Image') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="editingSection.data.type === 'how_it_works'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Step Flow & Layout') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Control the layout, step card style, and section spacing for this step section.') }}</p>
                                </div>
                            </div>
                            <div class="mt-4 inline-flex items-center gap-2 rounded-2xl border border-primary-100 bg-primary-50 px-4 py-3 text-xs font-semibold text-primary-700 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300">
                                <i class="ti ti-route text-sm"></i>
                                {{ t('Step-based section') }}
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect
                                    :model-value="String(editingSection.data.config.step_layout ?? 'cards')"
                                    @update:model-value="setConfigString('step_layout', String($event ?? 'cards'))"
                                    :label="t('Step Layout')"
                                    :placeholder="t('Select Step Layout...')"
                                    :options="howItWorksLayoutOptions"
                                />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.step_card_style ?? 'bordered')"
                                    @update:model-value="setConfigString('step_card_style', String($event ?? 'bordered'))"
                                    :label="t('Step Card Style')"
                                    :placeholder="t('Select Step Card Style...')"
                                    :options="howItWorksCardStyleOptions"
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Vertical Padding') }}</label>
                                    <input
                                        :value="String(editingSection.data.config.section_vertical_padding ?? 96)"
                                        @input="setConfigString('section_vertical_padding', ($event.target as HTMLInputElement).value)"
                                        type="number"
                                        min="0"
                                        max="240"
                                        :placeholder="t('Enter Vertical Padding...')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </div>
                            </div>
                        </div>
                        <div v-if="editingSection.data.type === 'hero'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hero Style') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Adjust the hero layout, background, overlay, and stats styling.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect :model-value="String(editingSection.data.config.layout ?? 'center')"
                                           @update:model-value="setConfigString('layout', String($event ?? 'center'))"
                                           :label="t('Content Alignment')"
                                           :placeholder="t('Select Content Alignment...')"
                                           :options="heroLayoutOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.hero_heading_size ?? 'lg')"
                                           @update:model-value="setConfigString('hero_heading_size', String($event ?? 'lg'))"
                                           :label="t('Heading Size')"
                                           :placeholder="t('Select Heading Size...')"
                                           :options="heroHeadingSizeOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.hero_section_height ?? 'default')"
                                           @update:model-value="setConfigString('hero_section_height', String($event ?? 'default'))"
                                           :label="t('Hero Height')"
                                           :placeholder="t('Select Hero Height...')"
                                           :options="heroHeightOptions" />
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Hero Vertical Padding') }}</label>
                                    <input :value="String(editingSection.data.config.hero_vertical_padding ?? 48)"
                                           @input="setConfigString('hero_vertical_padding', ($event.target as HTMLInputElement).value)"
                                           type="number" min="0" max="240"
                                           :placeholder="t('Enter Hero Vertical Padding...')"
                                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </div>
                                <AppSelect :model-value="String(editingSection.data.config.hero_heading_color ?? 'dark')"
                                           @update:model-value="setConfigString('hero_heading_color', String($event ?? 'dark'))"
                                           :label="t('Heading Color')"
                                           :placeholder="t('Select Heading Color...')"
                                           :options="heroHeadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.hero_subheading_color ?? 'light')"
                                           @update:model-value="setConfigString('hero_subheading_color', String($event ?? 'light'))"
                                           :label="t('Subheading Color')"
                                           :placeholder="t('Select Subheading Color...')"
                                           :options="heroSubheadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.primary_cta_style ?? 'primary_filled')"
                                           @update:model-value="setConfigString('primary_cta_style', String($event ?? 'primary_filled'))"
                                           :label="t('Primary Button Style')"
                                           :placeholder="t('Select Primary Button Style...')"
                                           :options="heroButtonStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.primary_cta_icon_position ?? 'left')"
                                           @update:model-value="setConfigString('primary_cta_icon_position', String($event ?? 'left'))"
                                           :label="t('Primary Button Icon Position')"
                                           :placeholder="t('Select Primary Button Icon Position...')"
                                           :options="heroIconPositionOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.secondary_cta_style ?? 'outline')"
                                           @update:model-value="setConfigString('secondary_cta_style', String($event ?? 'outline'))"
                                           :label="t('Secondary Button Style')"
                                           :placeholder="t('Select Secondary Button Style...')"
                                           :options="heroButtonStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.secondary_cta_icon_position ?? 'left')"
                                           @update:model-value="setConfigString('secondary_cta_icon_position', String($event ?? 'left'))"
                                           :label="t('Secondary Button Icon Position')"
                                           :placeholder="t('Select Secondary Button Icon Position...')"
                                           :options="heroIconPositionOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.stats_number_color ?? 'dark')"
                                           @update:model-value="setConfigString('stats_number_color', String($event ?? 'dark'))"
                                           :label="t('Stats Number Color')"
                                           :placeholder="t('Select Stats Number Color...')"
                                           :options="heroHeadingColorOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.stats_label_color ?? 'light')"
                                           @update:model-value="setConfigString('stats_label_color', String($event ?? 'light'))"
                                           :label="t('Stats Label Color')"
                                           :placeholder="t('Select Stats Label Color...')"
                                           :options="heroSubheadingColorOptions" />
                            </div>
                            <div class="mt-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background Media Upload') }}</label>
                                <input
                                    type="file"
                                    accept="image/*,video/*"
                                    :disabled="heroBackgroundUploading"
                                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-gray-200"
                                    @change="handleHeroBackgroundUpload($event)"
                                >
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ heroBackgroundUploading ? t('Uploading background media...') : t('Upload one image or video for the hero background.') }}
                                </p>
                                <div v-if="editingSection.data.config.hero_background_url" class="mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900">
                                    <img
                                        v-if="String(editingSection.data.config.hero_background_type ?? 'image') === 'image'"
                                        :src="resolveStoredMediaUrl(String(editingSection.data.config.hero_background_url))"
                                        :alt="t('Hero background preview')"
                                        class="h-36 w-full rounded-lg object-cover"
                                    >
                                    <video
                                        v-else
                                        :src="resolveStoredMediaUrl(String(editingSection.data.config.hero_background_url))"
                                        class="h-36 w-full rounded-lg object-cover"
                                        controls
                                    ></video>
                                    <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-danger-500" @click="clearHeroBackgroundMedia">
                                        <i class="ti ti-trash text-sm"></i>
                                        {{ t('Remove Background Media') }}
                                    </button>
                                </div>
                                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="editingSection.data.config.show_hero_gradient_overlay = !Boolean(editingSection.data.config.show_hero_gradient_overlay)">
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Gradient Overlay') }}</span>
                                            <span class="text-xs text-gray-500">{{ t('Add a dark gradient so hero text stays readable over the background media.') }}</span>
                                        </span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="editingSection.data.config.show_hero_gradient_overlay ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="editingSection.data.config.show_hero_gradient_overlay ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="editingSection.data.config.show_stats_separator = !Boolean(editingSection.data.config.show_stats_separator)">
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Line Before Stats') }}</span>
                                            <span class="text-xs text-gray-500">{{ t('Display a divider line above the hero stats row.') }}</span>
                                        </span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="editingSection.data.config.show_stats_separator ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="editingSection.data.config.show_stats_separator ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="editingSection.data.type === 'cta_banner'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('CTA Banner Style') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Set banner background and CTA button presentation.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <AppSelect :model-value="String(editingSection.data.config.background_style ?? 'gradient-1')" @update:model-value="setConfigString('background_style', String($event ?? 'gradient-1'))" :label="t('Background Style')" :placeholder="t('Select Background Style...')" :options="ctaBannerBackgroundStyleOptions" />
                                <AppSelect :model-value="String(editingSection.data.config.width ?? 'contained')" @update:model-value="setConfigString('width', String($event ?? 'contained'))" :label="t('Width')" :placeholder="t('Select Width...')" :options="ctaBannerWidthOptions" />
                                <AppSelect
                                    :model-value="String(editingSection.data.config.access ?? 'everyone')"
                                    @update:model-value="setConfigString('access', String($event ?? 'everyone'))"
                                    :label="t('Access')"
                                    :placeholder="t('Select Access...')"
                                    :options="[
                                        { value: 'everyone', label: t('Everyone') },
                                        { value: 'logged_in', label: t('Logged In') },
                                        { value: 'pro', label: t('Pro') },
                                    ]"
                                />
                                <AppSelect :model-value="String(editingSection.data.config.primary_style ?? 'primary_filled')" @update:model-value="setConfigString('primary_style', String($event ?? 'primary_filled'))" :label="t('Button Style')" :placeholder="t('Select Button Style...')" :options="heroButtonStyleOptions" />
                            </div>
                            <div class="mt-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background Image Upload') }}</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    :disabled="ctaBannerBackgroundUploading"
                                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-gray-200"
                                    @change="handleCtaBannerBackgroundUpload($event)"
                                >
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ ctaBannerBackgroundUploading ? t('Uploading background image...') : t('Upload one image for the CTA banner background.') }}
                                </p>
                                <div v-if="editingSection.data.config.background_image_url" class="mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900">
                                    <img
                                        :src="resolveStoredMediaUrl(String(editingSection.data.config.background_image_url))"
                                        :alt="t('CTA banner background preview')"
                                        class="h-36 w-full rounded-lg object-cover"
                                    >
                                    <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-danger-500" @click="clearCtaBannerBackgroundMedia">
                                        <i class="ti ti-trash text-sm"></i>
                                        {{ t('Remove Background Image') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeSettingsTab === 'advanced'" class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Advanced') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Optional controls for anchor links, CSS, and fine-tuning.') }}</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Section Anchor') }}</label>
                                    <input :value="String(editingSection.data.config.section_anchor ?? '')" @input="setConfigString('section_anchor', ($event.target as HTMLInputElement).value)" type="text" :placeholder="t('Enter Section Anchor...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Custom Class') }}</label>
                                    <input :value="String(editingSection.data.config.custom_class ?? '')" @input="setConfigString('custom_class', ($event.target as HTMLInputElement).value)" type="text" :placeholder="t('Enter Custom Class...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ t('Overlay Opacity') }}</label>
                                    <input :value="String(editingSection.data.config.overlay_opacity ?? 0)" @input="setConfigString('overlay_opacity', ($event.target as HTMLInputElement).value)" type="number" min="0" max="100" :placeholder="t('Enter Overlay Opacity...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </div>
                                <AppSelect :model-value="String(editingSection.data.config.animation ?? 'none')" @update:model-value="setConfigString('animation', String($event ?? 'none'))" :label="t('Animation')" :placeholder="t('Select Animation...')" :options="[{ value: 'none', label: t('None') }, { value: 'fade-up', label: t('Fade Up') }, { value: 'fade-in', label: t('Fade In') }, { value: 'slide-up', label: t('Slide Up') }]" />
                                <AppSelect :model-value="Boolean(editingSection.data.config.lazy_load_media) ? '1' : '0'" @update:model-value="setConfigString('lazy_load_media', String($event ?? '0'))" :label="t('Lazy Load Media')" :placeholder="t('Select...')" :options="[{ value: '0', label: t('Off') }, { value: '1', label: t('On') }]" />
                                <AppSelect :model-value="String(editingSection.data.config.visibility ?? 'all')" @update:model-value="setConfigString('visibility', String($event ?? 'all'))" :label="t('Visible On')" :placeholder="t('Select Visibility...')" :options="sectionVisibilityOptions" />
                            </div>
                        </div>
                    </div>
                    </div>
                <div class="flex items-center justify-end border-t border-gray-100 bg-gray-50 px-6 py-3 dark:border-surface-700 dark:bg-surface-800">
                    <button @click="saveSectionSettings" type="button" class="rounded-xl btn-primary px-5 py-2.5 text-sm font-bold transition-all shadow-lg shadow-primary-600/20">{{ t('Done') }}</button>
                </div>
            </div>
        </div>

        <!-- When a site template is active, show a message instead of the builder -->
        <div v-if="!isCustomHomepage" class="max-w-7xl mx-auto px-6">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6 mb-8">
                <h2 class="text-base font-bold text-gray-900 dark:text-white mb-4">{{ t('Site Template Active') }}</h2>
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                        <i class="ti ti-layout-dashboard text-2xl"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ t('The homepage is currently using the') }} <strong>{{ props.availableTemplates.find((template) => template.slug === props.activeHomepageTemplate)?.name ?? props.activeHomepageTemplate }}</strong> {{ t('template.') }}
                            {{ t('Switch to') }} <strong>{{ t('Custom Homepage') }}</strong> {{ t('above to use the drag & drop builder.') }}
                        </p>
                        <a
                            v-if="props.activeHomepageTemplate !== 'default'"
                            :href="route('admin.ai.templates.edit', props.activeHomepageTemplate)"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm font-bold transition-colors"
                        >
                            {{ t('Edit Template Settings') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="removeTargetIndex !== null"
            :title="t('Remove homepage section?')"
            :message="t('This section will be removed from the homepage builder.')"
            :confirm-label="t('Remove')"
            @cancel="removeTargetIndex = null"
            @confirm="confirmRemoveSection"
        />

        <ActionConfirmModal
            :open="resetConfirmOpen"
            :title="t('Reset homepage settings?')"
            :message="t('This will restore the homepage sections and settings to their defaults for the current builder.')"
            :confirm-label="t('Reset')"
            @cancel="resetConfirmOpen = false"
            @confirm="resetToDefaults"
        />

        <!-- Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ t('Import Homepage Configuration') }}</h3>
                    <textarea v-model="importJsonText" rows="10" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Paste JSON here...')"></textarea>
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button @click="showImportModal = false" class="px-4 py-2 text-sm font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">{{ t('Cancel') }}</button>
                        <button @click="importConfig" class="rounded-lg btn-primary px-4 py-2 text-sm font-bold">{{ t('Import') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
