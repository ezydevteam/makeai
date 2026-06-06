<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

type HeaderSectionKey = 'top' | 'main' | 'mobile' | 'mobile_bottom'
type BlockType = 'logo' | 'navigation' | 'search' | 'search_icon' | 'cta_button' | 'language_switcher' | 'dark_mode' | 'user_menu' | 'user_menu_icon' | 'home_link' | 'credit_balance' | 'notification_bell' | 'social_icons' | 'custom_html' | 'hamburger'
type HeaderConfigValue = string | number | boolean | null | string[]
type HeaderContainerWidth = 'default' | 'full' | 'boxed'
type HeaderStickyBehavior = 'none' | 'always' | 'upscroll' | 'downscroll'

interface HeaderBlock {
    id: string
    type: BlockType
    enabled: boolean
    config: Record<string, HeaderConfigValue>
}

interface HeaderSection {
    enabled: boolean
    sticky: boolean
    transparent_homepage: boolean
    height: number
    hide_on_scroll: boolean
    container_width: HeaderContainerWidth
    sticky_behavior: HeaderStickyBehavior
    upscroll_offset: number
    downscroll_offset: number
    transition_enabled: boolean
    shadow: boolean
    progressbar: boolean
    blocks: HeaderBlock[]
}

interface HeaderConfig {
    top: HeaderSection
    main: HeaderSection
    mobile: HeaderSection
    mobile_bottom: HeaderSection
}

interface MenuOption {
    id: number
    name: string
    slug: string
}

const props = defineProps<{
    config: HeaderConfig
    menus: MenuOption[]
}>()

const { t } = useTranslate()

const activeSection = ref<HeaderSectionKey>('main')
const selectedBlockIndex = ref<number | null>(null)
const selectedBlockSection = ref<HeaderSectionKey>('main')
const addElementTarget = ref<HeaderSectionKey>('main')
const showAddElementModal = ref(false)
const isDraggingBlock = ref(false)

let blockIdSequence = 0

const sectionKeys: HeaderSectionKey[] = ['top', 'main', 'mobile', 'mobile_bottom']

const containerWidthOptions: Array<{ value: HeaderContainerWidth; label: string }> = [
    { value: 'default', label: 'Default' },
    { value: 'full', label: 'Full Width' },
    { value: 'boxed', label: 'Boxed (1080px)' },
]

const stickyBehaviorOptions: Array<{ value: HeaderStickyBehavior; label: string }> = [
    { value: 'none', label: 'No Sticky' },
    { value: 'always', label: 'Always Sticky' },
    { value: 'upscroll', label: 'Show on Up Scroll' },
    { value: 'downscroll', label: 'Show on Down Scroll' },
]

const mobileBottomStickyBehaviorOptions: Array<{ value: Exclude<HeaderStickyBehavior, 'none'>; label: string }> = [
    { value: 'always', label: 'Always Sticky' },
    { value: 'upscroll', label: 'Show on Up Scroll' },
    { value: 'downscroll', label: 'Show on Down Scroll' },
]

const menuAlignmentOptions = [
    { value: 'left', label: 'Left' },
    { value: 'center', label: 'Center' },
    { value: 'right', label: 'Right' },
]

const menuHoverStyleOptions = [
    { value: 'underline', label: 'Underline Bar' },
    { value: 'pill', label: 'Soft Pill' },
    { value: 'box', label: 'Raised Box' },
    { value: 'glow', label: 'Glow Accent' },
]

const searchStyleOptions = [
    { value: 'box', label: 'Search Box' },
    { value: 'icon', label: 'Search Icon' },
]

const iconBgStyleOptions = [
    { value: 'light', label: 'Light Surface' },
    { value: 'transparent', label: 'Transparent' },
    { value: 'filled', label: 'Filled Primary' },
    { value: 'custom', label: t('Custom Color') },
]

const inferStickyBehavior = (section: Partial<HeaderSection>): HeaderStickyBehavior => {
    if (section.sticky_behavior) return section.sticky_behavior
    if (section.sticky === false) return 'none'

    return section.hide_on_scroll ? 'upscroll' : 'always'
}

const normalizeSectionOptions = (section: HeaderSection): HeaderSection => ({
    ...section,
    container_width: section.container_width ?? 'default',
    sticky_behavior: inferStickyBehavior(section),
    upscroll_offset: Number(section.upscroll_offset ?? 80),
    downscroll_offset: Number(section.downscroll_offset ?? 80),
    transition_enabled: section.transition_enabled ?? true,
    shadow: section.shadow ?? false,
    progressbar: section.progressbar ?? false,
})

