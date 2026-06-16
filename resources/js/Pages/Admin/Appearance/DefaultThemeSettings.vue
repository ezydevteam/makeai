<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { FONT_FAMILY_SELECT_OPTIONS } from '@/config/fontFamilies'

// Import site builders
import HeaderBuilder from './HeaderBuilder.vue'
import HomepageBuilder from './HomepageBuilder.vue'
import FooterBuilder from './FooterBuilder.vue'

defineOptions({ layout: AdminLayout })

type ThemeConfig = {
    name: string
    slug: string
    version?: string
}

const props = defineProps<{
    theme: ThemeConfig
    settings: Record<string, string>

    // Header Builder props
    headerConfig: any
    headerDefaults: any

    // Homepage Builder props
    homepageConfig: any
    sectionTypes: string[]
    activeHomepageTemplate: string
    availableTemplates: any[]
    gridTemplates: any[]

    // Footer Builder props
    footerConfig: any

    // Shared builder options
    menus: any[]
    pages: any[]
    aiCategories: any[]
}>()

const { t } = useTranslate()

const fontSizeOptions = ['12px', '13px', '14px', '15px', '16px', '18px', '20px'].map((size) => ({ value: size, label: size }))
const headingWeightOptions = ['400', '500', '600', '700', '800'].map((weight) => ({ value: weight, label: weight }))
const lineHeightOptions = ['1.25', '1.375', '1.5', '1.625', '1.75', '2'].map((value) => ({ value, label: value }))
const letterSpacingOptions = [
    { value: 'tighter', label: 'Tighter' },
    { value: 'tight', label: 'Tight' },
    { value: 'normal', label: 'Normal' },
    { value: 'wide', label: 'Wide' },
    { value: 'wider', label: 'Wider' },
]
const borderRadiusOptions = ['0px', '8px', '12px', '16px', '20px', '999px'].map((value) => ({ value, label: value }))
const containerWidthOptions = [
    { value: 'full', label: 'Full Width' },
    { value: '1080px', label: '1080px' },
    { value: '1280px', label: '1280px' },
    { value: '1536px', label: '1536px' },
]
const gradientDirectionOptions = [
    { value: 'to bottom', label: 'Top to Bottom' },
    { value: 'to right', label: 'Left to Right' },
    { value: 'to bottom right', label: 'Top Left to Bottom Right' },
    { value: 'to bottom left', label: 'Top Right to Bottom Left' },
]
const bgSizeOptions = [
    { value: 'cover', label: 'Cover' },
    { value: 'contain', label: 'Contain' },
    { value: 'auto', label: 'Auto' },
]
const bgRepeatOptions = [
    { value: 'no-repeat', label: 'No Repeat' },
    { value: 'repeat', label: 'Repeat' },
    { value: 'repeat-x', label: 'Repeat X' },
    { value: 'repeat-y', label: 'Repeat Y' },
]
const colorFields = [
    { key: 'primary_color', label: 'Primary' },
    { key: 'secondary_color', label: 'Secondary' },
    { key: 'accent_color', label: 'Accent' },
    { key: 'bg_color', label: 'Page BG' },
    { key: 'surface_color', label: 'Surface' },
    { key: 'text_primary_color', label: 'Text Primary' },
    { key: 'text_secondary_color', label: 'Text Muted' },
    { key: 'link_color', label: 'Link' },
    { key: 'button_color', label: 'Button' },
    { key: 'button_hover_color', label: 'Button Hover' },
    { key: 'header_background', label: 'Header BG' },
    { key: 'footer_background', label: 'Footer BG' },
] as const

const getBool = (key: string, fallback: boolean) => {
    const val = props.settings[key]
    if (val === undefined || val === null || val === '') return fallback
    return val === 'true' || val === '1' || val === true
}

const getNum = (key: string, fallback: number) => {
    const val = props.settings[key]
    if (val === undefined || val === null || val === '') return fallback
    return Number(val)
}

