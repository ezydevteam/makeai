<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

type HeaderSectionKey = 'top' | 'main' | 'mobile' | 'mobile_bottom'
type BlockType = 'logo' | 'navigation' | 'search' | 'search_icon' | 'cta_button' | 'language_switcher' | 'dark_mode' | 'user_menu' | 'user_menu_icon' | 'home_link' | 'credit_balance' | 'notification_bell' | 'social_icons' | 'custom_html' | 'hamburger'
type HeaderConfigObject = {
    [key: string]: HeaderConfigValue
}
type HeaderConfigValue = string | number | boolean | null | string[] | HeaderConfigObject
type HeaderContainerWidth = 'default' | 'full' | 'boxed'
type HeaderStickyBehavior = 'none' | 'always' | 'upscroll' | 'downscroll'

interface HeaderBlock {
    id: string
    type: BlockType
    enabled: boolean
    config: Record<string, HeaderConfigValue>
}

interface SectionBackground {
    color: string
    image_url: string
    image_path: string
    overlay_opacity: number
}

type ColumnFlex = 'default' | 'left' | 'center' | 'right'

interface HeaderSection {
    enabled: boolean
    sticky: boolean
    transparent_homepage: boolean
    height: number
    hide_on_scroll: boolean
    container_width: HeaderContainerWidth
    sticky_behavior: HeaderStickyBehavior
    sticky_height: number
    upscroll_offset: number
    downscroll_offset: number
    transition_enabled: boolean
    shadow: boolean
    progressbar: boolean
    text_color: string
    center_alignment: 'left' | 'center' | 'right'
    column_flex: ColumnFlex
    background: SectionBackground
    custom_css: string
    blocks: HeaderBlock[]
}