const createBlockId = (type: BlockType, usedIds: Set<string>) => {
    const base = type.replace(/[^a-z0-9_]+/gi, '_').toLowerCase()
    let id = ''

    do {
        blockIdSequence += 1
        id = `${base}_${Date.now().toString(36)}_${blockIdSequence.toString(36)}_${Math.random().toString(36).slice(2, 7)}`
    } while (usedIds.has(id))

    usedIds.add(id)

    return id
}

const normalizeConfig = (config: HeaderConfig): HeaderConfig => {
    const cloned = JSON.parse(JSON.stringify(config)) as HeaderConfig
    const usedIds = new Set<string>()

    sectionKeys.forEach((section) => {
        cloned[section] = normalizeSectionOptions(cloned[section])
        cloned[section].blocks = cloned[section].blocks.map((block) => {
            const existingId = String(block.id ?? '').trim()

            if (!existingId || usedIds.has(existingId)) {
                return {
                    ...block,
                    id: createBlockId(block.type, usedIds),
                }
            }

            usedIds.add(existingId)

            return block
        })
    })

    return cloned
}

const form = useForm<HeaderConfig>(normalizeConfig(props.config))

const sectionOptions: Array<{ id: HeaderSectionKey; label: string; description: string }> = [
    { id: 'top', label: 'Top Header', description: 'Small utility bar above the main header.' },
    { id: 'main', label: 'Main Header', description: 'Desktop primary logo, navigation, search, and actions.' },
    { id: 'mobile', label: 'Mobile Header', description: 'Mobile logo row, hamburger behavior, compact actions, and bottom bar.' },
]

const activeConfig = computed(() => form[activeSection.value])
const activeBlocks = computed(() => activeConfig.value.blocks)
const selectedBlock = computed(() => selectedBlockIndex.value === null ? null : form[selectedBlockSection.value].blocks[selectedBlockIndex.value] ?? null)
const activeSectionMeta = computed(() => sectionOptions.find((section) => section.id === activeSection.value) ?? sectionOptions[1])
const addElementSectionMeta = computed(() => sectionOptions.find((section) => section.id === addElementTarget.value) ?? { id: 'mobile_bottom', label: 'Mobile Bottom Header', description: 'Fixed bottom navigation bar for mobile screens.' })

const getBlockLabel = (type: BlockType) => ({
    logo: 'Logo',
    navigation: 'Navigation Menu',
    search: 'Search Bar',
    search_icon: 'Search Icon',
    cta_button: 'CTA Button',
    language_switcher: 'Language Switcher',
    dark_mode: 'Dark Mode Toggle',
    user_menu: 'User Menu / Login',
    user_menu_icon: 'Login / User Icon',
    home_link: 'Home Icon',
    credit_balance: 'Credit Balance',
    notification_bell: 'Notifications',
    social_icons: 'Social Icons',
    custom_html: 'Custom HTML',
    hamburger: 'Hamburger Menu',
}[type])