const getStr = (key: string, fallback: string) => props.settings[key] ?? fallback

const form = useForm({
    settings: {
        // Original colors & fonts settings
        primary_color: getStr('primary_color', '#10b981'),
        secondary_color: getStr('secondary_color', '#3b82f6'),
        accent_color: getStr('accent_color', '#8b5cf6'),
        bg_color: getStr('bg_color', '#f0fdf8'),
        surface_color: getStr('surface_color', '#ffffff'),
        text_primary_color: getStr('text_primary_color', '#111827'),
        text_secondary_color: getStr('text_secondary_color', '#6b7280'),
        link_color: getStr('link_color', '#3b82f6'),
        button_color: getStr('button_color', '#10b981'),
        button_hover_color: getStr('button_hover_color', '#059669'),
        header_background: getStr('header_background', '#ffffff'),
        footer_background: getStr('footer_background', '#ecfdf5'),
        font_body: getStr('font_body', 'Inter'),
        heading_font: getStr('heading_font', 'Plus Jakarta Sans'),
        base_font_size: getStr('base_font_size', '15px'),
        heading_weight: getStr('heading_weight', '700'),
        line_height: getStr('line_height', '1.5'),
        letter_spacing: getStr('letter_spacing', 'normal'),
        border_radius: getStr('border_radius', '12px'),
        container_width: getStr('container_width', '1280px'),
        bg_gradient: getStr('bg_gradient', ''),
        bg_gradient_direction: getStr('bg_gradient_direction', 'to bottom'),
        bg_image: getStr('bg_image', ''),
        bg_size: getStr('bg_size', 'cover'),
        bg_repeat: getStr('bg_repeat', 'no-repeat'),
        bg_attachment: getStr('bg_attachment', 'scroll'),
        bg_position: getStr('bg_position', 'center'),

        // Tool Page settings
        tool_page_show_credits: getBool('tool_page_show_credits', true),
        tool_page_show_favorite: getBool('tool_page_show_favorite', true),
        tool_page_show_share: getBool('tool_page_show_share', true),
        tool_page_related_count: getNum('tool_page_related_count', 4),
        tool_page_full_width: getBool('tool_page_full_width', false),
        tool_page_show_prompt: getBool('tool_page_show_prompt', false),

        // General Page settings
        general_page_toc: getBool('general_page_toc', true),
        general_page_show_date: getBool('general_page_show_date', true),
        general_page_show_author: getBool('general_page_show_author', false),
        general_page_share_position: getStr('general_page_share_position', 'bottom'),
        general_page_reading_time: getBool('general_page_reading_time', true),

        // Tool Index settings
        tool_index_layout: getStr('tool_index_layout', 'grid'),
        tool_index_per_page: getNum('tool_index_per_page', 12),
        tool_index_show_search: getBool('tool_index_show_search', true),
        tool_index_show_categories: getBool('tool_index_show_categories', true),
        tool_index_default_sort: getStr('tool_index_default_sort', 'popular'),

        // User Dashboard settings
        user_dashboard_default_tab: getStr('user_dashboard_default_tab', 'generator'),
        user_dashboard_show_credits: getBool('user_dashboard_show_credits', true),
        user_dashboard_show_chart: getBool('user_dashboard_show_chart', true),
        user_dashboard_grid_cols: getStr('user_dashboard_grid_cols', '3'),
        user_dashboard_quick_sidebar: getBool('user_dashboard_quick_sidebar', false),

        // Custom Code
        custom_css: getStr('custom_css', ''),
        custom_header_code: getStr('custom_header_code', ''),
        custom_footer_code: getStr('custom_footer_code', ''),
    },
})

const s = form.settings
const submit = () => form.post(route('admin.themes.settings.save', { slug: props.theme.slug }))