interface HeaderConfig {
    layout?: string
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

interface RemoveBlockTarget {
    index: number
    label: string
    section: HeaderSectionKey
}

interface DefaultSectionConfig {
    top: HeaderSection
    main: HeaderSection
    mobile: HeaderSection
    mobile_bottom: HeaderSection
}

const props = defineProps<{
    config: HeaderConfig
    menus: MenuOption[]
    defaults: DefaultSectionConfig
}>()

const { t } = useTranslate()

const activeSection = ref<HeaderSectionKey>('main')
const selectedBlockIndex = ref<number | null>(null)
const selectedBlockSection = ref<HeaderSectionKey>('main')
const addElementTarget = ref<HeaderSectionKey>('main')
const showAddElementModal = ref(false)
const showAnnouncementPanel = ref(false)
const showImportModal = ref(false)
const importJsonText = ref('')
const isDraggingBlock = ref(false)
const manualSortMode = ref(false)
const logoUploading = ref(false)
const removeBlockTarget = ref<RemoveBlockTarget | null>(null)

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

const blockAlignOptions = [
    { value: 'left', label: 'Left Column' },
    { value: 'right', label: 'Right Column' },
]
const mainBlockAlignOptions = [
    { value: 'left', label: 'Left Column' },
    { value: 'center', label: 'Center Column' },
    { value: 'right', label: 'Right Column' },
]
const centerAlignmentOptions = [
    { value: 'left', label: 'Align Left' },
    { value: 'center', label: 'Align Center' },
    { value: 'right', label: 'Align Right' },
]

const columnFlexOptions: Array<{ value: ColumnFlex; label: string }> = [
    { value: 'default', label: 'Default' },
    { value: 'left', label: 'Left Column' },
    { value: 'center', label: 'Center Column' },
    { value: 'right', label: 'Right Column' },
]
const containerWidthSelectOptions = containerWidthOptions.map((option) => ({ value: option.value, label: t(option.label) }))
const centerAlignmentSelectOptions = centerAlignmentOptions.map((option) => ({ value: option.value, label: t(option.label) }))
const columnFlexSelectOptions = columnFlexOptions.map((option) => ({ value: option.value, label: t(option.label) }))
const stickyBehaviorSelectOptions = stickyBehaviorOptions.map((option) => ({ value: option.value, label: t(option.label) }))
const mobileBottomStickyBehaviorSelectOptions = mobileBottomStickyBehaviorOptions.map((option) => ({ value: option.value, label: t(option.label) }))

const showSectionSettingsModal = ref(false)

const inferStickyBehavior = (section: Partial<HeaderSection>): HeaderStickyBehavior => {
    if (section.sticky_behavior) return section.sticky_behavior
    if (section.sticky === false) return 'none'
    return section.hide_on_scroll ? 'upscroll' : 'always'
}

const normalizeSectionOptions = (section: any): HeaderSection => ({
    ...section,
    container_width: section.container_width ?? 'default',
    sticky_behavior: inferStickyBehavior(section),
    sticky_height: Number(section.sticky_height ?? section.height ?? 64),
    upscroll_offset: Number(section.upscroll_offset ?? 80),
    downscroll_offset: Number(section.downscroll_offset ?? 80),
    transition_enabled: section.transition_enabled ?? true,
    shadow: section.shadow ?? false,
    progressbar: section.progressbar ?? false,
    text_color: String(section.text_color ?? ''),
    center_alignment: section.center_alignment ?? 'center',
    column_flex: section.column_flex ?? 'default',
    background: {
        color: section.background?.color ?? '',
        image_url: section.background?.image_url ?? '',
        image_path: section.background?.image_path ?? '',
        overlay_opacity: section.background?.overlay_opacity ?? 0,
    },
    custom_css: section.custom_css ?? '',
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

const normalizeConfig = (config: any): HeaderConfig => {
    const cloned = JSON.parse(JSON.stringify(config)) as any
    const usedIds = new Set<string>()

    sectionKeys.forEach((section) => {
        cloned[section] = normalizeSectionOptions(cloned[section] ?? {})
        cloned[section].blocks = (cloned[section].blocks ?? []).map((block: any) => {
            const existingId = String(block.id ?? '').trim()
            if (!existingId || usedIds.has(existingId)) {
                return { ...block, id: createBlockId(block.type, usedIds) }
            }
            usedIds.add(existingId)
            return block
        })
    })

    return cloned as HeaderConfig
}

const form = useForm<HeaderConfig>(normalizeConfig(props.config))

const sectionOptions: Array<{ id: HeaderSectionKey; label: string; description: string }> = [
    { id: 'top', label: 'Top Header', description: 'Small utility bar above the main header.' },
    { id: 'main', label: 'Main Header', description: 'Desktop primary logo, navigation, search, and actions.' },
    { id: 'mobile', label: 'Mobile Header', description: 'Mobile logo row, hamburger behavior, compact actions.' },
]
const activeConfig = computed(() => form[activeSection.value] as HeaderSection)
const activeBlocks = computed(() => activeConfig.value.blocks)
const selectedBlock = computed(() => selectedBlockIndex.value === null ? null : (form[selectedBlockSection.value] as HeaderSection).blocks[selectedBlockIndex.value] ?? null)
const activeSectionMeta = computed(() => sectionOptions.find((s) => s.id === activeSection.value) ?? sectionOptions[1])
const addElementSectionMeta = computed(() => sectionOptions.find((s) => s.id === addElementTarget.value) ?? { id: 'mobile_bottom', label: 'Mobile Bottom Header', description: 'Fixed bottom navigation bar for mobile screens.' })

const getBlockLabel = (type: BlockType) => ({
    logo: 'Logo', navigation: 'Navigation Menu', search: 'Search Bar', search_icon: 'Search Icon',
    cta_button: 'CTA Button', language_switcher: 'Language Switcher', dark_mode: 'Dark Mode Toggle',
    user_menu: 'User Menu / Login', user_menu_icon: 'Login / User Icon', home_link: 'Home Icon',
    credit_balance: 'Credit Balance', notification_bell: 'Notifications', social_icons: 'Social Icons',
    custom_html: 'Custom HTML', hamburger: 'Hamburger Menu',
}[type])

const availableElements: Array<{ type: BlockType; label: string; description: string; sections: HeaderSectionKey[]; config: Record<string, HeaderConfigValue> }> = [
    { type: 'logo', label: 'Logo', description: 'Brand mark and text.', sections: ['top', 'main', 'mobile'], config: { image: null, mobile_image: null, alt: '', link: '/', show_text: true, text: '', block_align: 'left' } },
    { type: 'navigation', label: 'Navigation Menu', description: 'Render a menu.', sections: ['top', 'main'], config: { menu_slug: 'main', alignment: 'center', text_color: '', hover_color: '', hover_style: 'underline', submenu_bg_color: '', submenu_text_color: '', block_align: 'center' } },
    { type: 'hamburger', label: 'Hamburger Menu', description: 'Open mobile drawer.', sections: ['mobile', 'mobile_bottom'], config: { menu_slug: 'mobile', label: 'Menu', icon_class: 'ti ti-menu-2', show_label: true, drawer_title: '', icon_color: '', bg_style: 'light', bg_color: '', block_align: 'left' } },
    { type: 'search', label: 'Search Bar', description: 'Public live search.', sections: ['main'], config: { compact: false, search_style: 'box', enable_live_search: true, show_suggestions: true, icon_class: 'ti ti-search', icon_color: '', bg_style: 'light', bg_color: '', block_align: 'center' } },
    { type: 'search_icon', label: 'Search Icon', description: 'Icon-triggered search.', sections: ['mobile', 'mobile_bottom'], config: { label: 'Search', icon_class: 'ti ti-search', show_label: true, enable_live_search: true, show_suggestions: true, icon_color: '', bg_style: 'light', bg_color: '', block_align: 'right' } },
    { type: 'home_link', label: 'Home Icon', description: 'Homepage link.', sections: ['mobile', 'mobile_bottom'], config: { link: '/', label: 'Home', icon_class: 'ti ti-home', show_label: true, icon_color: '', bg_style: 'light', bg_color: '', block_align: 'right' } },
    { type: 'user_menu_icon', label: 'Login / User Icon', description: 'Auth link.', sections: ['mobile', 'mobile_bottom'], config: { label: 'Account', guest_label: 'Sign In', icon_class: 'ti ti-user', show_label: true, icon_color: '', bg_style: 'light', bg_color: '', block_align: 'right' } },
    { type: 'cta_button', label: 'CTA Button', description: 'Prominent link button.', sections: ['top', 'main', 'mobile', 'mobile_bottom'], config: { text: 'Get Started', link: '/register', style: 'filled', color: 'primary', icon_class: '', icon_only: false, icon_color: '', bg_style: 'filled', bg_color: '', text_color: '', block_align: 'right' } },
    { type: 'language_switcher', label: 'Language Switcher', description: 'Locale selector.', sections: ['top', 'main', 'mobile', 'mobile_bottom'], config: { show_flag: true, show_name: true, label: 'Language', show_label: true, block_align: 'left' } },
    { type: 'dark_mode', label: 'Dark Mode Toggle', description: 'Theme toggle.', sections: ['main', 'mobile'], config: { label: 'Theme', icon_class: '', show_label: true, icon_color: '', bg_style: 'light', bg_color: '', block_align: 'right' } },
    { type: 'user_menu', label: 'User Menu / Login', description: 'Account menu.', sections: ['main'], config: { show_credits: true, show_avatar: true, block_align: 'right' } },
    { type: 'credit_balance', label: 'Credit Balance', description: 'Credit indicator.', sections: ['main'], config: { label: 'Credits', icon_class: 'ti ti-bolt', icon_color: '', bg_style: 'light', bg_color: '', block_align: 'right' } },
    { type: 'notification_bell', label: 'Notifications', description: 'Notification bell.', sections: ['main', 'mobile', 'mobile_bottom'], config: { label: 'Notifications', show_label: true, block_align: 'right' } },
    { type: 'social_icons', label: 'Social Icons', description: 'Social links.', sections: ['top', 'main'], config: { icons: [], display_mode: 'icons', block_align: 'right' } },
    { type: 'custom_html', label: 'Custom HTML', description: 'Custom markup slot.', sections: ['top', 'main'], config: { content: '', block_align: 'left' } },
]

const addableElements = computed(() => availableElements.filter((el) => el.sections.includes(addElementTarget.value)))

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

const importConfig = () => {
    try {
        const imported = JSON.parse(importJsonText.value)
        const merged = JSON.parse(JSON.stringify(form.data())) as any
        sectionKeys.forEach((section) => {
            if (imported[section] && imported[section].blocks) {
                merged[section] = imported[section]
            }
        })
        Object.assign(form, normalizeConfig(merged))
        showImportModal.value = false
        importJsonText.value = ''
    } catch {
        alert(t('Invalid JSON format.'))
    }
}

const submit = () => {
    sectionKeys.forEach((section) => {
        const config = form[section] as HeaderSection
        if (section === 'mobile_bottom') {
            config.sticky = true
            if (config.sticky_behavior === 'none') config.sticky_behavior = 'always'
            config.hide_on_scroll = config.sticky_behavior === 'upscroll'
            config.sticky_height = Number(config.sticky_height || config.height || 0)
            config.upscroll_offset = Number(config.upscroll_offset || 0)
            config.downscroll_offset = Number(config.downscroll_offset || 0)
            return
        }
        config.sticky = config.sticky_behavior !== 'none'
        config.hide_on_scroll = config.sticky_behavior === 'upscroll'
        config.sticky_height = Number(config.sticky_height || config.height || 0)
        config.upscroll_offset = Number(config.upscroll_offset || 0)
        config.downscroll_offset = Number(config.downscroll_offset || 0)
    })

    form.post(route('admin.header.update'), { preserveScroll: true })
}

const resetSection = () => {
    router.post(route('admin.header.reset', activeSection.value), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // Reload page to get fresh config
            window.location.reload()
        },
    })
}

const exportConfig = () => {
    const data = JSON.parse(JSON.stringify(form.data()))
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'header-config.json'
    a.click()
    URL.revokeObjectURL(url)
}

const toggleSection = () => { (activeConfig.value as HeaderSection).enabled = !(activeConfig.value as HeaderSection).enabled }

const updateBlockConfig = (key: string, value: HeaderConfigValue) => {
    if (!selectedBlock.value) return
    selectedBlock.value.config[key] = value
}

const hasLabelOptions = computed(() => selectedBlock.value ? ['hamburger', 'search_icon', 'home_link', 'user_menu_icon', 'notification_bell', 'dark_mode', 'language_switcher', 'credit_balance'].includes(selectedBlock.value.type) : false)
const hasIconOptions = computed(() => selectedBlock.value ? ['hamburger', 'search_icon', 'home_link', 'user_menu_icon', 'dark_mode', 'credit_balance'].includes(selectedBlock.value.type) || (selectedBlock.value.type === 'search' && selectedBlock.value.config.search_style === 'icon') : false)
const hasIconAppearanceOptions = computed(() => selectedBlock.value ? ['hamburger', 'search', 'search_icon', 'home_link', 'user_menu_icon', 'dark_mode', 'credit_balance', 'cta_button'].includes(selectedBlock.value.type) : false)
const hasBlockAlignSelector = computed(() => selectedBlock.value ? selectedBlockSection.value !== 'mobile_bottom' : false)
const blockAlignOptionsForBlock = computed(() => {
    if (!selectedBlock.value) return blockAlignOptions
    return selectedBlockSection.value === 'main' ? mainBlockAlignOptions : blockAlignOptions
})

const getBlockAlignLabel = (align: string) => {
    if (align === 'center') return 'Center'
    return align === 'right' ? 'Right' : 'Left'
}
const getBlockAlignBadgeClass = (align: string) => {
    if (align === 'center') return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
    return align === 'right' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
}

const addElement = (element: (typeof availableElements)[number]) => {
    const usedIds = new Set(sectionKeys.flatMap((section) => (form[section] as HeaderSection).blocks.map((block) => block.id)))
    ;(form[addElementTarget.value] as HeaderSection).blocks.push({
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
    ;(form[section] as HeaderSection).blocks.splice(index, 1)
    if (selectedBlockIndex.value === index && selectedBlockSection.value === section) selectedBlockIndex.value = null
}

const promptRemoveBlock = (index: number, section: HeaderSectionKey = activeSection.value) => {
    const block = (form[section] as HeaderSection).blocks[index]
    if (!block) return

    removeBlockTarget.value = {
        index,
        label: getBlockLabel(block.type),
        section,
    }
}

const confirmRemoveBlock = () => {
    if (!removeBlockTarget.value) return

    removeBlock(removeBlockTarget.value.index, removeBlockTarget.value.section)
    removeBlockTarget.value = null
}

const moveBlock = (section: HeaderSectionKey, index: number, direction: 'up' | 'down') => {
    const blocks = (form[section] as HeaderSection).blocks
    const targetIndex = direction === 'up' ? index - 1 : index + 1

    if (targetIndex < 0 || targetIndex >= blocks.length) return

    const [block] = blocks.splice(index, 1)
    blocks.splice(targetIndex, 0, block)

    if (selectedBlockSection.value === section && selectedBlockIndex.value === index) {
        selectedBlockIndex.value = targetIndex
    }
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

const handleLogoUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return
    logoUploading.value = true
    const formData = new FormData()
    formData.append('file', input.files[0])
    formData.append('directory', 'logos')
    try {
        const response = await fetch('/admin/builder/header/upload-logo', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
        })
        const data = await response.json()
        if (data.url) {
            updateBlockConfig('image', data.url)
        }
    } catch {
        // silently fail, user can paste URL manually
    } finally {
        logoUploading.value = false
        input.value = ''
    }
}

const handleBackgroundUpload = async (event: Event, section: HeaderSectionKey = activeSection.value) => {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return

    const formData = new FormData()
    formData.append('file', input.files[0])
    formData.append('directory', 'header-backgrounds')

    try {
        const response = await fetch('/admin/builder/header/upload-logo', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })

        const result = await response.json()

        if (result.url) {
            ;(form[section] as HeaderSection).background.image_url = String(result.url)
            ;(form[section] as HeaderSection).background.image_path = String(result.path ?? result.url)
        }
    } catch (error) {
        console.error('Background upload failed:', error)
    } finally {
        input.value = ''
    }
}

const clearBackgroundImage = (section: HeaderSectionKey = activeSection.value) => {
    ;(form[section] as HeaderSection).background.image_url = ''
    ;(form[section] as HeaderSection).background.image_path = ''
}

const toggleBooleanField = (section: HeaderSection, key: 'transition_enabled' | 'shadow' | 'progressbar' | 'transparent_homepage') => {
    section[key] = !section[key]
}

const toggleMobileBottomBooleanField = (key: 'shadow' | 'progressbar') => {
    const section = form.mobile_bottom as HeaderSection
    section[key] = !section[key]
}

const handleMobileLogoUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return
    logoUploading.value = true
    const formData = new FormData()
    formData.append('file', input.files[0])
    formData.append('directory', 'logos')
    try {
        const response = await fetch('/admin/builder/header/upload-logo', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
        })
        const data = await response.json()
        if (data.url) {
            updateBlockConfig('mobile_image', data.url)
        }
    } catch {
    } finally {
        logoUploading.value = false
        input.value = ''
    }
}
</script>