const availableElements: Array<{ type: BlockType; label: string; description: string; sections: HeaderSectionKey[]; config: Record<string, HeaderConfigValue> }> = [
    { type: 'logo', label: 'Logo', description: 'Brand mark and optional text.', sections: ['top', 'main', 'mobile'], config: { image: null, alt: '', link: '/', show_text: true, text: '' } },
    { type: 'navigation', label: 'Navigation Menu', description: 'Render a menu from the menu builder.', sections: ['top', 'main'], config: { menu_slug: 'main', alignment: 'center', text_color: '', hover_color: '', hover_style: 'underline', submenu_bg_color: '', submenu_text_color: '' } },
    { type: 'hamburger', label: 'Hamburger Menu', description: 'Open the mobile drawer from a selected menu.', sections: ['mobile', 'mobile_bottom'], config: { menu_slug: 'mobile', label: 'Menu', icon_class: 'ti ti-menu-2', show_label: true, drawer_title: '', icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'search', label: 'Search Bar', description: 'Public live search field.', sections: ['main'], config: { compact: false, search_style: 'box', enable_live_search: true, show_suggestions: true, icon_class: 'ti ti-search', icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'search_icon', label: 'Search Icon', description: 'Open mobile live search from an icon button.', sections: ['mobile', 'mobile_bottom'], config: { label: 'Search', icon_class: 'ti ti-search', show_label: true, enable_live_search: true, show_suggestions: true, icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'home_link', label: 'Home Icon', description: 'Link users back to the homepage.', sections: ['mobile', 'mobile_bottom'], config: { link: '/', label: 'Home', icon_class: 'ti ti-home', show_label: true, icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'user_menu_icon', label: 'Login / User Icon', description: 'Open login for guests or dashboard for users.', sections: ['mobile', 'mobile_bottom'], config: { label: 'Account', guest_label: 'Sign In', icon_class: 'ti ti-user', show_label: true, icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'cta_button', label: 'CTA Button', description: 'Prominent link button.', sections: ['top', 'main', 'mobile', 'mobile_bottom'], config: { text: 'Get Started', link: '/register', style: 'filled', color: 'primary', icon_class: '', icon_only: false, icon_color: '', bg_style: 'filled', bg_color: '', text_color: '' } },
    { type: 'language_switcher', label: 'Language Switcher', description: 'Locale selector.', sections: ['top', 'main', 'mobile', 'mobile_bottom'], config: { show_flag: true, show_name: true, label: 'Language', show_label: true } },
    { type: 'dark_mode', label: 'Dark Mode Toggle', description: 'Theme toggle button.', sections: ['main', 'mobile'], config: { label: 'Theme', icon_class: '', show_label: true, icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'user_menu', label: 'User Menu / Login', description: 'Account menu or auth links.', sections: ['main'], config: { show_credits: true, show_avatar: true } },
    { type: 'credit_balance', label: 'Credit Balance', description: 'Compact credit indicator.', sections: ['main'], config: { label: 'Credits', icon_class: 'ti ti-bolt', icon_color: '', bg_style: 'light', bg_color: '' } },
    { type: 'notification_bell', label: 'Notifications', description: 'Authenticated notification bell.', sections: ['main', 'mobile', 'mobile_bottom'], config: { label: 'Notifications', show_label: true } },
    { type: 'social_icons', label: 'Social Icons', description: 'Configured social links.', sections: ['top', 'main'], config: { icons: [], display_mode: 'icons' } },
    { type: 'custom_html', label: 'Custom HTML', description: 'Plain custom text or trusted markup slot.', sections: ['top', 'main'], config: { content: '' } },
]

const addableElements = computed(() => availableElements.filter((element) => element.sections.includes(addElementTarget.value)))

const setActiveSection = (section: HeaderSectionKey) => {
    activeSection.value = section
    selectedBlockIndex.value = null
    selectedBlockSection.value = section
    showAddElementModal.value = false
}

const openAddElementModal = (section: HeaderSectionKey) => {
    addElementTarget.value = section
    showAddElementModal.value = true
}

const submit = () => {
    sectionKeys.forEach((section) => {
        const config = form[section]
        if (section === 'mobile_bottom') {
            config.sticky = true
            if (config.sticky_behavior === 'none') {
                config.sticky_behavior = 'always'
            }
            config.hide_on_scroll = config.sticky_behavior === 'upscroll'
            config.upscroll_offset = Number(config.upscroll_offset || 0)
            config.downscroll_offset = Number(config.downscroll_offset || 0)
            return
        }

        config.sticky = config.sticky_behavior !== 'none'
        config.hide_on_scroll = config.sticky_behavior === 'upscroll'
        config.upscroll_offset = Number(config.upscroll_offset || 0)
        config.downscroll_offset = Number(config.downscroll_offset || 0)
    })

    form.post(route('admin.header.update'), {
        preserveScroll: true,
    })
}

const toggleSection = () => {
    activeConfig.value.enabled = !activeConfig.value.enabled
}

const updateBlockConfig = (key: string, value: HeaderConfigValue) => {
    if (!selectedBlock.value) return
    selectedBlock.value.config[key] = value
}

const hasLabelOptions = computed(() => selectedBlock.value ? ['hamburger', 'search_icon', 'home_link', 'user_menu_icon', 'notification_bell', 'dark_mode', 'language_switcher', 'credit_balance'].includes(selectedBlock.value.type) : false)
const hasIconOptions = computed(() => selectedBlock.value ? ['hamburger', 'search_icon', 'home_link', 'user_menu_icon', 'dark_mode', 'credit_balance'].includes(selectedBlock.value.type) || (selectedBlock.value.type === 'search' && selectedBlock.value.config.search_style === 'icon') : false)
const hasIconAppearanceOptions = computed(() => selectedBlock.value ? ['hamburger', 'search', 'search_icon', 'home_link', 'user_menu_icon', 'dark_mode', 'credit_balance', 'cta_button'].includes(selectedBlock.value.type) : false)

const addElement = (element: (typeof availableElements)[number]) => {
    const usedIds = new Set(sectionKeys.flatMap((section) => form[section].blocks.map((block) => block.id)))

    form[addElementTarget.value].blocks.push({
        id: createBlockId(element.type, usedIds),
        type: element.type,
        enabled: true,
        config: JSON.parse(JSON.stringify(element.config)) as Record<string, HeaderConfigValue>,
    })
    showAddElementModal.value = false
}

const openBlockSettings = (section: HeaderSectionKey, index: number) => {
    selectedBlockSection.value = section
    selectedBlockIndex.value = index
}

const removeBlock = (index: number, section: HeaderSectionKey = activeSection.value) => {
    form[section].blocks.splice(index, 1)
    if (selectedBlockIndex.value === index && selectedBlockSection.value === section) selectedBlockIndex.value = null
}

const getDragOptions = (section: HeaderSectionKey) => ({
    group: `header-${section}-blocks`,
    animation: 180,
    handle: '.header-block-drag-handle',
    ghostClass: 'header-block-ghost',
    chosenClass: 'header-block-chosen',
    dragClass: 'header-block-active',
    fallbackClass: 'header-block-fallback',
    fallbackOnBody: true,
    swapThreshold: 0.65,
    emptyInsertThreshold: 48,
})

const dragOptions = computed(() => getDragOptions(activeSection.value))
</script>

<template>
    <Head :title="t('Header Builder - Admin')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Header Builder') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure separate top, main, and mobile headers with independent blocks and behavior.') }}</p>
            </div>
            <button type="button" :disabled="form.processing" class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-500/20 transition-all hover:bg-primary-500 disabled:opacity-60" @click="submit">
                {{ form.processing ? t('Saving...') : t('Save Configuration') }}
            </button>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3">
            <button
                v-for="section in sectionOptions"
                :key="section.id"
                type="button"
                class="rounded-xl border p-4 text-left transition-all dark:border-surface-700 rtl:text-right"
                :class="activeSection === section.id ? 'border-primary-300 bg-primary-50 shadow-md dark:bg-primary-900/20' : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/50 dark:bg-surface-900'"
                @click="setActiveSection(section.id)"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ t(section.label) }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="form[section.id].enabled ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'">
                        {{ form[section.id].enabled ? t('Enabled') : t('Disabled') }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ t(section.description) }}</p>
            </button>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-surface-800">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t(activeSectionMeta.label) }}</h2>
                            <p class="mt-1 text-xs text-gray-500">{{ activeConfig.enabled ? t(':section is enabled', { section: t(activeSectionMeta.label) }) : t(':section is disabled', { section: t(activeSectionMeta.label) }) }}</p>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="activeConfig.enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'" @click="toggleSection">
                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="activeConfig.enabled ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Height') }}
                            <input v-model.number="activeConfig.height" type="number" :min="activeSection === 'top' ? 32 : 48" :max="activeSection === 'main' ? 120 : 96" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>

                        <label v-if="activeSection !== 'mobile'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Container Width') }}
                            <select v-model="activeConfig.container_width" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option v-for="option in containerWidthOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                            </select>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Sticky Behavior') }}
                            <select v-model="activeConfig.sticky_behavior" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option v-for="option in (activeSection === 'mobile_bottom' ? mobileBottomStickyBehaviorOptions : stickyBehaviorOptions)" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                            </select>
                            <span v-if="activeSection === 'mobile_bottom'" class="mt-1 block text-xs text-gray-500">{{ t('Mobile bottom header stays fixed, and this controls when it appears while scrolling.') }}</span>
                        </label>

                        <div v-if="activeConfig.sticky_behavior === 'upscroll' || activeConfig.sticky_behavior === 'downscroll'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Up Scroll Offset') }}
                                <input v-model.number="activeConfig.upscroll_offset" type="number" min="0" max="800" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Down Scroll Offset') }}
                                <input v-model.number="activeConfig.downscroll_offset" type="number" min="0" max="800" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Enable Transition') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Animate sticky header show and hide behavior.') }}</span>
                                </span>
                                <input v-model="activeConfig.transition_enabled" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Header Shadow') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Add a soft shadow under this header.') }}</span>
                                </span>
                                <input v-model="activeConfig.shadow" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Header Progress Bar') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Show a scroll progress bar at the bottom edge.') }}</span>
                                </span>
                                <input v-model="activeConfig.progressbar" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Transparent Homepage') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Overlay this header on the homepage hero.') }}</span>
                                </span>
                                <input v-model="activeConfig.transparent_homepage" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                        </div>
                    </div>
                </section>

                <section v-if="activeSection === 'mobile'" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-surface-800">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Mobile Bottom Header') }}</h2>
                            <p class="mt-1 text-xs text-gray-500">{{ form.mobile_bottom.enabled ? t('Bottom header is enabled') : t('Bottom header is disabled') }}</p>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="form.mobile_bottom.enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'" @click="form.mobile_bottom.enabled = !form.mobile_bottom.enabled">
                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="form.mobile_bottom.enabled ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Height') }}
                            <input v-model.number="form.mobile_bottom.height" type="number" min="48" max="96" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Sticky Behavior') }}
                            <select v-model="form.mobile_bottom.sticky_behavior" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option v-for="option in mobileBottomStickyBehaviorOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                            </select>
                        </label>

                        <div class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Header Shadow') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Add a soft shadow above the bottom header.') }}</span>
                                </span>
                                <input v-model="form.mobile_bottom.shadow" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Header Progress Bar') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Show a scroll progress bar at the top edge.') }}</span>
                                </span>
                                <input v-model="form.mobile_bottom.progressbar" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-6 lg:col-span-2">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex flex-col gap-2 border-b border-gray-100 pb-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t(':section Elements', { section: t(activeSectionMeta.label) }) }}</h2>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Reorder and configure the blocks for the selected header section.') }}</p>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-500" @click="openAddElementModal(activeSection)">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" /></svg>
                            {{ t('Add Element') }}
                        </button>
                    </div>

                    <VueDraggable
                        v-model="activeConfig.blocks"
                        class="header-block-dropzone space-y-3 rounded-xl border border-dashed border-gray-200 bg-white/70 p-2 dark:border-surface-700 dark:bg-surface-900/50"
                        v-bind="dragOptions"
                        @start="isDraggingBlock = true"
                        @end="isDraggingBlock = false"
                    >
                        <div
                            v-for="(block, index) in activeBlocks"
                            :key="block.id"
                            class="header-block-item flex items-center justify-between rounded-xl border p-4 transition-all hover:shadow-md"
                            :class="block.enabled ? 'border-primary-200 bg-primary-50/50 dark:border-primary-900/50 dark:bg-primary-900/10' : 'border-gray-100 bg-gray-50/70 dark:border-surface-700 dark:bg-surface-800/50'"
                        >
                            <div class="flex items-center gap-4">
                                <button type="button" class="header-block-drag-handle cursor-grab rounded-lg p-2 text-gray-400 hover:bg-white hover:text-primary-600 active:cursor-grabbing dark:hover:bg-surface-900" :aria-label="t('Drag block')">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01" /></svg>
                                </button>
                                <div>
                                    <div class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                                        {{ t(getBlockLabel(block.type)) }}
                                        <span class="h-2 w-2 rounded-full" :class="block.enabled ? 'bg-primary-500' : 'bg-gray-300'"></span>
                                    </div>
                                    <div class="mt-1 font-mono text-[11px] uppercase text-gray-400">{{ block.id }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" @click="openBlockSettings(activeSection, Number(index))">
                                    {{ t('Settings') }}
                                </button>
                                <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors" :class="block.enabled ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-surface-700'" @click="block.enabled = !block.enabled">
                                    {{ block.enabled ? t('On') : t('Off') }}
                                </button>
                                <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-900/20" :aria-label="t('Remove element')" @click="removeBlock(Number(index), activeSection)">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                    </VueDraggable>
                </section>

                <section v-if="activeSection === 'mobile'" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex flex-col gap-2 border-b border-gray-100 pb-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Mobile Bottom Header Elements') }}</h2>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Reorder and configure the fixed bottom mobile bar.') }}</p>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-500" @click="openAddElementModal('mobile_bottom')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" /></svg>
                            {{ t('Add Element') }}
                        </button>
                    </div>

                    <VueDraggable
                        v-model="form.mobile_bottom.blocks"
                        class="header-block-dropzone space-y-3 rounded-xl border border-dashed border-gray-200 bg-white/70 p-2 dark:border-surface-700 dark:bg-surface-900/50"
                        v-bind="getDragOptions('mobile_bottom')"
                        @start="isDraggingBlock = true"
                        @end="isDraggingBlock = false"
                    >
                        <div
                            v-for="(block, index) in form.mobile_bottom.blocks"
                            :key="block.id"
                            class="header-block-item flex items-center justify-between rounded-xl border p-4 transition-all hover:shadow-md"
                            :class="block.enabled ? 'border-primary-200 bg-primary-50/50 dark:border-primary-900/50 dark:bg-primary-900/10' : 'border-gray-100 bg-gray-50/70 dark:border-surface-700 dark:bg-surface-800/50'"
                        >
                            <div class="flex items-center gap-4">
                                <button type="button" class="header-block-drag-handle cursor-grab rounded-lg p-2 text-gray-400 hover:bg-white hover:text-primary-600 active:cursor-grabbing dark:hover:bg-surface-900" :aria-label="t('Drag block')">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01" /></svg>
                                </button>
                                <div>
                                    <div class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                                        {{ t(getBlockLabel(block.type)) }}
                                        <span class="h-2 w-2 rounded-full" :class="block.enabled ? 'bg-primary-500' : 'bg-gray-300'"></span>
                                    </div>
                                    <div class="mt-1 font-mono text-[11px] uppercase text-gray-400">{{ block.id }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" @click="openBlockSettings('mobile_bottom', Number(index))">
                                    {{ t('Settings') }}
                                </button>
                                <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors" :class="block.enabled ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-surface-700'" @click="block.enabled = !block.enabled">
                                    {{ block.enabled ? t('On') : t('Off') }}
                                </button>
                                <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-900/20" :aria-label="t('Remove element')" @click="removeBlock(Number(index), 'mobile_bottom')">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                    </VueDraggable>
                </section>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showAddElementModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showAddElementModal = false">
                <div class="w-full max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Add Element') }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Choose an element for :section.', { section: t(addElementSectionMeta.label) }) }}</p>
                        </div>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showAddElementModal = false">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="grid max-h-[70vh] grid-cols-1 gap-3 overflow-y-auto p-6 sm:grid-cols-2">
                        <button
                            v-for="element in addableElements"
                            :key="element.type"
                            type="button"
                            class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-800 dark:hover:bg-primary-900/20 rtl:text-right"
                            @click="addElement(element)"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" /></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-bold text-gray-900 dark:text-white">{{ t(element.label) }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ t(element.description) }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="selectedBlock" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="selectedBlockIndex = null">
                <div class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Configure') }}: {{ t(getBlockLabel(selectedBlock.type)) }}</h3>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="selectedBlockIndex = null">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-6">
                        <div v-if="hasLabelOptions" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <div class="grid grid-cols-1 gap-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Display Label') }}
                                    <input :value="String(selectedBlock.config.label ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="updateBlockConfig('label', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label v-if="selectedBlockSection === 'mobile_bottom'" class="flex items-center justify-between gap-4">
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Label in Bottom Bar') }}</span>
                                        <span class="text-xs text-gray-500">{{ t('Show text below the icon in the mobile bottom header.') }}</span>
                                    </span>
                                    <input :checked="selectedBlock.config.show_label !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_label', ($event.target as HTMLInputElement).checked)">
                                </label>
                            </div>
                        </div>

                        <IconClassSelect
                            v-if="hasIconOptions"
                            :model-value="String(selectedBlock.config.icon_class ?? '')"
                            :label="t('Icon Class')"
                            @update:model-value="updateBlockConfig('icon_class', $event)"
                        />

                        <div v-if="hasIconAppearanceOptions" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ t('Icon Appearance') }}</h4>
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Icon Color') }}
                                    <input :value="String(selectedBlock.config.icon_color ?? '')" type="text" :placeholder="t('var(--color-primary-600)')" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="updateBlockConfig('icon_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Icon Background Style') }}
                                    <select :value="String(selectedBlock.config.bg_style ?? 'light')" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @change="updateBlockConfig('bg_style', ($event.target as HTMLSelectElement).value)">
                                        <option v-for="option in iconBgStyleOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                                    </select>
                                </label>
                                <label v-if="selectedBlock.config.bg_style === 'custom' || selectedBlock.type === 'cta_button'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Background Color') }}
                                    <input :value="String(selectedBlock.config.bg_color ?? '')" type="text" :placeholder="t('#10b981')" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="updateBlockConfig('bg_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label v-if="selectedBlock.type === 'cta_button'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Text Color') }}
                                    <input :value="String(selectedBlock.config.text_color ?? '')" type="text" :placeholder="t('#ffffff')" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="updateBlockConfig('text_color', ($event.target as HTMLInputElement).value)">
                                </label>
                            </div>
                        </div>

                        <div v-if="selectedBlock.type === 'navigation'" class="grid grid-cols-1 gap-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Menu Source') }}
                                <select :value="String(selectedBlock.config.menu_slug ?? '')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('menu_slug', ($event.target as HTMLSelectElement).value)">
                                    <option value="">{{ t('Choose a menu') }}</option>
                                    <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                                </select>
                            </label>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Alignment') }}
                                    <select :value="String(selectedBlock.config.alignment ?? 'center')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('alignment', ($event.target as HTMLSelectElement).value)">
                                        <option v-for="option in menuAlignmentOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                                    </select>
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Hover Style') }}
                                    <select :value="String(selectedBlock.config.hover_style ?? 'underline')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('hover_style', ($event.target as HTMLSelectElement).value)">
                                        <option v-for="option in menuHoverStyleOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                                    </select>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Menu Text Color') }}
                                    <input :value="String(selectedBlock.config.text_color ?? '')" type="text" :placeholder="t('Fallback to header text color')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('text_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Hover Color') }}
                                    <input :value="String(selectedBlock.config.hover_color ?? '')" type="text" :placeholder="t('var(--color-primary-600)')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('hover_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Submenu Dropdown Background Color') }}
                                    <input :value="String(selectedBlock.config.submenu_bg_color ?? '')" type="text" :placeholder="t('var(--surface-card)')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('submenu_bg_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Submenu Text Color') }}
                                    <input :value="String(selectedBlock.config.submenu_text_color ?? '')" type="text" :placeholder="t('Fallback to menu text color')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('submenu_text_color', ($event.target as HTMLInputElement).value)">
                                </label>
                            </div>
                        </div>

                        <div v-if="selectedBlock.type === 'search'" class="grid grid-cols-1 gap-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Search Style') }}
                                <select :value="String(selectedBlock.config.search_style ?? 'box')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('search_style', ($event.target as HTMLSelectElement).value)">
                                    <option v-for="option in searchStyleOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                                </select>
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Enable Live Search') }}</span>
                                    <input :checked="selectedBlock.config.enable_live_search !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('enable_live_search', ($event.target as HTMLInputElement).checked)">
                                </label>
                                <label class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Search Suggestions') }}</span>
                                    <input :checked="selectedBlock.config.show_suggestions !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_suggestions', ($event.target as HTMLInputElement).checked)">
                                </label>
                            </div>
                        </div>

                        <div v-if="selectedBlock.type === 'search_icon'" class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Enable Live Search') }}</span>
                                <input :checked="selectedBlock.config.enable_live_search !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('enable_live_search', ($event.target as HTMLInputElement).checked)">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Search Suggestions') }}</span>
                                <input :checked="selectedBlock.config.show_suggestions !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_suggestions', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <div v-if="selectedBlock.type === 'hamburger'" class="grid grid-cols-1 gap-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Drawer Menu Source') }}
                                <select :value="String(selectedBlock.config.menu_slug ?? '')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('menu_slug', ($event.target as HTMLSelectElement).value)">
                                    <option value="">{{ t('Choose a menu') }}</option>
                                    <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Drawer Title') }}
                                <input :value="String(selectedBlock.config.drawer_title ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('drawer_title', ($event.target as HTMLInputElement).value)">
                            </label>
                        </div>

                        <div v-if="selectedBlock.type === 'language_switcher'" class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Flag') }}</span>
                                <input :checked="selectedBlock.config.show_flag !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_flag', ($event.target as HTMLInputElement).checked)">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Language Name') }}</span>
                                <input :checked="selectedBlock.config.show_name !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_name', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <div v-if="selectedBlock.type === 'cta_button'" class="grid grid-cols-1 gap-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Button Text') }}
                                <input :value="String(selectedBlock.config.text ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('text', ($event.target as HTMLInputElement).value)">
                            </label>
                            <IconClassSelect
                                :model-value="String(selectedBlock.config.icon_class ?? '')"
                                :label="t('Icon Class')"
                                @update:model-value="updateBlockConfig('icon_class', $event)"
                            />
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Use Icon Only') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Hide the button text and show only the selected icon.') }}</span>
                                </span>
                                <input :checked="Boolean(selectedBlock.config.icon_only)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('icon_only', ($event.target as HTMLInputElement).checked)">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('URL / Link') }}
                                <input :value="String(selectedBlock.config.link ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('link', ($event.target as HTMLInputElement).value)">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Button Style') }}
                                <select :value="String(selectedBlock.config.style ?? 'filled')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('style', ($event.target as HTMLSelectElement).value)">
                                    <option value="filled">{{ t('Filled') }}</option>
                                    <option value="bg_light">{{ t('Background Light') }}</option>
                                    <option value="outline">{{ t('Outline') }}</option>
                                    <option value="ghost">{{ t('Ghost') }}</option>
                                </select>
                            </label>
                        </div>

                        <div v-if="selectedBlock.type === 'user_menu'" class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show User Avatar') }}</span>
                                <input :checked="Boolean(selectedBlock.config.show_avatar)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_avatar', ($event.target as HTMLInputElement).checked)">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Credit Balance') }}</span>
                                <input :checked="Boolean(selectedBlock.config.show_credits)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_credits', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <div v-if="selectedBlock.type === 'logo'" class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Logo Image URL') }}
                                <input :value="String(selectedBlock.config.image ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('image', ($event.target as HTMLInputElement).value)">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Logo Link') }}
                                <input :value="String(selectedBlock.config.link ?? '/')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('link', ($event.target as HTMLInputElement).value)">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Logo Text') }}
                                <input :value="String(selectedBlock.config.text ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('text', ($event.target as HTMLInputElement).value)">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Alt Text') }}
                                <input :value="String(selectedBlock.config.alt ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('alt', ($event.target as HTMLInputElement).value)">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Text next to Logo') }}</span>
                                <input :checked="Boolean(selectedBlock.config.show_text)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_text', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <label v-if="selectedBlock.type === 'social_icons'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Display Mode') }}
                            <select :value="String(selectedBlock.config.display_mode ?? 'icons')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('display_mode', ($event.target as HTMLSelectElement).value)">
                                <option value="icons">{{ t('Icons') }}</option>
                                <option value="counts">{{ t('Counts') }}</option>
                                <option value="cards">{{ t('Cards') }}</option>
                            </select>
                        </label>

                        <label v-if="selectedBlock.type === 'home_link'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Home Link') }}
                            <input :value="String(selectedBlock.config.link ?? '/')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('link', ($event.target as HTMLInputElement).value)">
                        </label>

                        <label v-if="selectedBlock.type === 'custom_html'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Custom HTML') }}
                            <textarea :value="String(selectedBlock.config.content ?? '')" rows="6" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('content', ($event.target as HTMLTextAreaElement).value)"></textarea>
                        </label>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800">
                        <button type="button" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500" @click="selectedBlockIndex = null">
                            {{ t('Done') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.header-block-ghost {
    min-height: 58px;
    border-color: var(--color-primary-300);
    background: #fff !important;
    box-shadow: inset 0 0 0 2px rgb(99 102 241 / 0.16);
    opacity: 1;
}

.header-block-ghost > * {
    visibility: hidden;
}

.header-block-chosen,
.header-block-chosen.header-block-item,
.header-block-item.sortable-chosen,
.header-block-item.sortable-drag {
    background: #fff !important;
    opacity: 1 !important;
}

.header-block-chosen .header-block-drag-handle {
    color: var(--color-primary-600);
}

.header-block-active {
    background: #fff !important;
    box-shadow: var(--shadow-md) !important;
    opacity: 1 !important;
}

.header-block-fallback {
    background: #fff !important;
    border: 1px solid var(--color-primary-200) !important;
    box-shadow: var(--shadow-md) !important;
    opacity: 1 !important;
}

.header-block-dropzone:empty::before {
    content: '';
    display: block;
    min-height: 56px;
    border: 1px dashed var(--color-primary-200);
    border-radius: 0.75rem;
    background: rgb(99 102 241 / 0.06);
}
</style>