const previewStyle = computed(() => ({
    '--page-bg': s.bg_gradient
        ? `linear-gradient(${s.bg_gradient_direction}, ${s.bg_color}, ${s.bg_gradient})`
        : s.bg_color,
    '--surface': s.surface_color,
    '--primary': s.primary_color,
    '--secondary': s.secondary_color,
    '--accent': s.accent_color,
    '--text': s.text_primary_color,
    '--muted': s.text_secondary_color,
    '--link': s.link_color,
    '--button': s.button_color,
    '--button-hover': s.button_hover_color,
    '--header': s.header_background,
    '--footer': s.footer_background,
    '--radius': s.border_radius,
    '--body-font': s.font_body,
    '--heading-font': s.heading_font,
    '--heading-weight': s.heading_weight,
    '--font-size': s.base_font_size,
    '--line-height': s.line_height,
    '--letter-spacing': s.letter_spacing,
}))

// Tabs setup
const tabs = [
    { id: 'general', label: 'General', icon: 'ti ti-settings' },
    { id: 'colors_typo', label: 'Colors & Typography', icon: 'ti ti-palette' },
    { id: 'header', label: 'Header', icon: 'ti ti-layout-navbar' },
    { id: 'homepage', label: 'Homepage', icon: 'ti ti-home' },
    { id: 'footer', label: 'Footer', icon: 'ti ti-layout-bottombar' },
    { id: 'toolpage', label: 'Tool Page', icon: 'ti ti-device-laptop' },
    { id: 'general_page', label: 'General Page', icon: 'ti ti-file-text' },
    { id: 'tool_index', label: 'Tool Index', icon: 'ti ti-category' },
    { id: 'user_dashboard', label: 'User Dashboard', icon: 'ti ti-layout-dashboard' },
    { id: 'custom_code', label: 'Custom Code', icon: 'ti ti-code' },
] as const

const activeTab = ref<typeof tabs[number]['id']>('general')

onMounted(() => {
    if (typeof window !== 'undefined') {
        const tabParam = new URLSearchParams(window.location.search).get('tab')
        if (tabParam && tabs.some(t => t.id === tabParam)) {
            activeTab.value = tabParam as any
        }
    }
})
</script>