<template>
    <Head :title="t('Header Builder - Admin')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Header Builder') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure top, main, mobile headers with drag & drop blocks.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Tooltip :content="t('Export JSON')" placement="top">
                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Export JSON')" @click="exportConfig">
                        <i class="ti ti-file-export text-lg"></i>
                    </button>
                </Tooltip>
                <Tooltip :content="t('Import JSON')" placement="top">
                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Import JSON')" @click="showImportModal = true">
                        <i class="ti ti-file-import text-lg"></i>
                    </button>
                </Tooltip>
                <button type="button" :disabled="form.processing" class="rounded-lg btn-primary shadow-lg shadow-primary-500/20 transition-all disabled:opacity-60" @click="submit">
                    {{ form.processing ? t('Saving...') : t('Save Configuration') }}
                </button>
            </div>
        </div>

        <!-- Section Tabs -->
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
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="(form[section.id] as HeaderSection).enabled ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'">
                        {{ (form[section.id] as HeaderSection).enabled ? t('Enabled') : t('Disabled') }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ t(section.description) }}</p>
            </button>
        </div>

        <div class="space-y-8">
            <!-- Blocks -->
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex flex-col gap-2 border-b border-gray-100 pb-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t(':section Elements', { section: t(activeSectionMeta.label) }) }}</h2>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ manualSortMode ? t('Use the up and down controls to reorder blocks manually.') : t('Drag and drop blocks to reorder.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Tooltip :content="t('Header settings')" placement="top">
                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Header settings')" @click="showSectionSettingsModal = true">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </button>
                            </Tooltip>
                            <Tooltip :content="manualSortMode ? t('Disable manual sort') : t('Enable manual sort')" placement="top">
                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border transition" :class="manualSortMode ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20'" :aria-label="manualSortMode ? t('Disable manual sort') : t('Enable manual sort')" @click="manualSortMode = !manualSortMode">
                                    <i class="ti ti-arrows-sort text-base"></i>
                                </button>
                            </Tooltip>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-gray-800" @click="openAddElementModal(activeSection)">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" /></svg>
                                {{ t('Add Element') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="activeBlocks.length === 0" class="rounded-xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500 dark:border-surface-700">
                        {{ t('Nothing here yet. Add an element to get started.') }}
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
                                <div v-if="manualSortMode" class="flex flex-col gap-1">
                                    <Tooltip :content="t('Move up')" placement="top">
                                        <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Move up')" :disabled="Number(index) === 0" @click="moveBlock(activeSection, Number(index), 'up')">
                                            <i class="ti ti-chevron-up text-base"></i>
                                        </button>
                                    </Tooltip>
                                    <Tooltip :content="t('Move down')" placement="top">
                                        <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Move down')" :disabled="Number(index) === activeBlocks.length - 1" @click="moveBlock(activeSection, Number(index), 'down')">
                                            <i class="ti ti-chevron-down text-base"></i>
                                        </button>
                                    </Tooltip>
                                </div>
                                <Tooltip v-else :content="t('Drag element')" placement="top">
                                    <button type="button" class="header-block-drag-handle cursor-grab rounded-lg p-2 text-gray-400 hover:bg-white hover:text-primary-600 active:cursor-grabbing dark:hover:bg-surface-900" :aria-label="t('Drag block')">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01" /></svg>
                                    </button>
                                </Tooltip>
                                <div>
                                    <div class="flex items-center gap-2 text-sm font-bold" :class="block.enabled ? 'text-gray-900 dark:text-white' : 'text-gray-400 line-through dark:text-gray-500'">
                                        {{ t(getBlockLabel(block.type)) }}
                                        <span v-if="getBlockAlignLabel(String(block.config.block_align ?? ''))" class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold" :class="getBlockAlignBadgeClass(String(block.config.block_align ?? ''))">{{ getBlockAlignLabel(String(block.config.block_align ?? '')) }}</span>
                                    </div>
                                    <div class="mt-1 font-mono text-[11px] uppercase" :class="block.enabled ? 'text-gray-400' : 'text-gray-300 line-through dark:text-gray-600'">{{ block.id }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Tooltip :content="block.enabled ? t('Disable element') : t('Enable element')" placement="top">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border transition-colors" :class="block.enabled ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-500 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'" :aria-label="block.enabled ? t('Disable element') : t('Enable element')" @click="block.enabled = !block.enabled">
                                        <i :class="block.enabled ? 'ti ti-toggle-right' : 'ti ti-toggle-left'" class="text-lg"></i>
                                    </button>
                                </Tooltip>
                                <Tooltip :content="t('Edit element')" placement="top">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Edit element')" @click="openBlockSettings(activeSection, Number(index))">
                                        <i class="ti ti-settings text-base"></i>
                                    </button>
                                </Tooltip>
                                <Tooltip :content="t('Remove element')" placement="top">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Remove element')" @click="promptRemoveBlock(Number(index), activeSection)">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>
                    </VueDraggable>
                </section>

                <!-- Mobile Bottom blocks (shown on mobile tab) -->
                <section v-if="activeSection === 'mobile'" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex flex-col gap-2 border-b border-gray-100 pb-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Mobile Bottom Elements') }}</h2>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Fixed bottom mobile bar.') }}</p>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg btn-primary transition" @click="openAddElementModal('mobile_bottom')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" /></svg>
                            {{ t('Add Element') }}
                        </button>
                    </div>
                    <div v-if="(form.mobile_bottom as HeaderSection).blocks.length === 0" class="rounded-xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500 dark:border-surface-700">
                        {{ t('Nothing here yet.') }}
                    </div>
                    <VueDraggable
                        v-model="(form.mobile_bottom as HeaderSection).blocks"
                        class="header-block-dropzone space-y-3 rounded-xl border border-dashed border-gray-200 bg-white/70 p-2 dark:border-surface-700 dark:bg-surface-900/50"
                        v-bind="getDragOptions('mobile_bottom')"
                        @start="isDraggingBlock = true"
                        @end="isDraggingBlock = false"
                    >
                        <div
                            v-for="(block, index) in (form.mobile_bottom as HeaderSection).blocks"
                            :key="block.id"
                            class="header-block-item flex items-center justify-between rounded-xl border p-4 transition-all hover:shadow-md"
                            :class="block.enabled ? 'border-primary-200 bg-primary-50/50 dark:border-primary-900/50 dark:bg-primary-900/10' : 'border-gray-100 bg-gray-50/70 dark:border-surface-700 dark:bg-surface-800/50'"
                        >
                            <div class="flex items-center gap-4">
                                <div v-if="manualSortMode" class="flex flex-col gap-1">
                                    <Tooltip :content="t('Move up')" placement="top">
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Move up')" :disabled="Number(index) === 0" @click="moveBlock('mobile_bottom', Number(index), 'up')">
                                            <i class="ti ti-chevron-up text-base"></i>
                                        </button>
                                    </Tooltip>
                                    <Tooltip :content="t('Move down')" placement="top">
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Move down')" :disabled="Number(index) === (form.mobile_bottom as HeaderSection).blocks.length - 1" @click="moveBlock('mobile_bottom', Number(index), 'down')">
                                            <i class="ti ti-chevron-down text-base"></i>
                                        </button>
                                    </Tooltip>
                                </div>
                                <Tooltip v-else :content="t('Drag element')" placement="top">
                                    <button type="button" class="header-block-drag-handle cursor-grab rounded-lg p-2 text-gray-400 hover:bg-white hover:text-primary-600 active:cursor-grabbing dark:hover:bg-surface-900" :aria-label="t('Drag block')">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01" /></svg>
                                    </button>
                                </Tooltip>
                                <div>
                                    <div class="flex items-center gap-2 text-sm font-bold" :class="block.enabled ? 'text-gray-900 dark:text-white' : 'text-gray-400 line-through dark:text-gray-500'">{{ t(getBlockLabel(block.type)) }}<span class="h-2 w-2 rounded-full" :class="block.enabled ? 'bg-primary-500' : 'bg-gray-300'"></span></div>
                                    <div class="mt-1 font-mono text-[11px] uppercase" :class="block.enabled ? 'text-gray-400' : 'text-gray-300 line-through dark:text-gray-600'">{{ block.id }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Tooltip :content="t('Edit element')" placement="top">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Edit element')" @click="openBlockSettings('mobile_bottom', Number(index))">
                                        <i class="ti ti-settings text-base"></i>
                                    </button>
                                </Tooltip>
                                <Tooltip :content="block.enabled ? t('Disable element') : t('Enable element')" placement="top">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border transition-colors" :class="block.enabled ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-500 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'" :aria-label="block.enabled ? t('Disable element') : t('Enable element')" @click="block.enabled = !block.enabled">
                                        <i :class="block.enabled ? 'ti ti-toggle-right' : 'ti ti-toggle-left'" class="text-lg"></i>
                                    </button>
                                </Tooltip>
                                <Tooltip :content="t('Remove element')" placement="top">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-900/20" :aria-label="t('Remove element')" @click="promptRemoveBlock(Number(index), 'mobile_bottom')">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>
                    </VueDraggable>
                </section>
            </div>
        </div>

        <!-- Section Settings Modal -->
        <Teleport to="body">
            <div v-if="showSectionSettingsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showSectionSettingsModal = false">
                <div class="flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-3 dark:border-surface-800">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t(':section Settings', { section: t(activeSectionMeta.label) }) }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ activeConfig.enabled ? t(':section is enabled', { section: t(activeSectionMeta.label) }) : t(':section is disabled', { section: t(activeSectionMeta.label) }) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Tooltip :content="t('Reset section to defaults')" placement="top">
                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-danger-200 hover:bg-danger-50 hover:text-danger-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-danger-900/20" :aria-label="t('Reset section to defaults')" @click="resetSection">
                                    <i class="ti ti-restore text-base"></i>
                                </button>
                            </Tooltip>
                            <button type="button" class="inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-600 transition dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" @click="toggleSection">
                                <span>{{ activeConfig.enabled ? t('Enabled') : t('Disabled') }}</span>
                                <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="activeConfig.enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                    <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="activeConfig.enabled ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                </span>
                            </button>
                            <Tooltip :content="t('Close')" placement="top">
                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700" :aria-label="t('Close')" @click="showSectionSettingsModal = false">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </Tooltip>
                        </div>
                    </div>

                    <div class="grid flex-1 grid-cols-1 gap-6 overflow-y-auto p-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="space-y-6">
                            <section class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/40">
                                <div class="mb-4">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Structure') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('Control the header frame, sizing, and layout behavior.') }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Height') }}
                                        <input v-model.number="activeConfig.height" type="number" :min="activeSection === 'top' ? 32 : 48" :max="activeSection === 'main' ? 120 : 96" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                                    </label>

                                    <AppSelect v-if="activeSection !== 'mobile'" v-model="activeConfig.container_width" :label="t('Container Width')" :options="containerWidthSelectOptions" />

                                    <div v-if="activeSection === 'main'">
                                        <AppSelect v-model="activeConfig.center_alignment" :label="t('Center Column Alignment')" :options="centerAlignmentSelectOptions" />
                                        <span class="mt-1 block text-xs text-gray-500">{{ t('How blocks in the center column are aligned.') }}</span>
                                    </div>

                                    <div v-if="activeSection !== 'mobile_bottom'">
                                        <AppSelect v-model="activeConfig.column_flex" :label="t('Column Flex')" :options="columnFlexSelectOptions" />
                                        <span class="mt-1 block text-xs text-gray-500">{{ t('Which column stretches to fill available space.') }}</span>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/40">
                                <div class="mb-4">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Sticky behavior') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('Define how this header stays visible while scrolling.') }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <AppSelect v-model="activeConfig.sticky_behavior" :label="t('Sticky Behavior')" :options="activeSection === 'mobile_bottom' ? mobileBottomStickyBehaviorSelectOptions : stickyBehaviorSelectOptions" />
                                        <span v-if="activeSection === 'mobile_bottom'" class="mt-1 block text-xs text-gray-500">{{ t('Mobile bottom header stays fixed.') }}</span>
                                    </div>

                                    <template v-if="activeConfig.sticky_behavior !== 'none'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t('Sticky Header Height') }}
                                            <input v-model.number="activeConfig.sticky_height" type="number" :min="activeSection === 'top' ? 32 : 48" :max="activeSection === 'main' ? 120 : 96" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                                            <span class="mt-1 block text-xs text-gray-500">{{ t('Height used after the header becomes sticky.') }}</span>
                                        </label>

                                        <button type="button" class="flex items-center justify-between gap-4 self-end rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="toggleBooleanField(activeConfig, 'transition_enabled')">
                                            <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Enable Transition') }}</span><span class="text-xs text-gray-500">{{ t('Animate sticky show/hide.') }}</span></span>
                                            <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="activeConfig.transition_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                                <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="activeConfig.transition_enabled ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                            </span>
                                        </button>
                                    </template>

                                    <template v-if="activeConfig.sticky_behavior === 'upscroll' || activeConfig.sticky_behavior === 'downscroll'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t('Up Scroll Offset') }}
                                            <input v-model.number="activeConfig.upscroll_offset" type="number" min="0" max="800" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                                        </label>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t('Down Scroll Offset') }}
                                            <input v-model.number="activeConfig.downscroll_offset" type="number" min="0" max="800" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                                        </label>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/40">
                                <div class="mb-4">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Background') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('Set color, image, and overlay for this header area.') }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <AppColorPicker :model-value="String(activeConfig.background.color ?? '')" :label="t('Background Color')" @update:model-value="activeConfig.background.color = $event" />
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Overlay Opacity') }}
                                        <input v-model.number="activeConfig.background.overlay_opacity" type="number" min="0" max="1" step="0.1" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                                    </label>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ t('Background Image') }}
                                        </label>
                                        <div class="mt-2 space-y-3">
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                                @change="handleBackgroundUpload($event, activeSection)"
                                            >
                                        </div>
                                        <div v-if="activeConfig.background.image_url" class="mt-3 space-y-3">
                                            <img :src="String(activeConfig.background.image_url)" :alt="t('Background preview')" class="h-32 w-full rounded-xl border border-gray-200 object-cover dark:border-surface-700">
                                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm font-semibold text-danger-600 transition hover:bg-danger-100 dark:border-danger-900/40 dark:bg-danger-900/20 dark:text-danger-300" @click="clearBackgroundImage(activeSection)">
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Remove Image') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section v-if="activeSection === 'mobile'" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/40">
                                <div class="mb-4 flex items-center justify-between gap-4">
                                    <div>
                                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Mobile Bottom Header') }}</h4>
                                        <p class="mt-1 text-xs text-gray-500">{{ (form.mobile_bottom as HeaderSection).enabled ? t('Bottom header is enabled') : t('Bottom header is disabled') }}</p>
                                    </div>
                                    <button type="button" class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="(form.mobile_bottom as HeaderSection).enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'" @click="(form.mobile_bottom as HeaderSection).enabled = !(form.mobile_bottom as HeaderSection).enabled">
                                        <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="(form.mobile_bottom as HeaderSection).enabled ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('Height') }}
                                        <input v-model.number="(form.mobile_bottom as HeaderSection).height" type="number" min="48" max="96" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                                    </label>
                                    <AppSelect v-model="(form.mobile_bottom as HeaderSection).sticky_behavior" :label="t('Sticky Behavior')" :options="mobileBottomStickyBehaviorSelectOptions" />
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="toggleMobileBottomBooleanField('shadow')">
                                        <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Header Shadow') }}</span></span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="(form.mobile_bottom as HeaderSection).shadow ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="(form.mobile_bottom as HeaderSection).shadow ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="toggleMobileBottomBooleanField('progressbar')">
                                        <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Progress Bar') }}</span></span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="(form.mobile_bottom as HeaderSection).progressbar ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="(form.mobile_bottom as HeaderSection).progressbar ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-6">
                            <section class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/40">
                                <div class="mb-4">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Effects') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('Control animation, shadow, progress, and transparency.') }}</p>
                                </div>
                                <div class="mb-4">
                                    <AppColorPicker :model-value="String(activeConfig.text_color ?? '')" :label="t('Header Text Color')" @update:model-value="activeConfig.text_color = $event" />
                                </div>
                                <div class="space-y-3">
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="toggleBooleanField(activeConfig, 'shadow')">
                                        <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Header Shadow') }}</span><span class="text-xs text-gray-500">{{ t('Soft shadow under this header.') }}</span></span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="activeConfig.shadow ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="activeConfig.shadow ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="toggleBooleanField(activeConfig, 'progressbar')">
                                        <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Progress Bar') }}</span><span class="text-xs text-gray-500">{{ t('Scroll progress bar at edge.') }}</span></span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="activeConfig.progressbar ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="activeConfig.progressbar ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                    <button type="button" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="toggleBooleanField(activeConfig, 'transparent_homepage')">
                                        <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Transparent Homepage') }}</span><span class="text-xs text-gray-500">{{ t('Overlay on homepage hero.') }}</span></span>
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="activeConfig.transparent_homepage ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                            <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="activeConfig.transparent_homepage ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                        </span>
                                    </button>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-800/40">
                                <div class="mb-4">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Custom CSS') }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('Apply section-specific CSS adjustments.') }}</p>
                                </div>
                                <textarea v-model="activeConfig.custom_css" rows="10" :maxlength="2000" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-3 font-mono text-xs dark:border-surface-700 dark:bg-surface-900 dark:text-white" :placeholder="t('e.g. .header-logo { filter: drop-shadow(0 0 8px rgba(0,0,0,0.1)); }')"></textarea>
                            </section>
                        </aside>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800">
                        <p class="text-xs text-gray-500">{{ t('Changes are applied live and saved with the page configuration.') }}</p>
                        <button type="button" class="rounded-lg btn-primary" @click="showSectionSettingsModal = false">{{ t('Done') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <ActionConfirmModal
            :open="Boolean(removeBlockTarget)"
            :title="t('Remove element?')"
            :message="removeBlockTarget ? t('Remove :element from this header section?', { element: t(removeBlockTarget.label) }) : ''"
            :confirm-label="t('Remove')"
            :cancel-label="t('Cancel')"
            variant="danger"
            @cancel="removeBlockTarget = null"
            @confirm="confirmRemoveBlock"
        />

        <!-- Add Element Modal -->
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

        <!-- Block Settings Modal -->
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
                        <!-- Label options -->
                        <div v-if="hasLabelOptions" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <div class="grid grid-cols-1 gap-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Display Label') }}
                                    <input :value="String(selectedBlock.config.label ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="updateBlockConfig('label', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label v-if="selectedBlockSection === 'mobile_bottom'" class="flex items-center justify-between gap-4">
                                    <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Label') }}</span></span>
                                    <input :checked="selectedBlock.config.show_label !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_label', ($event.target as HTMLInputElement).checked)">
                                </label>
                            </div>
                        </div>

                        <!-- Icon select -->
                        <IconClassSelect
                            v-if="hasIconOptions"
                            :model-value="String(selectedBlock.config.icon_class ?? '')"
                            :label="t('Icon Class')"
                            @update:model-value="updateBlockConfig('icon_class', $event)"
                        />

                        <!-- Icon appearance -->
                        <div v-if="hasIconAppearanceOptions" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ t('Icon Appearance') }}</h4>
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Icon Color') }}
                                    <input :value="String(selectedBlock.config.icon_color ?? '')" type="text" :placeholder="t('var(--color-primary-600)')" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" @input="updateBlockConfig('icon_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Background Style') }}
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

                        <!-- Navigation -->
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
                                    <input :value="String(selectedBlock.config.text_color ?? '')" type="text" :placeholder="t('Fallback')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('text_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Hover Color') }}
                                    <input :value="String(selectedBlock.config.hover_color ?? '')" type="text" :placeholder="t('var(--color-primary-600)')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('hover_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Submenu BG Color') }}
                                    <input :value="String(selectedBlock.config.submenu_bg_color ?? '')" type="text" :placeholder="t('var(--surface-card)')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('submenu_bg_color', ($event.target as HTMLInputElement).value)">
                                </label>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Submenu Text Color') }}
                                    <input :value="String(selectedBlock.config.submenu_text_color ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('submenu_text_color', ($event.target as HTMLInputElement).value)">
                                </label>
                            </div>
                        </div>

                        <!-- Search -->
                        <div v-if="selectedBlock.type === 'search'" class="grid grid-cols-1 gap-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Search Style') }}
                                <select :value="String(selectedBlock.config.search_style ?? 'box')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('search_style', ($event.target as HTMLSelectElement).value)">
                                    <option v-for="option in searchStyleOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                                </select>
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Live Search') }}</span>
                                    <input :checked="selectedBlock.config.enable_live_search !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('enable_live_search', ($event.target as HTMLInputElement).checked)">
                                </label>
                                <label class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Suggestions') }}</span>
                                    <input :checked="selectedBlock.config.show_suggestions !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_suggestions', ($event.target as HTMLInputElement).checked)">
                                </label>
                            </div>
                        </div>

                        <!-- Search Icon -->
                        <div v-if="selectedBlock.type === 'search_icon'" class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Live Search') }}</span>
                                <input :checked="selectedBlock.config.enable_live_search !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('enable_live_search', ($event.target as HTMLInputElement).checked)">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Suggestions') }}</span>
                                <input :checked="selectedBlock.config.show_suggestions !== false" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_suggestions', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <!-- Hamburger -->
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

                        <!-- Language Switcher -->
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

                        <!-- CTA Button -->
                        <div v-if="selectedBlock.type === 'cta_button'" class="grid grid-cols-1 gap-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Button Text') }}
                                <input :value="String(selectedBlock.config.text ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('text', ($event.target as HTMLInputElement).value)">
                            </label>
                            <IconClassSelect :model-value="String(selectedBlock.config.icon_class ?? '')" :label="t('Icon Class')" @update:model-value="updateBlockConfig('icon_class', $event)" />
                            <label class="flex items-center justify-between gap-4">
                                <span><span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Icon Only') }}</span></span>
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

                        <!-- User Menu -->
                        <div v-if="selectedBlock.type === 'user_menu'" class="space-y-3">
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Avatar') }}</span>
                                <input :checked="Boolean(selectedBlock.config.show_avatar)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_avatar', ($event.target as HTMLInputElement).checked)">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Credit Balance') }}</span>
                                <input :checked="Boolean(selectedBlock.config.show_credits)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_credits', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <!-- Logo -->
                        <div v-if="selectedBlock.type === 'logo'" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Logo Image URL') }}
                                    <input :value="String(selectedBlock.config.image ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('image', ($event.target as HTMLInputElement).value)">
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:text-gray-300">
                                        <svg v-if="!logoUploading" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                        <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                        {{ logoUploading ? t('Uploading...') : t('Upload Logo') }}
                                        <input type="file" accept="image/*" class="hidden" @change="handleLogoUpload">
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Mobile Logo Image URL') }}
                                    <input :value="String(selectedBlock.config.mobile_image ?? '')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('mobile_image', ($event.target as HTMLInputElement).value)">
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:text-gray-300">
                                        <svg v-if="!logoUploading" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                        <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                        {{ logoUploading ? t('Uploading...') : t('Upload Mobile Logo') }}
                                        <input type="file" accept="image/*" class="hidden" @change="handleMobileLogoUpload">
                                    </label>
                                </div>
                            </div>
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
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Text') }}</span>
                                <input :checked="Boolean(selectedBlock.config.show_text)" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="updateBlockConfig('show_text', ($event.target as HTMLInputElement).checked)">
                            </label>
                        </div>

                        <!-- Social Icons -->
                        <label v-if="selectedBlock.type === 'social_icons'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Display Mode') }}
                            <select :value="String(selectedBlock.config.display_mode ?? 'icons')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @change="updateBlockConfig('display_mode', ($event.target as HTMLSelectElement).value)">
                                <option value="icons">{{ t('Icons') }}</option>
                                <option value="counts">{{ t('Counts') }}</option>
                                <option value="cards">{{ t('Cards') }}</option>
                            </select>
                        </label>

                        <!-- Home Link -->
                        <label v-if="selectedBlock.type === 'home_link'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Home Link') }}
                            <input :value="String(selectedBlock.config.link ?? '/')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('link', ($event.target as HTMLInputElement).value)">
                        </label>

                        <!-- Custom HTML -->
                        <label v-if="selectedBlock.type === 'custom_html'" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Custom HTML') }}
                            <textarea :value="String(selectedBlock.config.content ?? '')" rows="6" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @input="updateBlockConfig('content', ($event.target as HTMLTextAreaElement).value)"></textarea>
                        </label>
                        <!-- Block Align -->
                        <div v-if="hasBlockAlignSelector" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ t('Column Placement') }}</h4>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Choose which column this block appears in.') }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2" :class="selectedBlockSection === 'main' ? 'sm:grid-cols-3' : ''">
                                <button
                                    v-for="option in blockAlignOptionsForBlock"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-lg border p-2 text-center text-xs font-semibold transition"
                                    :class="String(selectedBlock.config.block_align ?? 'left') === option.value ? 'border-primary-300 bg-primary-50 text-primary-700 dark:bg-primary-900/20' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 dark:border-surface-700 dark:bg-surface-900 dark:hover:bg-primary-900/20'"
                                    @click="updateBlockConfig('block_align', option.value)"
                                >{{ t(option.label) }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800">
                        <button type="button" class="rounded-lg btn-primary" @click="selectedBlockIndex = null">{{ t('Done') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Import Modal -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showImportModal = false">
                <div class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Import Header Config') }}</h3>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" @click="showImportModal = false">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="p-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Paste JSON config') }}
                            <textarea v-model="importJsonText" rows="10" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder='{ "top": { ... }, "main": { ... } }'></textarea>
                        </label>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="showImportModal = false">{{ t('Cancel') }}</button>
                            <button type="button" class="rounded-lg btn-primary px-4 py-2 text-sm font-semibold" @click="importConfig">{{ t('Import') }}</button>
                        </div>
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
.header-block-ghost > * { visibility: hidden; }
.header-block-chosen,
.header-block-chosen.header-block-item,
.header-block-item.sortable-chosen,
.header-block-item.sortable-drag,
.header-block-active,
.header-block-fallback {
    background: #fff !important;
    opacity: 1 !important;
}
.header-block-chosen .header-block-drag-handle { color: var(--color-primary-600); }
.header-block-active { box-shadow: var(--shadow-md) !important; }
.header-block-fallback {
    border: 1px solid var(--color-primary-200) !important;
    box-shadow: var(--shadow-md) !important;
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