<template>
    <Head :title="t('Theme Settings')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <!-- Top bar header -->
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between border-b border-gray-100 pb-5 dark:border-surface-800">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Appearance Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ props.theme.name }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage branding styles, layout headers, homepage layout builders, page settings, and custom codes.') }}</p>
            </div>
            <div class="flex items-center gap-3 self-start">
                <Link
                    :href="route('admin.themes')"
                    class="inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-900"
                >
                    <i class="ti ti-arrow-left mr-1"></i>
                    {{ t('Back') }}
                </Link>
                <!-- Only show Save changes for non-builder views -->
                <button
                    v-if="activeTab !== 'header' && activeTab !== 'homepage' && activeTab !== 'footer'"
                    type="button"
                    :disabled="form.processing"
                    class="rounded-lg btn-primary disabled:opacity-60 inline-flex items-center gap-2"
                    @click="submit"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </div>

        <!-- Layout with left sidebar and right content area -->
        <div class="flex flex-col gap-6 lg:flex-row">
            <!-- Sidebar (left) -->
            <aside class="w-full lg:w-64 shrink-0">
                <div class="sticky top-6 flex flex-row overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 dark:border-surface-800 dark:bg-surface-900 lg:flex-col lg:overflow-x-visible shadow-sm">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="flex items-center gap-2.5 rounded-lg px-4 py-3 text-sm font-medium transition-all whitespace-nowrap lg:w-full"
                        :class="activeTab === tab.id
                            ? 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300 font-semibold shadow-sm'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-white'"
                        @click="activeTab = tab.id"
                    >
                        <i :class="[tab.icon, 'text-lg']"></i>
                        <span>{{ t(tab.label) }}</span>
                    </button>
                </div>
            </aside>

            <!-- Main Content Area (right) -->
            <div class="flex-1 min-w-0">
                <!-- 1. GENERAL TAB -->
                <div v-if="activeTab === 'general'" class="grid gap-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="space-y-6">
                                <div class="rounded-2xl border border-amber-100 bg-amber-50/40 p-5 dark:border-amber-950/20 dark:bg-amber-950/10">
                                    <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300 mb-4">{{ t('Layout & Background') }}</h2>
                                    <div class="grid gap-4">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <AppSelect v-model="s.border_radius" :label="t('Border radius')" :options="borderRadiusOptions" />
                                            <AppSelect v-model="s.container_width" :label="t('Container width')" :options="containerWidthOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                        </div>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <AppColorPicker v-model="s.bg_gradient" :label="t('Gradient color')" />
                                            <AppSelect v-model="s.bg_gradient_direction" :label="t('Gradient direction')" :options="gradientDirectionOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                        </div>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="block">
                                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background image URL') }}</span>
                                                <input v-model="s.bg_image" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('https://...')" />
                                            </label>
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <AppSelect v-model="s.bg_size" :label="t('Size')" :options="bgSizeOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                                <AppSelect v-model="s.bg_repeat" :label="t('Repeat')" :options="bgRepeatOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="rounded-2xl border border-violet-100 bg-violet-50/40 p-5 dark:border-violet-950/20 dark:bg-violet-950/10">
                                    <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300 mb-4">{{ t('Theme Summary') }}</h2>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                            <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Body Font') }}</div>
                                            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ s.font_body }}</div>
                                        </div>
                                        <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                            <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Container Width') }}</div>
                                            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ s.container_width }}</div>
                                        </div>
                                        <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                            <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Primary') }}</div>
                                            <div class="mt-2 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                                <span class="inline-block h-4 w-4 rounded-full border border-black/10" :style="{ backgroundColor: s.primary_color }"></span>
                                                {{ s.primary_color }}
                                            </div>
                                        </div>
                                        <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                            <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Background') }}</div>
                                            <div class="mt-2 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                                <span class="inline-block h-4 w-4 rounded-full border border-black/10" :style="{ backgroundColor: s.bg_color }"></span>
                                                {{ s.bg_color }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 1.5. COLORS & TYPOGRAPHY TAB -->
                <div v-else-if="activeTab === 'colors_typo'" class="grid gap-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="space-y-6">
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-5 dark:border-emerald-950/20 dark:bg-emerald-950/10">
                                    <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300 mb-4">{{ t('Core Colors') }}</h2>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div v-for="field in colorFields" :key="field.key" class="block">
                                            <AppColorPicker v-model="s[field.key]" :label="t(field.label)" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="rounded-2xl border border-sky-100 bg-sky-50/40 p-5 dark:border-sky-950/20 dark:bg-sky-950/10">
                                    <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300 mb-4">{{ t('Typography') }}</h2>
                                    <div class="grid gap-4">
                                        <AppSelect v-model="s.font_body" :label="t('Body font')" :options="FONT_FAMILY_SELECT_OPTIONS" live-search />
                                        <AppSelect v-model="s.heading_font" :label="t('Heading font')" :options="FONT_FAMILY_SELECT_OPTIONS" live-search />
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <AppSelect v-model="s.base_font_size" :label="t('Base size')" :options="fontSizeOptions" />
                                            <AppSelect v-model="s.heading_weight" :label="t('Heading weight')" :options="headingWeightOptions" />
                                            <AppSelect v-model="s.line_height" :label="t('Line height')" :options="lineHeightOptions" />
                                            <AppSelect v-model="s.letter_spacing" :label="t('Letter spacing')" :options="letterSpacingOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 2. HEADER BUILDER TAB -->
                <div v-else-if="activeTab === 'header'">
                    <HeaderBuilder
                        :config="props.headerConfig"
                        :menus="props.menus"
                        :defaults="props.headerDefaults"
                        :themeSlug="props.theme.slug"
                        :embed="true"
                    />
                </div>

                <!-- 3. HOMEPAGE BUILDER TAB -->
                <div v-else-if="activeTab === 'homepage'">
                    <HomepageBuilder
                        :config="props.homepageConfig"
                        :sectionTypes="props.sectionTypes"
                        :activeHomepageTemplate="props.activeHomepageTemplate"
                        :availableTemplates="props.availableTemplates"
                        :gridTemplates="props.gridTemplates"
                        :themeSlug="props.theme.slug"
                        :embed="true"
                    />
                </div>

                <!-- 3.5. FOOTER BUILDER TAB -->
                <div v-else-if="activeTab === 'footer'">
                    <FooterBuilder
                        :config="props.footerConfig"
                        :menus="props.menus"
                        :pages="props.pages"
                        :aiCategories="props.aiCategories"
                        :themeSlug="props.theme.slug"
                        :embed="true"
                    />
                </div>

                <!-- 4. TOOL PAGE TAB -->
                <div v-else-if="activeTab === 'toolpage'">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ t('Tool Page Settings') }}</h3>
                        <p class="text-xs text-gray-500 mb-6 border-b border-gray-100 pb-3 dark:border-surface-800">{{ t('Customize public layout options for AI generator tool template pages.') }}</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <!-- Left col properties -->
                            <div class="space-y-4">
                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_page_show_credits" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Credit Costs') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Display dynamic generation cost in the tool header bar.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_page_show_favorite" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Favorite Button') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Let logged-in users add the tool to their quick library dashboard.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_page_show_share" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Social Share Buttons') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Render direct facebook, twitter, and email sharing options.') }}</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Right col properties -->
                            <div class="space-y-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Related Tools Count') }}
                                    <input type="number" min="0" max="12" v-model.number="s.tool_page_related_count" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-primary-400 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                    <span class="block text-[11px] text-gray-400 mt-1.5">{{ t('Specify the maximum number of similar tools shown at the bottom of the page.') }}</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_page_full_width" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Full Width Layout') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Bypass standard container widths for wide playground generators.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_page_show_prompt" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Prompt Instruction Section') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Embed template AI configuration and system prompts box.') }}</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 5. GENERAL PAGE TAB -->
                <div v-if="activeTab === 'general_page'">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ t('General Page Settings') }}</h3>
                        <p class="text-xs text-gray-500 mb-6 border-b border-gray-100 pb-3 dark:border-surface-800">{{ t('Configure standard content documents, announcements, and terms pages.') }}</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="space-y-4">
                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.general_page_toc" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Table Of Contents') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Auto-generate table of contents from layout headings.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.general_page_show_date" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Publication Date') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Display document creation date badge under heading.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.general_page_show_author" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Author Profile') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Render content author avatar, name, and role biography.') }}</span>
                                    </div>
                                </label>
                            </div>

                            <div class="space-y-4">
                                <AppSelect
                                    v-model="s.general_page_share_position"
                                    :label="t('Share Buttons Position')"
                                    :options="[
                                        { value: 'none', label: t('None') },
                                        { value: 'top', label: t('Top of page') },
                                        { value: 'bottom', label: t('Bottom of page') },
                                        { value: 'both', label: t('Both Top and Bottom') }
                                    ]"
                                />

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.general_page_reading_time" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Reading Time') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Display estimated minutes indicator to improve conversion rates.') }}</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 6. TOOL INDEX TAB -->
                <div v-if="activeTab === 'tool_index'">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ t('Tool Index / Archive settings') }}</h3>
                        <p class="text-xs text-gray-500 mb-6 border-b border-gray-100 pb-3 dark:border-surface-800">{{ t('Configure standard browse directory card sizes and layout options.') }}</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="space-y-4">
                                <AppSelect
                                    v-model="s.tool_index_layout"
                                    :label="t('Default layout style')"
                                    :options="[
                                        { value: 'grid', label: t('Grid Grid (3 columns)') },
                                        { value: 'list', label: t('List row layout') },
                                        { value: 'compact', label: t('Compact grid') }
                                    ]"
                                />

                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Tools per page') }}
                                    <input type="number" min="1" max="100" v-model.number="s.tool_index_per_page" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-primary-400 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </label>

                                <AppSelect
                                    v-model="s.tool_index_default_sort"
                                    :label="t('Default sorting method')"
                                    :options="[
                                        { value: 'popular', label: t('Most Popular') },
                                        { value: 'newest', label: t('Newest additions') },
                                        { value: 'alphabetical', label: t('Alphabetical A-Z') }
                                    ]"
                                />
                            </div>

                            <div class="space-y-4">
                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_index_show_search" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Render Search Bar') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Show top-level input box inside category title blocks.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.tool_index_show_categories" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show Category Switchers') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Render horizontal category quick selection filter buttons.') }}</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 7. USER DASHBOARD TAB -->
                <div v-if="activeTab === 'user_dashboard'">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ t('User Dashboard settings') }}</h3>
                        <p class="text-xs text-gray-500 mb-6 border-b border-gray-100 pb-3 dark:border-surface-800">{{ t('Configure standard layouts inside logged in user panels.') }}</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="space-y-4">
                                <AppSelect
                                    v-model="s.user_dashboard_default_tab"
                                    :label="t('Default landing tab')"
                                    :options="[
                                        { value: 'generator', label: t('Tool playground') },
                                        { value: 'library', label: t('Saved items library') },
                                        { value: 'profile', label: t('Profile settings') },
                                        { value: 'billing', label: t('Billing subscriptions') }
                                    ]"
                                />

                                <AppSelect
                                    v-model="s.user_dashboard_grid_cols"
                                    :label="t('Generator grid columns')"
                                    :options="[
                                        { value: '2', label: t('2 Columns (wide cards)') },
                                        { value: '3', label: t('3 Columns (Standard)') },
                                        { value: '4', label: t('4 Columns (compact)') }
                                    ]"
                                />

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.user_dashboard_quick_sidebar" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Enable Quick Generate sidebar') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Add expandable drawer sidebar for lightning prompt inputs.') }}</span>
                                    </div>
                                </label>
                            </div>

                            <div class="space-y-4">
                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.user_dashboard_show_credits" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show credit balances') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Render total remaining quota values in side nav panels.') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/40 cursor-pointer">
                                    <input type="checkbox" v-model="s.user_dashboard_show_chart" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500 h-4.5 w-4.5" />
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Show usage history chart') }}</span>
                                        <span class="block text-xs text-gray-400 mt-0.5">{{ t('Embed dynamic line graph tracing generator quota consumption.') }}</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 8. CUSTOM CODE TAB -->
                <div v-if="activeTab === 'custom_code'">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ t('Custom Codes Injection') }}</h3>
                        <p class="text-xs text-gray-500 mb-6 border-b border-gray-100 pb-3 dark:border-surface-800">{{ t('Inject scoped CSS rules or third-party analytic script tags (like Google Analytics, Facebook Pixel) directly.') }}</p>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ t('Custom Scoped CSS') }}
                                    <textarea v-model="s.custom_css" rows="6" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs focus:border-primary-400 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder="body { ... }"></textarea>
                                </label>
                                <span class="block text-[11px] text-gray-400 mt-1.5">{{ t('This CSS will be automatically appended to the end of your variables stylesheet, overriding standard theme components.') }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ t('Custom Header Code (HTML/JS)') }}
                                    <textarea v-model="s.custom_header_code" rows="5" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs focus:border-primary-400 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder="<!-- Custom head tag code -->"></textarea>
                                </label>
                                <span class="block text-[11px] text-gray-400 mt-1.5">{{ t('HTML tags injected directly inside the page <head> tag.') }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ t('Custom Footer Code (HTML/JS)') }}
                                    <textarea v-model="s.custom_footer_code" rows="5" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs focus:border-primary-400 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder="<!-- Custom footer tag code -->"></textarea>
                                </label>
                                <span class="block text-[11px] text-gray-400 mt-1.5">{{ t('HTML tags injected directly before the closing </body> tag.') }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
